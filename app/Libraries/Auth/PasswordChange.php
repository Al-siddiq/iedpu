<?php
declare(strict_types=1);

namespace App\Libraries\Auth;

use App\Libraries\Security\AuditTrail;
use App\Libraries\Security\RequestId;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * WP02 authenticated password rotation.
 * Password update, account-wide session invalidation and chained audit append
 * are a single DB transaction. No email, token, public reset or role mutation.
 */
final class PasswordChange
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * @return string 'changed'|'incorrect'|'weak'|'same'|'unavailable'
     */
    public function change(int $userId, string $current, string $next): string
    {
        if ($userId < 1 || strlen($current) > 1024 || $current === '') {
            return 'incorrect';
        }
        if (strlen($next) < 12 || strlen($next) > 72
            || preg_match('/[[:cntrl:]]/', $next)
            || !preg_match('/[A-Za-z]/', $next)
            || !preg_match('/[0-9]/', $next)) {
            return 'weak';
        }
        if (hash_equals($current, $next)) {
            return 'same';
        }

        $this->db->transBegin();
        try {
            // Serialize concurrent changes and re-check the current hash
            // INSIDE the transaction; only one competing rotation can win.
            $rows = $this->db->query(
                "SELECT ai.id, ai.secret2, u.active
                 FROM auth_identities ai
                 INNER JOIN users u ON u.id=ai.user_id
                 WHERE ai.user_id=? AND ai.type='email_password'
                 FOR UPDATE",
                [$userId]
            )->getResultArray();
            if (count($rows) !== 1 || (int) $rows[0]['active'] !== 1) {
                throw new RuntimeException('Account not eligible.');
            }
            $identity = $rows[0];
            if (!is_string($identity['secret2']) || !password_verify($current, $identity['secret2'])) {
                $this->db->transRollback();
                return 'incorrect';
            }
            if (password_verify($next, $identity['secret2'])) {
                $this->db->transRollback();
                return 'same';
            }
            // Shield uses PHP-compatible password hashes. No raw passwords
            // are persisted or written to audit, telemetry or logs.
            $hash = password_hash($next, PASSWORD_DEFAULT);
            if (!is_string($hash) || !$this->db->table('auth_identities')
                ->where('id', $identity['id'])->update(['secret2' => $hash])) {
                throw new RuntimeException('Credential update failed.');
            }
            (new SessionVersion($this->db))->revokeAll($userId);
            (new AuditTrail($this->db))->append([
                'action_key' => 'auth.password_changed',
                'module_code' => 'security',
                'event_category' => 'security',
                'outcome' => 'success',
                'actor_user_id' => $userId,
                'request_id' => RequestId::current(),
            ]);
            if (!$this->db->transStatus() || !$this->db->transCommit()) {
                throw new RuntimeException('Password transaction failed.');
            }
            return 'changed';
        } catch (\Throwable $exception) {
            $this->db->transRollback();
            throw $exception;
        }
    }
}
