<?php
declare(strict_types=1);

namespace App\Libraries\Auth;

use CodeIgniter\Database\BaseConnection;

/**
 * Deny-by-default database permission decisions. Permission and scope MUST
 * belong to the same active assignment row; never merge separate grants.
 *
 * This service authorises a known resource context. It is not a substitute
 * for scoping every SQL list/count/detail query in a calling repository.
 */
final class ScopeAuthorizer
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    /**
     * @param 'global'|'zone'|'branch' $scope
     */
    public function allows(int $userId, string $permission, string $scope, ?int $zoneId = null, ?int $branchId = null): bool
    {
        if ($userId < 1 || !preg_match('/\A[a-z][a-z0-9_]*\.[a-z][a-z0-9_]*\z/D', $permission)) {
            return false;
        }
        if (!in_array($scope, ['global', 'zone', 'branch'], true)) {
            return false;
        }
        if (($scope === 'global' && ($zoneId !== null || $branchId !== null))
            || ($scope === 'zone' && ($zoneId === null || $branchId !== null))
            || ($scope === 'branch' && $branchId === null)) {
            return false;
        }

        // Resolve the target resource from the database; never trust a supplied
        // branch-zone relationship or accept inactive/out-of-project zones.
        if ($scope === 'branch') {
            $branch = $this->db->table('branches b')
                ->select('b.zone_id, b.operational_status, z.status AS zone_status, z.is_project_scope')
                ->join('org_zones z', 'z.id=b.zone_id')
                ->where('b.id', $branchId)->get()->getRowArray();
            if ($branch === null || $branch['zone_status'] !== 'active' || (int) $branch['is_project_scope'] !== 1) {
                return false;
            }
            if ($zoneId !== null && (int) $branch['zone_id'] !== $zoneId) {
                return false;
            }
            $zoneId = (int) $branch['zone_id'];
        } elseif ($scope === 'zone') {
            $zone = $this->db->table('org_zones')->select('status,is_project_scope')
                ->where('id', $zoneId)->get()->getRowArray();
            if ($zone === null || $zone['status'] !== 'active' || (int) $zone['is_project_scope'] !== 1) {
                return false;
            }
        }

        $user = $this->db->table('users')->select('active')->where('id', $userId)->get()->getRowArray();
        if ($user === null || (int) $user['active'] !== 1) {
            return false;
        }

        $rows = $this->db->table('portal_user_role_assignments a')
            ->select('a.scope_type,a.zone_id,a.branch_id')
            ->join('portal_roles r', 'r.id=a.role_id')
            ->join('portal_role_permissions rp', 'rp.role_id=r.id')
            ->join('portal_permissions p', 'p.id=rp.permission_id')
            ->where('a.user_id', $userId)
            ->where('a.assignment_status', 'active')
            ->where('r.status', 'active')
            ->where('p.status', 'active')
            ->where('p.permission_key', $permission)
            ->where('a.starts_at <=', gmdate('Y-m-d H:i:s'))
            ->groupStart()->where('a.expires_at', null)->orWhere('a.expires_at >', gmdate('Y-m-d H:i:s'))->groupEnd()
            ->get()->getResultArray();

        foreach ($rows as $grant) {
            if ($grant['scope_type'] === 'global' && $grant['zone_id'] === null && $grant['branch_id'] === null) {
                return true;
            }
            if ($scope === 'global') {
                continue;
            }
            if ($grant['scope_type'] === 'zone' && $grant['branch_id'] === null
                && $grant['zone_id'] !== null && (int) $grant['zone_id'] === $zoneId) {
                return true;
            }
            if ($scope === 'branch' && $grant['scope_type'] === 'branch'
                && $grant['branch_id'] !== null && (int) $grant['branch_id'] === $branchId) {
                // A branch grant with an optional zone ID must match its parent.
                if ($grant['zone_id'] === null || (int) $grant['zone_id'] === $zoneId) {
                    return true;
                }
            }
        }
        return false;
    }

    /**
     * Privileged login requires a separately approved MFA challenge. Until that
     * mechanism is deployed, refuse login to ANY active role assignee.
     * This is intentionally broader than the final privileged-role policy.
     */
    public function requiresPendingMfa(int $userId): bool
    {
        $count = $this->db->table('portal_user_role_assignments a')
            ->join('portal_roles r', 'r.id=a.role_id')
            ->where('a.user_id', $userId)->where('a.assignment_status', 'active')
            ->where('r.status', 'active')
            ->where('a.starts_at <=', gmdate('Y-m-d H:i:s'))
            ->groupStart()->where('a.expires_at', null)->orWhere('a.expires_at >', gmdate('Y-m-d H:i:s'))->groupEnd()
            ->countAllResults();
        return $count > 0;
    }
}
