<?php
declare(strict_types=1);

namespace App\Commands;

use App\Libraries\Auth\SessionVersion;
use App\Libraries\Security\AuditTrail;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Throwable;

/** Disposable development fixture ONLY; no real-account revocation CLI. */
final class P2RevokeSyntheticSessions extends BaseCommand
{
    protected $group = 'IEDPU';
    protected $name = 'iedpu:p2-revoke-synthetic-sessions';
    protected $description = 'Invalidate all browser sessions for the local synthetic MFA fixture.';

    public function run(array $params): int
    {
        if (ENVIRONMENT !== 'development'
            || (string) env('IEDPU_ALLOW_SYNTHETIC_AUTH', '0') !== '1'
            || CLI::getOption('confirm-synthetic') === null) {
            CLI::write('REFUSED: development fixture only; enable IEDPU_ALLOW_SYNTHETIC_AUTH and pass --confirm-synthetic.', 'red');
            return 1;
        }
        try {
            $db = Database::connect();
            if (!in_array((string) $db->hostname, ['localhost','127.0.0.1','::1'], true)
                || !preg_match('/\Aiedpu_(?:dev|test|local|disposable)[a-z0-9_]*\z/iD', (string) $db->database)) {
                CLI::write('REFUSED: local disposable/development IEDPU database required.', 'red');
                return 1;
            }
            $identifier = $db->table('portal_login_identifiers')
                ->select('user_id')->where('identifier_type', 'phone')
                ->where('normalised_value', '+2348000000000')
                ->where('status', 'active')->get()->getRowArray();
            if ($identifier === null) {
                CLI::write('Synthetic fixture not found.', 'red');
                return 1;
            }
            $id = (int) $identifier['user_id'];
            $db->transBegin();
            (new SessionVersion($db))->revokeAll($id);
            (new AuditTrail($db))->append([
                'action_key' => 'auth.sessions_revoked',
                'module_code' => 'security',
                'event_category' => 'security',
                'outcome' => 'success',
                'actor_user_id' => $id,
            ]);
            if (!$db->transStatus() || !$db->transCommit()) {
                throw new \RuntimeException('Transaction could not commit.');
            }
            CLI::write('Synthetic fixture browser sessions invalidated. Reauthenticate in every browser.', 'green');
            return 0;
        } catch (Throwable $e) {
            if (isset($db)) {
                $db->transRollback();
            }
            CLI::write('Revocation failed; no successful invalidation can be claimed.', 'red');
            CLI::write('Exception class only: ' . get_class($e), 'yellow');
            return 1;
        }
    }
}
