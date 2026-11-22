<?php
declare(strict_types=1);
namespace App\Commands;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use App\Libraries\Auth\InvitationOutboxCrypto;
use RuntimeException;
use Throwable;
/** Read-only preflight, not a substitute for HTTP/SMTP end-to-end acceptance. */
final class P2InvitationReleasePreflight extends BaseCommand {
 protected $group='IEDPU';
 protected $name='iedpu:p2-invitation-release-preflight';
 protected $description='Check invitation schema, crypto, SMTP configuration and dispatch reconciliation state.';
 public function run(array $params): int {
  try {
   $db=Database::connect();
   foreach (['approval_requests','approval_decisions','portal_approval_action_bindings','portal_account_action_tokens','portal_invitation_outbox','audit_events','portal_totp_factors'] as $table) {
    if (!$db->tableExists($table)) throw new RuntimeException('Missing required table: '.$table);
    CLI::write('OK   Table '.$table,'green');
   }
   $sample=str_repeat('a',64);
   if (InvitationOutboxCrypto::open(InvitationOutboxCrypto::seal($sample))!==$sample) throw new RuntimeException('Crypto round-trip failed');
   CLI::write('OK   Encrypted outbox round-trip (no secret printed)','green');
   $sending=$db->table('portal_invitation_outbox')->where('status','sending')->countAllResults();
   $uncertain=$db->table('portal_invitation_outbox')->where('status','uncertain')->countAllResults();
   if ($sending+$uncertain>0) throw new RuntimeException('Unreconciled sending/uncertain messages: '.($sending+$uncertain));
   CLI::write('OK   No unreconciled sending/uncertain outbox rows','green');
   if ((string)env('IEDPU_INVITATION_SMTP_ENABLED','0')!=='1') throw new RuntimeException('SMTP delivery is disabled');
   foreach (['IEDPU_SMTP_HOST','IEDPU_SMTP_USER','IEDPU_SMTP_PASS','IEDPU_SMTP_FROM','IEDPU_PUBLIC_ORIGIN'] as $key) {
    if ((string)env($key,'')==='') throw new RuntimeException('Missing environment setting: '.$key);
   }
   CLI::write('OK   SMTP configuration present (connection not tested)','green');
   CLI::write('HOLD: Complete maker/checker/MFA/SMTP/activation browser acceptance before production enablement.','yellow');
   return 0;
  }catch(Throwable $e){CLI::write('HOLD: '.$e->getMessage(),'red');return 1;}
 }
}
