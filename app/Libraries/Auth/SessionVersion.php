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
    private const ISSUED_AT = 'iedpu_session_issued_at';
    private const LAST_SEEN_AT = 'iedpu_session_last_seen_at';

    // P2 provisional policy: 30 minutes idle, two hours maximum.
    // The underlying CI4 session expiration is also 7,200 seconds.
    private const IDLE_SECONDS = 1800;
    private const ABSOLUTE_SECONDS = 7200;

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
        $now = time();
        session()->set(self::ISSUED_AT, $now);
        session()->set(self::LAST_SEEN_AT, $now);
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
        if ($row === null || !hash_equals((string) $row['session_version'], $version)) {
            return false;
        }

        // Enforce both idle and absolute expiry on the server, not via JS.
        // Missing timestamps (including pre-upgrade sessions) fail closed.
        $issued = session()->get(self::ISSUED_AT);
        $last = session()->get(self::LAST_SEEN_AT);
        $now = time();
        if (!is_int($issued) || !is_int($last)
            || $issued <= 0 || $last < $issued || $last > $now || $issued > $now) {
            return false;
        }
        [$idle, $absolute] = $this->limits();
        if ($now - $last >= $idle || $now - $issued >= $absolute) {
            return false;
        }

        // Refresh idle timer only after the version and both clocks pass.
        // The absolute clock is NEVER refreshed by subsequent requests.
        session()->set(self::LAST_SEEN_AT, $now);
        return true;
    }

    /**
     * A short-lived local-only timing override for manual acceptance.
     * Requires explicit synthetic-fixture opt-in AND a disposable local DB.
     * Production, staging and nonlocal databases always use fixed policy.
     *
     * @return array{int,int} [idle seconds, absolute seconds]
     */
    private function limits(): array
    {
        $defaults = [self::IDLE_SECONDS, self::ABSOLUTE_SECONDS];
        if (ENVIRONMENT !== 'development'
            || (string) env('IEDPU_ALLOW_SYNTHETIC_AUTH', '0') !== '1'
            || !in_array((string) $this->db->hostname, ['localhost', '127.0.0.1', '::1'], true)
            || !preg_match('/\Aiedpu_(?:dev|test|local|disposable)[a-z0-9_]*\z/iD', (string) $this->db->database)) {
            return $defaults;
        }
        $idleRaw = (string) env('IEDPU_SESSION_TEST_IDLE_SECONDS', '');
        $absoluteRaw = (string) env('IEDPU_SESSION_TEST_ABSOLUTE_SECONDS', '');
        if (!preg_match('/\A[0-9]{2,4}\z/D', $idleRaw)
            || !preg_match('/\A[0-9]{2,4}\z/D', $absoluteRaw)) {
            return $defaults;
        }
        $idle = (int) $idleRaw;
        $absolute = (int) $absoluteRaw;
        if ($idle < 30 || $idle > 600 || $absolute < 60
            || $absolute > 1200 || $absolute <= $idle) {
            return $defaults;
        }
        return [$idle, $absolute];
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
        session()->remove(self::ISSUED_AT);
        session()->remove(self::LAST_SEEN_AT);
        session()->remove('iedpu_mfa_verified_user');
        session()->remove('iedpu_mfa_pending');
    }
}
