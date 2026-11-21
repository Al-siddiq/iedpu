<?php
declare(strict_types=1);

namespace App\Modules\Administration;

use App\Libraries\Auth\ScopeAuthorizer;
use CodeIgniter\Database\BaseConnection;
use InvalidArgumentException;

/**
 * WP03 first increment: server-scoped, read-only organisation directory.
 *
 * Permission and scope are bound to the SAME active role assignment in SQL.
 * Counts and pages use identical filters; neither a guessed UUID nor a
 * client-supplied zone/branch grants access.
 */
final class ScopedDirectory
{
    private const CATALOGUE = [
        'zones' => 'organisation.view',
        'branches' => 'branches.view',
        'offices' => 'offices.view',
        'roles' => 'roles.view',
        'permissions' => 'permissions.view',
    ];

    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function permissionFor(string $kind): string
    {
        if (!isset(self::CATALOGUE[$kind])) {
            throw new InvalidArgumentException('Unknown directory.');
        }
        return self::CATALOGUE[$kind];
    }

    /** @return array{items:list<array<string,mixed>>,page:int,per_page:int,total:int} */
    public function page(string $kind, int $userId, int $page, int $perPage): array
    {
        $permission = $this->permissionFor($kind);
        if ($userId < 1 || $page < 1 || $page > 200 || $perPage < 1 || $perPage > 50) {
            throw new InvalidArgumentException('Invalid page.');
        }
        $offset = ($page - 1) * $perPage;
        if (in_array($kind, ['roles', 'permissions', 'offices'], true)) {
            // Protected catalogues are global administrative references.
            // They are never inferred from an unrelated branch permission.
            if (!(new ScopeAuthorizer($this->db))->allows($userId, $permission, 'global')) {
                return ['items' => [], 'page' => $page, 'per_page' => $perPage, 'total' => 0];
            }
            [$table, $fields, $sort] = match ($kind) {
                'roles' => ['portal_roles', 'role_code,role_name,description,is_system_role,is_assignable,requires_second_approval,status', 'role_name'],
                'permissions' => ['portal_permissions', 'permission_key,module_code,action_code,permission_name,description,risk_level,status', 'permission_key'],
                default => ['official_office_catalog', 'office_code,office_name,organisational_level,description,source_section,is_active', 'office_name'],
            };
            $total = $this->db->table($table)->countAllResults();
            $items = $this->db->table($table)->select($fields)
                ->orderBy($sort, 'ASC')->limit($perPage, $offset)->get()->getResultArray();
            return ['items' => $items, 'page' => $page, 'per_page' => $perPage, 'total' => $total];
        }

        if ($kind === 'zones') {
            $from = 'org_zones z';
            $fields = 'z.public_id,z.zone_code,z.zone_name,z.display_alias,z.description,z.status';
            $conditions = "z.is_project_scope = 1 AND z.status = 'active'";
            $scopeSql = "(a.scope_type = 'global' AND a.zone_id IS NULL AND a.branch_id IS NULL)
                OR (a.scope_type = 'zone' AND a.zone_id = z.id AND a.branch_id IS NULL)";
            $sort = 'z.zone_name ASC,z.id ASC';
        } else {
            $from = 'branches b INNER JOIN org_zones z ON z.id=b.zone_id';
            $fields = 'b.public_id,b.branch_code,b.branch_name,b.locality,b.lga_or_district,b.recognition_status,b.operational_status,z.zone_name';
            $conditions = "z.is_project_scope = 1 AND z.status = 'active'";
            $scopeSql = "(a.scope_type = 'global' AND a.zone_id IS NULL AND a.branch_id IS NULL)
                OR (a.scope_type = 'zone' AND a.zone_id = z.id AND a.branch_id IS NULL)
                OR (a.scope_type = 'branch' AND a.branch_id = b.id AND (a.zone_id IS NULL OR a.zone_id = z.id))";
            $sort = 'b.branch_name ASC,b.id ASC';
        }

        $where = $conditions . " AND EXISTS (
            SELECT 1
            FROM portal_user_role_assignments a
            INNER JOIN portal_roles r ON r.id=a.role_id
            INNER JOIN portal_role_permissions rp ON rp.role_id=r.id
            INNER JOIN portal_permissions p ON p.id=rp.permission_id
            WHERE a.user_id = ?
              AND a.assignment_status='active'
              AND r.status='active'
              AND p.status='active'
              AND p.permission_key=?
              AND a.starts_at <= UTC_TIMESTAMP(6)
              AND (a.expires_at IS NULL OR a.expires_at > UTC_TIMESTAMP(6))
              AND (" . $scopeSql . ")
        )";
        $params = [$userId, $permission];
        $count = $this->db->query("SELECT COUNT(*) AS total FROM " . $from . " WHERE " . $where, $params)->getRowArray();
        $items = $this->db->query("SELECT " . $fields . " FROM " . $from . " WHERE " . $where
            . " ORDER BY " . $sort . " LIMIT " . (int) $perPage . " OFFSET " . (int) $offset, $params)->getResultArray();

        return ['items' => $items, 'page' => $page, 'per_page' => $perPage, 'total' => (int) ($count['total'] ?? 0)];
    }

    /** A true permission check without exposing whether any records exist. */
    public function canView(string $kind, int $userId): bool
    {
        $permission = $this->permissionFor($kind);
        if (in_array($kind, ['roles', 'permissions', 'offices'], true)) {
            return (new ScopeAuthorizer($this->db))->allows($userId, $permission, 'global');
        }
        // Permission existence is independent of whether the directory has
        // any rows. A valid empty organisation must show an empty state, not 403.
        $resourceJoin = $kind === 'zones'
            ? "INNER JOIN org_zones z ON z.id=a.zone_id AND z.is_project_scope=1 AND z.status='active'"
            : "INNER JOIN org_zones z ON z.id=a.zone_id AND z.is_project_scope=1 AND z.status='active'";
        $sql = "SELECT 1
            FROM portal_user_role_assignments a
            INNER JOIN portal_roles r ON r.id=a.role_id AND r.status='active'
            INNER JOIN portal_role_permissions rp ON rp.role_id=r.id
            INNER JOIN portal_permissions p ON p.id=rp.permission_id
            " . $resourceJoin . "
            WHERE a.user_id=? AND p.permission_key=? AND p.status='active'
              AND a.assignment_status='active'
              AND a.starts_at <= UTC_TIMESTAMP(6)
              AND (a.expires_at IS NULL OR a.expires_at > UTC_TIMESTAMP(6))
              AND a.scope_type='zone' AND a.branch_id IS NULL
            LIMIT 1";
        if ($this->db->query($sql, [$userId, $permission])->getRowArray() !== null) {
            return true;
        }
        if ($kind === 'branches') {
            $branchSql = "SELECT 1
                FROM portal_user_role_assignments a
                INNER JOIN portal_roles r ON r.id=a.role_id AND r.status='active'
                INNER JOIN portal_role_permissions rp ON rp.role_id=r.id
                INNER JOIN portal_permissions p ON p.id=rp.permission_id
                INNER JOIN branches b ON b.id=a.branch_id
                INNER JOIN org_zones z ON z.id=b.zone_id AND z.is_project_scope=1 AND z.status='active'
                WHERE a.user_id=? AND p.permission_key=? AND p.status='active'
                  AND a.assignment_status='active'
                  AND a.starts_at <= UTC_TIMESTAMP(6)
                  AND (a.expires_at IS NULL OR a.expires_at > UTC_TIMESTAMP(6))
                  AND a.scope_type='branch'
                  AND (a.zone_id IS NULL OR a.zone_id=z.id)
                LIMIT 1";
            return $this->db->query($branchSql, [$userId, $permission])->getRowArray() !== null;
        }
        return false;
    }
}
