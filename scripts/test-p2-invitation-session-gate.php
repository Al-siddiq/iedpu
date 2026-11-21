<?php
declare(strict_types=1);

/** Pure no-DB test; no users, tokens, sessions or audit events are changed. */
require_once dirname(__DIR__) . '/app/Libraries/Auth/InvitationIssuerSessionGate.php';

use App\Libraries\Auth\InvitationIssuerSessionGate as Gate;

$checks = [
    'Privileged issuer with matching MFA and current session accepted' =>
        Gate::allows(7, 7, true, 7, true),
    'Different authenticated issuer denied' =>
        !Gate::allows(7, 8, true, 7, true),
    'Missing authenticated issuer denied' =>
        !Gate::allows(0, 7, true, 7, true),
    'Revoked or expired session denied' =>
        !Gate::allows(7, 7, false, 7, true),
    'Missing privileged MFA denied' =>
        !Gate::allows(7, 7, true, null, true),
    'Other-user privileged MFA denied' =>
        !Gate::allows(7, 7, true, 8, true),
    'Untrusted MFA string denied' =>
        !Gate::allows(7, 7, true, '7', true),
    'Nonprivileged issuer still requires valid matching session' =>
        Gate::allows(7, 7, true, null, false)
        && !Gate::allows(7, 7, false, null, false),
];

foreach ($checks as $name => $ok) {
    if (!$ok) {
        fwrite(STDERR, 'FAIL ' . $name . PHP_EOL);
        exit(1);
    }
    echo 'OK   ' . $name . PHP_EOL;
}
echo 'HOLD: No invitation issuance or recovery route enabled.' . PHP_EOL;
