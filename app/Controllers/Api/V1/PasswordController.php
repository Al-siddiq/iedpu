<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Libraries\Api\ApiInput;
use App\Libraries\Api\ApiResponder;
use App\Libraries\Auth\PasswordChange;
use App\Libraries\Auth\ScopeAuthorizer;
use App\Libraries\Auth\SessionVersion;
use App\Libraries\Auth\VerifiedIdentifierResolver;
use App\Libraries\Security\SecurityEventWriter;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Explicit self-service password change. No unauthenticated reset,
 * arbitrary user target, privilege grant, or token-based bypass.
 */
final class PasswordController extends BaseController
{
    public function change(): ResponseInterface
    {
        $auth = auth('session');
        $user = $auth->user();
        if ($user === null) {
            return $this->error(401, 'unauthenticated', 'Sign in to continue.');
        }
        $id = (int) $user->id;
        try {
            $db = db_connect();
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
                return $this->error(401, 'reauthentication_required', 'Sign in using your authenticator to continue.');
            }
            $throttler = service('throttler');
            if (!$throttler->check('iedpu-password-change-' . hash('sha256', (string) $id), 5, 900)) {
                return $this->error(429, 'rate_limited', 'Too many attempts. Try again later.');
            }
            $input = ApiInput::jsonObject($this->request, 4096);
            if (!$input['ok']) {
                return ApiResponder::error($this->request, $this->response, 422, 'invalid_request', 'Invalid request.', $input['errors']);
            }
            $errors = ApiInput::requiredTextFields($input['data'], [
                'current_password' => 1024,
                'new_password' => 72,
                'confirm_password' => 72,
            ]);
            if ($errors !== []) {
                return ApiResponder::error($this->request, $this->response, 422, 'invalid_request', 'Invalid request.', $errors);
            }
            if (!hash_equals($input['data']['new_password'], $input['data']['confirm_password'])) {
                return $this->error(422, 'password_mismatch', 'The new passwords do not match.');
            }
            $result = (new PasswordChange($db))->change(
                $id,
                $input['data']['current_password'],
                $input['data']['new_password']
            );
            if ($result === 'incorrect') {
                try {
                    (new SecurityEventWriter($db))->record('auth.password_change_denied', 'medium', $id);
                } catch (Throwable) {
                    // A denied attempt never returns telemetry details.
                }
                return $this->error(401, 'incorrect_password', 'The current password is incorrect.');
            }
            if ($result === 'weak') {
                return $this->error(422, 'weak_password', 'Use 12–128 characters, including letters and numbers, with no control characters.');
            }
            if ($result === 'same') {
                return $this->error(422, 'password_reused', 'Choose a different password.');
            }
            if ($result !== 'changed') {
                return $this->error(503, 'change_unavailable', 'Password change is temporarily unavailable.');
            }

            // The transaction already invalidated every browser's session
            // version. Clear the current Shield session as well.
            $auth->logout();
            SessionVersion::clearLocal();
            return ApiResponder::success($this->request, $this->response, [
                'password_changed' => true,
                'sessions_revoked' => true,
                'sign_in_required' => true,
            ]);
        } catch (Throwable $exception) {
            log_message('error', 'IEDPU_PASSWORD_CHANGE exception_class={class}', ['class' => get_class($exception)]);
            return $this->error(503, 'change_unavailable', 'Password change is temporarily unavailable.');
        }
    }

    private function error(int $status, string $code, string $message): ResponseInterface
    {
        return ApiResponder::error($this->request, $this->response, $status, $code, $message);
    }
}
