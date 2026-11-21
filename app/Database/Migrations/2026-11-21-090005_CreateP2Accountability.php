<?php
declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/** P2-WP01 — Accountability. MySQL-compatible DDL; no application records created. */
final class CreateP2Accountability extends Migration
{
    private const TABLES = ['audit_events', 'security_events', 'audit_chain_checkpoints'];

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
CREATE TABLE `audit_events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`),
    `event_uuid` CHAR(36) NOT NULL,
    `occurred_at` DATETIME(6) NOT NULL,
    `actor_user_id` INT UNSIGNED NULL,
    `actor_member_id` BIGINT UNSIGNED NULL,
    `actor_role_snapshot` JSON NULL,
    `action_key` VARCHAR(120) NOT NULL,
    `permission_key` VARCHAR(120) NULL,
    `module_code` VARCHAR(60) NOT NULL,
    `event_category` VARCHAR(30) NOT NULL,
    `severity` VARCHAR(20) NOT NULL,
    `subject_type` VARCHAR(60) NULL,
    `subject_id` BIGINT UNSIGNED NULL,
    `zone_id` BIGINT UNSIGNED NULL,
    `branch_id` BIGINT UNSIGNED NULL,
    `outcome` VARCHAR(20) NOT NULL,
    `reason` TEXT NULL,
    `before_snapshot` JSON NULL,
    `after_snapshot` JSON NULL,
    `request_id` VARCHAR(100) NULL,
    `session_reference_hash` CHAR(64) NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(500) NULL,
    `previous_event_hash` CHAR(64) NULL,
    `event_hash` CHAR(64) NOT NULL,
    UNIQUE (`event_uuid`),
    KEY (`occurred_at`),
    FOREIGN KEY (`actor_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    FOREIGN KEY (`actor_member_id`) REFERENCES `members` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    KEY (`action_key`),
    KEY (`permission_key`),
    KEY (`module_code`),
    KEY (`event_category`),
    FOREIGN KEY (`zone_id`) REFERENCES `org_zones` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    KEY (`request_id`),
    KEY (`event_hash`),
    KEY (subject_type,subject_id),
    CHECK (event_category IN ('membership','finance','security','role','content','system','data_access')),
    CHECK (severity IN ('info','warning','high','critical')),
    CHECK (outcome IN ('success','failure','denied','deferred'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ) === false) {
            throw new RuntimeException('P2 DDL failed; inspect partial migration before retrying.');
        }
        if ($this->db->query(<<<'SQL'
CREATE TABLE `security_events` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`),
    `event_uuid` CHAR(36) NOT NULL,
    `event_code` VARCHAR(100) NOT NULL,
    `severity` VARCHAR(20) NOT NULL,
    `user_id` INT UNSIGNED NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` VARCHAR(500) NULL,
    `context_data` JSON NULL,
    `occurred_at` DATETIME(6) NOT NULL,
    `resolved_at` DATETIME(6) NULL,
    `resolved_by_user_id` INT UNSIGNED NULL,
    UNIQUE (`event_uuid`),
    KEY (`event_code`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    KEY (`ip_address`),
    KEY (`occurred_at`),
    FOREIGN KEY (`resolved_by_user_id`) REFERENCES `users` (`id`) ON DELETE RESTRICT ON UPDATE RESTRICT,
    CHECK (severity IN ('low','medium','high','critical'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ) === false) {
            throw new RuntimeException('P2 DDL failed; inspect partial migration before retrying.');
        }
        if ($this->db->query(<<<'SQL'
CREATE TABLE `audit_chain_checkpoints` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`),
    `first_event_id` BIGINT UNSIGNED NOT NULL,
    `last_event_id` BIGINT UNSIGNED NOT NULL,
    `event_count` BIGINT UNSIGNED NOT NULL,
    `chain_hash` CHAR(64) NOT NULL,
    `checkpoint_at` DATETIME(6) NOT NULL,
    `external_copy_reference` VARCHAR(500) NULL,
    `verification_status` VARCHAR(20) NOT NULL DEFAULT 'pending',
    UNIQUE (`chain_hash`),
    CHECK (verification_status IN ('pending','verified','failed')),
    CHECK (first_event_id <= last_event_id AND event_count > 0)
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
