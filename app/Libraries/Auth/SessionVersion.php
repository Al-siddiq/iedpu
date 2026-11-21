<?php
declare(strict_types=1);

namespace App\Libraries\Auth;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Account-wide session revocation with a monotonically increasing DB version.
 *
 * Existing sessions from before this migration have no version stamp and fail
 * closed on the next protected request. Only password/MFA success can stamp.
 */
final class SessionVersion
{
    private const SESSION_KEY = 'iedpu_session_version';
    private const SESSION_USER = 'iedpu_session_version_user';

    public function __construct(private readonly BaseConnection $db)
    {
    }

    private function ensureRow(int $userId): void
    {
        if ($userId < 1) {
            throw new RuntimeException('Invalid account.');
        }
        $now = gmdate('Y-m-d H:i:s') . '.000000';
        // INSERT IGNORE is safe for a single unique primary key. Never
        // overwrite a concurrently incremented session version.
        $ok = $this->db->query(
            'INSERT IGNORE INTO portal_user_session_versions (user_id,session_version,created_at,updated_at) VALUES (?,1,?,?)',
            [$userId, $now, $now]
        );
        if ($ok === false) {
            throw new RuntimeException('Cannot initialise session version.');
        }
    }

    /** Call ONLY after successful Shield password login or verified MFA. */
    public function stamp(int $userId): void
    {
        $this->ensureRow($userId);
        $row = $this->db->table('portal_user_session_versions')
            ->select('session_version')->where('user_id', $userId)->get()->getRowArray();
        if ($row === null || (int) $row['session_version'] < 1) {
            throw new RuntimeException('Session version unavailable.');
        }
        session()->set(self::SESSION_USER, $userId);
        session()->set(self::SESSION_KEY, (string) $row['session_version']);
    }

    /** Fail closed when the stamp is absent, malformed, stale or revoked. */
    public function isCurrent(int $userId): bool
    {
        if ($userId < 1 || (int) session()->get(self::SESSION_USER) !== $userId) {
            return false;
        }
        $version = session()->get(self::SESSION_KEY);
        if (!is_string($version) || !preg_match('/\A[1-9][0-9]*\z/D', $version)) {
            return false;
        }
        $row = $this->db->table('portal_user_session_versions')
            ->select('session_version')->where('user_id', $userId)->get()->getRowArray();
        return $row !== null && hash_equals((string) $row['session_version'], $version);
    }

    /**
     * Account-wide invalidation; existing browser sessions fail on next
     * request. The caller must enforce independent authorisation and audit.
     */
    public function revokeAll(int $userId): void
    {
        $this->ensureRow($userId);
        $updated = $this->db->query(
            'UPDATE portal_user_session_versions
             SET session_version=session_version+1,updated_at=UTC_TIMESTAMP(6)
             WHERE user_id=? AND session_version < 18446744073709551615',
            [$userId]
        );
        if ($updated === false || $this->db->affectedRows() !== 1) {
            throw new RuntimeException('Account session revocation failed.');
        }
    }

    public static function clearLocal(): void
    {
        session()->remove(self::SESSION_KEY);
        session()->remove(self::SESSION_USER);
        session()->remove('iedpu_mfa_verified_user');
        session()->remove('iedpu_mfa_pending');
    }
}
