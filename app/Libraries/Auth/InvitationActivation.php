<?php
declare(strict_types=1);

namespace App\Libraries\Auth;

use App\Libraries\Security\AtomicAuditedMutation;
use App\Libraries\Security\AuditTrail;
use App\Libraries\Security\RequestId;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Shield\Models\UserIdentityModel;
use RuntimeException;

/**
 * Internal completion of a previously approved and delivered invitation.
 *
 * Account creation, permission-scoped invitation issuance and recipient
 * delivery are NOT implemented here. Only a pending, inactive, pre-provisioned
 * Shield user with one pending identifier and no credential/role can activate.
 * This never grants a portal role or confirms IEDPU membership.
 */
final class InvitationActivation
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /** @return 'activated'|'invalid'|'weak' */
    public function complete(int $expectedUserId, string $token, string $password): string
    {
        if ($expectedUserId < 1 || !preg_match('/\A[a-f0-9]{64}\z/D', $token)) {
            return 'invalid';
        }
        if (!mb_check_encoding($password, 'UTF-8')
            || mb_strlen($password, 'UTF-8') < 6
            || strlen($password) > 72
            || trim($password) === ''
            || preg_match('/[[:cntrl:]]/', $password)) {
            return 'weak';
        }
        return (new AtomicAuditedMutation($this->db))->run(function () use ($expectedUserId, $token, $password): string {
            $row = $this->db->query(
                'SELECT id,active FROM users WHERE id=? FOR UPDATE', [$expectedUserId]
            )->getRowArray();
            if ($row === null || (int) $row['active'] !== 0) {
                return 'invalid';
            }
            // Never activate a pre-granted privileged account through a
            // simple possession token; independent bootstrap is a later gate.
            if ($this->db->table('portal_user_role_assignments')
                ->where('user_id', $expectedUserId)->countAllResults() > 0
                || $this->db->table('portal_totp_factors')
                    ->where('user_id', $expectedUserId)->countAllResults() > 0
                || $this->db->table('auth_identities')
                    ->where('user_id', $expectedUserId)
                    ->where('type', 'email_password')->countAllResults() > 0) {
                return 'invalid';
            }
            $tokenRow = $this->db->query(
                'SELECT issued_by_user_id FROM portal_account_action_tokens
                 WHERE token_hash=? AND purpose=? AND user_id=? FOR UPDATE',
                [AccountActionTokenCodec::digest($token), 'invitation_activation', $expectedUserId]
            )->getRowArray();
            if ($tokenRow === null || $tokenRow['issued_by_user_id'] === null) {
                return 'invalid';
            }
            $redeemed = (new AccountActionTokenStore($this->db))
                ->redeemWithinTransaction($token, 'invitation_activation', $expectedUserId);
            if ($redeemed === null) {
                return 'invalid';
            }
            $identifier = $this->db->query(
                'SELECT identifier_type,normalised_value FROM portal_login_identifiers
                 WHERE id=? AND user_id=? AND status=? FOR UPDATE',
                [$redeemed['identifier_id'], $expectedUserId, 'pending']
            )->getRowArray();
            if ($identifier === null) {
                throw new RuntimeException('Invitation identifier became unavailable.');
            }
            // This Shield API is also used by the accepted phone-only fixture.
            $users = model(config('Auth')->userProvider);
            $user = $users->findById($expectedUserId);
            if ($user === null) {
                throw new RuntimeException('Invitation user missing.');
            }
            model(UserIdentityModel::class)->createEmailIdentity($user, [
                'email' => $identifier['normalised_value'],
                'password' => $password,
            ]);
            $now = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
            if (!$this->db->table('portal_login_identifiers')
                ->where('id', $redeemed['identifier_id'])
                ->where('status', 'pending')
                ->update(['status' => 'active', 'verified_at' => $now, 'updated_at' => $now])
                || !$this->db->table('users')
                    ->where('id', $expectedUserId)->where('active', 0)
                    ->update(['active' => 1])) {
                throw new RuntimeException('Invitation activation update failed.');
            }
            (new AccountActionTokenStore($this->db))->revokePendingWithinTransaction($expectedUserId);
            (new SessionVersion($this->db))->revokeAll($expectedUserId);
            (new AuditTrail($this->db))->append([
                'action_key' => 'auth.invitation_activated',
                'module_code' => 'security',
                'event_category' => 'security',
                'outcome' => 'success',
                'actor_user_id' => $expectedUserId,
                'request_id' => RequestId::current(),
            ]);
            if (!$this->db->transStatus()) {
                throw new RuntimeException('Invitation activation transaction failed.');
            }
            return 'activated';
        });
    }
}
