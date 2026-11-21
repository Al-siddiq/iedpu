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
            if (!$user->active || $user->isBanned() || !$resolver->hasVerifiedIdentifier((int) $user->id)
                || $policy->requiresPendingMfa((int) $user->id)) {
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
            'mfa_available' => false,
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

        $failure = static fn (self $controller): ResponseInterface => ApiResponder::error(
            $controller->request, $controller->response, 401, 'invalid_credentials',
            'The supplied credentials could not be verified.'
        );
        try {
            $db = db_connect();
            $resolver = new VerifiedIdentifierResolver($db);
            $resolved = $resolver->resolve($identifier);
            if ($resolved === null) {
                // Run a constant-cost hash verification on the unknown-account path.
                password_verify($password, '$2y$12$wJkJzM5fHGkq7Sg8O8i97e5ZHXjQ30P3w9Tn9GhKp6gE6vFVFZpMG');
                return $failure($this);
            }
            $policy = new ScopeAuthorizer($db);
            if ($policy->requiresPendingMfa($resolved['user_id'])) {
                // Privileged login remains blocked until real MFA is approved.
                return $failure($this);
            }
            $result = $auth->attempt(['email' => $resolved['shield_secret'], 'password' => $password]);
            if (!$result->isOK()) {
                return $failure($this);
            }
            $user = $auth->user();
            if ($user === null || (int) $user->id !== $resolved['user_id']
                || !$user->active || $user->isBanned()) {
                $auth->logout();
                return $failure($this);
            }

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
        return ApiResponder::success($this->request, $this->response, ['authenticated' => false]);
    }
}
