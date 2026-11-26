<?php
declare(strict_types=1);
namespace App\Libraries\Auth;
use App\Libraries\Governance\ApprovalIntent;
use App\Libraries\Security\AtomicAuditedMutation;
use App\Libraries\Security\AuditTrail;
use App\Libraries\Security\RequestId;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
/** Approval-bound invitation requests, decisions and token/outbox commits. */
final class ApprovedInvitationRelease {
 public function __construct(private readonly BaseConnection $db) {}
 private function audit(string $action,int $actor,int $target,string $outcome='success'): void {
  (new AuditTrail($this->db))->append(['action_key'=>$action,'module_code'=>'security','event_category'=>'security','outcome'=>$outcome,'actor_user_id'=>$actor,'subject_type'=>'user','subject_id'=>$target,'request_id'=>RequestId::current()]);
 }
 private function state(int $target,int $identifier): string {
  $u=$this->db->table('users')->select('active')->where('id',$target)->get()->getRowArray();
  $i=$this->db->table('portal_login_identifiers')->select('user_id,identifier_type,normalised_value,status,verified_at,revoked_at')->where('id',$identifier)->get()->getRowArray();
  if (!$u || !$i || (int)$i['user_id']!==$target) throw new RuntimeException('Invalid invitation subject');
  return hash('sha256',json_encode([$u,$i],JSON_THROW_ON_ERROR));
 }
 private function authorize(int $actor,string $permission,string $scope,?int $zone,?int $branch): bool {
  return (new ScopeAuthorizer($this->db))->allows($actor,$permission,$scope,$zone,$branch);
 }
 public function request(int $actor,int $target,int $identifier,string $scope,?int $zone,?int $branch,string $reason): int {
  if (mb_strlen($reason)<10 || mb_strlen($reason)>500 || !$this->authorize($actor,'users.invite',$scope,$zone,$branch)
    || !(new InvitationIssuanceEligibility($this->db))->allows($actor,$target,$identifier,$scope,$zone,$branch)) throw new RuntimeException('Invitation request denied');
  return (new AtomicAuditedMutation($this->db))->run(function() use($actor,$target,$identifier,$scope,$zone,$branch,$reason): int {
   $this->db->query('SELECT id FROM users WHERE id=? FOR UPDATE',[$target]);
   if (!(new InvitationIssuanceEligibility($this->db))->allows($actor,$target,$identifier,$scope,$zone,$branch)) throw new RuntimeException('Invitation no longer eligible');
   $state=$this->state($target,$identifier);
   $change=['identifier_id'=>$identifier];
   $digest=ApprovalIntent::digest('account.invite','user',$target,$scope,$zone,$branch,$state,$change);
   $now=gmdate('Y-m-d H:i:s');
   $this->db->table('approval_requests')->insert([
    'public_id'=>$this->uuid(),'action_key'=>'account.invite','subject_type'=>'user','subject_id'=>$target,
    'requested_by_user_id'=>$actor,'request_reason'=>$reason,'required_approvals'=>1,'approval_status'=>'pending',
    'submitted_at'=>$now,'expires_at'=>gmdate('Y-m-d H:i:s',time()+86400)
   ]);
   $id=(int)$this->db->insertID();
   $this->db->table('portal_approval_action_bindings')->insert([
    'approval_request_id'=>$id,'action_key'=>'account.invite','subject_type'=>'user','subject_id'=>$target,
    'scope_type'=>$scope,'zone_id'=>$zone,'branch_id'=>$branch,'expected_state_sha256'=>$state,
    'proposed_change_json'=>json_encode($change,JSON_THROW_ON_ERROR),'intent_sha256'=>$digest,
    'execution_status'=>'pending','created_by_user_id'=>$actor,'created_at'=>$now
   ]);
   $this->audit('auth.invitation_approval_requested',$actor,$target);
   if (!$this->db->transStatus()) throw new RuntimeException('Approval request failed');
   return $id;
  });
 }
 public function decide(int $checker,int $requestId,bool $approve,string $reason): void {
  if ($requestId<1 || mb_strlen($reason)<5 || mb_strlen($reason)>500) throw new RuntimeException('Invalid decision');
  (new AtomicAuditedMutation($this->db))->run(function() use($checker,$requestId,$approve,$reason): void {
   $r=$this->db->query('SELECT * FROM approval_requests WHERE id=? FOR UPDATE',[$requestId])->getRowArray();
   $b=$this->db->query('SELECT * FROM portal_approval_action_bindings WHERE approval_request_id=? FOR UPDATE',[$requestId])->getRowArray();
   if (!$r||!$b||$r['action_key']!=='account.invite'||$r['approval_status']!=='pending'
    ||$b['execution_status']!=='pending'||(int)$r['requested_by_user_id']===$checker
    ||$r['expires_at']===null||$r['expires_at']<=gmdate('Y-m-d H:i:s')
    ||!$this->authorize($checker,'approvals.decide',$b['scope_type'],$b['zone_id']===null?null:(int)$b['zone_id'],$b['branch_id']===null?null:(int)$b['branch_id']))
    throw new RuntimeException('Independent approval denied');
   $this->db->table('approval_decisions')->insert(['approval_request_id'=>$requestId,'approver_user_id'=>$checker,
    'decision'=>$approve?'approved':'rejected','decision_reason'=>$reason,'decided_at'=>gmdate('Y-m-d H:i:s')]);
   $this->db->table('approval_requests')->where('id',$requestId)->where('approval_status','pending')->update([
    'approval_status'=>$approve?'approved':'rejected','completed_at'=>gmdate('Y-m-d H:i:s')
   ]);
   if (!$approve) $this->db->table('portal_approval_action_bindings')->where('id',$b['id'])->update([
    'execution_status'=>'invalidated','invalidated_at'=>gmdate('Y-m-d H:i:s')
   ]);
   $this->audit($approve?'auth.invitation_approved':'auth.invitation_rejected',$checker,(int)$r['subject_id']);
   if (!$this->db->transStatus()) throw new RuntimeException('Approval decision failed');
  });
 }
 public function issue(int $actor,int $requestId): void {
  if ($requestId<1) throw new RuntimeException('Invalid request');
  (new AtomicAuditedMutation($this->db))->run(function() use($actor,$requestId): void {
   $r=$this->db->query('SELECT * FROM approval_requests WHERE id=? FOR UPDATE',[$requestId])->getRowArray();
   $b=$this->db->query('SELECT * FROM portal_approval_action_bindings WHERE approval_request_id=? FOR UPDATE',[$requestId])->getRowArray();
   if (!$r||!$b||$r['approval_status']!=='approved'||$r['action_key']!=='account.invite'
    ||$b['execution_status']!=='pending'||(int)$r['requested_by_user_id']!==$actor
    ||$r['expires_at']===null||$r['expires_at']<=gmdate('Y-m-d H:i:s')) throw new RuntimeException('Invitation approval unavailable');
   $target=(int)$r['subject_id'];
   $change=json_decode((string)$b['proposed_change_json'],true,8,JSON_THROW_ON_ERROR);
   if (!is_array($change)||array_keys($change)!==['identifier_id']||!is_int($change['identifier_id'])) throw new RuntimeException('Invalid approved change');
   $identifier=$change['identifier_id'];
   $scope=$b['scope_type'];$zone=$b['zone_id']===null?null:(int)$b['zone_id'];$branch=$b['branch_id']===null?null:(int)$b['branch_id'];
   $this->db->query('SELECT id FROM users WHERE id=? FOR UPDATE',[$target]);
   $this->db->query('SELECT id FROM users WHERE id=? FOR UPDATE',[$actor]);
   $this->db->query('SELECT id FROM portal_user_role_assignments WHERE user_id=? FOR UPDATE',[$actor]);
   if (!$this->authorize($actor,'users.invite',$scope,$zone,$branch)
    ||!(new InvitationIssuanceEligibility($this->db))->allows($actor,$target,$identifier,$scope,$zone,$branch)
    ||!hash_equals((string)$b['expected_state_sha256'],$this->state($target,$identifier))
    ||!hash_equals((string)$b['intent_sha256'],ApprovalIntent::digest('account.invite','user',$target,$scope,$zone,$branch,(string)$b['expected_state_sha256'],$change)))
    throw new RuntimeException('Approved invitation stale or tampered');
   $approver=$this->db->table('approval_decisions')->where('approval_request_id',$requestId)->where('decision','approved')->get()->getRowArray();
   if (!$approver||(int)$approver['approver_user_id']===$actor) throw new RuntimeException('Independent approval missing');
   $recipient=$this->db->table('portal_login_identifiers')->where('id',$identifier)->get()->getRowArray();
   if (!$recipient||$recipient['identifier_type']!=='email'||!filter_var($recipient['normalised_value'],FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Only email invitations supported');
   $token=(new AccountActionTokenStore($this->db))->issueWithinTransaction($target,$identifier,'invitation_activation',$actor,$requestId,RequestId::current());
   $this->db->table('portal_invitation_outbox')->insert([
    'token_public_id'=>$token['public_id'],'recipient'=>$recipient['normalised_value'],
    'ciphertext'=>InvitationOutboxCrypto::seal($token['token']),'status'=>'queued',
    'created_at'=>gmdate('Y-m-d H:i:s')
   ]);
   $this->db->table('portal_approval_action_bindings')->where('id',$b['id'])->where('execution_status','pending')->update([
    'execution_status'=>'executed','executed_at'=>gmdate('Y-m-d H:i:s')
   ]);
   $this->audit('auth.invitation_queued',$actor,$target);
   if (!$this->db->transStatus()) throw new RuntimeException('Invitation issuance failed');
  });
 }
 private function uuid(): string {
  $b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);$h=bin2hex($b);
  return substr($h,0,8).'-'.substr($h,8,4).'-'.substr($h,12,4).'-'.substr($h,16,4).'-'.substr($h,20);
 }
}
