# P2-WP02 — Account invitations, recovery and governance foundations

**Date:** 21 November 2026. **Status:** SCHEMA AND INERT SECURITY PRIMITIVES COMMITTED; local migration/manual acceptance pending. **Not a working invitation, reset, administrator bootstrap or approval service.**

## Binding authority and boundaries

Use P2 Strategic Implementation Plan §5 A–E, P2-AUTH-01 (verified email OR phone), P2-AUTH-02 (privileged authenticator TOTP and independent recovery), Database Schema Design §5–9, P0 holds and owner decisions. No public registration, SMS dependency, fake email, privileged recovery bypass, technical self-elevation or automatic branch activation.

The owner confirmed the previous password-change flow and Show/Hide/six-character usability changes work locally. This increment starts the remaining account and governance work without granting live privileges.

## Committed deliverables

1. Migration §090008 adds three **empty** tables:
   - portal_account_action_tokens: one pending token per user and purpose; 256-bit token digest, purpose, intended Shield user and login-identifier FK, delivery-channel designation, expiry, consumed/revoked timestamps, issuer and optional approval reference. Enforces a single pending purpose per account with generated unique keys. Does **not** verify possession, deliver, issue, or redeem any token.
   - portal_approval_action_bindings: exactly one bound proposed action per existing approval request; target, scope, expected state hash/version, canonical intent hash, execution state. This closes a schema gap but is **not** an approval executor or immutable record by itself.
   - portal_bootstrap_state: single-record marker reserved for a future independently authorised, one-time CLI bootstrap; requires **distinct** Committee and technical users. It is empty after migration.
2. AccountActionTokenCodec: cryptographically random 256-bit token generation, strict hexadecimal digest and constant-time match; **no delivery or database write**. Tokens are never printed by the preflight.
3. ApprovalIntent: deterministic digest over an allowlisted action, subject, scope, expected-state hash and proposed change. Equivalent associative field ordering gives the same digest; altered intent changes the digest. This is a **primitive**, not a grant, decision, privilege ceiling or approval.
4. P2GovernancePreflight: local-only read-only command checking table existence, token primitive, intent digest tamper sensitivity and absence of initial bootstrap. Prints no credentials, contact details, tokens, role assignments or secrets.
5. Existing public/auth routes are **unchanged**. Account activation, reset, invitation issue, approval decisions, privileged provisioning and role assignment remain disabled by the existing 403 fallback.

## Safe local XAMPP verification

**Use only the existing disposable development database.** Make a backup of the disposable DB before the migration. Do not run on production or a DB containing real member/officer information.

1. In CMD:
   ~~~bat
   cd C:\xampp\htdocs\iedpu
   git switch feature/p2-database-auth-governance
   git pull --ff-only origin feature/p2-database-auth-governance
   php spark migrate:status
   php spark migrate --all
   php spark migrate:status
   ~~~
   Confirm the new 090008 migration is applied. If migration fails, **stop**: inspect the exact SQL/DB compatibility and partial tables before retrying. MySQL DDL is not transactionally reversible.
2. Run:
   ~~~bat
   php spark iedpu:p2-governance-preflight
   php spark iedpu:p2-auth-diagnose
   php spark iedpu:p2-audit-verify
   ~~~
   Expected preflight: all three tables OK, token primitive OK, approval intent OK, bootstrap **not performed**, and explicit HOLD message. Existing synthetic sign-in and audit remain intact.
3. In phpMyAdmin, verify all three new tables have **zero rows**. Do not manually insert tokens, approvals, administrator identities or role grants.
4. Browser/API negative checks: anonymous /api/v1/auth/activate, /api/v1/auth/password/reset and /api/v1/administration/approvals must still be **403** (unreleased routes); existing /sign-in, /account/security, /api/v1/auth/session, /api/v1/health and Constitution remain unchanged. The synthetic MFA-only account must still get 403 on the administration workspace.
5. Run the preflight a **second time**; it must not create data or change counts. No external email or SMS should be sent.

| Acceptance ID | Expected | Current evidence |
|---|---|---|
| GOV-F01 | Migration 090008 applies to disposable local DB | Owner local verification pending |
| GOV-F02 | Three new tables empty and constrained | Owner local verification pending |
| GOV-F03 | Read-only preflight reports OK and no bootstrap | Owner local verification pending |
| GOV-F04 | Repeated preflight does not mutate database | Owner local verification pending |
| GOV-F05 | Activation/reset/approval/role writes remain 403 | Owner local verification pending |
| GOV-F06 | Existing authentication, MFA, audit and public routes still work | Owner local verification pending |

## Remaining actual operational work (NOT implemented by this foundation)

| Area | Next required controlled implementation |
|---|---|
| Invitation | Approved issuer/permission and scope; account provisioning tied to verified email OR phone; delivery adapter and proof of recipient possession; transactional issuance/resend/revoke/consume; expiry/replay/CSRF/throttling/audit; no public registration |
| Password recovery | Generic request response, verified destination, time-bound single-use token, controlled reset of Shield password, revoke sessions and pending tokens; independent privileged lost-device approval, never SMS as MFA bypass |
| Independent bootstrap | Owner-designated separate Committee and technical identities, formal authorisation evidence, CLI-only one-time marker, concurrent invocation/partial failure safety, audited least-privilege grants and TOTP |
| Maker-checker | Approved action policy, immutable intent, same-scope permission and grant ceilings, no self-approval, expiry, lock/revalidate target, execute exactly once and atomically audit; ordinary branch activation remains direct under approved Committee authority |
| Protected role assignments | Scoped actor/target permission ceiling, role/profile policy, independent approval where required, no self-elevation, start/expiry/revoke, immediate session invalidation, audit and cross-scope manual acceptance |

## Decisions and holds before real activation

- **Delivery:** owner must approve the production verification channel/provider for email and/or phone, and the approved assisted process where phone delivery is unavailable. No live SMS/email transport is configured.
- **Recovery:** owner must approve independently authorised privileged lost-device recovery procedure and authoriser separation.
- **HOLD-01:** real initial Committee Super Administrator and technical operator identities, with evidence of their independent authorisation, remain unprovided; do not request passwords or TOTP secrets through chat.
- **Session expiry:** 30-minute idle / two-hour absolute provisional policy has not been separately owner-accepted.
- **Security integration:** audit-chain append concurrency and nested transaction boundaries require manual verification and a reliable atomic mutation wrapper before issuing credentials or executing approvals.

**No PR merge, live bootstrap or real account delivery is authorised by this migration.**


## Owner-reported local preflight acceptance — 21 November 2026

The owner supplied a Windows CMD screenshot with the following direct observations:
- Governance preflight: all three new tables OK, token primitive OK, approval intent canonicalisation/tamper detection OK, bootstrap **not performed**, foundation preflight passed and all operational routes explicitly held.
- Auth diagnose: all listed Shield/P2 tables, sodium/TOTP key, synthetic identifier, Shield account/password identity and synthetic MFA role OK; preflight passed.
- Audit verify: **16 events verified** and last hash reported. The verifier cautions that current DB hash integrity is not independent tamper-proof evidence.

**Accepted from screenshot:** foundation diagnostic, synthetic auth readiness and current audit-chain verification. **Not independently evidenced:** phpMyAdmin zero-row inspection of all tables, repeated preflight, HTTP negative-route regression, or real delivery/recovery. Do not silently mark GOV-F02, GOV-F04, GOV-F05 or GOV-F06 complete.

## Next increment — internal transactional token lifecycle (21 November 2026)

Added app/Libraries/Auth/AccountActionTokenStore.php as an **internal service only**. It supports, under a caller-owned transaction, issuance and replacement of a pending token, redemption with DB row locking and single-use transition, strict purpose/user/identifier checks, expiry checks using database UTC clock, and revocation of all pending account-action tokens. For reset, the destination identifier must already be active and verified; for invitation/verification, it must be pending. No token, recipient identifier, credential or secret is written to logs. This service does **not** send email/SMS, provision users, verify actual delivery, change passwords, assign roles, execute approvals, or commit any account change by itself. The future caller must atomically combine token consumption with the account mutation, session revocation and durable audit.

Added rollback-only local diagnostic: php spark iedpu:p2-token-lifecycle-check --confirm-synthetic. It requires ENVIRONMENT=development, IEDPU_ALLOW_SYNTHETIC_AUTH=1, a localhost disposable iedpu_dev*/iedpu_test*/iedpu_local*/iedpu_disposable* database, and the existing verified synthetic phone-only fixture. The diagnostic exercises wrong-purpose/wrong-user denial, valid single-use, replay rejection, replacement invalidation, expiry and revocation; it **always rolls back** all token writes and never prints tokens.

### Manual verification for this increment

1. In Windows CMD, update the same development branch and run the local-only command:
   ~~~bat
   cd C:\xampp\htdocs\iedpu
   git switch feature/p2-database-auth-governance
   git pull --ff-only origin feature/p2-database-auth-governance
   php spark iedpu:p2-token-lifecycle-check --confirm-synthetic
   ~~~
2. Expected: five OK lines (wrong purpose/account denied; one-time and replay; reissue invalidation; expired/revoked denial; rollback and unchanged row count) followed by an explicit HOLD line. No plaintext token appears.
3. Run it a second time; expected identical checks and unchanged token row count. Confirm portal_bootstrap_state is still empty. **Do not** insert or send real tokens.
4. Re-run php spark iedpu:p2-audit-verify; it should still pass. No new audit event is expected from the rollback-only diagnostic.
5. If the diagnostic fails, copy only its non-secret status lines and exception class. Do not send .env, token strings, password hashes or TOTP secrets.

**Acceptance status:** pending owner execution. The next activation step still requires approved delivery/recipient possession, independent privileged recovery, authorised bootstrap and an atomic mutation+audit service. All HTTP write routes remain 403 and PR #3 remains draft.
