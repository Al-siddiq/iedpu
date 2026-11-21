<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Libraries\Api\ApiInput;
use App\Libraries\Api\ApiResponder;
use App\Libraries\Auth\LoginIdentifier;
use App\Libraries\Auth\ScopeAuthorizer;
use App\Libraries\Auth\VerifiedIdentifierResolver;
use App\Libraries\Security\AuditTrail;
use App\Libraries\Security\RequestId;
use App\Libraries\Security\SecurityEventWriter;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * P2-WP02 initial, non-privileged session boundary.
 * No self-registration, account creation, recovery, role grants or MFA bypass.
 */
final class AuthController extends BaseController
{
    public function csrf(): ResponseInterface
    {
        helper('form');
        return ApiResponder::success($this->request, $this->response, [
            'header' => csrf_header(),
            'token' => csrf_hash(),
        ]);
    }

    public function session(): ResponseInterface
    {
        $auth = auth('session');
        $user = $auth->user();
        if ($user === null) {
            return ApiResponder::error($this->request, $this->response, 401, 'unauthenticated', 'Authentication required.');
        }
        try {
            $db = db_connect();
            $resolver = new VerifiedIdentifierResolver($db);
            $policy = new ScopeAuthorizer($db);
            $userId = (int) $user->id;
            $hasFactor = $db->table('portal_totp_factors')->where('user_id', $userId)->countAllResults() > 0;
            $requiresMfa = $policy->requiresPendingMfa($userId) || $hasFactor;
            if (!$user->active || $user->isBanned() || !$resolver->hasVerifiedIdentifier($userId)
                || ($requiresMfa && (int) session()->get('iedpu_mfa_verified_user') !== $userId)) {
                $auth->logout();
                return ApiResponder::error($this->request, $this->response, 401, 'unauthenticated', 'Authentication required.');
            }
        } catch (Throwable) {
            $auth->logout();
            return ApiResponder::error($this->request, $this->response, 503, 'auth_unavailable', 'Authentication is temporarily unavailable.');
        }
        return ApiResponder::success($this->request, $this->response, [
            'authenticated' => true,
            'privileged_access' => false,
            'mfa_verified' => $requiresMfa,
            'administration_available' => false,
        ]);
    }

    public function login(): ResponseInterface
    {
        $auth = auth('session');
        if ($auth->loggedIn()) {
            return ApiResponder::error($this->request, $this->response, 409, 'already_authenticated', 'Sign out before starting another session.');
        }
        $input = ApiInput::jsonObject($this->request, 4096);
        if (!$input['ok']) {
            return ApiResponder::error($this->request, $this->response, 422, 'invalid_request', 'Invalid request.', $input['errors']);
        }
        $errors = ApiInput::requiredTextFields($input['data'], ['identifier' => 190, 'password' => 1024]);
        if ($errors !== []) {
            return ApiResponder::error($this->request, $this->response, 422, 'invalid_request', 'Invalid request.', $errors);
        }
        $identifier = trim($input['data']['identifier']);
        $password = $input['data']['password'];
        try {
            $canonical = LoginIdentifier::normalise($identifier);
            $rateKey = $canonical['type'] . ':' . $canonical['value'];
        } catch (Throwable) {
            $rateKey = 'invalid:' . $identifier;
        }

        // Both network and identifier buckets; never persist raw identifiers in keys.
        try {
            $throttler = service('throttler');
            $ipKey = hash('sha256', $this->request->getIPAddress());
            if (!$throttler->check('iedpu-auth-ip-' . $ipKey, 20, 60)
                || !$throttler->check('iedpu-auth-id-' . hash('sha256', $rateKey), 6, 300)) {
                return ApiResponder::error($this->request, $this->response, 429, 'rate_limited', 'Too many attempts. Try again later.');
            }
        } catch (Throwable) {
            return ApiResponder::error($this->request, $this->response, 503, 'auth_unavailable', 'Authentication is temporarily unavailable.');
        }

        $failure = static function (self $controller): ResponseInterface {
            try {
                (new SecurityEventWriter(db_connect()))->record('auth.login_denied');
            } catch (Throwable) {
                // Security telemetry is best-effort on denied attempts.
            }
            return ApiResponder::error($controller->request, $controller->response,
                401, 'invalid_credentials', 'The supplied credentials could not be verified.');
        };
        try {
            $db = db_connect();
            $resolver = new VerifiedIdentifierResolver($db);
            $resolved = $resolver->resolve($identifier);
            if ($resolved === null) {
                // Run a constant-cost hash verification on the unknown-account path.
                password_verify($password, '$2y$12$EXhOo8gFQXW..a4NsOZfZOvPlGsfn6CFkOdokoAo8mRPfltDBQCSi');
                return $failure($this);
            }
            // Verify the Shield password BEFORE determining the second factor.
            // This avoids revealing whether an identifier belongs to an officer.
            // Shield::check does not log the submitted raw identifier.

            $result = $auth->check(['email' => $resolved['shield_secret'], 'password' => $password]);
            if (!$result->isOK()) {
                return $failure($this);
            }
            $user = $result->extraInfo();
            if ($user === null || (int) $user->id !== $resolved['user_id']
                || !$user->active || $user->isBanned()) {
                return $failure($this);
            }
            $policy = new ScopeAuthorizer($db);
            $hasFactor = $db->table('portal_totp_factors')->where('user_id', (int) $user->id)->countAllResults() > 0;
            if ($policy->requiresPendingMfa((int) $user->id) || $hasFactor) {
                // Password verification is NOT an authenticated Shield session.
                // Bind a short-lived challenge to this server-side session only.
                new \App\Libraries\Auth\TotpFactor($db); // fail closed if MFA key is missing
                session()->regenerate(true);
                session()->remove('iedpu_mfa_verified_user');
                session()->set('iedpu_mfa_pending', [
                    'user_id' => (int) $user->id,
                    'created_at' => time(),
                    'mode' => $hasFactor ? 'challenge' : 'enrol',
                ]);
                return ApiResponder::success($this->request, $this->response, [
                    'authenticated' => false,
                    'mfa_required' => true,
                    'enrolment_required' => !$hasFactor,
                    'next' => $hasFactor ? 'auth/mfa/verify' : 'auth/mfa/setup',
                ], 202);
            }
            // Shield's supported session login method rotates the session ID.
            $auth->login($user);

            // Audit must be durable before an authenticated session is returned.
            (new AuditTrail($db))->append([
                'action_key' => 'auth.login',
                'module_code' => 'security',
                'event_category' => 'security',
                'outcome' => 'success',
                'actor_user_id' => (int) $user->id,
                'request_id' => RequestId::current(),
            ]);
            return ApiResponder::success($this->request, $this->response, [
                'authenticated' => true,
                'privileged_access' => false,
                'mfa_available' => false,
            ]);
        } catch (Throwable) {
            $auth->logout();
            return ApiResponder::error($this->request, $this->response, 503, 'auth_unavailable', 'Authentication is temporarily unavailable.');
        }
    }

    public function logout(): ResponseInterface
    {
        $auth = auth('session');
        $user = $auth->user();
        if ($user !== null) {
            try {
                (new AuditTrail(db_connect()))->append([
                    'action_key' => 'auth.logout',
                    'module_code' => 'security',
                    'event_category' => 'security',
                    'outcome' => 'success',
                    'actor_user_id' => (int) $user->id,
                    'request_id' => RequestId::current(),
                ]);
            } catch (Throwable) {
                // Logout must remain available during audit outages.
            }
        }
        $auth->logout();
        session()->remove('iedpu_mfa_pending');
        session()->remove('iedpu_mfa_verified_user');
        return ApiResponder::success($this->request, $this->response, ['authenticated' => false]);
    }
}
