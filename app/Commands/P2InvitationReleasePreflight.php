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
   $key=base64_decode((string)env('IEDPU_INVITATION_OUTBOX_KEY',''),true);
   if (!is_string($key)||strlen($key)!==SODIUM_CRYPTO_SECRETBOX_KEYBYTES) throw new RuntimeException('Invitation outbox key missing or invalid');
   if ((string)env('CI_ENVIRONMENT','')==='production') {
    $app=config('App');$origin=(string)env('IEDPU_PUBLIC_ORIGIN','');
    if (!str_starts_with($app->baseURL,'https://')||$app->forceGlobalSecureRequests!==true||!preg_match('~\\Ahttps://[a-z0-9.-]+(?::[0-9]+)?\\z~iD',$origin)) throw new RuntimeException('Production baseURL/public origin must be HTTPS with ForceHTTPS enabled');
   }
   foreach (['IEDPU_SMTP_HOST','IEDPU_SMTP_USER','IEDPU_SMTP_PASS','IEDPU_SMTP_FROM','IEDPU_PUBLIC_ORIGIN'] as $key) {
    if ((string)env($key,'')===''||str_contains((string)env($key,''),'CHANGE_ME')) throw new RuntimeException('Missing environment setting: '.$key);
   }
   if (!filter_var((string)env('IEDPU_SMTP_FROM',''),FILTER_VALIDATE_EMAIL)||!in_array((int)env('IEDPU_SMTP_PORT',587),[465,587],true)) throw new RuntimeException('SMTP sender or TLS port invalid');
   CLI::write('OK   SMTP configuration present (connection not tested)','green');
   CLI::write('NOTE: SMTP success is relay acceptance only; mailbox delivery and bounces need provider-side evidence.','yellow');
   CLI::write('NOTE: Reconciliation remains disabled unless enabled under a separately approved two-person incident procedure.','yellow');
   CLI::write('HOLD: Complete maker/checker/MFA/SMTP/activation browser acceptance before production enablement.','yellow');
   return 0;
  }catch(Throwable $e){CLI::write('HOLD: '.$e->getMessage(),'red');return 1;}
 }
}
