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

/**
 * Disposable-local-only real nested-audit/outer-commit acceptance.
 * Persists exactly one non-secret synthetic audit event on success.
 */
final class P2AtomicAuditCheck extends BaseCommand
{
    protected $group = 'IEDPU';
    protected $name = 'iedpu:p2-atomic-audit-check';
    protected $description = 'Verify nested audit rollback and outer commit on a disposable local DB.';

    public function run(array $params): int
    {
        if (ENVIRONMENT !== 'development'
            || (string) env('IEDPU_ALLOW_SYNTHETIC_AUTH', '0') !== '1'
            || CLI::getOption('confirm-synthetic') === null) {
            CLI::write('REFUSED: development and synthetic opt-in required.', 'red');
            return 1;
        }
        $db = Database::connect();
        if (!in_array((string) $db->hostname, ['localhost','127.0.0.1','::1'], true)
            || !preg_match('/\Aiedpu_(?:dev|test|local|disposable)[a-z0-9_]*\z/iD', (string) $db->database)) {
            CLI::write('REFUSED: disposable local IEDPU database required.', 'red');
            return 1;
        }
        try {
            $before = $db->table('audit_events')->countAllResults();
            $atomic = new AtomicAuditedMutation($db);
            $rolledBack = false;
            try {
                $atomic->run(static function () use ($db): void {
                    (new AuditTrail($db))->append([
                        'action_key' => 'security.synthetic_rollback_check',
                        'module_code' => 'security',
                        'event_category' => 'security',
                        'outcome' => 'success',
                    ]);
                    throw new RuntimeException('Intentional synthetic rollback.');
                });
            } catch (RuntimeException $exception) {
                $rolledBack = $exception->getMessage() === 'Intentional synthetic rollback.';
            }
            if (!$rolledBack || $db->table('audit_events')->countAllResults() !== $before) {
                throw new RuntimeException('Outer rollback failed to remove nested audit.');
            }
            $atomic->run(static function () use ($db): void {
                (new AuditTrail($db))->append([
                    'action_key' => 'security.synthetic_atomic_audit_check',
                    'module_code' => 'security',
                    'event_category' => 'security',
                    'outcome' => 'success',
                ]);
            });
            if ($db->table('audit_events')->countAllResults() !== $before + 1) {
                throw new RuntimeException('Outer commit did not persist exactly one audit event.');
            }
            CLI::write('OK   Nested audit append rolled back with outer transaction', 'green');
            CLI::write('OK   Nested audit append committed with outer transaction', 'green');
            CLI::write('OK   Exactly one non-secret synthetic audit event persisted', 'green');
            CLI::write('NEXT: Run php spark iedpu:p2-audit-verify.', 'yellow');
            return 0;
        } catch (Throwable $exception) {
            CLI::write('FAIL: atomic audit acceptance; exception class: ' . get_class($exception), 'red');
            CLI::write('STOP: inspect local logs and audit chain before retrying.', 'red');
            return 1;
        }
    }
}
