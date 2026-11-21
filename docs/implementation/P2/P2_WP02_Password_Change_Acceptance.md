# P2-WP02 — Authenticated self-service password rotation

**Committed:** 21 November 2026. **Status: owner local manual acceptance PENDING.** This is one working account-management increment, **not** complete invitation/recovery/bootstrap/governance delivery. Session-expiry increment also remains owner-acceptance pending.

## Security boundary and delivered code

- `GET /account/security` displays a private, responsive password-change form **only** for a currently signed-in active user with a verified identifier, existing TOTP factor, successfully verified authenticator in the current session, and current DB session version and expiry. The synthetic zero-permission MFA account may access its own account-security page; it cannot access administration.
- `POST /api/v1/auth/password/change` is the only new mutation. It accepts **only** `current_password`, `new_password`, `confirm_password`; requires the same verified active MFA session, global CSRF and per-account throttling. It cannot change anyone else's account.
- Requires the correct current password; new password must differ, be 12–128 bytes, include letters and numbers, and contain no ASCII control characters. A full password-policy/compromised-password screening decision remains a later hardening task.
- Under a DB transaction and locked Shield password identity row: verify the current hash, replace **the single Shield `auth_identities.secret2` hash** with a PHP-compatible password hash, increment the account-wide session version, append a chained `auth.password_changed` audit event, and commit. A failed audit causes transaction rollback. No passwords or hashes appear in API responses, logs or audit events.
- On success, log out the current Shield session and return `password_changed=true`, `sessions_revoked=true` and `sign_in_required=true`. Every other browser's old session fails on its next protected request.
- No public password reset, email/SMS delivery, lost-device recovery, passwordless login, invitation, role grant, official office privilege, or admin account-change route is enabled. Users without an enrolled and verified authenticator cannot use this endpoint yet; their secure enrolment/recovery lifecycle is still a WP02 blocker.

## Manual acceptance on disposable local XAMPP

**Important:** This test **changes the synthetic fixture password**. You must know the current test password, choose a new unique test password, and retain it privately. Do not paste either password, an MFA key, recovery code, or a CSRF token into chat. Back up your **disposable** database before testing. Do not use real member or officer accounts.

1. Update:
   ```bat
   cd C:\xampp\htdocs\iedpu
   git switch feature/p2-database-auth-governance
   git pull --ff-only origin feature/p2-database-auth-governance
   ```
   No migration is required. If the earlier session-expiry test overrides remain in `.env`, remove them and restart Spark so the normal session policy applies.
2. Browser A: sign in as the existing synthetic phone-only account, complete Google Authenticator, then open `http://127.0.0.1:8080/account/security`. The page must load. Browser B: independently sign in with the same synthetic account and fresh authenticator code; `/api/v1/auth/session` should return HTTP 200.
3. Browser A: submit an incorrect current password with a valid new password and matching confirmation. Expect **401** and no password change. A mismatched confirmation must return **422**. Repeated incorrect attempts must eventually return **429**. Do not test high-volume throttling on a live environment.
4. Browser A: submit the **correct** current password, a different strong new test password (12–128 characters, letters and numbers), and matching confirmation. Expect success message and the form to disappear. Do **not** navigate away until you have privately recorded the new test password.
5. Browser B: refresh `/api/v1/auth/session`. Expect HTTP **401** because the password change invalidated all prior sessions. Browser A must also be logged out.
6. Attempt sign-in with the **old** password; expect **401**. Sign in using the **new** password and a fresh TOTP; expect the existing MFA challenge and then HTTP 200 on `/api/v1/auth/session`. The account must retain its same identifier and authenticator factor.
7. Anonymous browser: `/account/security` must redirect to sign-in; unauthenticated password change must return 401 with a valid CSRF token; missing-CSRF POST must return 403. The synthetic MFA-only role must still be denied the WP03 administrative workspace (403).
8. Run:
   ```bat
   php spark iedpu:p2-audit-verify
   ```
   Expect the hash chain to verify and a successful `auth.password_changed` audit event to exist. No secret should be logged. Public health/Constitution and the disabled administrative mutation routes must remain unchanged.
9. **Optional advanced failure-path test only on a separate disposable DB copy:** make the audit table unavailable in the copy, attempt a password change, and verify HTTP 503 plus that the original password still works and the new one does not. Restore the copy; do not damage your main local database.

## Governance gates and next implementation work

| Control | Current state |
|---|---|
| Verified email OR phone login and authenticator MFA | Implemented; prior local tests accepted |
| Cross-device account session revocation | Owner-accepted |
| Idle/absolute session expiry | Committed; owner confirmation of timing policy and test pending |
| MFA-verified self-service password rotation | **This increment; manual acceptance pending** |
| Secure invitation, possession verification and account activation | **Not enabled**; requires delivery-channel decision and token lifecycle |
| Password reset and privileged lost-device recovery | **Not enabled**; requires independent recovery authorisers and delivery policy |
| One-time independent Committee/technical bootstrap | **Not enabled**; real owner-designated identities are HOLD-01 |
| Maker-checker action binding, independent decision and one-time execution | **Not enabled**; approval tables alone do not implement governance |
| Protected role grants, scope ceilings, branch mutations, office appointments | **Not enabled** until bootstrap and approvals are accepted |

**Please report the result of the numbered checks and whether you accept the new password-change increment.** This is not authority to merge draft PR #3 or deploy live accounts.
