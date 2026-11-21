<?php
declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/** P2-WP01 — Organisation. MySQL-compatible DDL; no application records created. */
final class CreateP2Organisation extends Migration
{
    private const TABLES = ['org_zones', 'geo_states', 'project_coverage_states', 'branches', 'emirate_ancestral_areas'];

    public function up(): void
    {
        if ($this->db->getPlatform() !== 'MySQLi' || $this->db->getPrefix() !== '') {
            throw new RuntimeException('P2 requires MySQLi with an empty table prefix.');
        }
        $version = (string) $this->db->query('SELECT VERSION() AS server_version')->getRowArray()['server_version'];
        $maria = stripos($version, 'MariaDB') !== false;
        $numeric = preg_replace('/^5\.5\.5-(?=\d)/', '', $version);
        if (!preg_match('/^\d+\.\d+\.\d+/', $numeric, $match)
            || version_compare($match[0], $maria ? '10.4.0' : '8.0.16', '<')) {
            throw new RuntimeException('P2 requires enforced CHECK constraints and generated columns; review server compatibility.');
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
CREATE TABLE `org_zones` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`),
    `public_id` CHAR(36) NOT NULL,
    `zone_code` VARCHAR(30) NOT NULL,
    `zone_name` VARCHAR(120) NOT NULL,
    `display_alias` VARCHAR(120) NULL,
    `description` TEXT NULL,
    `is_project_scope` TINYINT(1) NOT NULL DEFAULT 0,
    `status` VARCHAR(20) NOT NULL,
    `created_at` DATETIME(6) NOT NULL,
    `updated_at` DATETIME(6) NULL,
    UNIQUE (`public_id`),
    UNIQUE (`zone_code`),
    CHECK (status IN ('active','inactive')),
    CHECK (is_project_scope IN (0,1))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ) === false) {
            throw new RuntimeException('P2 DDL failed; inspect partial migration before retrying.');
        }
        if ($this->db->query(<<<'SQL'
CREATE TABLE `geo_states` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`),
    `state_code` VARCHAR(10) NOT NULL,
    `state_name` VARCHAR(100) NOT NULL,
    `geo_type` VARCHAR(20) NOT NULL,
    `country_code` CHAR(2) NOT NULL DEFAULT 'NG',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME(6) NOT NULL,
    UNIQUE (`state_code`),
    UNIQUE (`state_name`),
    CHECK (geo_type IN ('state','fct'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ) === false) {
            throw new RuntimeException('P2 DDL failed; inspect partial migration before retrying.');
        }
        if ($this->db->query(<<<'SQL'
CREATE TABLE `project_coverage_states` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`),
    `state_id` BIGINT UNSIGNED NOT NULL,
    `zone_id` BIGINT UNSIGNED NOT NULL,
    `coverage_status` VARCHAR(20) NOT NULL,
    `effective_from` DATE NOT NULL,
    `effective_to` DATE NULL,
    `approved_by_user_id` INT UNSIGNED NULL,
    `approval_reference` VARCHAR(150) NULL,
    `created_at` DATETIME(6) NOT NULL,
    FOREIGN KEY (`state_id`) REFERENCES `geo_states` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    FOREIGN KEY (`zone_id`) REFERENCES `org_zones` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    FOREIGN KEY (`approved_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    UNIQUE (state_id,zone_id),
    CHECK (coverage_status IN ('active','suspended','closed')),
    CHECK (effective_to IS NULL OR effective_to >= effective_from)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ) === false) {
            throw new RuntimeException('P2 DDL failed; inspect partial migration before retrying.');
        }
        if ($this->db->query(<<<'SQL'
CREATE TABLE `branches` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`),
    `public_id` CHAR(36) NOT NULL,
    `zone_id` BIGINT UNSIGNED NOT NULL,
    `state_id` BIGINT UNSIGNED NULL,
    `branch_code` VARCHAR(40) NULL,
    `branch_name` VARCHAR(160) NOT NULL,
    `source_name` VARCHAR(160) NULL,
    `locality` VARCHAR(160) NULL,
    `lga_or_district` VARCHAR(160) NULL,
    `office_address` VARCHAR(500) NULL,
    `contact_phone` VARCHAR(40) NULL,
    `contact_email` VARCHAR(190) NULL,
    `recognition_status` VARCHAR(30) NOT NULL DEFAULT 'pending_confirmation',
    `operational_status` VARCHAR(30) NOT NULL DEFAULT 'inactive',
    `recognition_reference` VARCHAR(150) NULL,
    `recognition_date` DATE NULL,
    `notes` TEXT NULL,
    `created_at` DATETIME(6) NOT NULL,
    `updated_at` DATETIME(6) NULL,
    `created_by_user_id` INT UNSIGNED NULL,
    `updated_by_user_id` INT UNSIGNED NULL,
    UNIQUE (`public_id`),
    FOREIGN KEY (`zone_id`) REFERENCES `org_zones` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    FOREIGN KEY (`state_id`) REFERENCES `geo_states` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    UNIQUE (`branch_code`),
    FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CHECK (recognition_status IN ('approved','provisional','pending_confirmation','not_recognised')),
    CHECK (operational_status IN ('active','inactive','suspended','deregistered')),
    CHECK (operational_status <> 'active' OR recognition_status = 'approved'),
    KEY (zone_id,operational_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ) === false) {
            throw new RuntimeException('P2 DDL failed; inspect partial migration before retrying.');
        }
        if ($this->db->query(<<<'SQL'
CREATE TABLE `emirate_ancestral_areas` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`),
    `area_name` VARCHAR(160) NOT NULL,
    `area_type` VARCHAR(30) NOT NULL,
    `parent_area_id` BIGINT UNSIGNED NULL,
    `associated_lga_name` VARCHAR(120) NULL,
    `source_reference` VARCHAR(150) NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME(6) NOT NULL,
    FOREIGN KEY (`parent_area_id`) REFERENCES `emirate_ancestral_areas` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    UNIQUE (area_name,area_type),
    CHECK (area_type IN ('town','district','ward','other'))
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
