# P2-AUTH-02 — Approved TOTP authenticator MFA

Owner approval: 21 November 2026. The owner explicitly accepted the recommended policy: **email or phone + password for all accounts; mandatory authenticator-app TOTP for privileged accounts; optional TOTP for ordinary members in a later UI increment; no compulsory SMS or recurring SMS provider; secure recovery by separate authorised approval.**

## Decision and boundaries
- Google Authenticator, Microsoft Authenticator and any RFC 6238-compatible app work. Do not assume all Nigerian members own smartphones or know authenticator apps. Ordinary members must not be forced to install one.
- All users may log in using verified email **or** verified phone. Neither identifier is individually compulsory. One Shield password hash per user. No SMS is needed to receive TOTP codes; the authenticator app generates codes locally.
- Every active portal role assignment requires MFA before a Shield session is issued, including technical operators. Roles are not automatically assigned. An existing factor continues to require MFA even after the last active role is removed; optional member self-management can follow later.
- For first-time privileged login, a password-verified pending session allows enrolment, with a 15-minute setup window. Existing factors require a five-minute challenge window. QR setup and manual key are available. No access before the correct six-digit code is verified.
- RFC 6238 TOTP: SHA1, six digits, 30-second time step, ±1 time step, reject previously used time steps atomically in the database. TOTP is not phishing-resistant; use HTTPS, generic errors, CSRF, throttling and step-up for high-risk actions.
- TOTP secrets are encrypted using sodium secretbox with a **separate 32-byte base64 IEDPU_TOTP_KEY**, never checked into Git. Without this key, privileged sign-in fails closed. The key must be securely backed up and rotated with a reviewed decrypt/re-encrypt plan; replacing it without migration will lock out all factors.
- Ten 128-bit random recovery codes are generated on enrolment. Only keyed SHA256 hashes are stored; the plaintext codes are shown exactly once and must be saved offline. Each code can be used only once in place of a TOTP code at the second factor step. Recovery codes **do not reset passwords, replace possession verification, grant a role, or independently establish account ownership**.
- If both the authenticator and all recovery codes are lost, **account recovery remains disabled** until the separate independent-authorisation and verified-identity recovery process is approved and implemented. Never let a single support person reset a privileged factor.
- This increment does not enable administrative CRUD, role assignments, approval execution, invitation delivery, password reset, production activation, cross-device revocation or public member registration.

## Committed implementation
- Migration `2026-11-21-090006_CreateP2TotpFactors.php`: encrypted TOTP factor and hashed one-use recovery code tables; refuses rollback of populated data.
- `TotpFactor`: strict base32, RFC6238 verification, sodium encryption, atomic anti-replay, 10 recovery codes.
- `AuthController`: after verified Shield password, any active role or existing factor yields HTTP 202 with pending MFA state; **no authenticated Shield session until second factor succeeds**. Session introspection checks MFA marker.
- `MfaController`: `GET /api/v1/auth/mfa/setup` and `POST /api/v1/auth/mfa/verify`, CSRF for POST, per-session/per-user throttling, expiry, fresh user/identifier recheck, one-time recovery and audited success. Session is bound server-side; no client-selected user ID.
- `GET /sign-in`: accessible, mobile-friendly email-or-phone form, local QR generator (MIT), manual key fallback, TOTP/recovery challenge, one-time recovery code display. No external QR API, no credentials in browser storage, no SW caching.
- `iedpu:p2-auth-fixture --confirm-synthetic --with-mfa-role`: creates a synthetic phone-only account with a **zero-permission** active role in an explicitly disposable development DB. It is not real bootstrap and does not grant privileges.

## Local operator instructions — Windows XAMPP

**Do not run the synthetic fixture against real member data.** First back up the existing development database. Prefer a fresh disposable local DB named `iedpu_disposable_mfa`; adjust your private `.env` accordingly.

1. Pull the P2 branch. Confirm PHP sodium extension: `C:\xampp\php\php.exe -m` (look for `sodium`).
2. Generate a private MFA key in CMD:
   ```bat
   C:\xampp\php\php.exe -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
   ```
   Copy the output **only** into your private `.env` as `IEDPU_TOTP_KEY = 'YOUR_BASE64_VALUE'`. Never send the key to the assistant, a screenshot, a ticket or GitHub.
3. Run migrations on your disposable local DB:
   ```bat
   C:\xampp\php\php.exe spark migrate --all
   C:\xampp\php\php.exe spark migrate:status
   ```
   Verify the two new `portal_totp_*` tables in phpMyAdmin.
4. Set `IEDPU_ALLOW_SYNTHETIC_AUTH = 1` in **private local .env** only. Ensure `CI_ENVIRONMENT = development`, DB hostname localhost/127.0.0.1 and DB name matches the fixture's disposable naming rule. Execute:
   ```bat
   C:\xampp\php\php.exe spark iedpu:p2-auth-fixture --confirm-synthetic --with-mfa-role
   ```
   The command prints a one-time synthetic password. Do **not** paste that password into chat or screenshots.
5. Start Spark: `C:\xampp\php\php.exe spark serve --host 127.0.0.1 --port 8080`. Open `http://127.0.0.1:8080/sign-in`. Use phone `08000000000` and the locally displayed password. Confirm the password step does **not** log in, but shows authenticator setup and QR. Install Google Authenticator (or compatible app), scan the QR, enter the six-digit code, and verify. Save the 10 recovery codes offline. Confirm signed-in status and `/api/v1/auth/session`.
6. Sign out. Sign in again with `+2348000000000` and the same password. Confirm existing-factor challenge, not re-enrolment. Enter a fresh TOTP and verify.
7. Sign out, sign in again and use **one** saved recovery code. Confirm successful login; sign out and retry the same recovery code after a new password step: it must be rejected. Test an incorrect TOTP and expired TOTP; reject both.
8. Confirm `/api/v1/administration` and `/api/v1/technical` still return 403 even after MFA; factor verification is not a permission grant.
9. Run `C:\xampp\php\php.exe spark iedpu:p2-audit-verify`; inspect `audit_events` for `auth.mfa_enrolled`, `auth.mfa_verified`, `auth.mfa_recovery_login` without secrets. Check `portal_totp_factors.encrypted_secret` is ciphertext, and `portal_totp_recovery_codes.code_hash` contains only hashes.
10. Test a missing/invalid MFA key **only on disposable DB**; privileged password step must fail with 503 and must not issue an authenticated session. Restore the original private key afterwards.
11. Repeat P1 health, constitution, public-only service worker and negative API tests. Verify the QR script loads locally, no remote requests, no key/code in localStorage/IndexedDB, no cached auth HTML, and responsive/keyboard access at 320px and 200% zoom.
12. Record results in a local acceptance log. Destroy synthetic accounts and the disposable DB after testing; remove `IEDPU_ALLOW_SYNTHETIC_AUTH` from private `.env`.

## Manual acceptance table

| ID | Expected |
|---|---|
| MFA-01 | Privileged password yields 202 and **no authenticated session** |
| MFA-02 | QR and manual setup key work; setup expires after 15 min |
| MFA-03 | Valid six-digit code enrols factor and creates 10 recovery codes |
| MFA-04 | Existing factor requires a new TOTP each login; 5-min challenge expiry |
| MFA-05 | Replayed/expired/incorrect codes rejected, including concurrent reuse |
| MFA-06 | Recovery code is single-use; hashes only at rest |
| MFA-07 | Missing key, revoked user or unverified identifier denies session |
| MFA-08 | Anonymous and non-privileged accounts remain unaffected; email/phone equivalence |
| MFA-09 | All admin/technical actions remain disabled; no privilege elevation |
| MFA-10 | Audit verification passes; no secret/OTP in logs or browser persistence |
| MFA-11 | Mobile, keyboard, low-bandwidth and QR/manual fallback |
| MFA-12 | No SMS provider or per-message fees |

**Status:** implementation committed; PHP syntax CI is not a substitute for live Shield/MySQL/browser verification. The owner must manually execute and accept the above. Remaining WP02 tasks: real invitation and verification, secure account recovery and reset, cross-device session revocation, controlled role/grant approvals, initial Committee/technical bootstrap, scoped API guards and transactionally coupled audit. Do not declare P2-WP02 complete.
