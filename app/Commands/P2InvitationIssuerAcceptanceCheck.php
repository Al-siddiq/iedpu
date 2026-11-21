<?php
declare(strict_types=1);

namespace App\Commands;

use App\Libraries\Auth\AccountActionTokenStore;
use App\Libraries\Auth\Delivery\AccountActionDelivery;
use App\Libraries\Auth\Delivery\SyntheticLocalDelivery;
use App\Libraries\Auth\DevelopmentInvitationIssuer;
use App\Libraries\Auth\ScopeAuthorizer;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use ReflectionMethod;
use RuntimeException;
use Throwable;

/**
 * One-shot, persistent acceptance test of the PRIVATE audited issuer core.
 * No fake Shield session, HTTP route, or MFA override is installed.
 * Only run on an isolated database that can be DESTROYED after acceptance.
 */
final class P2InvitationIssuerAcceptanceCheck extends BaseCommand
{
    protected $group = 'IEDPU';
    protected $name = 'iedpu:p2-invitation-issuer-acceptance-check';
    protected $description = 'One-shot disposable invitation issuer transaction/delivery acceptance suite.';

    public function run(array $params): int
    {
        if (ENVIRONMENT !== 'development'
            || (string) env('IEDPU_ALLOW_SYNTHETIC_AUTH', '0') !== '1'
            || CLI::getOption('confirm-synthetic') === null
            || CLI::getOption('confirm-disposable') === null) {
            CLI::write('REFUSED: development, synthetic opt-in and both confirmations required.', 'red');
            return 1;
        }
        $db = Database::connect();
        if (!in_array((string) $db->hostname, ['localhost','127.0.0.1','::1'], true)
            || !preg_match('/\Aiedpu_disposable[a-z0-9_]*\z/iD', (string) $db->database)) {
            CLI::write('REFUSED: dedicated local iedpu_disposable* database required.', 'red');
            return 1;
        }
        $roleCode = 'synthetic_invite_acceptance';
        $phone = '+2348000000000';
        $email = 'iedpu-invite-fixture@example.invalid';
        $actor = $db->table('portal_login_identifiers i')
            ->select('i.user_id,u.active')->join('users u', 'u.id=i.user_id')
            ->where('i.normalised_value', $phone)->where('i.status', 'active')
            ->get()->getRowArray();
        $target = $db->table('portal_login_identifiers i')
            ->select('i.id,i.user_id,u.active')->join('users u', 'u.id=i.user_id')
            ->where('i.normalised_value', $email)->where('i.status', 'pending')
            ->where('i.verified_at', null)->where('i.revoked_at', null)
            ->get()->getRowArray();
        $permission = $db->table('portal_permissions')
            ->select('id')->where('permission_key', 'users.invite')
            ->where('status', 'active')->get()->getRowArray();
        if ($actor === null || (int) $actor['active'] !== 1
            || $target === null || (int) $target['active'] !== 0
            || (int) $actor['user_id'] === (int) $target['user_id']
            || $permission === null
            || $db->table('portal_roles')->where('role_code', $roleCode)->countAllResults() !== 0) {
            CLI::write('REFUSED: missing clean fixtures/catalogue, or suite already run.', 'red');
            CLI::write('HOLD: Do not delete audit history or reuse a populated acceptance database.', 'yellow');
            return 1;
        }
        $issuerId = (int) $actor['user_id'];
        $targetId = (int) $target['user_id'];
        $identifierId = (int) $target['id'];
        foreach (['auth_identities','portal_user_role_assignments','portal_totp_factors'] as $table) {
            if ($db->table($table)->where('user_id', $targetId)->countAllResults() !== 0) {
                CLI::write('REFUSED: target is no longer uncredentialed and unprivileged.', 'red');
                return 1;
            }
        }
        if ($db->table('portal_account_action_tokens')->where('user_id', $targetId)->countAllResults() !== 0) {
            CLI::write('REFUSED: acceptance target already has action-token history.', 'red');
            return 1;
        }

        try {
            // Test-only grant is conspicuous and never assignable. It is
            // persisted because audit rows must not be erased after commit.
            $now = gmdate('Y-m-d H:i:s') . '.000000';
            $db->transBegin();
            $ok = $db->table('portal_roles')->insert([
                'role_code' => $roleCode,
                'role_name' => 'Disposable Invitation Acceptance ONLY',
                'description' => 'TEST ONLY. Destroy this entire disposable database after acceptance.',
                'is_system_role' => 0, 'is_assignable' => 0,
                'requires_second_approval' => 0, 'status' => 'active', 'created_at' => $now,
            ]);
            if (!$ok) {
                throw new RuntimeException('Synthetic role insert failed.');
            }
            $roleId = (int) $db->insertID();
            $ok = $db->table('portal_role_permissions')->insert([
                'role_id' => $roleId, 'permission_id' => (int) $permission['id'],
                'granted_at' => $now, 'granted_by_user_id' => $issuerId,
            ]);
            if (!$ok) {
                throw new RuntimeException('Synthetic permission link failed.');
            }
            $ok = $db->table('portal_user_role_assignments')->insert([
                'public_id' => $this->uuid(),
                'user_id' => $issuerId, 'role_id' => $roleId,
                'scope_type' => 'global', 'starts_at' => $now,
                'assignment_status' => 'active',
                'assignment_reason' => 'DISPOSABLE TEST ONLY; NOT AN APPROVED OPERATIONAL GRANT',
                'assigned_by_user_id' => $issuerId, 'created_at' => $now,
            ]);
            if (!$ok || !$db->transStatus() || !$db->transCommit()) {
                throw new RuntimeException('Synthetic grant transaction failed.');
            }

            $allows = new ScopeAuthorizer($db);
            $this->check($allows->allows($issuerId, 'users.invite', 'global'),
                'Synthetic test-only scoped permission granted');

            $delivery = new SyntheticLocalDelivery(
                ENVIRONMENT, (string) $db->database, (string) $db->hostname, true
            );
            $issuer = new DevelopmentInvitationIssuer($db, $delivery);
            // Invoke ONLY the private core; the public entry point still
            // requires real Shield authentication, current session and MFA.
            $core = new ReflectionMethod(DevelopmentInvitationIssuer::class, 'issueAfterSessionGate');
            $beforeAudit = $db->table('audit_events')->countAllResults();
            $result = $core->invoke($issuer, $issuerId, $targetId, $identifierId, 'global');
            $this->check($result === 'delivered', 'Private issuer core committed successful issuance');
            $messages = $delivery->takeMessages();
            $this->check(count($messages) === 1
                && $messages[0]['recipient'] === $email
                && $messages[0]['purpose'] === 'invitation_activation',
                'Post-commit synthetic delivery captured exactly one invitation');
            $first = $db->table('portal_account_action_tokens')
                ->where('user_id', $targetId)->orderBy('id', 'DESC')->get()->getRowArray();
            $this->check($first !== null && $first['status'] === 'pending'
                && $first['token_hash'] !== $messages[0]['token']
                && hash_equals((string) $first['token_hash'], hash('sha256', $messages[0]['token'])),
                'Committed pending token stored as digest, not plaintext');
            $this->check($db->table('audit_events')->countAllResults() === $beforeAudit + 1
                && $db->table('audit_events')
                    ->where('action_key', 'auth.invitation_issued_synthetic')
                    ->where('subject_id', $targetId)->countAllResults() >= 1,
                'Successful token and durable audit event committed');

            // A deliberate delivery exception MUST revoke the newly issued
            // token in a SECOND audited transaction.
            $failingDelivery = new class implements AccountActionDelivery {
                public function deliver(string $purpose, string $recipient, string $token): void
                {
                    throw new RuntimeException('Synthetic injected delivery failure');
                }
            };
            $failingIssuer = new DevelopmentInvitationIssuer($db, $failingDelivery);
            $auditBeforeFailure = $db->table('audit_events')->countAllResults();
            $result = $core->invoke($failingIssuer, $issuerId, $targetId, $identifierId, 'global');
            $this->check($result === 'delivery_failed', 'Injected delivery failure returned explicit failure');
            $rows = $db->table('portal_account_action_tokens')
                ->where('user_id', $targetId)->orderBy('id', 'ASC')->get()->getResultArray();
            $this->check(count($rows) === 2
                && $rows[0]['status'] === 'revoked'
                && $rows[1]['status'] === 'revoked'
                && $rows[1]['revoked_at'] !== null,
                'Failed delivery revoked newly committed token and superseded token');
            $this->check($db->table('audit_events')->countAllResults() === $auditBeforeFailure + 2
                && $db->table('audit_events')
                    ->where('action_key', 'auth.invitation_delivery_failed_revoked')
                    ->where('subject_id', $targetId)->countAllResults() >= 1,
                'Issuance and compensating revocation each audited');

            // Revoked tokens cannot be consumed; perform redemption check
            // in a rollback-only transaction.
            $db->transBegin();
            $redeemed = (new AccountActionTokenStore($db))->redeemWithinTransaction(
                $messages[0]['token'], 'invitation_activation', $targetId
            );
            $db->transRollback();
            $this->check($redeemed === null, 'Superseded delivered token cannot be redeemed');

            $db->table('portal_user_role_assignments')
                ->where('user_id', $issuerId)->where('role_id', $roleId)
                ->update(['assignment_status' => 'revoked', 'revoked_at' => $now,
                    'revoked_by_user_id' => $issuerId]);
            $this->check(!$allows->allows($issuerId, 'users.invite', 'global'),
                'Revoked scoped permission is denied');
            $beforeTokens = $db->table('portal_account_action_tokens')->countAllResults();
            $beforeAudits = $db->table('audit_events')->countAllResults();
            $this->check($core->invoke($issuer, $issuerId, $targetId, $identifierId, 'global') === 'denied'
                && $db->table('portal_account_action_tokens')->countAllResults() === $beforeTokens
                && $db->table('audit_events')->countAllResults() === $beforeAudits
                && $delivery->takeMessages() === [],
                'Authorization revocation prevents issuance without side effects');
            $this->check($core->invoke($issuer, $issuerId, $issuerId, $identifierId, 'global') === 'denied',
                'Self-invitation denied');

            CLI::write('HOLD: Private core tested; authenticated Shield/MFA entry-point success, concurrency and independent provisioning approval are NOT tested.', 'yellow');
            CLI::write('HOLD: Synthetic grant and audit records remain; DESTROY disposable database after acceptance.', 'yellow');
            return 0;
        } catch (Throwable $error) {
            // Roll back only a still-open transaction on this connection.
            $state = $db->query('SELECT @@in_transaction AS active')->getRowArray();
            if ((int) ($state['active'] ?? 0) === 1) {
                $db->transRollback();
            }
            CLI::write('FAIL: invitation issuer acceptance (' . get_class($error) . ': ' . $error->getMessage() . ').', 'red');
            CLI::write('HOLD: Do not rerun on the same database; inspect disposable state first.', 'yellow');
            return 1;
        }
    }

    private function check(bool $condition, string $label): void
    {
        if (!$condition) {
            throw new RuntimeException($label);
        }
        CLI::write('OK   ' . $label, 'green');
    }

    private function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 15) | 64);
        $b[8] = chr((ord($b[8]) & 63) | 128);
        $h = bin2hex($b);
        return substr($h,0,8) . '-' . substr($h,8,4) . '-'
            . substr($h,12,4) . '-' . substr($h,16,4) . '-' . substr($h,20);
    }
}
