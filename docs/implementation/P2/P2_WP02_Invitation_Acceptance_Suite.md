# P2-WP02 — Consolidated disposable invitation acceptance

## Scope

This package tests the **actual issuer's private transaction-and-delivery core** against a dedicated local MySQL database. The public `DevelopmentInvitationIssuer::issue()` remains protected by real Shield authentication, session-version validation and MFA. The earlier denial check tests that public gate without fabricating a session.

The acceptance command uses PHP reflection to invoke the **private core**, after creating a conspicuous synthetic role and permission grant on a disposable database. Reflection is limited to this CLI acceptance command; it is not a runtime bypass, an HTTP route, or evidence that an authenticated success path has been exercised.

## Preflight

- Use only `iedpu_disposable*` on loopback, development environment, `IEDPU_ALLOW_SYNTHETIC_AUTH=1`.
- Run all migrations and the `P2PermissionCatalogueSeeder` if `users.invite` is not already present.
- The existing active `+2348000000000` synthetic account and inactive `iedpu-invite-fixture@example.invalid` pending identifier must exist.
- The target must have no auth identity, role assignment, TOTP or token history.
- The role `synthetic_invite_acceptance` must not exist. **This suite runs once per fresh disposable database**.
- Do not run against real people or accounts. The suite intentionally commits token, audit and synthetic-grant records.

## Run all checks together

```bat
git pull --ff-only origin feature/p2-database-auth-governance
php -l app\Libraries\Auth\DevelopmentInvitationIssuer.php
php -l app\Commands\P2InvitationIssuerAcceptanceCheck.php
php spark iedpu:p2-governance-preflight
php spark iedpu:p2-invitation-issuer-denial-check --confirm-synthetic --confirm-disposable
php spark iedpu:p2-invitation-issuer-acceptance-check --confirm-synthetic --confirm-disposable
php spark iedpu:p2-audit-verify
```

The consolidated suite verifies: test-only scope grant, authorized core issuance, in-memory delivery, hashed token storage, durable success audit, deliberate delivery exception, compensating revocation, failure audit, replay denial, revoked permission denial, no side effects after denial, and self-invitation denial.

## Important limitations

This is **not** full end-to-end issuer acceptance. It does not prove that a real authenticated Shield+TOTP browser session can issue successfully, that permission revalidation withstands concurrent changes between checks, or that an independent approved provisioning request exists. It also does not inject a failure of the compensating transaction itself. Those remain release HOLD items.

No production delivery, public invitation activation, reset, privileged recovery, bootstrap, role mutation or HTTP invitation endpoint is enabled.

Never delete selected audit rows to reset this suite. Destroy the entire disposable database after testing.
