# P2-WP02 — Synthetic local delivery boundary

Status: SOURCE COMMITTED; LOCAL ACCEPTANCE PENDING. No HTTP invitation or recovery route is enabled.

Owner decision (21 Nov 2026): use synthetic delivery only on a disposable local development database. Staging/production delivery stays disabled.

This increment adds an `AccountActionDelivery` interface and a fail-closed `SyntheticLocalDelivery` implementation. The implementation holds test messages only in process memory. It does not send email/SMS, persist tokens, write token logs, or print secrets. The test harness must explicitly opt in, supply the actual environment, database name and host, and use only localhost with an `iedpu_dev*`, `iedpu_test*`, `iedpu_local*` or `iedpu_disposable*` database.

This is a transport boundary, not a working invitation or recovery flow. The next increment must implement authorized provisioning, audited token issuance, safe post-commit delivery and failure handling, generic rate-limited requests, and acceptance tests. Never deliver within `AtomicAuditedMutation`. Never expose `takeMessages()` through an HTTP endpoint. Avoid logging recipients or tokens.

Local review: PHP lint both new classes, inspect that `Routes.php` is unchanged, verify disabled activation/reset routes remain HTTP 403, and run the existing governance, token lifecycle and audit checks. Add dedicated negative tests before connecting this adapter to a workflow.

HOLD: no production provider, no privileged recovery, no administrator bootstrap, no approval execution, no role assignment, no live credentials, and no PR merge.
