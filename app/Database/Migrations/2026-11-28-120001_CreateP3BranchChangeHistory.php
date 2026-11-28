<?php
declare(strict_types=1);

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/** P3-WP03-A: preserve branch directory change history without altering P2 foundation tables. */
final class CreateP3BranchChangeHistory extends Migration
{
    public function up(): void
    {
        if ($this->db->getPlatform() !== 'MySQLi' || !$this->db->tableExists('branches')) {
            throw new RuntimeException('P3 requires the existing MySQLi branch directory.');
        }
        if ($this->db->tableExists('branch_change_history')) {
            throw new RuntimeException('branch_change_history already exists; inspect migration history.');
        }
        $this->db->query(<<<'SQL'
CREATE TABLE branch_change_history (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 branch_id BIGINT UNSIGNED NOT NULL,
 actor_user_id INT UNSIGNED NOT NULL,
 action_code VARCHAR(40) NOT NULL,
 reason VARCHAR(500) NOT NULL,
 before_json JSON NULL,
 after_json JSON NOT NULL,
 created_at DATETIME(6) NOT NULL,
 FOREIGN KEY (branch_id) REFERENCES branches(id),
 FOREIGN KEY (actor_user_id) REFERENCES users(id),
 KEY idx_branch_history (branch_id, id),
 CHECK (action_code IN ('created','updated','activated','deactivated'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        );
        if (!$this->db->tableExists('branch_change_history')) {
            throw new RuntimeException('P3 branch history table was not created.');
        }
    }

    public function down(): void
    {
        if ($this->db->tableExists('branch_change_history')
            && $this->db->table('branch_change_history')->countAllResults() > 0) {
            throw new RuntimeException('Refusing to remove populated branch history.');
        }
        $this->forge->dropTable('branch_change_history', true);
    }
}
