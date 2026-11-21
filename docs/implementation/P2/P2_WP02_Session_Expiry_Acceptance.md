# P2-WP02 — Server-enforced idle and absolute session expiry

**Implemented:** 21 November 2026. **Owner acceptance: PENDING.** Prior cross-device session revocation increment was accepted by the owner.

## Increment and provisional policy

- An authenticated session expires after **30 minutes without a successful authenticated protected request** (idle expiry).
- An authenticated session also expires after **two hours from successful login**, even if the user remains active (absolute expiry).
- These are **provisional P2 security defaults**, not a claimed owner-approved organisational policy. They match or tighten the existing CodeIgniter session storage expiration of 7,200 seconds; owner acceptance of these time limits is requested during this increment.
- The server records `issued_at` and `last_seen_at` in its session state **only after successful password or authenticator verification**. The absolute timestamp is never refreshed by activity. Both timers are checked on `GET /api/v1/auth/session`, the protected administrative workspace, and protected directory APIs. Session versions must also match the database.
- Missing/malformed timestamps, a clock moving backwards, revoked session version, or elapsed expiry fail closed; Shield logout and local stamp clearing follow. `POST /api/v1/auth/login` can recover from an expired session and start a new password/MFA challenge.
- Password-verified but pending TOTP sessions retain their existing five-minute challenge or fifteen-minute enrolment deadline; they are not authenticated sessions.
- No new migration, new cookie, public privilege, account or persistent browser storage. The existing P1 pages and denied WP03 writes remain unchanged.

### Local-only fast manual test settings

To avoid waiting 30 minutes or two hours, the code accepts **two optional local test settings only** when all of the following hold: CodeIgniter `development`, `IEDPU_ALLOW_SYNTHETIC_AUTH=1`, MySQL hostname loopback, and database name `iedpu_dev*`, `iedpu_test*`, `iedpu_local*` or `iedpu_disposable*`.

If both values are valid, use them for manual tests. The idle value must be 30–600 seconds; absolute 60–1,200 seconds and greater than idle. Any missing/invalid value falls back to 30 minutes/two hours. **Staging and production always ignore these overrides**, even if accidentally configured. Do not use them for real accounts.

## Windows XAMPP manual acceptance

1. Update:
   ```bat
   cd C:\xampp\htdocs\iedpu
   git switch feature/p2-database-auth-governance
   git pull --ff-only origin feature/p2-database-auth-governance
   ```
   **No new migration** is needed.
2. In the **private local** `.env`, retain `CI_ENVIRONMENT = development` and `IEDPU_ALLOW_SYNTHETIC_AUTH = 1` for the disposable synthetic fixture, then temporarily add:
   ```ini
   IEDPU_SESSION_TEST_IDLE_SECONDS = 40
   IEDPU_SESSION_TEST_ABSOLUTE_SECONDS = 100
   ```
   Restart `php spark serve --host 127.0.0.1 --port 8080` to reload configuration. Do not commit `.env`.
3. **Idle expiry:** sign in to the synthetic phone-only account with password + fresh authenticator code. Open `http://127.0.0.1:8080/api/v1/auth/session`; expect HTTP 200 with `authenticated: true`. **Do not access any authenticated page or API for at least 45 seconds**. Refresh the session URL; expect HTTP **401** and `authenticated` must not be true.
4. **Re-login:** go to `/sign-in`, enter the same synthetic account credentials and a new TOTP code; expect successful authentication. This proves the expiry is not a ban or global revocation.
5. **Absolute expiry despite activity:** sign in again (or use the fresh login from step 4). Open the session URL every **15–20 seconds** so the idle timer never reaches 40 seconds. The responses should remain HTTP 200 while the absolute lifetime is under 100 seconds. Once **100 seconds have passed since login**, the next session request must return **401**, even though activity continued. Do not use the browser's Back button as evidence of authenticated server access.
6. After expiry, `/administration/workspace` must not reveal private content; the existing zero-permission synthetic role should still get 403 after fresh successful MFA login. Public `/api/v1/health` and `/constitution/2015` must still work. Administrative POST routes remain disabled.
7. **Restore the default policy:** remove BOTH temporary `IEDPU_SESSION_TEST_*` lines from the local `.env`, restart Spark, and sign in again. Confirm `/api/v1/auth/session` returns authenticated. This ensures the development override is not accidentally retained.
8. Verify audit integrity:
   ```bat
   php spark iedpu:p2-audit-verify
   ```
   It must succeed. This increment does not claim a new audit event for each expired request.

### Acceptance matrix

| ID | Expected result |
|---|---|
| SEXP-01 | Fresh synthetic login and MFA produce an authenticated session |
| SEXP-02 | Inactive session fails with 401 after 40 seconds under local test policy |
| SEXP-03 | Fresh sign-in after idle expiry works |
| SEXP-04 | Active session expires after 100 seconds absolute lifetime despite requests every 15–20 seconds |
| SEXP-05 | Protected administration fails closed; zero-permission role stays denied |
| SEXP-06 | Public routes, existing revocation and audit verifier unaffected |
| SEXP-07 | Removing local test settings restores 30-minute/2-hour default |
| SEXP-08 | Owner approves or requests adjustment to provisional 30-minute idle / two-hour absolute policy |

**Do not claim WP02 complete, enable real privileged accounts or merge draft PR #3 based on these checks.** The invitation, verification, recovery, independent approval, bootstrap and scoped administration gates remain outstanding.
