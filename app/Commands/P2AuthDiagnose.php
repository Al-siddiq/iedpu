<?php
declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Throwable;

/**
 * Read-only, local-only P2 authentication readiness diagnostic.
 * Prints no credentials, keys, password hashes, session IDs or personal data.
 */
final class P2AuthDiagnose extends BaseCommand
{
    protected $group = 'IEDPU';
    protected $name = 'iedpu:p2-auth-diagnose';
    protected $description = 'Safely diagnose a local synthetic P2 sign-in without displaying secrets.';

    public function run(array $params): int
    {
        if (ENVIRONMENT !== 'development') {
            CLI::write('REFUSED: development environment only.', 'red');
            return 1;
        }
        try {
            $db = Database::connect();
            if (!in_array((string) $db->hostname, ['localhost', '127.0.0.1', '::1'], true)
                || !preg_match('/\Aiedpu_(?:dev|test|local|disposable)[a-z0-9_]*\z/iD', (string) $db->database)) {
                CLI::write('REFUSED: use a local disposable/development IEDPU database.', 'red');
                return 1;
            }
            CLI::write('IEDPU P2 local sign-in readiness (read-only; no secrets)', 'yellow');
            $ready = true;
            foreach (['users', 'auth_identities', 'portal_login_identifiers',
                'portal_roles', 'portal_user_role_assignments',
                'portal_totp_factors', 'portal_totp_recovery_codes',
                'audit_events', 'security_events'] as $table) {
                $exists = $db->tableExists($table);
                CLI::write(($exists ? 'OK   ' : 'FAIL ') . 'Database table: ' . $table, $exists ? 'green' : 'red');
                $ready = $ready && $exists;
            }
            $key = base64_decode((string) env('IEDPU_TOTP_KEY', ''), true);
            $hasKey = extension_loaded('sodium') && is_string($key)
                && strlen($key) === SODIUM_CRYPTO_SECRETBOX_KEYBYTES;
            CLI::write(($hasKey ? 'OK   ' : 'FAIL ') . 'Sodium extension and valid private TOTP key', $hasKey ? 'green' : 'red');
            $ready = $ready && $hasKey;
            if (!$db->tableExists('portal_login_identifiers') || !$db->tableExists('users')
                || !$db->tableExists('auth_identities')) {
                CLI::write('Run spark migrate --all before inspecting the fixture.', 'yellow');
                return 1;
            }
            $phone = '+2348000000000';
            $identifier = $db->table('portal_login_identifiers')
                ->select('user_id,status,verified_at,revoked_at')
                ->where('identifier_type', 'phone')
                ->where('normalised_value', $phone)
                ->get()->getRowArray();
            if ($identifier === null) {
                CLI::write('FAIL Synthetic phone-only fixture not found.', 'red');
                CLI::write('On a disposable DB only: spark iedpu:p2-auth-fixture --confirm-synthetic --with-mfa-role', 'yellow');
                return 1;
            }
            $validIdentifier = $identifier['status'] === 'active'
                && $identifier['verified_at'] !== null && $identifier['revoked_at'] === null;
            CLI::write(($validIdentifier ? 'OK   ' : 'FAIL ') . 'Synthetic identifier active and verified', $validIdentifier ? 'green' : 'red');
            $ready = $ready && $validIdentifier;
            $userId = (int) $identifier['user_id'];
            $user = $db->table('users')->select('active')->where('id', $userId)->get()->getRowArray();
            $active = $user !== null && (int) $user['active'] === 1;
            CLI::write(($active ? 'OK   ' : 'FAIL ') . 'Synthetic Shield user active', $active ? 'green' : 'red');
            $ready = $ready && $active;
            $identity = $db->table('auth_identities')
                ->select('secret,secret2')
                ->where('user_id', $userId)
                ->where('type', 'email_password')
                ->get()->getRowArray();
            $hasPassword = $identity !== null && $identity['secret'] === $phone
                && is_string($identity['secret2']) && password_get_info($identity['secret2'])['algo'] !== null;
            CLI::write(($hasPassword ? 'OK   ' : 'FAIL ') . 'Synthetic Shield password identity and hash', $hasPassword ? 'green' : 'red');
            $ready = $ready && $hasPassword;
            if ($db->tableExists('portal_user_role_assignments') && $db->tableExists('portal_roles')) {
                $roles = $db->table('portal_user_role_assignments a')
                    ->join('portal_roles r', 'r.id=a.role_id')
                    ->where('a.user_id', $userId)
                    ->where('a.assignment_status', 'active')
                    ->where('r.status', 'active')
                    ->countAllResults();
                CLI::write(($roles > 0 ? 'OK   ' : 'NOTE ') . 'Synthetic MFA-triggering role: '
                    . ($roles > 0 ? 'present' : 'absent; password-only test'), $roles > 0 ? 'green' : 'yellow');
            }
            CLI::write($ready
                ? 'Preflight passed. If login still returns 503, inspect IEDPU_AUTH_DIAG stage in writable/logs.'
                : 'Preflight failed. Correct the FAIL items before retrying.',
                $ready ? 'green' : 'red');
            return $ready ? 0 : 1;
        } catch (Throwable $error) {
            CLI::write('Local diagnostic could not complete. Check database connectivity and migration status.', 'red');
            CLI::write('Exception class only: ' . get_class($error), 'yellow');
            return 1;
        }
    }
}
