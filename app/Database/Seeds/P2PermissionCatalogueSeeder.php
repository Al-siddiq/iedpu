<?php
declare(strict_types=1);

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use RuntimeException;

/**
 * P2 catalogue only. These records confer no access. WP02 must implement
 * server-side grant ceilings, scope and independent approval policy.
 */
final class P2PermissionCatalogueSeeder extends Seeder
{
    // key => [label, risk, reason required, grant domain]
    public const CATALOGUE = [
        'organisation.view' => ['View organisation', 'low', 0, 'committee'],
        'organisation.manage' => ['Manage organisation and coverage', 'high', 1, 'committee'],
        'branches.view' => ['View branches', 'low', 0, 'committee'],
        'branches.manage' => ['Manage branches', 'high', 1, 'committee'],
        'branches.activate' => ['Activate validated branch', 'high', 1, 'committee'],
        'offices.view' => ['View official offices', 'low', 0, 'committee'],
        'offices.manage' => ['Manage official offices', 'high', 1, 'committee'],
        'roles.view' => ['View role profiles', 'medium', 0, 'protected'],
        'roles.manage' => ['Manage role profiles', 'critical', 1, 'protected'],
        'roles.assign' => ['Assign scoped roles', 'critical', 1, 'protected'],
        'roles.revoke' => ['Revoke scoped roles', 'high', 1, 'protected'],
        'permissions.view' => ['View permission catalogue', 'medium', 0, 'protected'],
        'users.view' => ['View accounts', 'medium', 0, 'protected'],
        'users.invite' => ['Invite account', 'high', 1, 'protected'],
        'users.suspend' => ['Suspend account', 'critical', 1, 'protected'],
        'approvals.view' => ['View approval requests', 'medium', 0, 'protected'],
        'approvals.decide' => ['Decide authorised approval requests', 'critical', 1, 'protected'],
        'audit.view' => ['View scoped audit trail', 'high', 0, 'protected'],
        'audit.export' => ['Export scoped audit trail', 'critical', 1, 'protected'],
        'privacy.view' => ['View privacy notices', 'low', 0, 'committee'],
        'privacy.approve' => ['Approve privacy notice', 'high', 1, 'committee'],
        'files.upload' => ['Upload authorised private files', 'high', 1, 'protected'],
        'files.view' => ['Retrieve authorised private files', 'high', 0, 'protected'],
        'technical.diagnostics' => ['View technical diagnostics', 'medium', 0, 'technical'],
        'technical.security' => ['View technical security events', 'high', 0, 'technical'],
        'technical.backup' => ['Perform approved backup', 'critical', 1, 'technical'],
        'technical.restore' => ['Perform independently approved restoration', 'critical', 1, 'technical'],
    ];

    public function run(): void
    {
        $this->db->transBegin();
        try {
            foreach (self::CATALOGUE as $key => [$label, $risk, $reason, $domain]) {
                [$module, $action] = explode('.', $key, 2);
                $row = [
                    'permission_key' => $key,
                    'module_code' => $module,
                    'action_code' => $action,
                    'permission_name' => $label,
                    'description' => 'P2 release catalogue; grant domain: ' . $domain,
                    'risk_level' => $risk,
                    'requires_reason' => $reason,
                    'requires_online' => 1,
                    'is_system_permission' => 1,
                    'status' => 'active',
                ];
                $existing = $this->db->table('portal_permissions')->where('permission_key', $key)->get()->getRowArray();
                if ($existing !== null) {
                    foreach ($row as $column => $value) {
                        if ((string) $existing[$column] !== (string) $value) {
                            throw new RuntimeException('Catalogue drift at ' . $key . '; use a reviewed release migration.');
                        }
                    }
                    continue;
                }
                $row['created_at'] = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
                if (!$this->db->table('portal_permissions')->insert($row)) {
                    throw new RuntimeException('Permission catalogue insert failed.');
                }
            }
            if (!$this->db->transStatus() || !$this->db->transCommit()) {
                throw new RuntimeException('Permission catalogue transaction failed.');
            }
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
    }
}
