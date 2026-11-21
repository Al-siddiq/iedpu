# IEDPU modular-monolith boundaries (P1-WP02)

This directory is reserved for business modules implemented in P2 onward.
It intentionally contains no executable business operations during P1.

| Future module | Owns | Does not own |
|---|---|---|
| Membership | Existing-member registration, member profile, account claim | Shield credentials, organisational approval authority |
| Organisation | Admin-managed zones, branches, offices, office-holder assignments | Hardcoded branch names, portal authentication |
| Verification | Branch-chairman review and member verification history | Payment capture, NEC official ID issuance |
| Communications | Announcements, audience snapshots and delivery records | Branch activation, member identity credentials |
| Finance | Dues rules, approvals, ledger and reconciliation | Independent bank settlement authority |
| OfficialIdentity | NEC-gated official membership ID and QR lifecycle | Internal registration reference allocation |
| TechnicalAdministration | System configuration, diagnostics, developer operations | Committee business approvals or privilege escalation |
| Governance | Constitutional/consent approvals and audit evidence | Technical service credentials |

## Dependency direction

- HTTP controllers translate requests and responses; they do not own business rules.
- Each module owns its domain services, validation, persistence and policy checks.
- Shared infrastructure may be used by modules; modules must not bypass scoped authorisation or audit services.
- Cross-module operations go through explicit application services and events, not direct controller-to-controller calls.
- Shield authentication and account identity are integrated in P2, never simulated in P1.
- A portal role profile, zone, branch, office title or office holder is never hardcoded as organisational master data.
- The permission-key catalogue may be release-controlled in migrations, but role assignments remain governed data.
- Technical administration is isolated from Committee business administration; no developer bypass.
- No module may enable live data or operations before its approved phase and activation gate.

Namespaces for new modules follow `App\\Modules\\<ModuleName>\\...` under
`app/Modules/<ModuleName>/`. Existing `App\\Controllers\\Api\\V1`
controllers and `App\\Libraries\\Api` utilities are the P1 HTTP boundary.

## Current release status

Only `GET /api/v1/health` and `GET /api/v1/bootstrap` are active.
All business/API paths are denied by the P1 route map. Full auth, database
migrations, real member data and administrative operations begin in P2+.
