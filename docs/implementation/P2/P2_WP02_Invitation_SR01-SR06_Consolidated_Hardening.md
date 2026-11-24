# IEDPU invitation — consolidated SR-01 to SR-06 hardening

Date: 2026-11-24. Branch: feature/p2-database-auth-governance. PR #3 remains draft.

## SR-01 — Base-path-aware activation URLs
Activation page now generates CSRF, activation API, sign-in, CSS and JS URLs with CodeIgniter site_url()/base_url(). Test root-host and subdirectory hosting using actual app.baseURL. The invitation email public origin must be externally reachable HTTPS and include any configured public prefix.

## SR-02 — Strict activation CSP
Inline JavaScript and CSS moved to same-origin static assets. Activation response permits script/style/connect only from self, denies objects/framing/base URI and sets no-store, no-referrer and nosniff. CI smoke checks verify the source-level contract. Browser CSP/CSRF regression remains required.

## SR-03 — SMTP evidence semantics
Dispatcher now records smtp_accepted_at rather than delivered_at. SMTP success means the relay accepted the message; it does not prove inbox delivery. Provider-specific bounce/complaint webhooks are not implemented because no SMTP provider or authenticated event contract has been selected. Before launch, select provider, verify SPF/DKIM/DMARC, controlled mailbox receipt, and configure signed bounce/complaint events or an explicitly approved provider alert process. Do not claim automated bounce monitoring before this exists.

## SR-04 — Interrupted dispatch
No automatic resend. Reconciliation is disabled unless IEDPU_INVITATION_RECONCILE_ENABLED=1, an incident-ticket reference, token UUID and explicit confirmation are supplied. It revokes pending token, clears ciphertext and audits the event. This CLI gate does not authenticate two human approvers. Keep disabled until a two-person incident process, named shell operators, OS command audit logging and independent token-ID verification are active. New invitation requires fresh maker-checker approval.

## SR-05 — Production controls
Development, staging and production examples now include SMTP/outbox-key/reconciliation settings disabled by default. Preflight checks a valid 32-byte outbox key, non-placeholder SMTP values, sender address, TLS port and production HTTPS baseURL/public origin/ForceHTTPS. External evidence remains required for TLS/proxy/cookies, SMTP connectivity from host, least-privilege scheduler, monitoring/alerts, encrypted database+key backups, restore drill, log access and tested rollback.

## SR-06 — Exact SHA and independent review
The previous 13-scenario test was not accompanied by an exact tested commit SHA; baseline is UNKNOWN, not inferred. This package changes activation UI/CSP, outbox schema/timestamp semantics, reconciliation and preflight. Rerun the full suite on the final commit and record SHA. A reviewer who did not author the implementation must review the full PR diff and sign off that same SHA. This cannot be self-certified or replaced by CI. Keep PR draft until evidence exists.

## Local validation
```bat
git pull --ff-only origin feature/p2-database-auth-governance
php spark migrate
php -l app\Controllers\InvitationPage.php
php -l app\Commands\P2InvitationSmtpDispatch.php
php -l app\Commands\P2InvitationReconcile.php
php -l app\Commands\P2InvitationReleasePreflight.php
php -l app\Database\Migrations\2026-11-22-100002_AddInvitationSmtpAcceptance.php
php scripts\test-p2-invitation-release-hardening.php
php spark iedpu:p2-governance-preflight
php spark iedpu:p2-invitation-release-acceptance
php spark iedpu:p2-audit-verify
```

Then rerun the 13 scenarios plus root/subdirectory activation, CSP inline-blocking, SMTP acceptance timestamp semantics, explicit SMTP failure, simulated crash/no-resend, reconciliation gate, production preflight negative cases, scheduler overlap and deployment restore/rollback.

## Status
SR-01/SR-02 implementation committed; SR-03 relay semantics committed but provider bounce integration pending; SR-04 fail-closed ticket gate committed but two-person operational control pending; SR-05 config/preflight committed but infrastructure evidence pending; SR-06 exact SHA and independent review pending.

**Implementation package delivered; release acceptance and production authorization remain HOLD.**
