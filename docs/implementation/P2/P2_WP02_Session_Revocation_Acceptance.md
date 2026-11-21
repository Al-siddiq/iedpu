# P2-WP02 — Account-wide session revocation increment

**Implementation:** 21 November 2026. **Status: committed, owner manual acceptance pending.** This increment follows owner acceptance of the first WP03 read-only directory foundation. It does **not** close the WP02 or WP03 completion gates.

## Security intent

Previously, an already-authenticated browser session could remain usable after a separate device/session needed to be revoked. This increment adds a monotonically increasing **per-account session version** in the database. Every newly authenticated Shield session is stamped with the current version after password verification or successful TOTP/recovery challenge. On every existing authenticated introspection and protected administration request, the version must match the current database value. A mismatch, missing stamp, inactive account, missing verified identifier or missing required MFA fails closed.

The version table is separate from Shield's authentication identities; it stores no password, token, MFA secret or device fingerprint. `SessionVersion::revokeAll()` is an internal capability only, **not a publicly callable admin endpoint**. The only new revocation command is restricted to the local synthetic test account, disposable/development database and an explicit confirmation flag. No real-user session revocation API is exposed without the independently authorised governance controls.

### New/changed code

- Migration `2026-11-21-090007_CreateP2SessionVersions.php`: `portal_user_session_versions` with one row per Shield user, nonzero monotonically increasing version and restrictive FK; refuses populated rollback.
- `app/Libraries/Auth/SessionVersion.php`: initialisation, stamp after successful authentication, per-request validation, atomic increment and local cleanup. Sessions predating this migration have no version stamp and must sign in again.
- `AuthController`: check version during `GET /api/v1/auth/session`; on fresh password-only login, stamp before returning success. A stale session encountered at login is logged out before accepting a new login.
- `MfaController`: stamp **only after successful** authenticator verification, never at the password-only pending step.
- `AdminWorkspace` and `DirectoryController`: check the version on every protected request before returning private content.
- `iedpu:p2-revoke-synthetic-sessions --confirm-synthetic`: local fixture-only command to atomically invalidate all browser sessions and write a chained audit event. No arbitrary user ID/phone is accepted.

### Limitations and later gates

- This is account-wide **version invalidation on the next protected request**, not immediate remote destruction of each underlying Shield session cookie. Session storage cleanup and independent device listing are future work.
- This increment does not implement a real administrator's ability to revoke someone else's sessions, nor password change/reset, verified invitations, privileged lost-device recovery, role grant approvals or account bootstrap.
- The next account-recovery and role-assignment services must invoke `revokeAll` **in the same durable transaction as the sensitive account change**, and ensure a separate authorised audit. This cannot be claimed until those services exist and are tested.
- A privileged user who still knows a valid password and has the required TOTP/recovery factor may authenticate afresh after version invalidation. Revocation is not equivalent to banning the account or rotating its password.

## Local manual acceptance — Windows XAMPP

**Prerequisite:** the owner has an existing synthetic phone-only MFA fixture in a strictly local disposable DB, and has already completed the MFA test. **Back up the disposable database before migration. Do not run the synthetic command against real data.**

1. Update the existing branch:
   ```bat
   cd C:\xampp\htdocs\iedpu
   git switch feature/p2-database-auth-governance
   git pull --ff-only origin feature/p2-database-auth-governance
   ```
2. Apply the **new** migration, which adds one table without altering existing accounts or MFA secrets:
   ```bat
   php spark migrate --all
   php spark migrate:status
   ```
   Confirm `portal_user_session_versions` exists in phpMyAdmin.
3. Restart Spark if needed. Existing authenticated browser sessions from before the migration **will require sign-in again**; this is intentional.
4. In **Browser A**, sign in using your existing synthetic phone-only account and Google Authenticator. Verify `http://127.0.0.1:8080/api/v1/auth/session` returns `authenticated: true`. Do not share credentials or recovery codes.
5. In **Browser B** (private/incognito window or a second browser), independently sign in with the same synthetic account and fresh TOTP (do not reuse the previous time step). Both browsers should report an authenticated session. If your authenticator refuses the reused time step, wait for a new code.
6. Ensure private local `.env` still contains `IEDPU_ALLOW_SYNTHETIC_AUTH = 1` and `CI_ENVIRONMENT = development`. Execute:
   ```bat
   php spark iedpu:p2-revoke-synthetic-sessions --confirm-synthetic
   ```
   Expect: `Synthetic fixture browser sessions invalidated. Reauthenticate in every browser.`
7. Refresh `/api/v1/auth/session` in Browser A and Browser B. **Both must now return HTTP 401**, not `authenticated: true`. Refresh `/administration/workspace`; it must not expose protected content. The zero-permission fixture should still get 403 after a new successful MFA login.
8. In Browser A, sign in again with the original test password and a **fresh** TOTP. `/api/v1/auth/session` must return authenticated. The account is not banned or reset.
9. Run:
   ```bat
   php spark iedpu:p2-audit-verify
   ```
   Expect audit verification to succeed and include an additional `auth.sessions_revoked` event. Do not rely on the hash alone as evidence of external immutability.
10. Confirm `GET /api/v1/health` and public Constitution still work; `POST /api/v1/administration/directory/branches` still returns 403. Check no version or session secret is written into localStorage, IndexedDB or URL query strings.

### Manual acceptance checklist

| ID | Pass condition |
|---|---|
| SREV-01 | New migration creates one version table; existing MFA records unchanged |
| SREV-02 | A fresh password/TOTP login creates a session-version stamp |
| SREV-03 | Two independent browser sessions can authenticate before revocation |
| SREV-04 | One local fixture revocation invalidates both sessions on next protected request |
| SREV-05 | After revocation, neither browser can read the authenticated session endpoint |
| SREV-06 | Fresh password + TOTP works after revocation; no accidental ban |
| SREV-07 | Audit chain verifies; `auth.sessions_revoked` recorded |
| SREV-08 | No synthetic command in production/nonlocal DB; no public revocation endpoint |
| SREV-09 | Existing WP03 401/403 permission boundaries and public P1 pages preserved |

**Please report which checks passed or failed.** Do not claim WP02 complete or merge PR #3 until the other security prerequisites have been delivered and accepted.
