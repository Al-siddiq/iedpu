<?php
declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/** P2-WP01 — Governance. MySQL-compatible DDL; no application records created. */
final class CreateP2Governance extends Migration
{
    private const TABLES = ['portal_roles', 'portal_permissions', 'portal_role_permissions', 'approval_requests', 'approval_decisions', 'portal_user_role_assignments', 'official_office_catalog'];

    public function up(): void
    {
        if ($this->db->getPlatform() !== 'MySQLi' || $this->db->getPrefix() !== '') {
            throw new RuntimeException('P2 requires MySQLi with an empty table prefix.');
        }
        $user = $this->db->query("SELECT COLUMN_TYPE AS column_type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'id'")->getRowArray();
        if (!preg_match('/^int(?:\(\d+\))? unsigned$/i', $user['column_type'] ?? '')) {
            throw new RuntimeException('Run official Shield migrations first; expected users.id INT UNSIGNED.');
        }
        foreach (self::TABLES as $table) {
            if ($this->db->tableExists($table)) {
                throw new RuntimeException('Existing P2 table: ' . $table . '. Inspect migration history; do not overwrite.');
            }
        }
        if ($this->db->query(<<<'SQL'
CREATE TABLE `portal_roles` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`),
    `role_code` VARCHAR(80) NOT NULL,
    `role_name` VARCHAR(120) NOT NULL,
    `description` TEXT NULL,
    `is_system_role` TINYINT(1) NOT NULL DEFAULT 0,
    `is_assignable` TINYINT(1) NOT NULL DEFAULT 1,
    `requires_second_approval` TINYINT(1) NOT NULL DEFAULT 0,
    `status` VARCHAR(20) NOT NULL,
    `created_at` DATETIME(6) NOT NULL,
    `updated_at` DATETIME(6) NULL,
    UNIQUE (`role_code`),
    CHECK (status IN ('active','inactive','retired'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ) === false) {
            throw new RuntimeException('P2 DDL failed; inspect partial migration before retrying.');
        }
        if ($this->db->query(<<<'SQL'
CREATE TABLE `portal_permissions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`),
    `permission_key` VARCHAR(120) NOT NULL,
    `module_code` VARCHAR(60) NOT NULL,
    `action_code` VARCHAR(60) NOT NULL,
    `permission_name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `risk_level` VARCHAR(20) NOT NULL,
    `requires_reason` TINYINT(1) NOT NULL DEFAULT 0,
    `requires_online` TINYINT(1) NOT NULL DEFAULT 1,
    `is_system_permission` TINYINT(1) NOT NULL DEFAULT 1,
    `status` VARCHAR(20) NOT NULL,
    `created_at` DATETIME(6) NOT NULL,
    UNIQUE (`permission_key`),
    KEY (`module_code`),
    CHECK (risk_level IN ('low','medium','high','critical')),
    CHECK (status IN ('active','retired'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ) === false) {
            throw new RuntimeException('P2 DDL failed; inspect partial migration before retrying.');
        }
        if ($this->db->query(<<<'SQL'
CREATE TABLE `portal_role_permissions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`),
    `role_id` BIGINT UNSIGNED NOT NULL,
    `permission_id` BIGINT UNSIGNED NOT NULL,
    `granted_at` DATETIME(6) NOT NULL,
    `granted_by_user_id` INT UNSIGNED NULL,
    FOREIGN KEY (`role_id`) REFERENCES `portal_roles` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    FOREIGN KEY (`permission_id`) REFERENCES `portal_permissions` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    FOREIGN KEY (`granted_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    UNIQUE (role_id,permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ) === false) {
            throw new RuntimeException('P2 DDL failed; inspect partial migration before retrying.');
        }
        if ($this->db->query(<<<'SQL'
CREATE TABLE `approval_requests` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`),
    `public_id` CHAR(36) NOT NULL,
    `action_key` VARCHAR(120) NOT NULL,
    `subject_type` VARCHAR(60) NOT NULL,
    `subject_id` BIGINT UNSIGNED NOT NULL,
    `requested_by_user_id` INT UNSIGNED NOT NULL,
    `request_reason` TEXT NOT NULL,
    `required_approvals` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `approval_status` VARCHAR(25) NOT NULL,
    `submitted_at` DATETIME(6) NOT NULL,
    `completed_at` DATETIME(6) NULL,
    `expires_at` DATETIME(6) NULL,
    UNIQUE (`public_id`),
    KEY (`action_key`),
    FOREIGN KEY (`requested_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    KEY (subject_type,subject_id),
    CHECK (required_approvals >= 1),
    CHECK (approval_status IN ('pending','approved','rejected','cancelled','expired'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ) === false) {
            throw new RuntimeException('P2 DDL failed; inspect partial migration before retrying.');
        }
        if ($this->db->query(<<<'SQL'
CREATE TABLE `approval_decisions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`),
    `approval_request_id` BIGINT UNSIGNED NOT NULL,
    `approver_user_id` INT UNSIGNED NOT NULL,
    `decision` VARCHAR(20) NOT NULL,
    `decision_reason` TEXT NOT NULL,
    `decided_at` DATETIME(6) NOT NULL,
    FOREIGN KEY (`approval_request_id`) REFERENCES `approval_requests` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    FOREIGN KEY (`approver_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    UNIQUE (approval_request_id,approver_user_id),
    CHECK (decision IN ('approved','rejected','returned'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ) === false) {
            throw new RuntimeException('P2 DDL failed; inspect partial migration before retrying.');
        }
        if ($this->db->query(<<<'SQL'
CREATE TABLE `portal_user_role_assignments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`),
    `public_id` CHAR(36) NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `role_id` BIGINT UNSIGNED NOT NULL,
    `scope_type` VARCHAR(20) NOT NULL,
    `zone_id` BIGINT UNSIGNED NULL,
    `branch_id` BIGINT UNSIGNED NULL,
    `starts_at` DATETIME(6) NOT NULL,
    `expires_at` DATETIME(6) NULL,
    `assignment_status` VARCHAR(20) NOT NULL,
    `assignment_reason` VARCHAR(500) NOT NULL,
    `assigned_by_user_id` INT UNSIGNED NOT NULL,
    `approval_request_id` BIGINT UNSIGNED NULL,
    `revoked_at` DATETIME(6) NULL,
    `revoked_by_user_id` INT UNSIGNED NULL,
    `created_at` DATETIME(6) NOT NULL,
    UNIQUE (`public_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    FOREIGN KEY (`role_id`) REFERENCES `portal_roles` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    FOREIGN KEY (`zone_id`) REFERENCES `org_zones` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    FOREIGN KEY (`assigned_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    FOREIGN KEY (`approval_request_id`) REFERENCES `approval_requests` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    FOREIGN KEY (`revoked_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CHECK ((scope_type='global' AND zone_id IS NULL AND branch_id IS NULL) OR (scope_type='zone' AND zone_id IS NOT NULL AND branch_id IS NULL) OR (scope_type='branch' AND branch_id IS NOT NULL)),
    CHECK (expires_at IS NULL OR expires_at > starts_at),
    CHECK (assignment_status IN ('active','suspended','revoked','expired')),
    KEY (user_id,assignment_status,starts_at,expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ) === false) {
            throw new RuntimeException('P2 DDL failed; inspect partial migration before retrying.');
        }
        if ($this->db->query(<<<'SQL'
CREATE TABLE `official_office_catalog` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`),
    `office_code` VARCHAR(80) NOT NULL,
    `office_name` VARCHAR(150) NOT NULL,
    `organisational_level` VARCHAR(20) NOT NULL,
    `description` TEXT NULL,
    `source_section` VARCHAR(80) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME(6) NOT NULL,
    UNIQUE (`office_code`),
    CHECK (organisational_level IN ('national','zone','branch'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ) === false) {
            throw new RuntimeException('P2 DDL failed; inspect partial migration before retrying.');
        }
    }

    public function down(): void
    {
        // MySQL DDL commits implicitly. Refuse destructive rollback of populated tables.
        foreach (self::TABLES as $table) {
            if ($this->db->tableExists($table) && $this->db->table($table)->countAllResults() > 0) {
                throw new RuntimeException('Refusing rollback of populated table: ' . $table);
            }
        }
        foreach (array_reverse(self::TABLES) as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
