<?php
declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/** P2-WP01 — AccountIdentifiers. MySQL-compatible DDL; no application records created. */
final class CreateP2AccountIdentifiers extends Migration
{
    private const TABLES = ['portal_login_identifiers'];

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
CREATE TABLE `portal_login_identifiers` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    PRIMARY KEY (`id`),
    `public_id` CHAR(36) NOT NULL,
    `user_id` INT UNSIGNED NOT NULL,
    `identifier_type` VARCHAR(10) NOT NULL,
    `normalised_value` VARCHAR(190) NOT NULL,
    `verified_at` DATETIME(6) NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'pending',
    `revoked_at` DATETIME(6) NULL,
    `created_at` DATETIME(6) NOT NULL,
    `updated_at` DATETIME(6) NULL,
    UNIQUE (`public_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
    CHECK (identifier_type IN ('email','phone')),
    CHECK (status IN ('pending','active','revoked')),
    CHECK (status <> 'active' OR (verified_at IS NOT NULL AND revoked_at IS NULL)),
    CHECK (status <> 'revoked' OR revoked_at IS NOT NULL),
    current_value VARCHAR(190) GENERATED ALWAYS AS (CASE WHEN status IN ('pending','active') THEN normalised_value ELSE NULL END) STORED,
    current_type VARCHAR(10) GENERATED ALWAYS AS (CASE WHEN status IN ('pending','active') THEN identifier_type ELSE NULL END) STORED,
    UNIQUE (identifier_type,current_value),
    UNIQUE (user_id,current_type),
    KEY (user_id,status)
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
