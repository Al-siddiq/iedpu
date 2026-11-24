<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$page=file_get_contents($root.'/app/Controllers/InvitationPage.php');
$js=file_get_contents($root.'/public/assets/js/pages/invitation-activation.js');
$css=file_get_contents($root.'/public/assets/css/invitation-activation.css');
$dispatch=file_get_contents($root.'/app/Commands/P2InvitationSmtpDispatch.php');
$reconcile=file_get_contents($root.'/app/Commands/P2InvitationReconcile.php');
$checks=[
 'Framework-generated base-path-aware URLs'=>str_contains($page,"site_url('api/v1/auth/csrf')")&&str_contains($page,"site_url('api/v1/auth/invitations/activate')")&&str_contains($page,"base_url('assets/js/pages/invitation-activation.js')"),
 'No inline script or style blocks'=>!preg_match('/<script(?:\\s|>)/i',$page)&&!preg_match('/<style(?:\\s|>)/i',$page),
 'CSP restricts scripts and styles to same origin'=>str_contains($page,"script-src 'self'; style-src 'self'"),
 'Activation JS uses same-origin credentials and CSRF'=>str_contains($js,"credentials:'same-origin'")&&str_contains($js,'csrf.data.header'),
 'External activation stylesheet exists'=>is_string($css)&&str_contains($css,'max-width'),
 'SMTP acceptance is not called inbox delivery'=>str_contains($dispatch,'smtp_accepted_at')&&str_contains($dispatch,'recipient inbox delivery is not confirmed'),
 'Reconciliation disabled by default and ticket gated'=>str_contains($reconcile,'IEDPU_INVITATION_RECONCILE_ENABLED')&&str_contains($reconcile,'--incident-ticket='),
];
$failed=0;foreach($checks as $label=>$ok){echo ($ok?'OK   ':'FAIL ').$label.PHP_EOL;if(!$ok)$failed++;}
exit($failed===0?0:1);
