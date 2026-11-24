<?php
declare(strict_types=1);
namespace App\Controllers;
use CodeIgniter\HTTP\ResponseInterface;
final class InvitationPage extends BaseController
{
 public function index(): ResponseInterface
 {
  $csrfUrl=esc(site_url('api/v1/auth/csrf'),'attr');
  $activateUrl=esc(site_url('api/v1/auth/invitations/activate'),'attr');
  $signInUrl=esc(site_url('sign-in'),'attr');
  $scriptUrl=esc(base_url('assets/js/pages/invitation-activation.js'),'attr');
  $styleUrl=esc(base_url('assets/css/invitation-activation.css'),'attr');
  $html=<<<HTML
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Activate IEDPU invitation</title><link rel="stylesheet" href="{$styleUrl}"><script src="{$scriptUrl}" defer></script></head>
<body><main id="invitation-activation" data-csrf-url="{$csrfUrl}" data-activation-url="{$activateUrl}">
<h1>Activate your IEDPU account</h1><p>Enter the account ID and invitation token from your email. The token can only be used once.</p>
<form id="activate" autocomplete="off"><label>Account ID<input name="user_id" type="number" min="1" required></label><label>Invitation token<input name="token" type="text" pattern="[a-f0-9]{64}" minlength="64" maxlength="64" required autocomplete="off"></label><label>New password<input name="password" type="password" minlength="6" maxlength="72" required autocomplete="new-password"></label><button type="submit">Activate account</button></form>
<p id="status" role="status" aria-live="polite"></p><p><a href="{$signInUrl}">Return to sign in</a></p></main></body></html>
HTML;
  return $this->response->setHeader('Cache-Control','no-store, private, max-age=0')
   ->setHeader('Pragma','no-cache')->setHeader('Referrer-Policy','no-referrer')
   ->setHeader('X-Content-Type-Options','nosniff')
   ->setHeader('Content-Security-Policy',"default-src 'none'; script-src 'self'; style-src 'self'; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'; object-src 'none'")
   ->setBody($html);
 }
}
