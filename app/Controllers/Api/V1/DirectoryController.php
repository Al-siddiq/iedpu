<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Libraries\Api\ApiResponder;
use App\Libraries\Auth\ScopeAuthorizer;
use App\Libraries\Auth\SessionVersion;
use App\Libraries\Auth\VerifiedIdentifierResolver;
use App\Libraries\Security\AuditTrail;
use App\Libraries\Security\RequestId;
use App\Libraries\Security\SecurityEventWriter;
use App\Modules\Administration\ScopedDirectory;
use CodeIgniter\HTTP\ResponseInterface;
use InvalidArgumentException;
use Throwable;

/** WP03 protected read-only directory; NO branch/role/office mutations. */
final class DirectoryController extends BaseController
{
    public function show(string $kind): ResponseInterface
    {
        if (!in_array($kind, ['zones', 'branches', 'offices', 'roles', 'permissions'], true)) {
            return ApiResponder::error($this->request, $this->response, 404, 'not_found', 'Unknown directory.');
        }
        $auth = auth('session');
        $user = $auth->user();
        if ($user === null) {
            return ApiResponder::error($this->request, $this->response, 401, 'unauthenticated', 'Sign in to continue.');
        }

        $page = filter_var($this->request->getGet('page') ?? '1', FILTER_VALIDATE_INT);
        $perPage = filter_var($this->request->getGet('per_page') ?? '20', FILTER_VALIDATE_INT);
        if ($page === false || $page < 1 || $page > 200
            || $perPage === false || $perPage < 1 || $perPage > 50) {
            return ApiResponder::error($this->request, $this->response, 422, 'invalid_page', 'Use page 1–200 and per_page 1–50.');
        }
        try {
            $db = db_connect();
            $id = (int) $user->id;
            $fresh = $db->table('users')->select('active')->where('id', $id)->get()->getRowArray();
            $policy = new ScopeAuthorizer($db);
            if (!(new SessionVersion($db))->isCurrent($id)
                || $fresh === null || (int) $fresh['active'] !== 1 || $user->isBanned()
                || !(new VerifiedIdentifierResolver($db))->hasVerifiedIdentifier($id)
                || (int) session()->get('iedpu_mfa_verified_user') !== $id
                || !$policy->requiresPendingMfa($id)) {
                $auth->logout();
                SessionVersion::clearLocal();
                return ApiResponder::error($this->request, $this->response, 401, 'unauthenticated', 'Sign in with your authenticator to continue.');
            }
            $directory = new ScopedDirectory($db);
            if (!$directory->canView($kind, $id)) {
                try {
                    (new SecurityEventWriter($db))->record('admin.directory_denied', 'medium', $id);
                } catch (Throwable) {
                    // Never disclose security telemetry details.
                }
                return ApiResponder::error($this->request, $this->response, 403, 'forbidden', 'You do not have permission to view this directory.');
            }
            $result = $directory->page($kind, $id, $page, $perPage);
            // Read access is audited before any potentially sensitive role
            // catalogue response is returned. Fail closed on audit failure.
            (new AuditTrail($db))->append([
                'action_key' => 'admin.directory_view',
                'module_code' => 'administration',
                'event_category' => 'data_access',
                'outcome' => 'success',
                'actor_user_id' => $id,
                'permission_key' => $directory->permissionFor($kind),
                'request_id' => RequestId::current(),
            ]);
            return ApiResponder::success($this->request, $this->response, [
                'directory' => $kind,
                'items' => $result['items'],
                'page' => $result['page'],
                'per_page' => $result['per_page'],
                'total' => $result['total'],
                'read_only' => true,
            ]);
        } catch (InvalidArgumentException) {
            return ApiResponder::error($this->request, $this->response, 404, 'not_found', 'Unknown directory.');
        } catch (Throwable $exception) {
            log_message('error', 'IEDPU_WP03_DIRECTORY error_class={class} request_id={request_id}', [
                'class' => get_class($exception),
                'request_id' => RequestId::current(),
            ]);
            return ApiResponder::error($this->request, $this->response, 503, 'directory_unavailable', 'The directory is temporarily unavailable.');
        }
    }
}
