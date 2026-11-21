<?php
declare(strict_types=1);

namespace App\Commands;

use App\Libraries\Auth\AccountActionTokenStore;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use RuntimeException;
use Throwable;

/**
 * Local-only synthetic token lifecycle check. All token rows are ROLLED BACK;
 * tokens, password hashes, contact details and credentials are never printed.
 */
final class P2TokenLifecycleCheck extends BaseCommand
{
    protected $group = 'IEDPU';
    protected $name = 'iedpu:p2-token-lifecycle-check';
    protected $description = 'Rollback-only synthetic checks for token issue, expiry, replay and revocation.';

    public function run(array $params): int
    {
        if (ENVIRONMENT !== 'development'
            || (string) env('IEDPU_ALLOW_SYNTHETIC_AUTH', '0') !== '1'
            || CLI::getOption('confirm-synthetic') === null) {
            CLI::write('REFUSED: development, synthetic opt-in and --confirm-synthetic required.', 'red');
            return 1;
        }
        $db = Database::connect();
        if (!in_array((string) $db->hostname, ['localhost','127.0.0.1','::1'], true)
            || !preg_match('/\Aiedpu_(?:dev|test|local|disposable)[a-z0-9_]*\z/iD', (string) $db->database)) {
            CLI::write('REFUSED: local disposable IEDPU database required.', 'red');
            return 1;
        }
        $identifier = $db->table('portal_login_identifiers')
            ->select('id,user_id,status,verified_at')
            ->where('identifier_type', 'phone')
            ->where('normalised_value', '+2348000000000')
            ->get()->getRowArray();
        if ($identifier === null || $identifier['status'] !== 'active' || $identifier['verified_at'] === null) {
            CLI::write('REFUSED: existing verified synthetic fixture required.', 'red');
            return 1;
        }
        $userId = (int) $identifier['user_id'];
        $identifierId = (int) $identifier['id'];
        $before = $db->table('portal_account_action_tokens')->where('user_id', $userId)->countAllResults();
        $db->transBegin();
        $passed = false;
        try {
            $tokens = new AccountActionTokenStore($db);
            $one = $tokens->issueWithinTransaction($userId, $identifierId, 'password_reset');
            $wrongPurpose = $tokens->redeemWithinTransaction($one['token'], 'identifier_verification', $userId);
            $wrongAccount = $tokens->redeemWithinTransaction($one['token'], 'password_reset', $userId + 1);
            $first = $tokens->redeemWithinTransaction($one['token'], 'password_reset', $userId);
            $replay = $tokens->redeemWithinTransaction($one['token'], 'password_reset', $userId);
            $two = $tokens->issueWithinTransaction($userId, $identifierId, 'password_reset');
            $three = $tokens->issueWithinTransaction($userId, $identifierId, 'password_reset');
            $superseded = $tokens->redeemWithinTransaction($two['token'], 'password_reset', $userId);
            $db->query(
                'UPDATE portal_account_action_tokens SET expires_at=UTC_TIMESTAMP(6) - INTERVAL 1 SECOND
                 WHERE public_id=?', [$three['public_id']]
            );
            $expired = $tokens->redeemWithinTransaction($three['token'], 'password_reset', $userId);
            $four = $tokens->issueWithinTransaction($userId, $identifierId, 'password_reset');
            $tokens->revokePendingWithinTransaction($userId);
            $revoked = $tokens->redeemWithinTransaction($four['token'], 'password_reset', $userId);

            if ($wrongPurpose !== null || $wrongAccount !== null || $first === null
                || $replay !== null || $superseded !== null || $expired !== null
                || $revoked !== null || (int) $first['user_id'] !== $userId
                || !$db->transStatus()) {
                throw new RuntimeException('Token lifecycle invariant failed.');
            }
            $passed = true;
        } catch (Throwable $error) {
            CLI::write('FAIL: synthetic token lifecycle invariant. Exception class: ' . get_class($error), 'red');
        } finally {
            // Never persist synthetic issue/redeem/expiry/revocation operations.
            $db->transRollback();
        }
        $after = $db->table('portal_account_action_tokens')->where('user_id', $userId)->countAllResults();
        if (!$passed || $before !== $after) {
            CLI::write('FAIL: lifecycle check did not pass or token row count changed.', 'red');
            return 1;
        }
        CLI::write('OK   Wrong purpose and wrong account denied', 'green');
        CLI::write('OK   Valid token accepted once; replay denied', 'green');
        CLI::write('OK   Reissued token invalidates prior pending token', 'green');
        CLI::write('OK   Expired and revoked tokens denied', 'green');
        CLI::write('OK   Transaction rolled back; token row count unchanged', 'green');
        CLI::write('HOLD: No delivery, password reset, account activation or privileged recovery route enabled.', 'yellow');
        return 0;
    }
}
