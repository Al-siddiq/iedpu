<?php
declare(strict_types=1);

namespace App\Libraries\Auth;

use App\Libraries\Auth\Delivery\SyntheticLocalDelivery;
use App\Libraries\Security\AtomicAuditedMutation;
use App\Libraries\Security\AuditTrail;
use App\Libraries\Security\RequestId;
use CodeIgniter\Database\BaseConnection;
use RuntimeException;
use Throwable;

/**
 * INTERNAL disposable-development-only invitation issuance.
 *
 * Requires an existing, inactive, unprivileged pre-provisioned account.
 * No HTTP/CLI route invokes this service. No real delivery or role grant.
 * A future route must independently enforce CSRF, throttling and provisioning
 * approval; until then this is not an operational invitation workflow.
 */
final class DevelopmentInvitationIssuer
{
    public function __construct(
        private readonly BaseConnection $db,
        private readonly SyntheticLocalDelivery $delivery
    ) {
    }

    /**
     * @return 'delivered'|'denied'|'delivery_failed'
     */
    public function issue(
        int $issuerId,
        int $targetId,
        int $identifierId,
        string $scope,
        ?int $zoneId = null,
        ?int $branchId = null
    ): string {
        $this->assertDisposableLocal();

        // Never accept a claimed actor or MFA marker as a method argument.
        // Shield and server-side session are the only sources of identity.
        $actor = auth('session')->user();
        if ($actor === null || (int) $actor->id !== $issuerId || $actor->isBanned()
            || !(new SessionVersion($this->db))->isCurrent($issuerId)
            || !InvitationIssuerSessionGate::allows(
                (int) $actor->id,
                $issuerId,
                true,
                session()->get('iedpu_mfa_verified_user'),
                true
            )) {
            return 'denied';
        }
        if ($issuerId === $targetId || $issuerId < 1 || $targetId < 1 || $identifierId < 1) {
            return 'denied';
        }

        // Do not begin a write transaction if the preliminary gate fails.
        if (!(new InvitationIssuanceEligibility($this->db))->allows(
            $issuerId, $targetId, $identifierId, $scope, $zoneId, $branchId
        )) {
            return 'denied';
        }

        // All mutable account eligibility predicates are rechecked under the
        // transaction. Token and success audit append commit or roll back as one.
        $issued = (new AtomicAuditedMutation($this->db))->run(function () use (
            $issuerId, $targetId, $identifierId, $scope, $zoneId, $branchId
        ): ?array {
            $user = $this->db->query(
                'SELECT id,active FROM users WHERE id=? FOR UPDATE', [$targetId]
            )->getRowArray();
            if ($user === null || (int) $user['active'] !== 0) {
                return null;
            }

            // Lock the identifier before token creation, preventing concurrent
            // identifier state changes from silently passing this check.
            $identifier = $this->db->query(
                'SELECT id,identifier_type,normalised_value,status,verified_at,revoked_at
                 FROM portal_login_identifiers WHERE id=? AND user_id=? FOR UPDATE',
                [$identifierId, $targetId]
            )->getRowArray();
            if ($identifier === null
                || !in_array($identifier['identifier_type'], ['email', 'phone'], true)
                || $identifier['status'] !== 'pending'
                || $identifier['verified_at'] !== null
                || $identifier['revoked_at'] !== null) {
                return null;
            }

            // Scope and permission must still be valid at issuance time.
            if (!(new ScopeAuthorizer($this->db))->allows(
                $issuerId, 'users.invite', $scope, $zoneId, $branchId
            )) {
                return null;
            }
            foreach (['portal_user_role_assignments', 'portal_totp_factors', 'auth_identities'] as $table) {
                if ($this->db->table($table)->where('user_id', $targetId)->countAllResults() !== 0) {
                    return null;
                }
            }

            $token = (new AccountActionTokenStore($this->db))->issueWithinTransaction(
                $targetId, $identifierId, 'invitation_activation', $issuerId,
                null, RequestId::current()
            );
            (new AuditTrail($this->db))->append([
                'action_key' => 'auth.invitation_issued_synthetic',
                'module_code' => 'security',
                'event_category' => 'security',
                'outcome' => 'success',
                'actor_user_id' => $issuerId,
                'subject_type' => 'user',
                'subject_id' => $targetId,
                'request_id' => RequestId::current(),
            ]);
            if (!$this->db->transStatus()) {
                throw new RuntimeException('Synthetic invitation issuance transaction failed.');
            }
            return [
                'token' => $token['token'],
                'public_id' => $token['public_id'],
                'recipient' => $identifier['normalised_value'],
            ];
        });

        if ($issued === null) {
            return 'denied';
        }

        // External effects NEVER occur inside the account/audit transaction.
        try {
            $this->delivery->deliver('invitation_activation', $issued['recipient'], $issued['token']);
            return 'delivered';
        } catch (Throwable $deliveryError) {
            // A failed delivery must revoke exactly the newly issued token.
            // Never revoke a newer token created by a concurrent request.
            (new AtomicAuditedMutation($this->db))->run(function () use ($issued, $issuerId, $targetId): void {
                $row = $this->db->query(
                    'SELECT id,status FROM portal_account_action_tokens WHERE public_id=? AND user_id=? FOR UPDATE',
                    [$issued['public_id'], $targetId]
                )->getRowArray();
                if ($row === null || $row['status'] !== 'pending') {
                    throw new RuntimeException('Cannot confirm failed-delivery token revocation.');
                }
                $ok = $this->db->query(
                    "UPDATE portal_account_action_tokens SET status='revoked',revoked_at=UTC_TIMESTAMP(6)
                     WHERE id=? AND status='pending'",
                    [(int) $row['id']]
                );
                if ($ok === false || $this->db->affectedRows() !== 1) {
                    throw new RuntimeException('Failed-delivery token revocation failed.');
                }
                (new AuditTrail($this->db))->append([
                    'action_key' => 'auth.invitation_delivery_failed_revoked',
                    'module_code' => 'security',
                    'event_category' => 'security',
                    'outcome' => 'failure',
                    'actor_user_id' => $issuerId,
                    'subject_type' => 'user',
                    'subject_id' => $targetId,
                    'request_id' => RequestId::current(),
                ]);
                if (!$this->db->transStatus()) {
                    throw new RuntimeException('Failed-delivery revocation audit failed.');
                }
            });
            return 'delivery_failed';
        }
    }

    private function assertDisposableLocal(): void
    {
        if (ENVIRONMENT !== 'development'
            || (string) env('IEDPU_ALLOW_SYNTHETIC_AUTH', '0') !== '1'
            || !in_array((string) $this->db->hostname, ['localhost', '127.0.0.1', '::1'], true)
            || !preg_match('/\Aiedpu_(?:dev|test|local|disposable)[a-z0-9_]*\z/iD', (string) $this->db->database)) {
            throw new RuntimeException('Development invitation issuance disabled.');
        }
    }
}
