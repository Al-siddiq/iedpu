<?php
declare(strict_types=1);

namespace App\Commands;

use App\Libraries\Auth\AccountActionTokenCodec;
use App\Libraries\Governance\ApprovalIntent;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;
use Throwable;

/**
 * Read-only local diagnostic: no accounts, invitations, reset tokens,
 * administrator roles, approvals or bootstrap records are created.
 */
final class P2GovernancePreflight extends BaseCommand
{
    protected $group = 'IEDPU';
    protected $name = 'iedpu:p2-governance-preflight';
    protected $description = 'Read-only local check for invitation/recovery and governance schema foundations.';

    public function run(array $params): int
    {
        if (ENVIRONMENT !== 'development') {
            CLI::write('REFUSED: development-only preflight.', 'red');
            return 1;
        }
        try {
            $db = Database::connect();
            if (!in_array((string) $db->hostname, ['localhost','127.0.0.1','::1'], true)
                || !preg_match('/\Aiedpu_(?:dev|test|local|disposable)[a-z0-9_]*\z/iD', (string) $db->database)) {
                CLI::write('REFUSED: use a local disposable/development IEDPU database.', 'red');
                return 1;
            }
            CLI::write('IEDPU P2 account and governance foundations — read-only', 'yellow');
            $ready = true;
            foreach (['portal_account_action_tokens','portal_approval_action_bindings','portal_bootstrap_state'] as $table) {
                $exists = $db->tableExists($table);
                CLI::write(($exists ? 'OK   ' : 'FAIL ') . 'Table: ' . $table, $exists ? 'green' : 'red');
                $ready = $ready && $exists;
            }

            $token = AccountActionTokenCodec::generate();
            $digest = AccountActionTokenCodec::digest($token);
            $codecWorks = strlen($token) === 64
                && AccountActionTokenCodec::matches($token, $digest)
                && !AccountActionTokenCodec::matches(str_repeat('0', 64), $digest);
            unset($token, $digest);
            CLI::write(($codecWorks ? 'OK   ' : 'FAIL ') . 'Cryptographic token primitive (no token printed or saved)', $codecWorks ? 'green' : 'red');
            $ready = $ready && $codecWorks;

            $state = str_repeat('a', 64);
            $a = ApprovalIntent::digest('role.assign', 'user', 1, 'global', null, null, $state, [
                'role_code' => 'synthetic', 'scope' => 'global',
            ]);
            $b = ApprovalIntent::digest('role.assign', 'user', 1, 'global', null, null, $state, [
                'scope' => 'global', 'role_code' => 'synthetic',
            ]);
            $c = ApprovalIntent::digest('role.assign', 'user', 1, 'global', null, null, $state, [
                'scope' => 'global', 'role_code' => 'different',
            ]);
            $intentWorks = hash_equals($a, $b) && !hash_equals($a, $c);
            CLI::write(($intentWorks ? 'OK   ' : 'FAIL ') . 'Approval intent canonicalisation and tamper detection', $intentWorks ? 'green' : 'red');
            $ready = $ready && $intentWorks;

            if ($db->tableExists('portal_bootstrap_state')) {
                $count = $db->table('portal_bootstrap_state')->countAllResults();
                CLI::write(($count === 0 ? 'OK   ' : 'REVIEW ') . 'Initial administrator bootstrap: '
                    . ($count === 0 ? 'not performed' : 'marker exists; inspect authorisation before proceeding'),
                    $count === 0 ? 'green' : 'yellow');
                $ready = $ready && $count === 0;
            }
            CLI::write('HOLD: No account delivery, token issuance/redemption, recovery, bootstrap, approval execution or role mutation route is enabled.', 'yellow');
            CLI::write($ready ? 'Foundation preflight passed; operational workflows remain disabled.'
                : 'Foundation preflight incomplete. Inspect FAIL/REVIEW results.', $ready ? 'green' : 'red');
            return $ready ? 0 : 1;
        } catch (Throwable $error) {
            CLI::write('Preflight could not complete. Check migration status and database connectivity.', 'red');
            CLI::write('Exception class: ' . get_class($error), 'yellow');
            return 1;
        }
    }
}
