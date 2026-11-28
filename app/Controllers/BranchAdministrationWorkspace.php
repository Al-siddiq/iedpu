<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Auth\ScopeAuthorizer;
use App\Libraries\Auth\SessionVersion;
use App\Libraries\Auth\VerifiedIdentifierResolver;
use CodeIgniter\HTTP\ResponseInterface;

final class BranchAdministrationWorkspace extends BaseController
{
    public function index(): ResponseInterface
    {
        $this->response->setHeader('Cache-Control','no-store, private, max-age=0')
            ->setHeader('X-Content-Type-Options','nosniff')
            ->setHeader('Referrer-Policy','no-referrer')
            ->setHeader('Content-Security-Policy',"default-src 'none'; script-src 'self'; style-src 'self'; connect-src 'self'; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");
        $auth=auth('session');$user=$auth->user();
        if(!$user)return redirect()->to(site_url('sign-in'));
        $id=(int)$user->id;$db=db_connect();
        if(!$user->active||$user->isBanned()||!(new SessionVersion($db))->isCurrent($id)
            ||!(new VerifiedIdentifierResolver($db))->hasVerifiedIdentifier($id)
            ||(int)session()->get('iedpu_mfa_verified_user')!==$id
            ||!(new ScopeAuthorizer($db))->requiresPendingMfa($id)){
            return $this->response->setStatusCode(401)->setBody('Please sign in again and complete authenticator verification.');
        }
        if(!(new ScopeAuthorizer($db))->allows($id,'branches.manage','global')){
            return $this->response->setStatusCode(403)->setBody('Your account does not have branch management permission.');
        }
        return $this->response->setBody(view('admin/branch_management'));
    }
}
