# P2-WP02 — Internal development invitation issuer

Status: CODE COMMITTED; LOCAL ACCEPTANCE AND INTEGRATION TESTS PENDING.

The `DevelopmentInvitationIssuer` is internal only. There is no route, controller, command, public registration, live delivery, or account provisioning operation. It requires a pre-provisioned inactive user and pending identifier. Only explicitly enabled disposable local development databases qualify.

Flow: Shield session and MFA marker -> session version -> scoped `users.invite` eligibility -> `AtomicAuditedMutation` -> lock target user and identifier -> revalidate authorization and absence of role, TOTP and credentials -> `AccountActionTokenStore::issueWithinTransaction` -> append audit -> commit -> `SyntheticLocalDelivery`.

On delivery exception, a second audited mutation attempts to revoke the specific token by public ID, without revoking a newer concurrent issuance. A failure of that compensating transaction throws; the token must be treated as potentially pending and manually investigated before any activation route is enabled.

Security limitations and holds:
- There is not yet a trusted invitation provisioning approval, a dedicated issuance HTTP endpoint, CSRF/rate limiting, or a supported local synthetic integration fixture. Do not call this service from a route or CLI yet.
- The role permission check is re-run within the transaction, but no row-level lock is currently held on the issuer's role assignments. Role assignment mutations remain disabled. Reconcile this before enabling concurrent grant/revoke operations.
- Recipient delivery success is synthetic only and not proof of real recipient possession.
- Do not allow a synthetic token to activate a real account. Do not print, log or persist plaintext tokens outside the existing in-process adapter.
- No privileged recovery, production delivery, bootstrap, approval execution or role mutation.

Local acceptance: pull branch; lint `app/Libraries/Auth/DevelopmentInvitationIssuer.php`; run governance preflight and audit verifier. Expected audit count unchanged because adding this service does not invoke it. Before operational use, add disposable-DB tests for allowed issuance, denied issuer, stale session, missing MFA, target active/credentialed/role-bearing, replay/reissue, delivery exception revocation, revocation failure, rollback and concurrent issuance.
