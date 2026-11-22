<?php
declare(strict_types=1);
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
use RuntimeException;
final class CreateP2InvitationOutbox extends Migration {
 public function up(): void {
  if ($this->db->tableExists('portal_invitation_outbox')) throw new RuntimeException('Invitation outbox exists');
  $this->db->query(<<<'SQL'
CREATE TABLE portal_invitation_outbox (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 token_public_id CHAR(36) NOT NULL,
 recipient VARCHAR(190) NOT NULL,
 ciphertext TEXT NOT NULL,
 status VARCHAR(20) NOT NULL DEFAULT 'queued',
 attempts INT UNSIGNED NOT NULL DEFAULT 0,
 claimed_at DATETIME(6) NULL,
 delivered_at DATETIME(6) NULL,
 failed_at DATETIME(6) NULL,
 created_at DATETIME(6) NOT NULL,
 UNIQUE KEY uq_invitation_outbox_token (token_public_id),
 KEY idx_invitation_outbox_status (status,created_at),
 FOREIGN KEY (token_public_id) REFERENCES portal_account_action_tokens(public_id),
 CHECK (status IN ('queued','sending','sent','failed','uncertain'))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
 }
 public function down(): void {
  if ($this->db->tableExists('portal_invitation_outbox') && $this->db->table('portal_invitation_outbox')->countAllResults()>0) throw new RuntimeException('Refusing populated invitation outbox rollback');
  $this->forge->dropTable('portal_invitation_outbox',true);
 }
}
