<?php
declare(strict_types=1);

namespace App\Controllers\Api\V1;

use App\Controllers\BaseController;
use App\Libraries\Api\ApiInput;
use App\Libraries\Api\ApiResponder;
use App\Libraries\Auth\TotpFactor;
use App\Libraries\Auth\SessionVersion;
use App\Libraries\Auth\VerifiedIdentifierResolver;
use App\Libraries\Security\AuditTrail;
use App\Libraries\Security\RequestId;
use App\Libraries\Security\SecurityEventWriter;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Password-verified, session-bound MFA enrolment and challenge.
 * No Shield authenticated session exists until TOTP/recovery verification.
 */
final class MfaController extends BaseController
{
    /** @return array{user_id:int,created_at:int,mode:string}|null */
    private function pending(): ?array
    {
        if (auth('session')->loggedIn()) {
            return null;
        }
        $pending = session()->get('iedpu_mfa_pending');
        if (!is_array($pending) || !isset($pending['user_id'], $pending['created_at'], $pending['mode'])
            || !is_int($pending['user_id']) || $pending['user_id'] < 1
            || !is_int($pending['created_at']) || $pending['created_at'] > time()
            || time() - $pending['created_at'] > ($pending['mode'] === 'enrol' ? 900 : 300)
            || !in_array($pending['mode'], ['enrol','challenge'], true)) {
            session()->remove('iedpu_mfa_pending');
            return null;
        }
        return $pending;
    }

    public function setup(): ResponseInterface
    {
        $pending = $this->pending();
        if ($pending === null || $pending['mode'] !== 'enrol') {
            return $this->error(401, 'mfa_session_expired', 'Sign in again to configure your authenticator.');
        }
        try {
            $factor = new TotpFactor(db_connect());
            if ($factor->hasFactor($pending['user_id'])) {
                session()->remove('iedpu_mfa_pending');
                return $this->error(409, 'mfa_already_configured', 'Authenticator is already configured. Sign in again.');
            }
            $state = session()->get('iedpu_mfa_pending');
            if (!isset($state['secret_ciphertext'])) {
                $secret = $factor->generateSecret();
                $state['secret_ciphertext'] = $factor->encryptSecret($secret);
                session()->set('iedpu_mfa_pending', $state);
            } else {
                $secret = $factor->decryptSecret((string) $state['secret_ciphertext']);
            }
            return ApiResponder::success($this->request, $this->response, [
                'manual_key' => $secret,
                'otpauth_uri' => $factor->provisioningUri($pending['user_id'], $secret),
                'instructions' => 'Open a compatible authenticator app, add an account using this setup key or QR, then enter its six-digit code. Keep recovery codes offline.',
                'expires_in_seconds' => max(0, 900 - (time() - $pending['created_at'])),
            ]);
        } catch (Throwable) {
            return $this->error(503, 'mfa_unavailable', 'Authenticator setup is temporarily unavailable.');
        }
    }

    public function verify(): ResponseInterface
    {
        $pending = $this->pending();
        if ($pending === null) {
            return $this->error(401, 'mfa_session_expired', 'Sign in again to continue.');
        }
        $input = ApiInput::jsonObject($this->request, 1024);
        if (!$input['ok']) {
            return ApiResponder::error($this->request, $this->response, 422, 'invalid_request', 'Invalid request.', $input['errors']);
        }
        $errors = ApiInput::requiredTextFields($input['data'], ['code' => 40]);
        if ($errors !== []) {
            return ApiResponder::error($this->request, $this->response, 422, 'invalid_request', 'Enter your authenticator code or a recovery code.', $errors);
        }
        $code = trim($input['data']['code']);
        try {
            $throttler = service('throttler');
            $key = hash('sha256', $pending['user_id'] . ':' . session_id());
            if (!$throttler->check('iedpu-mfa-session-' . $key, 6, 300)
                || !$throttler->check('iedpu-mfa-user-' . hash('sha256', (string) $pending['user_id']), 15, 600)) {
                return $this->error(429, 'rate_limited', 'Too many attempts. Sign in again later.');
            }
            $db = db_connect();
            $factor = new TotpFactor($db);
            $codes = [];
            $recoveryUsed = false;
            if ($pending['mode'] === 'enrol') {
                $state = session()->get('iedpu_mfa_pending');
                if (!isset($state['secret_ciphertext']) || !is_string($state['secret_ciphertext'])) {
                    return $this->error(409, 'setup_required', 'Open the authenticator setup screen first.');
                }
                $secret = $factor->decryptSecret($state['secret_ciphertext']);
                $step = $factor->matchStep($secret, $code);
                if ($step === null) {
                    $this->recordDenied($pending['user_id']);
                    return $this->error(401, 'invalid_mfa_code', 'The code is incorrect or expired.');
                }
                $codes = $factor->enrol($pending['user_id'], $secret, $step);
            } else {
                $recoveryUsed = preg_match('/\A[0-9a-fA-F-]{32,39}\z/D', $code) === 1;
                $verified = $recoveryUsed
                    ? $factor->consumeRecoveryCode($pending['user_id'], $code)
                    : $factor->consumeTotp($pending['user_id'], $code);
                if (!$verified) {
                    $this->recordDenied($pending['user_id']);
                    return $this->error(401, 'invalid_mfa_code', 'The code is incorrect, expired or already used.');
                }
            }
            $users = model(config('Auth')->userProvider);
            $user = $users->findById($pending['user_id']);
            if ($user === null || !$user->active || $user->isBanned()
                || !(new VerifiedIdentifierResolver($db))->hasVerifiedIdentifier($pending['user_id'])) {
                session()->remove('iedpu_mfa_pending');
                return $this->error(401, 'unauthenticated', 'Sign in again.');
            }
            auth('session')->login($user);
            (new SessionVersion($db))->stamp($pending['user_id']);
            session()->set('iedpu_mfa_verified_user', $pending['user_id']);
            session()->remove('iedpu_mfa_pending');
            (new AuditTrail($db))->append([
                'action_key' => $recoveryUsed ? 'auth.mfa_recovery_login'
                    : ($codes !== [] ? 'auth.mfa_enrolled' : 'auth.mfa_verified'),
                'module_code' => 'security',
                'event_category' => 'security',
                'outcome' => 'success',
                'actor_user_id' => $pending['user_id'],
                'request_id' => RequestId::current(),
            ]);
            return ApiResponder::success($this->request, $this->response, [
                'authenticated' => true,
                'mfa_verified' => true,
                'administration_available' => false,
                'recovery_code_used' => $recoveryUsed,
                'new_recovery_codes' => $codes,
                'recovery_notice' => $codes === [] ? null : 'Save these one-time recovery codes offline now. They will never be shown again.',
            ]);
        } catch (Throwable) {
            auth('session')->logout();
            SessionVersion::clearLocal();
            return $this->error(503, 'mfa_unavailable', 'Authenticator verification is temporarily unavailable. Sign in again.');
        }
    }

    private function recordDenied(int $userId): void
    {
        try {
            (new SecurityEventWriter(db_connect()))->record('auth.mfa_denied', 'medium', $userId);
        } catch (Throwable) {
            // Telemetry is best effort; no sensitive input is logged.
        }
    }

    private function error(int $status, string $code, string $message): ResponseInterface
    {
        return ApiResponder::error($this->request, $this->response, $status, $code, $message);
    }
}
