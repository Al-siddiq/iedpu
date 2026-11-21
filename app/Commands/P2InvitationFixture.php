<?php
declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use RuntimeException;
use Throwable;

/**
 * Explicit opt-in disposable database fixture for invitation integration.
 * NEVER use with member/officer data or a shared development database.
 */
final class P2InvitationFixture extends BaseCommand
{
    protected $group = 'IEDPU';
    protected $name = 'iedpu:p2-invitation-fixture';
    protected $description = 'Provision one inactive, passwordless, synthetic invitation fixture locally.';

    public function run(array $params): int
    {
        if (ENVIRONMENT !== 'development'
            || (string) env('IEDPU_ALLOW_SYNTHETIC_AUTH', '0') !== '1'
            || CLI::getOption('confirm-synthetic') === null
            || CLI::getOption('confirm-disposable') === null) {
            CLI::write('REFUSED: development, synthetic opt-in and BOTH explicit confirmations required.', 'red');
            return 1;
        }
        $db = Database::connect();
        if (!in_array((string) $db->hostname, ['localhost', '127.0.0.1', '::1'], true)
            || !preg_match('/\Aiedpu_(?:test|disposable)[a-z0-9_]*\z/iD', (string) $db->database)) {
            CLI::write('REFUSED: dedicated local iedpu_test* or iedpu_disposable* database required.', 'red');
            return 1;
        }
        $address = 'iedpu-invite-fixture@example.invalid';
        if ($db->table('portal_login_identifiers')->where('normalised_value', $address)->countAllResults() !== 0) {
            CLI::write('REFUSED: synthetic invitation fixture already exists.', 'yellow');
            return 1;
        }
        // Require the separate existing synthetic FK-only issuer fixture.
        $actor = $db->table('portal_login_identifiers')
            ->select('user_id')->where('normalised_value', '+2348000000000')
            ->where('status', 'active')->get()->getRowArray();
        if ($actor === null) {
            CLI::write('REFUSED: synthetic phone-only account fixture is required first.', 'yellow');
            return 1;
        }
        $db->transBegin();
        try {
            $users = model(config('Auth')->userProvider);
            $user = $users->createNewUser(['active' => 0]);
            $id = (int) $users->insert($user);
            if ($id < 1 || $id === (int) $actor['user_id']) {
                throw new RuntimeException('Synthetic user creation failed.');
            }
            $bytes = random_bytes(16);
            $bytes[6] = chr((ord($bytes[6]) & 15) | 64);
            $bytes[8] = chr((ord($bytes[8]) & 63) | 128);
            $hex = bin2hex($bytes);
            $uuid = substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-'
                . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
            $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
            if (!$db->table('portal_login_identifiers')->insert([
                'public_id' => $uuid,
                'user_id' => $id,
                'identifier_type' => 'email',
                'normalised_value' => $address,
                'verified_at' => null,
                'status' => 'pending',
                'created_at' => $now,
            ]) || !$db->transStatus() || !$db->transCommit()) {
                throw new RuntimeException('Synthetic identifier creation failed.');
            }
        } catch (Throwable $error) {
            $db->transRollback();
            CLI::write('FAIL: fixture transaction; exception class ' . get_class($error), 'red');
            return 1;
        }
        CLI::write('OK   Inactive synthetic invitation fixture created with pending reserved identifier.', 'green');
        CLI::write('HOLD: No real contact, password, role, activation or invitation route created.', 'yellow');
        CLI::write('Destroy the disposable database after testing.', 'yellow');
        return 0;
    }
}
