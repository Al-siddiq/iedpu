<?php
declare(strict_types=1);

namespace App\Libraries\Auth;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Internal token persistence for future authorised invitation and recovery.
 *
 * IMPORTANT: issueWithinTransaction() and redeemWithinTransaction() MUST be
 * called inside one caller-owned database transaction. The caller must
 * atomically commit the intended account change, session invalidation and
 * audit event with redemption. No HTTP/CLI issuance route is provided.
 *
 * A token is proof of possession ONLY when an approved channel actually
 * delivered it to the intended recipient; this class does not send messages.
 */
final class AccountActionTokenStore
{
    private const TTL = [
        'invitation_activation' => 86400,
        'password_reset' => 900,
        'identifier_verification' => 900,
    ];

    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * Generates a fresh, unprinted token and replaces a previous pending
     * token for the same user and purpose. Caller owns the DB transaction.
     * For invitation/verification, the identifier must be pending; for reset
     * it must already be verified and active.
     *
     * @return array{token:string,public_id:string}
     */
    public function issueWithinTransaction(
        int $userId,
        int $identifierId,
        string $purpose,
        ?int $issuerId = null,
        ?int $approvalRequestId = null,
        ?string $requestId = null
    ): array {
        $this->assertPurpose($purpose);
        if ($userId < 1 || $identifierId < 1
            || ($issuerId !== null && $issuerId < 1)
            || ($approvalRequestId !== null && $approvalRequestId < 1)
            || ($requestId !== null && strlen($requestId) > 100)) {
            throw new RuntimeException('Invalid token issuance context.');
        }
        if ($purpose === 'invitation_activation' && $issuerId === null) {
            throw new RuntimeException('Invitation issuer is required.');
        }

        // Serialise same-user issue/resend operations by locking the parent.
        $user = $this->db->query(
            'SELECT id,active FROM users WHERE id=? FOR UPDATE', [$userId]
        )->getRowArray();
        if ($user === null || ($purpose === 'password_reset' && (int) $user['active'] !== 1)) {
            throw new RuntimeException('Account unavailable for requested action.');
        }
        $identifier = $this->db->query(
            'SELECT id,user_id,identifier_type,status,verified_at,revoked_at
             FROM portal_login_identifiers WHERE id=? FOR UPDATE', [$identifierId]
        )->getRowArray();
        if ($identifier === null || (int) $identifier['user_id'] !== $userId
            || !in_array($identifier['identifier_type'], ['email','phone'], true)
            || !$this->eligibleIdentifier($identifier, $purpose)) {
            throw new RuntimeException('Identifier is not eligible for this token.');
        }

        $this->db->query(
            "UPDATE portal_account_action_tokens
             SET status='revoked',revoked_at=UTC_TIMESTAMP(6)
             WHERE user_id=? AND purpose=? AND status='pending'",
            [$userId, $purpose]
        );
        if (!$this->db->transStatus()) {
            throw new RuntimeException('Could not revoke prior pending token.');
        }

        $token = AccountActionTokenCodec::generate();
        $publicId = $this->uuid4();
        $ok = $this->db->table('portal_account_action_tokens')->insert([
            'public_id' => $publicId,
            'user_id' => $userId,
            'login_identifier_id' => $identifierId,
            'purpose' => $purpose,
            'token_hash' => AccountActionTokenCodec::digest($token),
            'delivery_channel' => $identifier['identifier_type'],
            'status' => 'pending',
            'created_at' => $this->utcNow(),
            'expires_at' => $this->utcAfter(self::TTL[$purpose]),
            'issued_by_user_id' => $issuerId,
            'approval_request_id' => $approvalRequestId,
            'request_id' => $requestId,
        ]);
        if (!$ok || !$this->db->transStatus()) {
            throw new RuntimeException('Could not persist account token.');
        }
        return ['token' => $token, 'public_id' => $publicId];
    }

    /**
     * Locks, validates and consumes one token in the CALLER'S transaction.
     * Returns null on invalid/expired/replayed/mismatched tokens. A successful
     * return does not itself change a password or activate an account.
     *
     * @return array{user_id:int,identifier_id:int,delivery_channel:string}|null
     */
    public function redeemWithinTransaction(string $token, string $purpose, int $expectedUserId): ?array
    {
        $this->assertPurpose($purpose);
        if ($expectedUserId < 1 || !preg_match('/\A[a-f0-9]{64}\z/D', $token)) {
            return null;
        }
        $hash = AccountActionTokenCodec::digest($token);
        $row = $this->db->query(
            'SELECT t.id,t.user_id,t.login_identifier_id,t.purpose,t.token_hash,
                    t.delivery_channel,t.status,t.expires_at,
                    i.identifier_type,i.status AS identifier_status,
                    i.verified_at,i.revoked_at,u.active AS user_active
             FROM portal_account_action_tokens t
             INNER JOIN portal_login_identifiers i ON i.id=t.login_identifier_id AND i.user_id=t.user_id
             INNER JOIN users u ON u.id=t.user_id
             WHERE t.token_hash=? FOR UPDATE',
            [$hash]
        )->getRowArray();
        if ($row === null || !hash_equals((string) $row['token_hash'], $hash)
            || (int) $row['user_id'] !== $expectedUserId
            || $row['purpose'] !== $purpose || $row['status'] !== 'pending'
            || $row['delivery_channel'] !== $row['identifier_type']
            || !$this->eligibleIdentifier([
                'status' => $row['identifier_status'],
                'verified_at' => $row['verified_at'],
                'revoked_at' => $row['revoked_at'],
            ], $purpose)
            || ($purpose === 'password_reset' && (int) $row['user_active'] !== 1)) {
            return null;
        }
        $fresh = $this->db->query(
            'SELECT (expires_at > UTC_TIMESTAMP(6)) AS valid
             FROM portal_account_action_tokens WHERE id=?', [(int) $row['id']]
        )->getRowArray();
        if ($fresh === null || (int) $fresh['valid'] !== 1) {
            return null;
        }
        $ok = $this->db->query(
            "UPDATE portal_account_action_tokens
             SET status='consumed',consumed_at=UTC_TIMESTAMP(6)
             WHERE id=? AND status='pending' AND expires_at>UTC_TIMESTAMP(6)",
            [(int) $row['id']]
        );
        if ($ok === false || $this->db->affectedRows() !== 1 || !$this->db->transStatus()) {
            throw new RuntimeException('Concurrent token redemption conflict.');
        }
        return [
            'user_id' => (int) $row['user_id'],
            'identifier_id' => (int) $row['login_identifier_id'],
            'delivery_channel' => $row['delivery_channel'],
        ];
    }

    /** Caller-owned transaction. Invalidate pending actions after recovery. */
    public function revokePendingWithinTransaction(int $userId): void
    {
        if ($userId < 1) {
            throw new RuntimeException('Invalid account.');
        }
        $ok = $this->db->query(
            "UPDATE portal_account_action_tokens
             SET status='revoked',revoked_at=UTC_TIMESTAMP(6)
             WHERE user_id=? AND status='pending'", [$userId]
        );
        if ($ok === false || !$this->db->transStatus()) {
            throw new RuntimeException('Token invalidation failed.');
        }
    }

    /** @param array<string,mixed> $identifier */
    private function eligibleIdentifier(array $identifier, string $purpose): bool
    {
        if ($identifier['revoked_at'] !== null) {
            return false;
        }
        if ($purpose === 'password_reset') {
            return $identifier['status'] === 'active' && $identifier['verified_at'] !== null;
        }
        return $identifier['status'] === 'pending' && $identifier['verified_at'] === null;
    }

    private function assertPurpose(string $purpose): void
    {
        if (!array_key_exists($purpose, self::TTL)) {
            throw new RuntimeException('Unsupported token purpose.');
        }
    }

    private function utcNow(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->format('Y-m-d H:i:s.u');
    }

    private function utcAfter(int $seconds): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify('+' . $seconds . ' seconds')->format('Y-m-d H:i:s.u');
    }

    private function uuid4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex,0,8) . '-' . substr($hex,8,4) . '-'
            . substr($hex,12,4) . '-' . substr($hex,16,4) . '-' . substr($hex,20);
    }
}
