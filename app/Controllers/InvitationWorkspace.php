<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Auth\ScopeAuthorizer;
use App\Libraries\Auth\SessionVersion;
use CodeIgniter\HTTP\ResponseInterface;

/** Protected maker/checker invitation workspace for P2 acceptance. */
final class InvitationWorkspace extends BaseController
{
    public function index(): ResponseInterface
    {
        $this->response->setHeader('Cache-Control', 'no-store, private, max-age=0')
            ->setHeader('Pragma', 'no-cache')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Referrer-Policy', 'no-referrer')
            ->setHeader('Content-Security-Policy', "default-src 'none'; script-src 'self'; style-src 'self'; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'; object-src 'none'");

        $auth = auth('session');
        $user = $auth->user();
        if ($user === null) {
            return redirect()->to(site_url('sign-in'));
        }

        $id = (int) $user->id;
        $db = db_connect();
        if (!$user->active || $user->isBanned()
            || !(new SessionVersion($db))->isCurrent($id)
            || (int) session()->get('iedpu_mfa_verified_user') !== $id
            || $db->table('portal_totp_factors')->where('user_id', $id)->countAllResults() < 1) {
            $auth->logout();
            SessionVersion::clearLocal();
            return redirect()->to(site_url('sign-in'));
        }

        $policy = new ScopeAuthorizer($db);
        $canRequest = $policy->allows($id, 'users.invite', 'global');
        $canApprove = $policy->allows($id, 'approvals.decide', 'global');
        $canView = $policy->allows($id, 'approvals.view', 'global');
        if (!$canRequest && !$canApprove && !$canView) {
            return $this->response->setStatusCode(403)->setBody('Your account does not have invitation workflow access.');
        }

        return $this->response->setBody(view('admin/invitation_workspace', [
            'userId' => $id,
            'canRequest' => $canRequest,
            'canApprove' => $canApprove,
        ]));
    }
}
