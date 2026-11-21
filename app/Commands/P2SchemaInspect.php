<?php
declare(strict_types=1);

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/** Read-only operator inspection; does not create tables or expose records. */
final class P2SchemaInspect extends BaseCommand
{
    protected $group = 'IEDPU';
    protected $name = 'iedpu:p2-schema';
    protected $description = 'Inspect P2 migration tables, user key types and database constraints.';

    public function run(array $params): int
    {
        $db = Database::connect();
        $tables = [
            'org_zones','geo_states','project_coverage_states','branches','emirate_ancestral_areas',
            'portal_roles','portal_permissions','portal_role_permissions','approval_requests','approval_decisions',
            'portal_user_role_assignments','official_office_catalog','file_assets','privacy_notice_versions',
            'members','member_user_links','member_account_claims','official_office_assignments',
            'portal_login_identifiers','audit_events','security_events','audit_chain_checkpoints',
        ];
        $server = $db->query('SELECT VERSION() AS server_version, @@session.sql_mode AS sql_mode')->getRowArray();
        CLI::write('Server: ' . $server['server_version']);
        CLI::write('SQL mode: ' . $server['sql_mode']);
        $users = $db->query("SELECT COLUMN_TYPE AS column_type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='id'")->getRowArray();
        $type = $users['column_type'] ?? 'missing';
        CLI::write('Shield users.id: ' . $type);
        $ok = preg_match('/^int(?:\(\d+\))? unsigned$/i', $type) === 1;
        foreach ($tables as $table) {
            $row = $db->query('SELECT ENGINE AS engine, TABLE_COLLATION AS collation FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?', [$table])->getRowArray();
            if ($row === null) {
                CLI::write('[MISSING] ' . $table, 'red');
                $ok = false;
                continue;
            }
            $valid = strcasecmp($row['engine'], 'InnoDB') === 0 && $row['collation'] === 'utf8mb4_unicode_ci';
            $ok = $ok && $valid;
            $counts = $db->query('SELECT CONSTRAINT_TYPE AS kind, COUNT(*) AS total FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? GROUP BY CONSTRAINT_TYPE', [$table])->getResultArray();
            CLI::write(($valid ? '[PRESENT] ' : '[REVIEW] ') . $table . ' ' . $row['engine'] . ' ' . $row['collation'] . ' ' . json_encode($counts));
        }
        $refs = $db->query("SELECT k.TABLE_NAME AS table_name,k.COLUMN_NAME AS column_name,c.COLUMN_TYPE AS column_type FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.COLUMNS c ON c.TABLE_SCHEMA=k.TABLE_SCHEMA AND c.TABLE_NAME=k.TABLE_NAME AND c.COLUMN_NAME=k.COLUMN_NAME WHERE k.TABLE_SCHEMA=DATABASE() AND k.REFERENCED_TABLE_NAME='users' AND k.REFERENCED_COLUMN_NAME='id'")->getResultArray();
        foreach ($refs as $ref) {
            if (!in_array($ref['table_name'], $tables, true)) {
                continue;
            }
            $valid = preg_match('/^int(?:\(\d+\))? unsigned$/i', $ref['column_type']) === 1;
            $ok = $ok && $valid;
            CLI::write(($valid ? '[FK] ' : '[REVIEW FK] ') . $ref['table_name'] . '.' . $ref['column_name'] . ': ' . $ref['column_type']);
        }
        CLI::write($ok ? 'Metadata inspection complete; manual constraint and rollback checks still required.' : 'Schema inspection found missing or incompatible metadata.');
        return $ok ? 0 : 1;
    }
}
