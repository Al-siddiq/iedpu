<?php
declare(strict_types=1);
namespace App\Database\Migrations;
use CodeIgniter\Database\Migration;
final class AddInvitationSmtpAcceptance extends Migration
{
 public function up(): void
 {
  if(!$this->db->fieldExists('smtp_accepted_at','portal_invitation_outbox'))
   $this->db->query('ALTER TABLE portal_invitation_outbox ADD smtp_accepted_at DATETIME(6) NULL AFTER delivered_at');
 }
 public function down(): void
 {
  // Preserve delivery evidence; never drop this column automatically.
 }
}
