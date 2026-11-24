<?php
declare(strict_types=1);
namespace App\Commands;
use App\Libraries\Security\AtomicAuditedMutation;
use App\Libraries\Security\AuditTrail;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use RuntimeException;
use Throwable;
/** Incident-only revoke, no SMTP retry. Requires operator's explicit token public ID. */
final class P2InvitationReconcile extends BaseCommand {
 protected $group='IEDPU';
 protected $name='iedpu:p2-invitation-reconcile';
 protected $description='Revoke an uncertain SMTP invitation with audited, explicit CLI confirmation.';
 public function run(array $params): int {
  $id=CLI::getOption('token-public-id');
  $ticket=CLI::getOption('incident-ticket');
  if ((string)env('IEDPU_INVITATION_RECONCILE_ENABLED','0')!=='1'
   ||!is_string($id)||!preg_match('/\A[a-f0-9-]{36}\z/iD',$id)
   ||!is_string($ticket)||!preg_match('/\A[A-Z][A-Z0-9-]{5,39}\z/D',$ticket)
   ||CLI::getOption('confirm-revoke')===null) {
   CLI::write('REFUSED: reconciliation disabled by default. Requires an approved two-person incident procedure, IEDPU_INVITATION_RECONCILE_ENABLED=1, --incident-ticket=REF-12345, --token-public-id=<UUID> and --confirm-revoke.','red');return 1;
  }
  try {
   $db=Database::connect();
   (new AtomicAuditedMutation($db))->run(function() use($db,$id): void {
    $row=$db->query('SELECT * FROM portal_invitation_outbox WHERE token_public_id=? FOR UPDATE',[$id])->getRowArray();
    if (!$row||!in_array($row['status'],['sending','uncertain','failed'],true)) throw new RuntimeException('No uncertain/failed dispatch for supplied ID');
    $token=$db->query('SELECT id,user_id,status FROM portal_account_action_tokens WHERE public_id=? FOR UPDATE',[$id])->getRowArray();
    if (!$token) throw new RuntimeException('Token missing');
    if ($token['status']==='pending') {
     $db->query("UPDATE portal_account_action_tokens SET status='revoked',revoked_at=UTC_TIMESTAMP(6) WHERE id=? AND status='pending'",[$token['id']]);
     if ($db->affectedRows()!==1) throw new RuntimeException('Token revocation conflict');
    }
    $db->table('portal_invitation_outbox')->where('id',$row['id'])->update([
     'status'=>'failed','failed_at'=>gmdate('Y-m-d H:i:s'),'ciphertext'=>''
    ]);
    (new AuditTrail($db))->append(['action_key'=>'auth.invitation_uncertain_revoked',
     'module_code'=>'security','event_category'=>'security','outcome'=>'failure',
     'subject_type'=>'user','subject_id'=>(int)$token['user_id']]);
    if (!$db->transStatus()) throw new RuntimeException('Reconciliation transaction failed');
   });
   CLI::write('OK   Uncertain invitation revoked and audited. Retain two-person approval evidence for ticket '.$ticket.'. Do not resend; create a new approved request.','green');return 0;
  }catch(Throwable $e){CLI::write('HOLD: Reconciliation failed; preserve incident evidence.','red');return 1;}
 }
}
