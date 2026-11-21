<?php
declare(strict_types=1);

namespace App\Libraries\Security;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Append-only business/security audit hash chain.
 *
 * Uses a connection-level MySQL advisory lock to serialise the initial and
 * subsequent appends, and a transaction for row+hash atomicity. DB privileges
 * must deny UPDATE/DELETE to the web runtime; checkpoints externalised in WP04.
 */
final class AuditTrail
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * Strictly allowlisted fields only; no request body, credentials or
     * arbitrary before/after payloads. Calling code must not supply secrets.
     *
     * @param array{action_key:string,module_code:string,event_category:string,
     *  outcome:string,reason?:string,actor_user_id?:int|null,
     *  permission_key?:string|null,subject_type?:string|null,subject_id?:int|null,
     *  zone_id?:int|null,branch_id?:int|null,request_id?:string|null} $event
     */
    public function append(array $event): void
    {
        $required = ['action_key', 'module_code', 'event_category', 'outcome'];
        foreach ($required as $key) {
            if (!isset($event[$key]) || !is_string($event[$key]) || $event[$key] === '') {
                throw new RuntimeException('Invalid audit event.');
            }
        }
        $allowed = array_merge($required, ['reason','actor_user_id','permission_key','subject_type','subject_id','zone_id','branch_id','request_id']);
        if (array_diff(array_keys($event), $allowed) !== []) {
            throw new RuntimeException('Audit field not allowlisted.');
        }
        if (!in_array($event['event_category'], ['membership','finance','security','role','content','system','data_access'], true)
            || !in_array($event['outcome'], ['success','failure','denied','deferred'], true)) {
            throw new RuntimeException('Invalid audit classification.');
        }
        if (strlen($event['action_key']) > 120 || strlen($event['module_code']) > 60
            || strlen((string) ($event['reason'] ?? '')) > 500) {
            throw new RuntimeException('Audit field too long.');
        }

        $lock = $this->db->query("SELECT GET_LOCK('iedpu_audit_chain_v1', 5) AS acquired")->getRowArray();
        if ((int) ($lock['acquired'] ?? 0) !== 1) {
            throw new RuntimeException('Audit lock unavailable; operation denied.');
        }
        try {
            $this->db->transBegin();
            try {
                $last = $this->db->query('SELECT event_hash FROM audit_events ORDER BY id DESC LIMIT 1 FOR UPDATE')->getRowArray();
                $previous = $last['event_hash'] ?? null;
                $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
                $row = [
                    'event_uuid' => $this->uuid4(),
                    'occurred_at' => $now,
                    'actor_user_id' => $event['actor_user_id'] ?? null,
                    'action_key' => $event['action_key'],
                    'permission_key' => $event['permission_key'] ?? null,
                    'module_code' => $event['module_code'],
                    'event_category' => $event['event_category'],
                    'severity' => $event['outcome'] === 'success' ? 'info' : 'warning',
                    'subject_type' => $event['subject_type'] ?? null,
                    'subject_id' => $event['subject_id'] ?? null,
                    'zone_id' => $event['zone_id'] ?? null,
                    'branch_id' => $event['branch_id'] ?? null,
                    'outcome' => $event['outcome'],
                    'reason' => $event['reason'] ?? null,
                    'request_id' => $event['request_id'] ?? null,
                    'previous_event_hash' => $previous,
                ];
                $canonical = json_encode($row, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $row['event_hash'] = hash('sha256', ($previous ?? str_repeat('0', 64)) . "\n" . $canonical);
                if (!$this->db->table('audit_events')->insert($row) || !$this->db->transStatus()) {
                    throw new RuntimeException('Audit persistence failed.');
                }
                if (!$this->db->transCommit()) {
                    throw new RuntimeException('Audit commit failed.');
                }
            } catch (\Throwable $e) {
                $this->db->transRollback();
                throw $e;
            }
        } finally {
            $this->db->query("SELECT RELEASE_LOCK('iedpu_audit_chain_v1')");
        }
    }

    private function uuid4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4)
            . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20, 12);
    }
}
