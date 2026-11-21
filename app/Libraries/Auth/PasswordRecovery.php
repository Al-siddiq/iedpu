<?php
declare(strict_types=1);

namespace App\Libraries\Auth;

use App\Libraries\Security\AtomicAuditedMutation;
use App\Libraries\Security\AuditTrail;
use App\Libraries\Security\RequestId;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * Internal password recovery completion. No public route, token delivery,
 * identity lookup by untrusted input, or privileged lost-device bypass.
 *
 * Future HTTP controller must derive expectedUserId from an independently
 * verified server-side flow, not from a user-supplied arbitrary account ID.
 * Never disclose which eligibility condition failed to an unauthenticated
 * caller. Caller must prove that token was delivered to its intended owner.
 */
final class PasswordRecovery
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /** @return 'changed'|'invalid'|'weak'|'same'|'privileged_hold' */
    public function complete(int $expectedUserId, string $token, string $newPassword): string
    {
        if ($expectedUserId < 1 || !preg_match('/\A[a-f0-9]{64}\z/D', $token)) {
            return 'invalid';
        }
        if (!mb_check_encoding($newPassword, 'UTF-8')
            || mb_strlen($newPassword, 'UTF-8') < 6
            || strlen($newPassword) > 72
            || trim($newPassword) === ''
            || preg_match('/[[:cntrl:]]/', $newPassword)) {
            return 'weak';
        }

        return (new AtomicAuditedMutation($this->db))->run(function () use ($expectedUserId, $token, $newPassword): string {
            // Lock the user before token and credential to match issue ordering.
            $user = $this->db->query(
                'SELECT id,active FROM users WHERE id=? FOR UPDATE', [$expectedUserId]
            )->getRowArray();
            if ($user === null || (int) $user['active'] !== 1) {
                return 'invalid';
            }
            // Password-only recovery is NEVER a way around privileged TOTP.
            // Until an independent recovery procedure is approved, all active
            // role holders and accounts with any TOTP factor are held.
            $assigned = $this->db->table('portal_user_role_assignments')
                ->where('user_id', $expectedUserId)
                ->where('assignment_status', 'active')->countAllResults() > 0;
            $hasFactor = $this->db->table('portal_totp_factors')
                ->where('user_id', $expectedUserId)->countAllResults() > 0;
            if ($assigned || $hasFactor) {
                return 'privileged_hold';
            }

            $identities = $this->db->query(
                "SELECT id,secret2 FROM auth_identities
                 WHERE user_id=? AND type='email_password' FOR UPDATE", [$expectedUserId]
            )->getResultArray();
            if (count($identities) !== 1 || !is_string($identities[0]['secret2'])
                || $identities[0]['secret2'] === '') {
                return 'invalid';
            }
            if (password_verify($newPassword, $identities[0]['secret2'])) {
                return 'same';
            }
            $redeemed = (new AccountActionTokenStore($this->db))
                ->redeemWithinTransaction($token, 'password_reset', $expectedUserId);
            if ($redeemed === null) {
                return 'invalid';
            }
            $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
            if (!is_string($newHash) || !$this->db->table('auth_identities')
                ->where('id', $identities[0]['id'])->update(['secret2' => $newHash])) {
                throw new RuntimeException('Recovery credential update failed.');
            }
            $tokens = new AccountActionTokenStore($this->db);
            $tokens->revokePendingWithinTransaction($expectedUserId);
            (new SessionVersion($this->db))->revokeAll($expectedUserId);
            (new AuditTrail($this->db))->append([
                'action_key' => 'auth.password_recovered',
                'module_code' => 'security',
                'event_category' => 'security',
                'outcome' => 'success',
                'actor_user_id' => $expectedUserId,
                'request_id' => RequestId::current(),
            ]);
            if (!$this->db->transStatus()) {
                throw new RuntimeException('Recovery transaction failed.');
            }
            return 'changed';
        });
    }
}
