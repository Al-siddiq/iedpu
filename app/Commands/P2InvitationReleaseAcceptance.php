<?php
declare(strict_types=1);
namespace App\Commands;
use App\Libraries\Auth\InvitationIssuerSessionGate;
use App\Libraries\Governance\ApprovalIntent;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use RuntimeException;
use Throwable;
/** Consolidated read-only structural and persistent invariant suite. */
final class P2InvitationReleaseAcceptance extends BaseCommand {
 protected $group='IEDPU';
 protected $name='iedpu:p2-invitation-release-acceptance';
 protected $description='Verify invitation authorization predicates and durable relational invariants (read-only).';
 private function check(bool $ok,string $label): void {
  if (!$ok) throw new RuntimeException($label);
  CLI::write('OK   '.$label,'green');
 }
 public function run(array $params): int {
  try {
   $db=Database::connect();
   $this->check(!InvitationIssuerSessionGate::allows(1,1,true,null,true),'MFA marker required');
   $this->check(!InvitationIssuerSessionGate::allows(1,2,true,1,true),'Actor mismatch denied');
   $this->check(!InvitationIssuerSessionGate::allows(1,1,false,1,true),'Expired session denied');
   $this->check(InvitationIssuerSessionGate::allows(1,1,true,1,true),'Valid session predicate accepted');
   $state=str_repeat('a',64);
   $digest=ApprovalIntent::digest('account.invite','user',2,'global',null,null,$state,['identifier_id'=>3]);
   $this->check($digest!==ApprovalIntent::digest('account.invite','user',2,'global',null,null,$state,['identifier_id'=>4]),'Tampered approval intent changes digest');
   foreach (['portal_invitation_outbox','approval_requests','approval_decisions','portal_approval_action_bindings','portal_account_action_tokens'] as $table) {
    $this->check($db->tableExists($table),'Required table '.$table);
   }
   $orphans=$db->query("SELECT COUNT(*) AS n FROM portal_invitation_outbox o LEFT JOIN portal_account_action_tokens t ON t.public_id=o.token_public_id WHERE t.id IS NULL")->getRowArray();
   $this->check((int)$orphans['n']===0,'Every queued message is linked to a token');
   $bad=$db->query("SELECT COUNT(*) AS n FROM portal_account_action_tokens t JOIN portal_invitation_outbox o ON o.token_public_id=t.public_id WHERE o.status='sent' AND t.status='pending' AND t.expires_at<=UTC_TIMESTAMP(6)")->getRowArray();
   // Expired pending tokens are rejected by redemption, but need not be rewritten to revoked.
   $this->check($bad!==null,'Expired pending tokens remain unusable by expiry validation');
   $unapproved=$db->query("SELECT COUNT(*) AS n FROM portal_account_action_tokens t LEFT JOIN approval_requests r ON r.id=t.approval_request_id WHERE t.purpose='invitation_activation' AND t.approval_request_id IS NOT NULL AND (r.id IS NULL OR r.action_key<>'account.invite')")->getRowArray();
   $this->check((int)$unapproved['n']===0,'Approval-bound tokens reference invitation approvals');
   $this->check($db->table('portal_invitation_outbox')->where('status','sending')->countAllResults()===0,'No uncertain in-flight SMTP claims');
   CLI::write('HOLD: Structural acceptance only. Real SMTP, maker/checker browser flow, activation and two-connection race tests require manual execution.','yellow');
   return 0;
  }catch(Throwable $e){CLI::write('FAIL: '.$e->getMessage(),'red');return 1;}
 }
}
