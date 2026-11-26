<?php
declare(strict_types=1);

$root = dirname(__DIR__);
$routes = file_get_contents($root . '/app/Config/Routes.php');
$controller = file_get_contents($root . '/app/Controllers/Api/V1/InvitationController.php');
$service = file_get_contents($root . '/app/Libraries/Auth/ApprovedInvitationRelease.php');
$workspace = file_get_contents($root . '/app/Controllers/InvitationWorkspace.php');
$view = file_get_contents($root . '/app/Views/admin/invitation_workspace.php');
$js = file_get_contents($root . '/public/assets/js/pages/invitation-workspace.js');
$checks = [
    'Protected invitation workspace route exists' =>
        str_contains($routes, "administration/invitations', 'InvitationWorkspace::index'"),
    'Workspace API route exists' =>
        str_contains($routes, "auth/invitations/workspace', 'InvitationController::workspaceData'"),
    'Maker request, checker decision and maker issue routes exist' =>
        str_contains($routes, "auth/invitations/request', 'InvitationController::requestApproval'")
        && str_contains($routes, "auth/invitations/decide', 'InvitationController::decide'")
        && str_contains($routes, "auth/invitations/issue', 'InvitationController::issue'"),
    'Workspace API requires current MFA session' =>
        str_contains($controller, "InvitationIssuerSessionGate::allows")
        && str_contains($controller, "iedpu_mfa_verified_user"),
    'Checker approval uses approvals.decide rather than maker users.invite' =>
        str_contains($service, "'approvals.decide'") && str_contains($service, "'users.invite'"),
    'Maker issue remains bound to original request owner' =>
        str_contains($service, "requested_by_user_id']!==\$actor"),
    'Local token preview is restricted to disposable database' =>
        str_contains($service, "iedpu_disposable_invitation")
        && str_contains($service, 'InvitationOutboxCrypto::open'),
    'Workspace provides maker, checker and issue controls' =>
        str_contains($view, 'Submit an invitation request')
        && str_contains($view, 'Requests awaiting your approval')
        && str_contains($view, 'My invitation requests'),
    'Browser client calls the implemented invitation endpoints' =>
        str_contains($js, 'auth/invitations/workspace')
        && str_contains($js, 'auth/invitations/request')
        && str_contains($js, 'auth/invitations/decide')
        && str_contains($js, 'auth/invitations/issue')
        && str_contains($js, 'auth/invitations/local-preview'),
    'Browser client sends CSRF-protected same-origin requests' =>
        str_contains($js, 'credentials: \'same-origin\'')
        && str_contains($js, 'headers[csrf.header] = csrf.token'),
];
$failed = 0;
foreach ($checks as $label => $ok) {
    echo ($ok ? 'OK   ' : 'FAIL ') . $label . PHP_EOL;
    if (!$ok) $failed++;
}
exit($failed === 0 ? 0 : 1);
