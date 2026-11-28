<?php
declare(strict_types=1);

namespace App\Commands;

use App\Libraries\Auth\Delivery\SyntheticLocalDelivery;
use App\Libraries\Auth\DevelopmentInvitationIssuer;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Throwable;

/**
 * Negative integration test against the REAL issuer. CLI has no authenticated
 * Shield/MFA browser session: denial is required and no mutation is permitted.
 * Does not claim to exercise successful issuance or delivery compensation.
 */
final class P2InvitationIssuerDenialCheck extends BaseCommand
{
    protected $group = 'IEDPU';
    protected $name = 'iedpu:p2-invitation-issuer-denial-check';
    protected $description = 'Verify the real invitation issuer fails closed without a Shield MFA session.';

    public function run(array $params): int
    {
        if (ENVIRONMENT !== 'development'
            || (string) env('IEDPU_ALLOW_SYNTHETIC_AUTH', '0') !== '1'
            || CLI::getOption('confirm-synthetic') === null
            || CLI::getOption('confirm-disposable') === null) {
            CLI::write('REFUSED: development and both synthetic/disposable confirmations required.', 'red');
            return 1;
        }
        $db = Database::connect();
        if (!in_array((string) $db->hostname, ['localhost', '127.0.0.1', '::1'], true)
            || !preg_match('/\Aiedpu_(?:test|disposable)[a-z0-9_]*\z/iD', (string) $db->database)) {
            CLI::write('REFUSED: dedicated loopback disposable database required.', 'red');
            return 1;
        }

        $actor = $db->table('portal_login_identifiers')
            ->select('user_id')->where('normalised_value', '+2348000000000')
            ->where('status', 'active')->get()->getRowArray();
        $target = $db->table('portal_login_identifiers i')
            ->select('i.id,i.user_id')->join('users u', 'u.id=i.user_id')
            ->where('i.normalised_value', 'iedpu-invite-fixture@example.invalid')
            ->where('i.status', 'pending')->where('u.active', 0)
            ->get()->getRowArray();
        if ($actor === null || $target === null
            || (int) $actor['user_id'] === (int) $target['user_id']) {
            CLI::write('REFUSED: expected isolated synthetic fixtures are missing.', 'red');
            return 1;
        }

        $tokensBefore = $db->table('portal_account_action_tokens')->countAllResults();
        $auditsBefore = $db->table('audit_events')->countAllResults();
        try {
            $delivery = new SyntheticLocalDelivery(
                ENVIRONMENT, (string) $db->database, (string) $db->hostname, true
            );
            $issuer = new DevelopmentInvitationIssuer($db, $delivery);
            // The real Shield session is intentionally absent. Never simulate
            // a successful login or inject MFA session state in this command.
            $result = $issuer->issue(
                (int) $actor['user_id'], (int) $target['user_id'], (int) $target['id'], 'global'
            );
            if ($result !== 'denied') {
                throw new \RuntimeException('Issuer did not deny unauthenticated CLI issuance.');
            }
            if ($delivery->takeMessages() !== []
                || $db->table('portal_account_action_tokens')->countAllResults() !== $tokensBefore
                || $db->table('audit_events')->countAllResults() !== $auditsBefore) {
                throw new \RuntimeException('Denied issuance caused a side effect.');
            }
        } catch (Throwable $error) {
            CLI::write('FAIL: issuer denial invariant (' . get_class($error) . ').', 'red');
            return 1;
        }
        CLI::write('OK   Real issuer denied CLI request without authenticated MFA session.', 'green');
        CLI::write('OK   No synthetic delivery, token or audit row was created.', 'green');
        CLI::write('HOLD: Authorized commit and delivery-failure compensation remain untested.', 'yellow');
        return 0;
    }
}
