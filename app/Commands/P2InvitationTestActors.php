<?php
declare(strict_types=1);

namespace App\Commands;

use App\Libraries\Security\AtomicAuditedMutation;
use App\Libraries\Security\AuditTrail;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Shield\Models\UserIdentityModel;
use Config\Database;
use RuntimeException;
use Throwable;

/**
 * One-time local fixture for the invitation browser acceptance.
 * Creates a temporary checker and restores the existing synthetic maker grant.
 * This is not production provisioning and sends no SMS/email.
 */
final class P2InvitationTestActors extends BaseCommand
{
    protected $group = 'IEDPU';
    protected $name = 'iedpu:p2-invitation-test-actors';
    protected $description = 'Prepare synthetic Maker and temporary Checker accounts for local invitation testing.';

    public function run(array $params): int
    {
        if (ENVIRONMENT !== 'development'
            || (string) env('IEDPU_ALLOW_SYNTHETIC_AUTH', '0') !== '1'
            || CLI::getOption('confirm-synthetic') === null
            || CLI::getOption('confirm-disposable') === null) {
            CLI::write('REFUSED: development, synthetic opt-in and both confirmations are required.', 'red');
            return 1;
        }

        $db = Database::connect();
        if (!in_array((string) $db->hostname, ['localhost', '127.0.0.1', '::1'], true)
            || (string) $db->database !== 'iedpu_disposable_invitation') {
            CLI::write('REFUSED: this fixture is restricted to local iedpu_disposable_invitation.', 'red');
            return 1;
        }

        $makerPhone = '+2348000000000';
        $checkerPhone = '+2348000000001';
        $maker = $db->table('portal_login_identifiers i')
            ->select('i.user_id, u.active')
            ->join('users u', 'u.id = i.user_id')
            ->where('i.normalised_value', $makerPhone)
            ->where('i.identifier_type', 'phone')
            ->where('i.status', 'active')
            ->get()->getRowArray();

        if ($maker === null || (int) $maker['active'] !== 1
            || $db->table('portal_totp_factors')->where('user_id', (int) $maker['user_id'])->countAllResults() < 1) {
            CLI::write('REFUSED: expected active synthetic Maker with enrolled TOTP was not found.', 'red');
            return 1;
        }

        $makerId = (int) $maker['user_id'];
        $makerRole = $db->table('portal_roles')->where('role_code', 'synthetic_invite_acceptance')
            ->where('status', 'active')->get()->getRowArray();
        $invitePermission = $db->table('portal_permissions')->where('permission_key', 'users.invite')
            ->where('status', 'active')->get()->getRowArray();
        $checkerView = $db->table('portal_permissions')->where('permission_key', 'approvals.view')
            ->where('status', 'active')->get()->getRowArray();
        $checkerDecide = $db->table('portal_permissions')->where('permission_key', 'approvals.decide')
            ->where('status', 'active')->get()->getRowArray();

        if ($makerRole === null || $invitePermission === null || $checkerView === null || $checkerDecide === null) {
            CLI::write('REFUSED: required synthetic role or permission catalogue entries are missing.', 'red');
            return 1;
        }
        if ($db->table('portal_role_permissions')->where('role_id', (int) $makerRole['id'])
            ->where('permission_id', (int) $invitePermission['id'])->countAllResults() !== 1) {
            CLI::write('REFUSED: existing Maker role does not contain users.invite.', 'red');
            return 1;
        }
        if ($db->table('portal_login_identifiers')->where('normalised_value', $checkerPhone)->countAllResults() !== 0
            || $db->table('portal_roles')->where('role_code', 'synthetic_invitation_checker')->countAllResults() !== 0) {
            CLI::write('REFUSED: temporary Checker fixture already exists; inspect it rather than duplicating it.', 'yellow');
            return 1;
        }

        $password = bin2hex(random_bytes(18)) . 'Aa9!';
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $nowSql = $now->format('Y-m-d H:i:s.u');
        $expirySql = $now->modify('+8 hours')->format('Y-m-d H:i:s.u');
        $checkerId = 0;

        try {
            (new AtomicAuditedMutation($db))->run(function () use (
                $db, $makerId, $makerRole, $invitePermission, $checkerView, $checkerDecide,
                $checkerPhone, $password, $nowSql, $expirySql, &$checkerId
            ): void {
                // Restore the known, already-created synthetic Maker assignment for this test window.
                $assignment = $db->table('portal_user_role_assignments')
                    ->where('user_id', $makerId)
                    ->where('role_id', (int) $makerRole['id'])
                    ->orderBy('id', 'DESC')->get()->getRowArray();

                if ($assignment === null) {
                    throw new RuntimeException('Existing synthetic Maker role assignment not found.');
                }
                $db->table('portal_user_role_assignments')->where('id', (int) $assignment['id'])->update([
                    'scope_type' => 'global',
                    'zone_id' => null,
                    'branch_id' => null,
                    'starts_at' => $nowSql,
                    'expires_at' => $expirySql,
                    'assignment_status' => 'active',
                    'assignment_reason' => 'TEMPORARY LOCAL INVITATION ACCEPTANCE; expires automatically',
                    'revoked_at' => null,
                    'revoked_by_user_id' => null,
                ]);

                $users = model(config('Auth')->userProvider);
                $user = $users->createNewUser(['active' => 1]);
                $checkerId = (int) $users->insert($user);
                if ($checkerId < 1) {
                    throw new RuntimeException('Temporary Checker account creation failed.');
                }
                $user = $users->findById($checkerId);
                if ($user === null) {
                    throw new RuntimeException('Temporary Checker account could not be reloaded.');
                }
                model(UserIdentityModel::class)->createEmailIdentity($user, [
                    'email' => $checkerPhone,
                    'password' => $password,
                ]);

                $uuid = $this->uuid();
                if (!$db->table('portal_login_identifiers')->insert([
                    'public_id' => $uuid,
                    'user_id' => $checkerId,
                    'identifier_type' => 'phone',
                    'normalised_value' => $checkerPhone,
                    'verified_at' => $nowSql,
                    'status' => 'active',
                    'created_at' => $nowSql,
                ])) {
                    throw new RuntimeException('Temporary Checker login identifier creation failed.');
                }

                if (!$db->table('portal_roles')->insert([
                    'role_code' => 'synthetic_invitation_checker',
                    'role_name' => 'Temporary Invitation Checker (LOCAL TEST ONLY)',
                    'description' => 'Temporary local acceptance fixture; expires with its assignment.',
                    'is_system_role' => 0,
                    'is_assignable' => 0,
                    'requires_second_approval' => 0,
                    'status' => 'active',
                    'created_at' => $nowSql,
                ])) {
                    throw new RuntimeException('Temporary Checker role creation failed.');
                }
                $roleId = (int) $db->insertID();

                foreach ([$checkerView, $checkerDecide] as $permission) {
                    if (!$db->table('portal_role_permissions')->insert([
                        'role_id' => $roleId,
                        'permission_id' => (int) $permission['id'],
                        'granted_at' => $nowSql,
                        'granted_by_user_id' => $makerId,
                    ])) {
                        throw new RuntimeException('Temporary Checker permission link failed.');
                    }
                }

                if (!$db->table('portal_user_role_assignments')->insert([
                    'public_id' => $this->uuid(),
                    'user_id' => $checkerId,
                    'role_id' => $roleId,
                    'scope_type' => 'global',
                    'zone_id' => null,
                    'branch_id' => null,
                    'starts_at' => $nowSql,
                    'expires_at' => $expirySql,
                    'assignment_status' => 'active',
                    'assignment_reason' => 'TEMPORARY LOCAL INVITATION ACCEPTANCE; approvals.view and approvals.decide only',
                    'assigned_by_user_id' => $makerId,
                    'created_at' => $nowSql,
                ])) {
                    throw new RuntimeException('Temporary Checker role assignment failed.');
                }

                (new AuditTrail($db))->append([
                    'action_key' => 'security.synthetic_invitation_test_actors_provisioned',
                    'module_code' => 'security',
                    'event_category' => 'role',
                    'outcome' => 'success',
                    'reason' => 'Local disposable invitation acceptance; temporary checker expires in eight hours.',
                    'actor_user_id' => $makerId,
                    'subject_type' => 'user',
                    'subject_id' => $checkerId,
                    'permission_key' => 'roles.assign',
                ]);
            });
        } catch (Throwable $error) {
            CLI::write('FAIL: temporary test actor provisioning failed (' . get_class($error) . ').', 'red');
            CLI::write('No credentials were printed. Inspect database state before retrying.', 'yellow');
            return 1;
        }

        CLI::write('OK   Existing synthetic User 1 restored as temporary Maker; TOTP remains enrolled.', 'green');
        CLI::write('OK   Temporary Checker account created as User ' . $checkerId . '.', 'green');
        CLI::write('OK   Checker permissions limited to approvals.view and approvals.decide.', 'green');
        CLI::write('OK   Both temporary role assignments expire automatically in eight hours.', 'green');
        CLI::write('Checker login identifier: ' . $checkerPhone);
        CLI::write('One-time Checker password (save locally; do not share): ' . $password);
        CLI::write('NEXT: Sign in as Checker, enrol TOTP, then sign in as Maker to run invitation acceptance.');
        CLI::write('No email or SMS was sent.');
        return 0;
    }

    private function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 15) | 64);
        $b[8] = chr((ord($b[8]) & 63) | 128);
        $h = bin2hex($b);
        return substr($h, 0, 8) . '-' . substr($h, 8, 4) . '-' . substr($h, 12, 4)
            . '-' . substr($h, 16, 4) . '-' . substr($h, 20);
    }
}
