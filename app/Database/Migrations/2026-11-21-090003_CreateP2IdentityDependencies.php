<?php
declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/** P2-WP01 — IdentityDependencies. MySQL-compatible DDL; no application records created. */
final class CreateP2IdentityDependencies extends Migration
{
    private const TABLES = ['file_assets', 'privacy_notice_versions', 'members', 'member_user_links', 'member_account_claims', 'official_office_assignments'];

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
CREATE TABLE `file_assets` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`),
    `public_id` CHAR(36) NOT NULL,
    `storage_disk` VARCHAR(40) NOT NULL,
    `storage_key` VARCHAR(500) NOT NULL,
    `original_filename` VARCHAR(255) NOT NULL,
    `stored_filename` VARCHAR(255) NOT NULL,
    `mime_type` VARCHAR(120) NOT NULL,
    `file_extension` VARCHAR(15) NOT NULL,
    `file_size_bytes` BIGINT UNSIGNED NOT NULL,
    `sha256_hash` CHAR(64) NOT NULL,
    `classification` VARCHAR(25) NOT NULL,
    `scan_status` VARCHAR(20) NOT NULL DEFAULT 'pending',
    `uploaded_by_user_id` INT UNSIGNED NULL,
    `uploaded_at` DATETIME(6) NOT NULL,
    `deleted_at` DATETIME(6) NULL,
    UNIQUE (`public_id`),
    UNIQUE (`storage_key`),
    KEY (`sha256_hash`),
    FOREIGN KEY (`uploaded_by_user_id`) REFERENCES `users` (`id`),
    CHECK (classification IN ('public','internal','confidential','restricted')),
    CHECK (scan_status IN ('pending','clean','rejected','not_available'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ) === false) {
            throw new RuntimeException('P2 DDL failed; inspect partial migration before retrying.');
        }
        if ($this->db->query(<<<'SQL'
CREATE TABLE `privacy_notice_versions` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`),
    `version_code` VARCHAR(40) NOT NULL,
    `title` VARCHAR(180) NOT NULL,
    `notice_text` LONGTEXT NOT NULL,
    `effective_at` DATETIME(6) NOT NULL,
    `retired_at` DATETIME(6) NULL,
    `approved_by_user_id` INT UNSIGNED NULL,
    `created_at` DATETIME(6) NOT NULL,
    UNIQUE (`version_code`),
    FOREIGN KEY (`approved_by_user_id`) REFERENCES `users` (`id`),
    CHECK (retired_at IS NULL OR retired_at >= effective_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ) === false) {
            throw new RuntimeException('P2 DDL failed; inspect partial migration before retrying.');
        }
        if ($this->db->query(<<<'SQL'
CREATE TABLE `members` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`),
    `public_id` CHAR(36) NOT NULL,
    `membership_number` VARCHAR(60) NULL,
    `first_name` VARCHAR(100) NOT NULL,
    `middle_name` VARCHAR(100) NULL,
    `last_name` VARCHAR(100) NOT NULL,
    `other_names` VARCHAR(150) NULL,
    `gender` VARCHAR(20) NULL,
    `date_of_birth` DATE NULL,
    `ancestral_area_id` BIGINT UNSIGNED NULL,
    `ancestry_details` TEXT NULL,
    `current_branch_id` BIGINT UNSIGNED NOT NULL,
    `current_residence_country` VARCHAR(100) NULL,
    `current_residence_state_id` BIGINT UNSIGNED NULL,
    `current_residence_city` VARCHAR(160) NULL,
    `current_residence_lga` VARCHAR(160) NULL,
    `current_residence_address` VARCHAR(500) NULL,
    `relationship_to_iedpu` VARCHAR(120) NULL,
    `profile_photo_file_id` BIGINT UNSIGNED NULL,
    `verification_status` VARCHAR(25) NOT NULL,
    `membership_status` VARCHAR(25) NOT NULL,
    `duplicate_of_member_id` BIGINT UNSIGNED NULL,
    `record_version` BIGINT UNSIGNED NOT NULL DEFAULT 1,
    `verified_at` DATETIME(6) NULL,
    `created_at` DATETIME(6) NOT NULL,
    `updated_at` DATETIME(6) NULL,
    `created_by_user_id` INT UNSIGNED NULL,
    `updated_by_user_id` INT UNSIGNED NULL,
    `anonymised_at` DATETIME(6) NULL,
    UNIQUE (`public_id`),
    UNIQUE (`membership_number`),
    KEY (`first_name`),
    KEY (`last_name`),
    FOREIGN KEY (`ancestral_area_id`) REFERENCES `emirate_ancestral_areas` (`id`),
    FOREIGN KEY (`current_branch_id`) REFERENCES `branches` (`id`),
    FOREIGN KEY (`current_residence_state_id`) REFERENCES `geo_states` (`id`),
    FOREIGN KEY (`profile_photo_file_id`) REFERENCES `file_assets` (`id`),
    FOREIGN KEY (`duplicate_of_member_id`) REFERENCES `members` (`id`),
    FOREIGN KEY (`created_by_user_id`) REFERENCES `users` (`id`),
    FOREIGN KEY (`updated_by_user_id`) REFERENCES `users` (`id`),
    CHECK (verification_status IN ('not_submitted','pending','verified','disputed','rejected')),
    CHECK (membership_status IN ('active','suspended','expelled','inactive')),
    CHECK (record_version >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ) === false) {
            throw new RuntimeException('P2 DDL failed; inspect partial migration before retrying.');
        }
        if ($this->db->query(<<<'SQL'
CREATE TABLE `member_user_links` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`),
    `member_id` BIGINT UNSIGNED NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `link_status` VARCHAR(20) NOT NULL,
    `linked_at` DATETIME(6) NOT NULL,
    `linked_by_user_id` INT UNSIGNED NULL,
    `link_method` VARCHAR(30) NOT NULL,
    `revoked_at` DATETIME(6) NULL,
    `revocation_reason` VARCHAR(500) NULL,
    `created_at` DATETIME(6) NOT NULL,
    FOREIGN KEY (`member_id`) REFERENCES `members` (`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
    FOREIGN KEY (`linked_by_user_id`) REFERENCES `users` (`id`),
    CHECK (link_status IN ('active','suspended','revoked')),
    CHECK (link_method IN ('member_claim','admin_link','migration')),
    active_member_id BIGINT UNSIGNED GENERATED ALWAYS AS (CASE WHEN link_status='active' THEN member_id ELSE NULL END) STORED,
    active_user_id INT UNSIGNED GENERATED ALWAYS AS (CASE WHEN link_status='active' THEN user_id ELSE NULL END) STORED,
    UNIQUE (active_member_id),
    UNIQUE (active_user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ) === false) {
            throw new RuntimeException('P2 DDL failed; inspect partial migration before retrying.');
        }
        if ($this->db->query(<<<'SQL'
CREATE TABLE `member_account_claims` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`),
    `public_id` CHAR(36) NOT NULL,
    `member_id` BIGINT UNSIGNED NOT NULL,
    `requested_by_user_id` INT UNSIGNED NOT NULL,
    `claim_token_hash` CHAR(64) NULL,
    `status` VARCHAR(25) NOT NULL,
    `verification_method` VARCHAR(40) NULL,
    `reviewed_by_user_id` INT UNSIGNED NULL,
    `review_reason` VARCHAR(500) NULL,
    `expires_at` DATETIME(6) NULL,
    `reviewed_at` DATETIME(6) NULL,
    `created_at` DATETIME(6) NOT NULL,
    UNIQUE (`public_id`),
    FOREIGN KEY (`member_id`) REFERENCES `members` (`id`),
    FOREIGN KEY (`requested_by_user_id`) REFERENCES `users` (`id`),
    UNIQUE (`claim_token_hash`),
    FOREIGN KEY (`reviewed_by_user_id`) REFERENCES `users` (`id`),
    CHECK (status IN ('pending','approved','rejected','expired','cancelled'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ) === false) {
            throw new RuntimeException('P2 DDL failed; inspect partial migration before retrying.');
        }
        if ($this->db->query(<<<'SQL'
CREATE TABLE `official_office_assignments` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`),
    `public_id` CHAR(36) NOT NULL,
    `member_id` BIGINT UNSIGNED NULL,
    `holder_name_snapshot` VARCHAR(180) NOT NULL,
    `office_id` BIGINT UNSIGNED NOT NULL,
    `zone_id` BIGINT UNSIGNED NULL,
    `branch_id` BIGINT UNSIGNED NULL,
    `term_start_date` DATE NOT NULL,
    `term_end_date` DATE NULL,
    `assignment_status` VARCHAR(20) NOT NULL,
    `appointment_method` VARCHAR(30) NULL,
    `appointment_reference` VARCHAR(150) NULL,
    `evidence_file_id` BIGINT UNSIGNED NULL,
    `recorded_by_user_id` INT UNSIGNED NOT NULL,
    `approved_by_user_id` INT UNSIGNED NULL,
    `created_at` DATETIME(6) NOT NULL,
    UNIQUE (`public_id`),
    FOREIGN KEY (`member_id`) REFERENCES `members` (`id`),
    FOREIGN KEY (`office_id`) REFERENCES `official_office_catalog` (`id`),
    FOREIGN KEY (`zone_id`) REFERENCES `org_zones` (`id`),
    FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
    FOREIGN KEY (`evidence_file_id`) REFERENCES `file_assets` (`id`),
    FOREIGN KEY (`recorded_by_user_id`) REFERENCES `users` (`id`),
    FOREIGN KEY (`approved_by_user_id`) REFERENCES `users` (`id`),
    CHECK (assignment_status IN ('current','completed','vacated','suspended','revoked')),
    CHECK (term_end_date IS NULL OR term_end_date >= term_start_date),
    KEY (office_id,zone_id,branch_id,assignment_status)
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
