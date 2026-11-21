<?php
declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/**
 * WP02 session invalidation. Version stored independently of Shield session
 * handler; revocation works even when the session backend cannot enumerate IDs.
 */
final class CreateP2SessionVersions extends Migration
{
    public function up(): void
    {
        if ($this->db->getPlatform() !== 'MySQLi' || $this->db->getPrefix() !== '') {
            throw new RuntimeException('P2 requires MySQLi with no table prefix.');
        }
        if ($this->db->tableExists('portal_user_session_versions')) {
            throw new RuntimeException('Existing session-version table. Inspect migration history.');
        }
        if ($this->db->query(<<<'SQL'
CREATE TABLE portal_user_session_versions (
    user_id INT UNSIGNED NOT NULL,
    session_version BIGINT UNSIGNED NOT NULL DEFAULT 1,
    created_at DATETIME(6) NOT NULL,
    updated_at DATETIME(6) NOT NULL,
    PRIMARY KEY (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
    CHECK (session_version >= 1)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        ) === false) {
            throw new RuntimeException('Could not create session version table.');
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('portal_user_session_versions')
            && $this->db->table('portal_user_session_versions')->countAllResults() > 0) {
            throw new RuntimeException('Refusing rollback of populated session-version table.');
        }
        $this->forge->dropTable('portal_user_session_versions', true);
    }
}
