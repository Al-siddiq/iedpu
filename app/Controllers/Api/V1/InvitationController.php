<?php
declare(strict_types=1);
namespace App\Controllers\Api\V1;
use App\Controllers\BaseController;
use App\Libraries\Api\ApiInput;
use App\Libraries\Api\ApiResponder;
use App\Libraries\Auth\ApprovedInvitationRelease;
use App\Libraries\Auth\InvitationActivation;
use App\Libraries\Auth\InvitationIssuerSessionGate;
use App\Libraries\Auth\ScopeAuthorizer;
use App\Libraries\Auth\SessionVersion;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;
/** CSRF-protected invitation request, maker/checker approval and issue API. */
final class InvitationController extends BaseController {
 private function reject(int $status,string $code): ResponseInterface {
  return ApiResponder::error($this->request,$this->response,$status,$code,'Invitation request cannot be completed.');
 }
 private function actor(): ?int {
  $user=auth('session')->user();
  if ($user===null||!$user->active||$user->isBanned()) return null;
  $id=(int)$user->id;
  if (!(new SessionVersion(db_connect()))->isCurrent($id)
   ||!InvitationIssuerSessionGate::allows($id,$id,true,session()->get('iedpu_mfa_verified_user'),true)
   ||db_connect()->table('portal_totp_factors')->where('user_id',$id)->countAllResults()===0) return null;
  return $id;
 }
 private function json(array $keys): ?array {
  $parsed=ApiInput::jsonObject($this->request,4096);
  if (!$parsed['ok']) return null;
  $actual=array_keys($parsed['data']);sort($actual);sort($keys);
  if ($actual!==$keys) return null;
  return $parsed['data'];
 }
 private function rate(int $id,string $action): bool {
  return service('throttler')->check('iedpu-invite-'.$action.'-'.hash('sha256',(string)$id),10,3600)
   && service('throttler')->check('iedpu-invite-net-'.hash('sha256',$this->request->getIPAddress()),30,3600);
 }
 public function workspaceData(): ResponseInterface {
  try {
   $id=$this->actor();if ($id===null) return $this->reject(401,'unauthenticated');
   $policy=new ScopeAuthorizer(db_connect());
   $canRequest=$policy->allows($id,'users.invite','global');
   $canApprove=$policy->allows($id,'approvals.decide','global');
   $canView=$policy->allows($id,'approvals.view','global');
   $db=db_connect();$candidates=[];$pending=[];$mine=[];
   if ($canRequest) {
    $rows=$db->query("SELECT u.id AS target_user_id, i.id AS identifier_id, i.normalised_value AS email
      FROM users u JOIN portal_login_identifiers i ON i.user_id=u.id
      WHERE u.active=0 AND i.identifier_type='email' AND i.status='pending'
       AND i.verified_at IS NULL AND i.revoked_at IS NULL
       AND NOT EXISTS (SELECT 1 FROM portal_user_role_assignments a WHERE a.user_id=u.id)
       AND NOT EXISTS (SELECT 1 FROM portal_totp_factors f WHERE f.user_id=u.id)
       AND NOT EXISTS (SELECT 1 FROM auth_identities x WHERE x.user_id=u.id)
      ORDER BY u.id, i.id")->getResultArray();
    $candidates=$rows;
    $mine=$db->query("SELECT r.id,r.subject_id AS target_user_id,r.request_reason,r.approval_status,r.submitted_at,
       i.normalised_value AS email
      FROM approval_requests r
      JOIN portal_approval_action_bindings b ON b.approval_request_id=r.id
      JOIN portal_login_identifiers i ON i.id=CAST(JSON_UNQUOTE(JSON_EXTRACT(b.proposed_change_json,'$.identifier_id')) AS UNSIGNED)
      WHERE r.action_key='account.invite' AND r.requested_by_user_id=?
      ORDER BY r.id DESC LIMIT 50",[$id])->getResultArray();
   }
   if ($canView || $canApprove) {
    $pending=$db->query("SELECT r.id,r.subject_id AS target_user_id,r.requested_by_user_id,r.request_reason,r.approval_status,r.submitted_at,
       i.normalised_value AS email
      FROM approval_requests r
      JOIN portal_approval_action_bindings b ON b.approval_request_id=r.id
      JOIN portal_login_identifiers i ON i.id=CAST(JSON_UNQUOTE(JSON_EXTRACT(b.proposed_change_json,'$.identifier_id')) AS UNSIGNED)
      WHERE r.action_key='account.invite' AND r.approval_status='pending' AND r.requested_by_user_id<>?
      ORDER BY r.id ASC LIMIT 50",[$id])->getResultArray();
   }
   return ApiResponder::success($this->request,$this->response,[
    'can_request'=>$canRequest,'can_approve'=>$canApprove,'can_issue'=>$canRequest,
    'candidates'=>$candidates,'pending_approvals'=>$pending,'my_requests'=>$mine
   ]);
  }catch(Throwable){return $this->reject(503,'invitation_workspace_unavailable');}
 }
 public function requestApproval(): ResponseInterface {
  try {
   $id=$this->actor();if ($id===null) return $this->reject(401,'unauthenticated');
   if (!$this->rate($id,'request')) return $this->reject(429,'rate_limited');
   $d=$this->json(['target_user_id','identifier_id','scope','zone_id','branch_id','reason']);
   if ($d===null||!is_int($d['target_user_id'])||!is_int($d['identifier_id'])||!is_string($d['scope'])
    ||!is_string($d['reason'])||!in_array($d['scope'],['global','zone','branch'],true)
    ||($d['zone_id']!==null&&!is_int($d['zone_id']))||($d['branch_id']!==null&&!is_int($d['branch_id']))) return $this->reject(422,'invalid_request');
   $requestId=(new ApprovedInvitationRelease(db_connect()))->request($id,$d['target_user_id'],$d['identifier_id'],$d['scope'],$d['zone_id'],$d['branch_id'],$d['reason']);
   return ApiResponder::success($this->request,$this->response,['approval_request_id'=>$requestId],201);
  }catch(Throwable){return $this->reject(403,'invitation_denied');}
 }
 public function decide(): ResponseInterface {
  try {
   $id=$this->actor();if ($id===null) return $this->reject(401,'unauthenticated');
   if (!$this->rate($id,'decide')) return $this->reject(429,'rate_limited');
   $d=$this->json(['approval_request_id','approve','reason']);
   if ($d===null||!is_int($d['approval_request_id'])||!is_bool($d['approve'])||!is_string($d['reason'])) return $this->reject(422,'invalid_request');
   (new ApprovedInvitationRelease(db_connect()))->decide($id,$d['approval_request_id'],$d['approve'],$d['reason']);
   return ApiResponder::success($this->request,$this->response,['recorded'=>true]);
  }catch(Throwable){return $this->reject(403,'approval_denied');}
 }
 public function issue(): ResponseInterface {
  try {
   $id=$this->actor();if ($id===null) return $this->reject(401,'unauthenticated');
   if (!$this->rate($id,'issue')) return $this->reject(429,'rate_limited');
   $d=$this->json(['approval_request_id']);
   if ($d===null||!is_int($d['approval_request_id'])) return $this->reject(422,'invalid_request');
   (new ApprovedInvitationRelease(db_connect()))->issue($id,$d['approval_request_id']);
   return ApiResponder::success($this->request,$this->response,['queued'=>true],202);
  }catch(Throwable){return $this->reject(403,'invitation_denied');}
 }
 public function localPreview(): ResponseInterface {
  try {
   $id=$this->actor();if ($id===null) return $this->reject(401,'unauthenticated');
   if (!$this->rate($id,'preview')) return $this->reject(429,'rate_limited');
   $d=$this->json(['approval_request_id']);
   if ($d===null||!is_int($d['approval_request_id'])) return $this->reject(422,'invalid_request');
   $preview=(new ApprovedInvitationRelease(db_connect()))->localPreview($id,$d['approval_request_id']);
   return ApiResponder::success($this->request,$this->response,$preview);
  }catch(Throwable){return $this->reject(403,'local_preview_unavailable');}
 }
 public function activate(): ResponseInterface {
  try {
   $parsed=ApiInput::jsonObject($this->request,2048);
   if (!$parsed['ok']) return $this->reject(422,'invalid_request');
   $d=$parsed['data'];
   if (count($d)!==3||!array_key_exists('user_id',$d)||!array_key_exists('token',$d)||!array_key_exists('password',$d)||!is_int($d['user_id'])
    ||!is_string($d['token'])||!is_string($d['password'])) return $this->reject(422,'invalid_request');
   if (!service('throttler')->check('iedpu-invite-activation-'.hash('sha256',$this->request->getIPAddress()),8,3600)
    ||!service('throttler')->check('iedpu-invite-activation-user-'.hash('sha256',(string)$d['user_id']),8,3600)) return $this->reject(429,'rate_limited');
   $result=(new InvitationActivation(db_connect()))->complete($d['user_id'],$d['token'],$d['password']);
   if ($result==='weak') return $this->reject(422,'weak_password');
   if ($result!=='activated') return $this->reject(400,'invalid_invitation');
   return ApiResponder::success($this->request,$this->response,['activated'=>true]);
  }catch(Throwable){return $this->reject(400,'invalid_invitation');}
 }
}
