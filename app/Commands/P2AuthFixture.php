<?php
declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Shield\Models\UserIdentityModel;
use Config\Database;
use RuntimeException;

/**
 * Strictly local, disposable test fixture; NOT production provisioning.
 * Never invoke on a database containing real member or officer records.
 */
final class P2AuthFixture extends BaseCommand
{
    protected $group = 'IEDPU';
    protected $name = 'iedpu:p2-auth-fixture';
    protected $description = 'Create one synthetic phone-only account for local authentication testing.';

    public function run(array $params): int
    {
        if (ENVIRONMENT !== 'development' || (string) env('IEDPU_ALLOW_SYNTHETIC_AUTH', '0') !== '1'
            || CLI::getOption('confirm-synthetic') === null) {
            CLI::write('REFUSED: requires development, IEDPU_ALLOW_SYNTHETIC_AUTH=1, and --confirm-synthetic.', 'red');
            return 1;
        }
        $db = Database::connect();
        $hostname = (string) $db->hostname;
        $database = (string) $db->database;
        if (!in_array($hostname, ['localhost','127.0.0.1','::1'], true)
            || !preg_match('/\Aiedpu_(?:dev|test|local|disposable)[a-z0-9_]*\z/iD', $database)) {
            CLI::write('REFUSED: use a LOCAL database named iedpu_dev*, iedpu_test*, iedpu_local* or iedpu_disposable*.', 'red');
            return 1;
        }
        $phone = '+2348000000000'; // synthetic only, no SMS must ever be sent.
        if ($db->table('portal_login_identifiers')->where('normalised_value', $phone)->countAllResults() > 0) {
            CLI::write('Synthetic fixture already exists; refusing duplicate.', 'yellow');
            return 1;
        }
        $password = bin2hex(random_bytes(18)) . 'Aa9!';
        $db->transBegin();
        try {
            $users = model(config('Auth')->userProvider);
            $user = $users->createNewUser(['active' => 1]);
            $id = (int) $users->insert($user);
            if ($id < 1) {
                throw new RuntimeException('Fixture user insert failed.');
            }
            $user = $users->findById($id);
            if ($user === null) {
                throw new RuntimeException('Fixture user not found.');
            }
            // Shield's official identity model owns the password hash. Its
            // secret is the real verified identifier, here a synthetic E.164
            // phone, never an invented email or a separate password store.
            model(UserIdentityModel::class)->createEmailIdentity($user, [
                'email' => $phone, 'password' => $password,
            ]);
            $bytes = random_bytes(16);
            $bytes[6] = chr((ord($bytes[6]) & 15) | 64);
            $bytes[8] = chr((ord($bytes[8]) & 63) | 128);
            $hex = bin2hex($bytes);
            $uuid = substr($hex,0,8) . '-' . substr($hex,8,4) . '-' . substr($hex,12,4)
                . '-' . substr($hex,16,4) . '-' . substr($hex,20);
            $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
            if (!$db->table('portal_login_identifiers')->insert([
                'public_id' => $uuid,
                'user_id' => $id,
                'identifier_type' => 'phone',
                'normalised_value' => $phone,
                'verified_at' => $now,
                'status' => 'active',
                'created_at' => $now,
            ]) || !$db->transStatus() || !$db->transCommit()) {
                throw new RuntimeException('Fixture transaction failed.');
            }
        } catch (\Throwable $error) {
            $db->transRollback();
            CLI::write('Fixture creation failed. Inspect logs; do not retry on a partly populated database.', 'red');
            return 1;
        }
        CLI::write('Synthetic phone-only test account created. NEVER send SMS to this number.', 'green');
        CLI::write('Identifier: ' . $phone);
        CLI::write('One-time test password (do not copy to chat, screenshots, or logs): ' . $password);
        CLI::write('Destroy the disposable test database after acceptance.');
        return 0;
    }
}
