<?php
declare(strict_types=1);
namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;
use RuntimeException;

/** WP02 schema foundations only. No accounts, tokens, approvals or grants are created. */
final class CreateP2AccountGovernanceGates extends Migration
{
    private const TABLES = ['portal_account_action_tokens','portal_approval_action_bindings','portal_bootstrap_state'];

    public function up(): void
    {
        if ($this->db->getPlatform() !== 'MySQLi' || $this->db->getPrefix() !== '') {
            throw new RuntimeException('P2 requires MySQLi without a table prefix.');
        }
        $user = $this->db->query(
            "SELECT COLUMN_TYPE AS column_type FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' AND COLUMN_NAME='id'"
        )->getRowArray();
        if (!preg_match('/^int(?:\(\d+\))? unsigned$/i', $user['column_type'] ?? '')) {
            throw new RuntimeException('Shield users.id must be INT UNSIGNED.');
        }
        foreach (['portal_login_identifiers','approval_requests','org_zones','branches'] as $dependency) {
            if (!$this->db->tableExists($dependency)) {
                throw new RuntimeException('Missing P2 prerequisite: ' . $dependency);
            }
        }
        foreach (self::TABLES as $table) {
            if ($this->db->tableExists($table)) {
                throw new RuntimeException('Existing governance table: ' . $table);
            }
        }
        $this->execute(<<<'SQL'
CREATE TABLE portal_account_action_tokens (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 public_id CHAR(36) NOT NULL,
 user_id INT UNSIGNED NOT NULL,
 login_identifier_id BIGINT UNSIGNED NOT NULL,
 purpose VARCHAR(30) NOT NULL,
 token_hash CHAR(64) NOT NULL,
 delivery_channel VARCHAR(10) NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'pending',
 created_at DATETIME(6) NOT NULL,
 expires_at DATETIME(6) NOT NULL,
 consumed_at DATETIME(6) NULL,
 revoked_at DATETIME(6) NULL,
 issued_by_user_id INT UNSIGNED NULL,
 approval_request_id BIGINT UNSIGNED NULL,
 request_id VARCHAR(100) NULL,
 pending_user_id INT UNSIGNED GENERATED ALWAYS AS
    (CASE WHEN status='pending' THEN user_id ELSE NULL END) STORED,
 pending_purpose VARCHAR(30) GENERATED ALWAYS AS
    (CASE WHEN status='pending' THEN purpose ELSE NULL END) STORED,
 UNIQUE KEY uq_token_public (public_id),
 UNIQUE KEY uq_token_hash (token_hash),
 UNIQUE KEY uq_token_pending (pending_user_id,pending_purpose),
 KEY idx_token_expiry (status,expires_at),
 FOREIGN KEY (user_id) REFERENCES users(id),
 FOREIGN KEY (login_identifier_id) REFERENCES portal_login_identifiers(id),
 FOREIGN KEY (issued_by_user_id) REFERENCES users(id),
 FOREIGN KEY (approval_request_id) REFERENCES approval_requests(id),
 CHECK (purpose IN ('invitation_activation','password_reset','identifier_verification')),
 CHECK (delivery_channel IN ('email','phone')),
 CHECK (status IN ('pending','consumed','revoked')),
 CHECK (expires_at > created_at),
 CHECK ((status='pending' AND consumed_at IS NULL AND revoked_at IS NULL)
   OR (status='consumed' AND consumed_at IS NOT NULL AND revoked_at IS NULL)
   OR (status='revoked' AND revoked_at IS NOT NULL AND consumed_at IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        );
        $this->execute(<<<'SQL'
CREATE TABLE portal_approval_action_bindings (
 id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
 approval_request_id BIGINT UNSIGNED NOT NULL,
 action_key VARCHAR(120) NOT NULL,
 subject_type VARCHAR(60) NOT NULL,
 subject_id BIGINT UNSIGNED NOT NULL,
 scope_type VARCHAR(20) NOT NULL,
 zone_id BIGINT UNSIGNED NULL,
 branch_id BIGINT UNSIGNED NULL,
 expected_record_version BIGINT UNSIGNED NULL,
 expected_state_sha256 CHAR(64) NOT NULL,
 proposed_change_json JSON NOT NULL,
 intent_sha256 CHAR(64) NOT NULL,
 execution_status VARCHAR(20) NOT NULL DEFAULT 'pending',
 created_by_user_id INT UNSIGNED NOT NULL,
 created_at DATETIME(6) NOT NULL,
 executed_at DATETIME(6) NULL,
 invalidated_at DATETIME(6) NULL,
 UNIQUE KEY uq_binding_request (approval_request_id),
 KEY idx_binding_subject (subject_type,subject_id),
 FOREIGN KEY (approval_request_id) REFERENCES approval_requests(id),
 FOREIGN KEY (created_by_user_id) REFERENCES users(id),
 FOREIGN KEY (zone_id) REFERENCES org_zones(id),
 FOREIGN KEY (branch_id) REFERENCES branches(id),
 CHECK ((scope_type='global' AND zone_id IS NULL AND branch_id IS NULL)
    OR (scope_type='zone' AND zone_id IS NOT NULL AND branch_id IS NULL)
    OR (scope_type='branch' AND branch_id IS NOT NULL)),
 CHECK (execution_status IN ('pending','executed','invalidated')),
 CHECK ((execution_status='pending' AND executed_at IS NULL AND invalidated_at IS NULL)
    OR (execution_status='executed' AND executed_at IS NOT NULL AND invalidated_at IS NULL)
    OR (execution_status='invalidated' AND invalidated_at IS NOT NULL AND executed_at IS NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        );
        $this->execute(<<<'SQL'
CREATE TABLE portal_bootstrap_state (
 id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
 status VARCHAR(20) NOT NULL,
 committee_user_id INT UNSIGNED NOT NULL,
 technical_user_id INT UNSIGNED NOT NULL,
 authorisation_reference_sha256 CHAR(64) NOT NULL,
 started_at DATETIME(6) NOT NULL,
 completed_at DATETIME(6) NULL,
 FOREIGN KEY (committee_user_id) REFERENCES users(id),
 FOREIGN KEY (technical_user_id) REFERENCES users(id),
 CHECK (id=1),
 CHECK (committee_user_id <> technical_user_id),
 CHECK (status IN ('in_progress','completed')),
 CHECK ((status='in_progress' AND completed_at IS NULL)
    OR (status='completed' AND completed_at IS NOT NULL))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL
        );
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if ($this->db->tableExists($table) && $this->db->table($table)->countAllResults() > 0) {
                throw new RuntimeException('Refusing populated governance rollback: ' . $table);
            }
        }
        foreach (array_reverse(self::TABLES) as $table) {
            $this->forge->dropTable($table, true);
        }
    }

    private function execute(string $sql): void
    {
        if ($this->db->query($sql) === false) {
            throw new RuntimeException('Governance DDL failed; inspect partial migration.');
        }
    }
}
