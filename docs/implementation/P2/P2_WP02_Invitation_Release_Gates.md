# P2-WP02 — Invitation release gates and concurrency contract

Status: **HOLD — not approved for operational use**.

## Verified on disposable database (reported 2026-11-21)

The private issuer core successfully committed hashed tokens and audit events, delivered in-memory tokens after commit, and revoked a failed-delivery token with a second audit event. Unauthenticated CLI entry was denied. The audit chain verified 21 events. These are **not** full authenticated end-to-end tests.

## New issuer concurrency rule

The issuer now locks the target user, pending identifier, issuer user and issuer's existing role-assignment rows in its audited transaction before the final `users.invite` scope decision. **Every future role assignment, revocation, suspension, role-permission modification, and issuer-account suspension writer must use a reviewed consistent lock order and recheck relevant authorization within its transaction.** Locking existing assignment rows alone cannot prevent all concurrent inserts, role permission edits, or changes by non-cooperating writers. Do not mark concurrency acceptance passed until two-connection tests cover grant revocation, new assignment insert, role deactivation, permission deactivation, and issuer suspension. Avoid deadlocks by defining and enforcing a common lock order before activating writers.

## Outstanding release blockers

1. **Independent provisioning approval**: a bound, untampered, approved and unexpired action, approved by a distinct authorized checker, must be validated and consumed atomically. The current issuer does not require this; never enable an HTTP issuance route before it is implemented.
2. **Real Shield/TOTP entry point**: test successful issuance only through genuine authenticated session and completed MFA, with CSRF, throttling, and appropriate denial cases. No test-only MFA marker or identity spoofing in an operational controller.
3. **Concurrent mutations**: verify the lock contract under two database connections and fail closed on stale or conflicting authorization.
4. **Compensation failure**: inject a deterministic failure of the *second* audited transaction; confirm an explicit error, no false delivered status, incident response and durable token invalidation strategy. A rollback of compensation can leave a pending token, so this remains a critical release blocker.
5. **Delivery safety**: real delivery adapters, secret handling, retries/idempotency and recipient proof require separate approval; only synthetic in-memory delivery is allowed now.
6. **Production permissions**: approved role-management workflows, grant ceilings, maker/checker separation, immutable audit checkpoints, and least-privilege database grants must be reviewed.

## Acceptance / safe next action

Do not rerun `iedpu:p2-invitation-issuer-acceptance-check` against the database on which it already passed; it is intentionally one-time and committed synthetic records. Pull the new issuer locking change, run PHP lint and the **read-only** governance and audit checks. For further mutation/concurrency tests, provision a fresh dedicated disposable database from migrations and fixtures. Never selectively delete audit events or transplant synthetic grants into a real database.

Keep PR #3 draft and all operational invitation, recovery, activation, administrator and role-mutation routes disabled.
