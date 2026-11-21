# P2-WP02 — Authentication, Scoped Authorisation, Governance and Audit

**Status:** SECURITY FOUNDATION COMMITTED; **NOT YET ACCEPTED**. No live privileged account, invitation, privileged account recovery, approval execution, or administrative CRUD. Authenticator-app TOTP enrolment and challenge are implemented but await owner manual acceptance. P2-WP01 and DB-10 accepted by owner. PR #3 remains draft.

## Source of truth
P2 Strategic Implementation Plan §5 A–E, owner P2-AUTH-01, P0 Engineering Decision Register, Database Schema Design §§5–9 and 24–26, and locked Shield 1.4.1 / CI4 4.7.6. The constitution reader remains read-only and unaffected.

## Delivered in this increment

1. `Config/Auth.php`: Shield session authenticator only; self-registration, magic links, remember-me, token/HMAC chains disabled. Shield's default routes are NOT mounted. `Config/AuthGroups.php` removes all starter superadmin/admin/developer groups and starter permissions; portal roles and permissions remain separate DB grants.
2. `Config/Filters.php`: global framework CSRF protection for all mutations; page cache removed from required filters; `ApiCsrf` returns JSON 403 on API CSRF failure. Production cookie configuration already has Secure/HttpOnly/SameSite Lax; loopback development exception remains. Do not use HTTP for staging/production.
3. `GET /api/v1/auth/csrf`, `GET /api/v1/auth/session`, `POST /api/v1/auth/login`, `POST /api/v1/auth/logout`. Every other `/api/v1/auth/*` path remains disabled. Public registration and all administrative routes remain disabled. All API responses use server-generated request ID; clients cannot set audit correlation.
4. `VerifiedIdentifierResolver` accepts only `portal_login_identifiers` entries that are active, verified, unrevoked, and linked to active Shield users. Both verified email and E.164 phone can resolve to the same Shield account. Shield's own `email_password` identity remains the single password store; its `secret` may contain the actual verified phone for phone-only accounts, **never a fabricated email**. This was checked against locked Shield 1.4.1's `UserModel::findByCredentials`, `Session::check`, `Session::login`, and `UserIdentityModel::createEmailIdentity`. The identity secret must itself remain verified and active.
5. Login uses Shield `check` and `login`, not a custom password verifier or credential table. It deliberately avoids Shield `attempt` because that method stores raw email/phone in `auth_logins`; failed attempts are recorded as minimised `security_events` instead. Per-IP and per-canonical-identifier throttling; generic invalid-credentials response. Login success requires an append-only audit event; audit failure logs the session out and returns 503.
6. `ScopeAuthorizer`: denies by default, matches the exact permission AND scope within a single active role assignment, checks role/permission state, effective dates, actual branch parent, active project zone, active user. A global grant does not authorise an out-of-project zone. No caller may combine permission from one assignment with scope from another. It is not yet connected to administrative endpoints because those endpoints remain disabled.
7. **P2-AUTH-02 IMPLEMENTED; NOT YET ACCEPTED:** all users with any active role assignment must complete password-verified TOTP enrolment/challenge before Shield login; existing factors also require TOTP. Enrolment includes a local QR code and ten one-use recovery codes. Missing MFA encryption key fails closed. All administrative endpoints remain 403. See [approved MFA decision and local acceptance runbook](P2_AUTH02_TOTP_Decision_and_Acceptance.md).
8. `AuditTrail`: server-generated UUID, strict event allowlist, serialised MySQL named-lock hash-chain append, transaction, UTC timestamps; no arbitrary payload, secrets or tokens. `SecurityEventWriter` records minimised denied login telemetry. `iedpu:p2-audit-verify` checks hash links and current DB event contents. **Not yet a complete business-mutation audit transaction wrapper**; do not use for privileged business writes until the mutation+audit transaction boundary is integrated. External immutable checkpoints are WP04.
9. `public/assets/js/core/auth-client.js`: same-origin Fetch client, session cookies, CSRF header, no local persistence, no auto replay of mutating requests. UI sign-in is a later integration; no false “login available” banner in public bootstrap.
10. `iedpu:p2-auth-fixture`: optional, **strictly development-only** synthetic phone-only Shield account in a local disposable database named `iedpu_dev*`, `iedpu_test*`, `iedpu_local*` or `iedpu_disposable*`. Requires `IEDPU_ALLOW_SYNTHETIC_AUTH=1` and explicit `--confirm-synthetic`. It uses a non-deliverable synthetic number and prints a random temporary password locally. **Never run on real or staging data, never send SMS to the synthetic number, and destroy the fixture DB after testing.** No real user, role or branch is seeded.

## Route and security matrix

| Method | Route | Authentication | Scope / effect | Status |
|---|---|---|---|---|
| GET | /api/v1/auth/csrf | Anonymous | Create/read session CSRF token, no-store | Enabled |
| POST | /api/v1/auth/login | Anonymous + CSRF | Verified identifier + Shield password, rate limit; unprivileged only | Enabled for eligible pre-existing verified accounts |
| GET | /api/v1/auth/session | Cookie session | Revalidate active account, identifier, MFA hold; no-store | Enabled |
| POST | /api/v1/auth/logout | Cookie + CSRF | Clear current Shield session | Enabled |
| GET | /api/v1/auth/mfa/setup | Password-verified pending session | QR/manual secret setup, no Shield login | Enabled for unenrolled privileged users |
| POST | /api/v1/auth/mfa/verify | Password-verified pending session + CSRF | One-time TOTP/recovery verification; then Shield session | Enabled; manual acceptance pending |
| GET | /sign-in | Public | Accessible sign-in and authenticator setup UI, noindex | Enabled for testing |
| ALL | /api/v1/auth/* other | None | Disabled; no registration, reset, activation, MFA bypass | 403 |
| ALL | /api/v1/administration/* | None | Disabled; no grants or CRUD | 403 |
| ALL | /api/v1/technical/* | None | Disabled; no diagnostics or privileged access | 403 |
| ALL | /api/v1/registration, members, finance, payments etc. | None | Disabled per P1 gate | 403 |

## CI verification (source-level only)

GitHub Actions workflow `.github/workflows/php-lint.yml` runs `php -l` on all `app/` and `scripts/` PHP files and the standalone login-identifier smoke script. The run for commit `260d937c053b24f2f27eb8c2bfa1b21a36cc40f0` completed successfully (run 44891508251). This **does not** validate Shield runtime, MySQL, CSRF integration, session cookies, synthetic provisioning, privilege enforcement or any local/staging deployment. Those remain operator manual gates.

## Local manual acceptance — do not claim success before execution

1. Check out the P2 branch and pull latest; run `C:\xampp\php\php.exe -l` on all new/modified PHP files (listed below). Run `spark routes` and confirm no Shield default routes.
2. GET `/api/v1/auth/csrf` must return 200 with a session cookie and JSON `data.header`/`data.token`; GET `/api/v1/auth/session` before login must return JSON 401.
3. POST login with no CSRF must return JSON 403; malformed/extra fields 422; unknown account/bad password 401; burst attempts 429. Check that no raw identifier/password is logged in application security_events; verify no unauthorised token auth.
4. **Only on a disposable local DB** enable the explicit synthetic fixture env flag, run `spark iedpu:p2-auth-fixture --confirm-synthetic`, retain the generated password locally, and test a phone-only login via the CSRF-aware browser client. Test `08000000000` and `+2348000000000` equivalence. Confirm Shield session cookie, authenticated session JSON, logout and re-login.
5. Insert a *synthetic* active role assignment via carefully reviewed disposable fixtures and confirm login is refused pending MFA; do not use live officer records.
6. Verify denied role, wrong zone/branch, cross-branch mismatch, inactive zone, expired grant, permission from separate assignment all return false from ScopeAuthorizer. Do not expose business endpoints just to test these conditions.
7. Run `spark iedpu:p2-audit-verify` after successful login/logout; hash-chain verification should pass. Simulate audit DB outage only in disposable DB and confirm login fails closed. No claim of immutable storage or cross-device revocation.
8. Repeat existing P1 health/bootstrap 200, disabled operations 403, unknown API 404; test constitution reader and offline public shell unchanged.
9. Test CSRF rotation after login; the frontend must fetch a new token, never auto replay a POST. Inspect cookies in browser: HttpOnly, SameSite=Lax, Secure on HTTPS staging; no session or password in localStorage, IndexedDB, service worker cache.
10. Record PHP version, MySQL version, reviewer, date, status codes, and test results before marking this increment accepted.

Lint list:
```bat
C:\xampp\php\php.exe -l app\Config\Auth.php
C:\xampp\php\php.exe -l app\Config\AuthGroups.php
C:\xampp\php\php.exe -l app\Config\Filters.php
C:\xampp\php\php.exe -l app\Config\Routes.php
C:\xampp\php\php.exe -l app\Config\Security.php
C:\xampp\php\php.exe -l app\Controllers\Api\V1\AuthController.php
C:\xampp\php\php.exe -l app\Filters\ApiCsrf.php
C:\xampp\php\php.exe -l app\Libraries\Auth\VerifiedIdentifierResolver.php
C:\xampp\php\php.exe -l app\Libraries\Auth\ScopeAuthorizer.php
C:\xampp\php\php.exe -l app\Libraries\Security\AuditTrail.php
C:\xampp\php\php.exe -l app\Libraries\Security\SecurityEventWriter.php
C:\xampp\php\php.exe -l app\Libraries\Security\RequestId.php
C:\xampp\php\php.exe -l app\Commands\P2AuthFixture.php
C:\xampp\php\php.exe -l app\Commands\P2AuditVerify.php
```

## Quick Windows negative-path smoke

With XAMPP MySQL and Spark running in a separate CMD window, use:
```bat
cd C:\xampp\htdocs\iedpu
git pull --ff-only origin feature/p2-database-auth-governance
C:\xampp\php\php.exe scripts\test-p2-login-identifier.php
powershell -ExecutionPolicy Bypass -File scripts\test-p2-auth-smoke.ps1
C:\xampp\php\php.exe spark iedpu:p2-audit-verify
```
The PowerShell script checks health 200, CSRF 200, anonymous session 401, missing-CSRF login 403, unknown credentials 401, disabled administration 403 and unknown API 404. It does **not** create users or prove MFA/approval acceptance. The PHP identifier script runs without Composer dependencies.

## WP02 completion blockers / next engineering increments

- **Account lifecycle**: invitation/activation/recovery/password change with single-use hashed expiry-bound tokens, verified email/phone ownership, delivery adapters, safe resend, and session revocation across devices. Production SMS/email provider and assisted recovery policy not approved.
- **Privileged MFA**: authenticator-app TOTP setup, challenge, anti-replay and one-use recovery codes are implemented, pending manual acceptance. Independently authorised lost-device recovery, MFA reset/revocation and high-risk step-up remain disabled until approved.
- **Governance**: immutable bound maker-checker approval service, target-state concurrency, protected role edit/assignment/revocation, actor/target grant ceiling, self-elevation prevention, one-time audited bootstrap, and transactionally coupled business mutation+audit. Initial real Committee Super Administrator and technical operator remain HOLD-01.
- **Authorised APIs**: route guard integration, scoped query filtering and role/approval/technical operations, denial audit, and privacy-controlled audit retrieval. No live privileged CRUD yet.
- **Security hardening**: shared session revocation state, server-enforced idle/absolute expiry, robust session fixation testing, rate limiter storage in staging, full event taxonomy and external audit checkpoints in WP04.
- **Testing**: automated integration tests and full manual acceptance against actual local/staging infrastructure.

**Do not accept WP02 as complete based on this first increment.** Do not merge PR #3 or deploy real accounts until all WP02 exit gates have passed.

## Session revocation increment — committed, owner acceptance pending (21 November 2026)

The owner has accepted the **first read-only WP03 directory increment**. The implementation sequence has returned to an outstanding WP02 prerequisite: cross-device session invalidation. A new `portal_user_session_versions` table, `SessionVersion` service, Shield-login/MFA session stamping, per-request protected-session validation, and a local-only synthetic-account revocation command are committed.

The [two-browser manual acceptance runbook](P2_WP02_Session_Revocation_Acceptance.md) is the binding test for this increment. This change **does not** provide production administrator revocation, secure invitations, account recovery, independent approvals or protected role grants. Do not interpret a successful lint run as manual acceptance.
