<?php
declare(strict_types=1);

namespace App\Libraries\Auth;

use CodeIgniter\Database\BaseConnection;

/**
 * Read-only, fail-closed eligibility gate for a PRE-PROVISIONED invitation.
 * Not an invitation issuer: no token creation, delivery, or role grant.
 *
 * Caller must additionally prove a fresh authenticated MFA session and an
 * independently authorised provisioning action before any issuance is enabled.
 */
final class InvitationIssuanceEligibility
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function allows(
        int $issuerUserId,
        int $targetUserId,
        int $identifierId,
        string $scope,
        ?int $zoneId = null,
        ?int $branchId = null
    ): bool {
        if ($issuerUserId < 1 || $targetUserId < 1 || $identifierId < 1
            || $issuerUserId === $targetUserId) {
            return false;
        }

        // Never combine permission and scope from different role assignments.
        if (!(new ScopeAuthorizer($this->db))->allows(
            $issuerUserId, 'users.invite', $scope, $zoneId, $branchId
        )) {
            return false;
        }

        $target = $this->db->table('users')
            ->select('id,active')->where('id', $targetUserId)->get()->getRowArray();
        if ($target === null || (int) $target['active'] !== 0) {
            return false;
        }

        $identifier = $this->db->table('portal_login_identifiers')
            ->select('id,identifier_type,status,verified_at,revoked_at')
            ->where('id', $identifierId)->where('user_id', $targetUserId)
            ->get()->getRowArray();
        if ($identifier === null
            || !in_array($identifier['identifier_type'], ['email', 'phone'], true)
            || $identifier['status'] !== 'pending'
            || $identifier['verified_at'] !== null
            || $identifier['revoked_at'] !== null) {
            return false;
        }

        // An invitation must never activate a pre-granted or credentialed
        // administrator. Revalidate these predicates under row locks when
        // implementing the eventual transaction-owning issuer.
        foreach (['portal_user_role_assignments', 'portal_totp_factors', 'auth_identities'] as $table) {
            if ($this->db->table($table)->where('user_id', $targetUserId)->countAllResults() !== 0) {
                return false;
            }
        }

        return true;
    }
}
