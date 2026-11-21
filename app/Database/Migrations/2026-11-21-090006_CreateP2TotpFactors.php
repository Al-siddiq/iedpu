<?php
declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/** Approved P2-AUTH-02: encrypted authenticator-app factors and one-use recovery codes. */
final class CreateP2TotpFactors extends Migration
{
    private const TABLES = ['portal_totp_factors', 'portal_totp_recovery_codes'];

    public function up(): void
    {
        if ($this->db->getPlatform() !== 'MySQLi' || $this->db->getPrefix() !== '') {
            throw new RuntimeException('P2 requires MySQLi without a table prefix.');
        }
        $user = $this->db->query("SELECT COLUMN_TYPE AS column_type FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='id'")->getRowArray();
        if (!preg_match('/^int(?:\(\d+\))? unsigned$/i', $user['column_type'] ?? '')) {
            throw new RuntimeException('Run official Shield migrations first.');
        }
        foreach (self::TABLES as $table) {
            if ($this->db->tableExists($table)) {
                throw new RuntimeException('Existing TOTP table ' . $table . '; inspect migration history.');
            }
        }
        if ($this->db->query(<<<'SQL'
CREATE TABLE `portal_totp_factors` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `encrypted_secret` VARBINARY(512) NOT NULL,
    `last_used_step` BIGINT NOT NULL DEFAULT -1,
    `enrolled_at` DATETIME(6) NOT NULL,
    `updated_at` DATETIME(6) NULL,
    PRIMARY KEY (`id`),
    UNIQUE (`user_id`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
    CHECK (`last_used_step` >= -1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ) === false) {
            throw new RuntimeException('Could not create TOTP factors.');
        }
        if ($this->db->query(<<<'SQL'
CREATE TABLE `portal_totp_recovery_codes` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` INT UNSIGNED NOT NULL,
    `code_hash` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
    `created_at` DATETIME(6) NOT NULL,
    `used_at` DATETIME(6) NULL,
    PRIMARY KEY (`id`),
    UNIQUE (`user_id`,`code_hash`),
    FOREIGN KEY (`user_id`) REFERENCES `users` (`id`),
    KEY (`user_id`,`used_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ) === false) {
            throw new RuntimeException('Could not create TOTP recovery codes.');
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if ($this->db->tableExists($table) && $this->db->table($table)->countAllResults() > 0) {
                throw new RuntimeException('Refusing to drop populated MFA table ' . $table);
            }
        }
        foreach (array_reverse(self::TABLES) as $table) {
            $this->forge->dropTable($table, true);
        }
    }
}
