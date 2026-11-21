<?php
declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/** Read-only deterministic hash-chain verification. */
final class P2AuditVerify extends BaseCommand
{
    protected $group = 'IEDPU';
    protected $name = 'iedpu:p2-audit-verify';
    protected $description = 'Verify all existing P2 audit hash links and payload hashes (read-only).';

    public function run(array $params): int
    {
        $db = Database::connect();
        $rows = $db->table('audit_events')->orderBy('id', 'ASC')->get()->getResultArray();
        $previous = null;
        foreach ($rows as $row) {
            if (($row['previous_event_hash'] ?? null) !== $previous) {
                CLI::write('FAILED: chain link mismatch at event id ' . $row['id'], 'red');
                return 1;
            }
            $canonical = [];
            foreach ([
                'event_uuid','occurred_at','actor_user_id','action_key','permission_key',
                'module_code','event_category','severity','subject_type','subject_id',
                'zone_id','branch_id','outcome','reason','request_id','previous_event_hash',
            ] as $key) {
                $canonical[$key] = $row[$key] ?? null;
            }
            // Database values returned as strings need the same integer types
            // used when hashing original inserted payloads.
            foreach (['actor_user_id','subject_id','zone_id','branch_id'] as $key) {
                if ($canonical[$key] !== null) {
                    $canonical[$key] = (int) $canonical[$key];
                }
            }
            $encoded = json_encode($canonical, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            $expected = hash('sha256', ($previous ?? str_repeat('0',64)) . "\n" . $encoded);
            if (!hash_equals($expected, (string) $row['event_hash'])) {
                CLI::write('FAILED: hash mismatch at event id ' . $row['id'], 'red');
                return 1;
            }
            $previous = (string) $row['event_hash'];
        }
        CLI::write('Verified ' . count($rows) . ' audit events; last hash: ' . ($previous ?? '(empty)'), 'green');
        CLI::write('NOTE: this verifies current DB contents, not external immutability or deletion of all rows.');
        return 0;
    }
}
