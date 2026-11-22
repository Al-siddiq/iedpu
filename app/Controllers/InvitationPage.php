<?php
declare(strict_types=1);
namespace App\Controllers;
use CodeIgniter\HTTP\ResponseInterface;
/** Public token redemption UI. Never embeds or logs a token in page HTML. */
final class InvitationPage extends BaseController {
 public function index(): ResponseInterface {
  return $this->response->setHeader('Cache-Control','no-store, private')
   ->setHeader('Referrer-Policy','no-referrer')
   ->setHeader('Content-Security-Policy',"default-src 'none'; style-src 'unsafe-inline'; script-src 'unsafe-inline'; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'")
   ->setBody(<<<'HTML'
<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Activate IEDPU invitation</title>
<style>body{font:16px system-ui,sans-serif;background:#f4f6f9;color:#182337;margin:0;padding:3rem 1rem}main{max-width:480px;margin:auto;background:white;padding:2rem;border-radius:12px;box-shadow:0 4px 24px #0001}label{display:block;margin:1rem 0}input{display:block;width:100%;box-sizing:border-box;padding:.75rem;margin-top:.4rem}button{padding:.8rem 1.5rem;background:#163c72;color:white;border:0;border-radius:5px;cursor:pointer}#status{min-height:2rem}</style></head>
<body><main><h1>Activate your IEDPU account</h1><p>Enter the account ID and invitation token from your email. The token can only be used once.</p>
<form id="activate" autocomplete="off"><label>Account ID<input name="user_id" type="number" min="1" required></label><label>Invitation token<input name="token" type="text" pattern="[a-f0-9]{64}" minlength="64" maxlength="64" required autocomplete="off"></label><label>New password<input name="password" type="password" minlength="6" maxlength="72" required autocomplete="new-password"></label><button type="submit">Activate account</button></form><p id="status" role="status" aria-live="polite"></p><p><a href="/sign-in">Return to sign in</a></p></main>
<script>
document.getElementById('activate').addEventListener('submit',async function(e){
 e.preventDefault();const f=e.target,s=document.getElementById('status');s.textContent='Processing…';
 try{
  const c=await fetch('/api/v1/auth/csrf',{credentials:'same-origin',cache:'no-store'});
  if(!c.ok)throw Error('Unavailable');const j=await c.json();
  const d={user_id:Number(f.user_id.value),token:f.token.value,password:f.password.value};
  const r=await fetch('/api/v1/auth/invitations/activate',{method:'POST',credentials:'same-origin',headers:{'Content-Type':'application/json',[j.data.header]:j.data.token},body:JSON.stringify(d),cache:'no-store'});
  f.password.value='';f.token.value='';
  s.textContent=r.ok?'Account activated. You can now sign in.':'Activation failed or invitation expired. Contact the inviting officer.';
 }catch(_){s.textContent='Activation is temporarily unavailable. Try again later.';}
});
</script></body></html>
HTML
   );
 }
}
