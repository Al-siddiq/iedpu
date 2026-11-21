<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Auth\SessionVersion;
use App\Libraries\Auth\VerifiedIdentifierResolver;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/** Password change form, never public account recovery. */
final class AccountSecurity extends BaseController
{
    public function index(): ResponseInterface
    {
        $this->response->setHeader('Cache-Control', 'no-store, private')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Content-Security-Policy', "default-src 'none'; script-src 'self'; style-src 'self'; connect-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'none'");
        $auth = auth('session');
        $user = $auth->user();
        if ($user === null) {
            return redirect()->to(base_url('sign-in'));
        }
        try {
            $db = db_connect();
            $id = (int) $user->id;
            $fresh = $db->table('users')->select('active')->where('id', $id)->get()->getRowArray();
            $hasFactor = $db->table('portal_totp_factors')->where('user_id', $id)->countAllResults() > 0;
            if (!(new SessionVersion($db))->isCurrent($id)
                || $fresh === null || (int) $fresh['active'] !== 1
                || $user->isBanned()
                || !(new VerifiedIdentifierResolver($db))->hasVerifiedIdentifier($id)
                || !$hasFactor
                || (int) session()->get('iedpu_mfa_verified_user') !== $id) {
                $auth->logout();
                SessionVersion::clearLocal();
                return $this->response->setStatusCode(401)->setBody('Your authenticated session has ended. Sign in again using your authenticator.');
            }
            return $this->response->setBody(view('auth/account_security'));
        } catch (Throwable) {
            return $this->response->setStatusCode(503)->setBody('Account security is temporarily unavailable.');
        }
    }
}
