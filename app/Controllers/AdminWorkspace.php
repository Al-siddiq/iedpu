<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Libraries\Auth\ScopeAuthorizer;
use App\Libraries\Auth\SessionVersion;
use App\Libraries\Auth\VerifiedIdentifierResolver;
use App\Libraries\Security\AuditTrail;
use App\Libraries\Security\RequestId;
use App\Modules\Administration\ScopedDirectory;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/** WP03 read-only administrative workspace, never a public preview. */
final class AdminWorkspace extends BaseController
{
    public function index(): ResponseInterface
    {
        $this->response->setHeader('Cache-Control', 'no-store, private')
            ->setHeader('X-Content-Type-Options', 'nosniff')
            ->setHeader('Content-Security-Policy', "default-src 'none'; script-src 'self'; style-src 'self'; img-src 'self'; connect-src 'self'; form-action 'self'; base-uri 'self'; frame-ancestors 'none'");
        $auth = auth('session');
        $user = $auth->user();
        if ($user === null) {
            return redirect()->to(base_url('sign-in'));
        }
        try {
            $db = db_connect();
            $id = (int) $user->id;
            $fresh = $db->table('users')->select('active')->where('id', $id)->get()->getRowArray();
            $policy = new ScopeAuthorizer($db);
            if (!(new SessionVersion($db))->isCurrent($id)
                || $fresh === null || (int) $fresh['active'] !== 1 || $user->isBanned()
                || !(new VerifiedIdentifierResolver($db))->hasVerifiedIdentifier($id)
                || !$policy->requiresPendingMfa($id)
                || (int) session()->get('iedpu_mfa_verified_user') !== $id) {
                $auth->logout();
                SessionVersion::clearLocal();
                return $this->response->setStatusCode(401)->setBody('Your authenticated administrator session has expired. Sign in again.');
            }
            $directory = new ScopedDirectory($db);
            $allowed = [];
            foreach (['zones', 'branches', 'offices', 'roles', 'permissions'] as $kind) {
                if ($directory->canView($kind, $id)) {
                    $allowed[] = $kind;
                }
            }
            if ($allowed === []) {
                return $this->response->setStatusCode(403)->setBody('You are not authorised to access this workspace.');
            }
            (new AuditTrail($db))->append([
                'action_key' => 'admin.workspace_view',
                'module_code' => 'administration',
                'event_category' => 'data_access',
                'outcome' => 'success',
                'actor_user_id' => $id,
                'request_id' => RequestId::current(),
            ]);
            return $this->response->setBody(view('admin/directory', [
                'allowedDirectories' => $allowed,
            ]));
        } catch (Throwable $exception) {
            log_message('error', 'IEDPU_WP03_WORKSPACE error_class={class} request_id={request_id}', [
                'class' => get_class($exception),
                'request_id' => RequestId::current(),
            ]);
            return $this->response->setStatusCode(503)->setBody('Administrative workspace temporarily unavailable.');
        }
    }
}
