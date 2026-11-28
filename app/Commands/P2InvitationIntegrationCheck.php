<?php
declare(strict_types=1);

namespace App\Commands;

use App\Libraries\Auth\AccountActionTokenStore;
use App\Libraries\Auth\Delivery\SyntheticLocalDelivery;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use RuntimeException;
use Throwable;

/**
 * Rollback-only database integration of the invitation token/store and
 * synthetic delivery boundary. DOES NOT call DevelopmentInvitationIssuer:
 * a CLI cannot truthfully fabricate a Shield authenticated MFA session.
 *
 * Requires an existing disposable synthetic user with no roles, identities
 * or TOTP, and a pending synthetic identifier. No new fixture is created.
 */
final class P2InvitationIntegrationCheck extends BaseCommand
{
    protected $group = 'IEDPU';
    protected $name = 'iedpu:p2-invitation-integration-check';
    protected $description = 'Rollback-only local invitation token/delivery database integration checks.';

    public function run(array $params): int
    {
        if (ENVIRONMENT !== 'development'
            || (string) env('IEDPU_ALLOW_SYNTHETIC_AUTH', '0') !== '1'
            || CLI::getOption('confirm-synthetic') === null) {
            CLI::write('REFUSED: development, synthetic opt-in and --confirm-synthetic required.', 'red');
            return 1;
        }
        $db = Database::connect();
        if (!in_array((string) $db->hostname, ['localhost', '127.0.0.1', '::1'], true)
            || !preg_match('/\Aiedpu_(?:dev|test|local|disposable)[a-z0-9_]*\z/iD', (string) $db->database)) {
            CLI::write('REFUSED: disposable loopback database required.', 'red');
            return 1;
        }

        // Explicitly reserved invalid contact, never a real recipient.
        $identifier = $db->table('portal_login_identifiers i')
            ->select('i.id,i.user_id,i.normalised_value,u.active')
            ->join('users u', 'u.id=i.user_id')
            ->where('i.identifier_type', 'email')
            ->where('i.normalised_value', 'iedpu-invite-fixture@example.invalid')
            ->where('i.status', 'pending')
            ->where('i.verified_at', null)
            ->where('i.revoked_at', null)
            ->get()->getRowArray();
        if ($identifier === null || (int) $identifier['active'] !== 0) {
            CLI::write('REFUSED: inactive pre-provisioned synthetic fixture with pending iedpu-invite-fixture@example.invalid identifier required.', 'yellow');
            CLI::write('HOLD: No fixture auto-created; do not run against real accounts.', 'yellow');
            return 1;
        }
        $uid = (int) $identifier['user_id'];
        foreach (['auth_identities', 'portal_user_role_assignments', 'portal_totp_factors'] as $table) {
            if ($db->table($table)->where('user_id', $uid)->countAllResults() !== 0) {
                CLI::write('REFUSED: fixture already has credentials, role or MFA.', 'red');
                return 1;
            }
        }
        $before = $db->table('portal_account_action_tokens')->where('user_id', $uid)->countAllResults();
        $db->transBegin();
        $passed = false;
        try {
            $store = new AccountActionTokenStore($db);
            // Synthetic actor ID must reference a different existing user.
            // The existing phone-only fixture is acceptable solely as an FK
            // value here, not as a claim of issuer authorization.
            $actor = $db->table('portal_login_identifiers')
                ->select('user_id')->where('normalised_value', '+2348000000000')
                ->where('status', 'active')->get()->getRowArray();
            if ($actor === null || (int) $actor['user_id'] === $uid) {
                throw new RuntimeException('Separate synthetic issuer fixture missing.');
            }
            $one = $store->issueWithinTransaction(
                $uid, (int) $identifier['id'], 'invitation_activation', (int) $actor['user_id']
            );
            $delivery = new SyntheticLocalDelivery(
                ENVIRONMENT, (string) $db->database, (string) $db->hostname, true
            );
            // This is only a transport contract check, NOT the production
            // sequencing test; the enclosing transaction will roll back.
            $delivery->deliver('invitation_activation', $identifier['normalised_value'], $one['token']);
            $messages = $delivery->takeMessages();
            if (count($messages) !== 1
                || !hash_equals($one['token'], $messages[0]['token'])
                || $delivery->takeMessages() !== []) {
                throw new RuntimeException('Synthetic delivery contract failed.');
            }
            unset($messages);
            $two = $store->issueWithinTransaction(
                $uid, (int) $identifier['id'], 'invitation_activation', (int) $actor['user_id']
            );
            if ($store->redeemWithinTransaction($one['token'], 'invitation_activation', $uid) !== null) {
                throw new RuntimeException('Superseded invitation was accepted.');
            }
            $store->revokePendingWithinTransaction($uid);
            if ($store->redeemWithinTransaction($two['token'], 'invitation_activation', $uid) !== null
                || !$db->transStatus()) {
                throw new RuntimeException('Revoked invitation was accepted.');
            }
            $passed = true;
        } catch (Throwable $error) {
            CLI::write('FAIL: invitation integration invariant; exception class ' . get_class($error), 'red');
        } finally {
            $db->transRollback();
        }
        $after = $db->table('portal_account_action_tokens')->where('user_id', $uid)->countAllResults();
        if (!$passed || $before !== $after) {
            CLI::write('FAIL: rollback or token row count invariant.', 'red');
            return 1;
        }
        CLI::write('OK   Invitation token issued for pending synthetic identifier (rolled back)', 'green');
        CLI::write('OK   Synthetic delivery captured token in memory only', 'green');
        CLI::write('OK   Reissued invitation invalidated previous token', 'green');
        CLI::write('OK   Revoked invitation could not be redeemed', 'green');
        CLI::write('OK   No invitation token rows persisted', 'green');
        CLI::write('HOLD: Full issuer/session/MFA, atomic audit commit, post-commit delivery and compensating failure path NOT YET integration-tested.', 'yellow');
        return 0;
    }
}
