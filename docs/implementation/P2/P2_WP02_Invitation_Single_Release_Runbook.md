# P2-WP02 single invitation release — integrated implementation

## Delivery and security boundaries

This branch includes an approval-bound invitation request, independent checker decision, MFA-gated issuer, atomic hashed token + encrypted SMTP outbox, a CLI SMTP dispatcher, public one-time activation, and durable audit events. No self-registration, role grants, administrator bootstrap or privileged recovery are enabled. SMTP sends only from a trusted CLI process. The outbox uses a 32-byte sodium secretbox key independent of Shield MFA encryption. Do not commit credentials or this key.

**Not yet production accepted.** The code was committed through the GitHub connector; PHP lint, migrations, real browser MFA/CSRF acceptance, concurrent database sessions, SMTP integration, and security review must run in the owner's environment. Existing local tests of the older private issuer core do not prove the new workflow.

## Required local environment (never commit real values)

```ini
IEDPU_INVITATION_OUTBOX_KEY = <base64 of 32 random bytes>
IEDPU_INVITATION_SMTP_ENABLED = 0
IEDPU_SMTP_HOST = <approved SMTP host>
IEDPU_SMTP_PORT = 587
IEDPU_SMTP_USER = <SMTP username>
IEDPU_SMTP_PASS = <SMTP app password>
IEDPU_SMTP_FROM = <verified sender address>
IEDPU_PUBLIC_ORIGIN = https://<actual HTTPS site>
```

Generate the encryption key once with `php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"`. Back up the key securely: losing it makes queued invitations unreadable. Configure SMTP credentials using secure deployment secrets, not source control. Set `IEDPU_INVITATION_SMTP_ENABLED=1` **only** in the authorized acceptance environment after approval. Do not enable SMTP against a disposable test database with real recipients.

## Deployment / single consolidated local verification

1. Back up the database, pull the branch, run `php spark migrate`. Confirm the new outbox table.
2. Run `php -l` on every new PHP file and `php spark routes`. Verify four explicit invitation POST routes and global CSRF filter.
3. Run `php spark iedpu:p2-governance-preflight`, `php spark iedpu:p2-invitation-release-preflight`, `php spark iedpu:p2-audit-verify`.
4. Use two **different real privileged users** with approved `users.invite` scope, both fully signed in through Shield and verified TOTP. Do not use a synthetic role in production. Provision a clean inactive unprivileged user with a pending email identifier by an independently approved account-provisioning process. No public provisioning API is enabled here.
5. POST JSON to `/api/v1/auth/invitations/request` from maker with exactly `target_user_id` (integer), `identifier_id` (integer), `scope` (`global`, `zone`, or `branch`), `zone_id` (integer/null), `branch_id` (integer/null), `reason` (10–500 chars). Use the same-origin session and CSRF token. Response returns `approval_request_id`.
6. POST `/api/v1/auth/invitations/decide` as a different authenticated MFA checker with `approval_request_id` (integer), `approve` (boolean), `reason` (5–500 chars). Checker must have `users.invite` permission in the same scope. Test maker self-approval denial.
7. POST `/api/v1/auth/invitations/issue` as original maker with `approval_request_id`. Expect 202 queued, never a token in HTTP response. Replays must fail.
8. Run `php spark iedpu:p2-invitation-smtp-dispatch` on the trusted server. Expect exactly one email at a **test mailbox**. Token is present in the recipient email only; do not paste it into logs or tickets. The email directs the recipient to the public activation form, which submits to the CSRF-protected activation API.
9. POST `/api/v1/auth/invitations/activate` with `user_id`, `token`, `password` (same-origin CSRF). Expect activated true once, then rejection on replay. Confirm active verified identifier, Shield credential and audit chain.
10. Test missing MFA, expired session, unauthorized scope, checker same as maker, stale/tampered approval, revoked assignment, double issuance, wrong/expired/replayed token, failed SMTP, outbox exception, and simultaneous issuance/approval changes. Verify `php spark iedpu:p2-audit-verify` afterward.

## Failure and recovery

If SMTP explicitly returns failure, the worker revokes the pending token in a second audited transaction and marks the outbox failed. If the process crashes after claiming or SMTP succeeds but finalization fails, the row remains `sending`; **never automatically resend**. Treat it as an uncertain-delivery incident, stop dispatch, investigate with a second operator, run \`php spark iedpu:p2-invitation-reconcile --token-public-id=<UUID> --confirm-revoke\` under an approved, recorded incident process, then issue a fresh invitation through a new approval. No automatic recovery command is implemented. This fail-closed operational hold must be resolved before production launch.

## Important remaining release holds

The approval request assumes the target account was preprovisioned through an independently authorized workflow. There is no automated two-connection concurrency test for all governance writers. The source-level suite is not an executed end-to-end integration test. **Do not describe this package as production ready or merge PR #3 until those holds are closed and the owner has recorded evidence.**
