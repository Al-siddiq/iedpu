<?php
declare(strict_types=1);
namespace App\Commands;
use App\Libraries\Auth\InvitationOutboxCrypto;
use App\Libraries\Security\AtomicAuditedMutation;
use App\Libraries\Security\AuditTrail;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use RuntimeException;
use Throwable;
/** Single-job SMTP dispatcher. Run via trusted scheduler, never HTTP. */
final class P2InvitationSmtpDispatch extends BaseCommand {
 protected $group='IEDPU';
 protected $name='iedpu:p2-invitation-smtp-dispatch';
 protected $description='Send one queued approved invitation via configured SMTP.';
 public function run(array $params): int {
  if ((string)env('IEDPU_INVITATION_SMTP_ENABLED','0')!=='1') {
   CLI::write('HOLD: SMTP invitation dispatch disabled.','yellow');return 1;
  }
  $host=(string)env('IEDPU_SMTP_HOST','');
  $user=(string)env('IEDPU_SMTP_USER','');
  $pass=(string)env('IEDPU_SMTP_PASS','');
  $from=(string)env('IEDPU_SMTP_FROM','');
  $origin=rtrim((string)env('IEDPU_PUBLIC_ORIGIN',''),'/');
  $port=(int)env('IEDPU_SMTP_PORT',587);
  if ($host===''||$user===''||$pass===''||!filter_var($from,FILTER_VALIDATE_EMAIL)
   ||!preg_match('~\Ahttps://[a-z0-9.-]+(?::[0-9]+)?\z~iD',$origin)
   ||!in_array($port,[465,587],true)) {CLI::write('REFUSED: SMTP configuration incomplete.','red');return 1;}
  $db=Database::connect();
  try {
   $row=(new AtomicAuditedMutation($db))->run(function() use($db): ?array {
    $row=$db->query("SELECT * FROM portal_invitation_outbox WHERE status='queued' ORDER BY id LIMIT 1 FOR UPDATE")->getRowArray();
    if (!$row) { (new AuditTrail($db))->append(['action_key'=>'auth.invitation_queue_checked','module_code'=>'security','event_category'=>'security','outcome'=>'success']);return null; }
    $token=$db->query('SELECT user_id,status,expires_at FROM portal_account_action_tokens WHERE public_id=? FOR UPDATE',[$row['token_public_id']])->getRowArray();
    if (!$token||$token['status']!=='pending'||$token['expires_at']<=gmdate('Y-m-d H:i:s')) {
     $db->table('portal_invitation_outbox')->where('id',$row['id'])->update(['status'=>'failed','failed_at'=>gmdate('Y-m-d H:i:s')]);
     (new AuditTrail($db))->append(['action_key'=>'auth.invitation_delivery_skipped','module_code'=>'security','event_category'=>'security','outcome'=>'failure']);
     return null;
    }
    $db->table('portal_invitation_outbox')->where('id',$row['id'])->update(['status'=>'sending','claimed_at'=>gmdate('Y-m-d H:i:s'),'attempts'=>((int)$row['attempts'])+1]);
    (new AuditTrail($db))->append(['action_key'=>'auth.invitation_delivery_claimed','module_code'=>'security','event_category'=>'security','outcome'=>'deferred','subject_type'=>'user','subject_id'=>(int)$token['user_id']]);
    return $row+['user_id'=>(int)$token['user_id']];
   });
   if ($row===null) {CLI::write('No deliverable invitations.');return 0;}
   $token=InvitationOutboxCrypto::open((string)$row['ciphertext']);
   $email=service('email');
   $email->initialize(['protocol'=>'smtp','SMTPHost'=>$host,'SMTPUser'=>$user,'SMTPPass'=>$pass,'SMTPPort'=>$port,
    'SMTPCrypto'=>$port===465?'ssl':'tls','SMTPTimeout'=>10,'mailType'=>'text','charset'=>'UTF-8',
    'newline'=>"\r\n",'CRLF'=>"\r\n",'validate'=>true]);
   $email->setFrom($from,'IEDPU')->setTo($row['recipient'])->setSubject('Your IEDPU account invitation');
   $email->setMessage("An invitation to activate your IEDPU account has been approved.\n\nOpen the secure activation page: ".$origin."/sign-in\n\nAccount ID: ".$row['user_id']."\nInvitation token: ".$token."\n\nThe token expires within 24 hours. Do not share it. If unexpected, ignore this email.");
   $sent=$email->send(false);
   (new AtomicAuditedMutation($db))->run(function() use($db,$row,$sent): void {
    $locked=$db->query('SELECT status FROM portal_invitation_outbox WHERE id=? FOR UPDATE',[$row['id']])->getRowArray();
    if (!$locked||$locked['status']!=='sending') throw new RuntimeException('Dispatch state uncertain');
    if (!$sent) {
     $db->query("UPDATE portal_account_action_tokens SET status='revoked',revoked_at=UTC_TIMESTAMP(6) WHERE public_id=? AND status='pending'",[$row['token_public_id']]);
    }
    $db->table('portal_invitation_outbox')->where('id',$row['id'])->update([
     'status'=>$sent?'sent':'failed', $sent?'delivered_at':'failed_at'=>gmdate('Y-m-d H:i:s'),'ciphertext'=>''
    ]);
    (new AuditTrail($db))->append(['action_key'=>$sent?'auth.invitation_smtp_sent':'auth.invitation_smtp_failed_revoked',
     'module_code'=>'security','event_category'=>'security','outcome'=>$sent?'success':'failure',
     'subject_type'=>'user','subject_id'=>$row['user_id']]);
    if (!$db->transStatus()) throw new RuntimeException('Dispatch finalization failed');
   });
   CLI::write($sent?'OK   Invitation sent and audited.':'FAIL   SMTP failed; token revoked and audited.', $sent?'green':'red');
   return $sent?0:1;
  } catch(Throwable $e) {
   // A claimed message with unknown SMTP outcome is NEVER automatically resent.
   // Operator must reconcile sending rows and revoke tokens before any retry.
   CLI::write('HOLD: SMTP dispatch uncertain; investigate sending outbox rows and revoke affected tokens.','red');
   return 1;
  }
 }
}
