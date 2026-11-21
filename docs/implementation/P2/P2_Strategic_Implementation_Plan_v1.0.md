# IEDPU North Zone Digital Membership, Administration and Development Portal

## P2 Strategic Implementation Plan and Manual Acceptance Playbook

**Version:** 1.0 | **Date:** 21 November 2026 | **Status:** Proposed for owner approval  
**Baseline:** `main` at `8df2f5d4afaf1fe977a6adeeec77775387ace9af`; P1 PR #2 verified merged.  
**Method:** Sequential delivery, manual verification, one phase branch and pull request.

## 1. Authority and purpose

This plan decomposes the twelve P2 activities in the approved Strategic Phase Implementation Plan into four deliverable work packages. It does not replace the Business Blueprint (B), Technical Blueprint/SRS (S), Database Schema Design (D), master Strategic Phase Implementation Plan (P), or P0 Engineering Decision Register (E). The later recorded owner clarifications govern conflicting older wording. Constitutional and properly delegated IEDPU decisions remain authoritative for organisational policy. The accepted P1 code is the implementation baseline, not authority to weaken a requirement. Source discrepancies requiring policy decisions are logged and held; technical integration details are resolved through documented engineering decisions.

Repository sources (baseline links):

- B: [Business Blueprint](https://github.com/Al-siddiq/iedpu/blob/8df2f5d4afaf1fe977a6adeeec77775387ace9af/docs/IEDPU_North_Zone_Business_Blueprint.md), particularly §§8–9.5, 18, 20–21, 29–31.
- S: [Technical Blueprint and SRS](https://github.com/Al-siddiq/iedpu/blob/8df2f5d4afaf1fe977a6adeeec77775387ace9af/docs/IEDPU_North_Zone_Technical_Blueprint_and_SRS_v1.0.md), particularly §§2–6, 7.5, 7.7, 7.15, 8, 11–16.
- D: [Database Schema Design](https://github.com/Al-siddiq/iedpu/blob/8df2f5d4afaf1fe977a6adeeec77775387ace9af/docs/Database_schema_Design.md), particularly §§2, 4–6, 7.1, 8–9, 21–26, 28–31.
- P: [Strategic Phase Implementation Plan](https://github.com/Al-siddiq/iedpu/blob/8df2f5d4afaf1fe977a6adeeec77775387ace9af/docs/IEDPU_North_Zone_Strategic_Phase_Implementation_Plan.md), P2, F2 M-011–M-026, migration and cross-cutting checklists.
- E: [P0 Engineering Decision Register](https://github.com/Al-siddiq/iedpu/blob/8df2f5d4afaf1fe977a6adeeec77775387ace9af/docs/implementation/P0/Engineering_Decision_Register.md), ENG-002–011, 014–024, 026–029 and applicable holds.
- [P1 owner acceptance](https://github.com/Al-siddiq/iedpu/blob/8df2f5d4afaf1fe977a6adeeec77775387ace9af/docs/implementation/P1/P1_Owner_Acceptance.md), WP01–WP04 records, hosting runbook and accepted code.

P2 establishes actual secure login accounts, database-driven operational roles, organisation data, scope enforcement, governance decisions and protected audit. No live membership enrolment, verification, self-service claims, finance, official membership IDs, announcements or private offline processing may activate. Public production deployment is separate from engineering acceptance.

## 2. Current baseline and gaps

P1 merge is verified. No new P2 implementation is claimed by this plan. The code inspection establishes these starting conditions:

| Existing baseline | P2 delta |
|---|---|
| CI4 4.7.6, Shield 1.4.1, Settings 2.4.0, Composer lock | Preserve; inspect official installed migrations and compatibility before any change |
| Empty app migration/seed directories | Implement versioned dependency-ordered schema and permission catalogue |
| `Config/Auth.php` includes registration, magic links and remember-me enabled by default, login/register actions null | Explicitly configure invitation-only session authentication and privileged MFA; do not expose default routes |
| `Config/AuthGroups.php` contains starter groups and permissions | Remove/neutralise starter business authority; portal role/permission tables are sole business authorisation source |
| `Routes.php` public preview shells, health/bootstrap, disabled business prefixes | Explicit verb-specific auth/protected routes before fallback; retain disabled later-phase capabilities |
| `Filters.php` CSRF inactive; global page-cache filters present | Enforce CSRF before mutations; prevent authenticated/secret-bearing pages and APIs from entering page cache; safe JSON failures |
| `ApiResponder` and `ApiInput` | Preserve envelope, validation; generate one trusted correlation ID per request, shared by audit/error/response, mask exceptions |
| `SystemController` declares authentication unavailable and capability flags false | Expose only accurate public readiness flags, separate private permission context; no sensitive diagnostics publicly |
| Vue 3.5.24, Bootstrap 5.3.9, local Bootstrap Icons 1.13.1, official branding | Reuse existing shell, native History API and Fetch; no frontend rebuild stack |
| `api.js` GET-only; P1 public-only SW and Dexie metadata | Extend same-origin authenticated API client and CSRF lifecycle; public cache only, no secrets/private data in persistent browser storage |

Local XAMPP PHP 8.2.4 remains a development-only exception. Staging must use a security-patched supported PHP version and a compatible MySQL/MariaDB host. Actual staging validation remains required. No dependency upgrade without a documented reason and lockfile review.

## 3. Consolidated delivery structure

| Package | Deliverable | Dependency | Original P2 activities |
|---|---|---|---|
| WP01 | Relational database and persistence foundation | Accepted P1 baseline | 1, 2/3/4/5/6/9/10 schema, 11, backup baseline from 12 |
| WP02 | Authentication, scoped authorisation, governance and audit | WP01 | 3, 5, 6, 7, 8, protected provisioning |
| WP03 | Organisation and administrative management | WP02 | 2, 3, 4, 5, 6, 7/8 operational UI, protected audit access |
| WP04 | Privacy, private storage, recovery and end-to-end acceptance | WP03 | 9, 10, 12, integrated 1–11 verification |

Four packages are the minimum practical sequential gates: storage integrity, enforcement, operational workflows and integrated acceptance. Schema dependencies are not a reason to enable features prematurely. Each package has a usable, inspectable output; WP01 is CLI/database-only, WP02 is a secure operational identity boundary, WP03 provides protected administrative functionality, WP04 proves P2 readiness.

## 4. P2-WP01 — Relational database and persistence foundation

**Objective:** establish the exact D-defined relational model needed by P2, with official Shield tables and a reproducible migration baseline; no live CRUD or authentication endpoints.

### Execution

1. Inspect Composer lock, installed Shield migration namespaces, authentication and Settings dependencies, migration status, database version, SQL modes, InnoDB, charset/collation, supported CHECK/generated-column/index behaviour and privileges. Create isolated disposable local/staging databases. Back up existing development DB before migration; no production or real data.
2. Run Shield's official migrations using the verified CI4 namespace command; inspect actual `users.id` type and all Shield tables with `SHOW CREATE TABLE`. Do not duplicate authentication tables or assume INT/BIGINT width or signedness.
3. Produce a table-by-table migration manifest from D. Use the exact fields, nullability, defaults, statuses, indexes and reference rules. The dependency set below is not a substitute for D's field definitions.

| Order | Tables / dependency group |
|---|---|
| A | Shield-owned authentication tables; required Settings migrations if applicable |
| B | `org_zones`, `geo_states`, `project_coverage_states`, `branches`, `emirate_ancestral_areas` |
| C | `portal_roles`, `portal_permissions`, `portal_role_permissions` |
| D | `approval_requests`, `approval_decisions`, `portal_user_role_assignments` |
| E | `official_office_catalog`, `file_assets`, `privacy_notice_versions` |
| F | `members` (canonical parent only, no registration workflow), `member_user_links`, `member_account_claims`, `official_office_assignments` |
| G | `audit_events`, `security_events`, `audit_chain_checkpoints` |

4. Include only additional technical support tables necessary for approved invitation, session revocation, MFA, approved action binding and audit sequencing where Shield/D cannot already provide the functionality. Specify rationale, fields, constraints, security impact and migration order in a schema reconciliation note. Do not silently change D or invent alternate users/credentials tables. Defer P3–P11 business tables unless a demonstrated direct FK dependency makes early schema creation essential.
5. Resolve circular dependencies with staged migrations: create parents, add dependent nullable columns/constraints at a later migration, verify references and enforce final nullability. No disabling FK checks to force success. Controlled polymorphic approval subjects require service-level type/record checks. Inspect branch recognition and evidence references against exact D definitions.
6. Implement database standards: BIGINT UNSIGNED custom business PKs; exact Shield FK type; UUID v4 public references where specified; InnoDB/utf8mb4; UTC DATETIME(6), Africa/Lagos display; DATE for term dates; restrictive historical deletion rules; approved status checks, relational joins, proper indexes, row locks for concurrency and no sequential public identifiers. Never use app timezone as implicit storage timezone.
7. Catalogue seeder is idempotent and release-controlled, uses stable permission keys, risk/online/reason metadata and protected grant ceilings. Seed no fixed zones, branches, office holders, role profiles or operational accounts. Geographic/historic reference imports are optional, reviewed and inactive where applicable. No arbitrary permission keys through UI.
8. Establish migration/deployment account (DDL), constrained runtime account (necessary DML only), restricted audit writing/reading and backup credentials as supported. No root in public runtime. Record any hosting limitation as a gate. Define forward remediation and safe reverse-order rollback in disposable DB; never rewrite an already-applied production migration.

### Deliverables and exit gate

Versioned migrations/seeders, table/constraint manifest, schema reconciliation notes, source traceability, migration runbook, database privilege matrix, human inspection record. Demonstrate clean install, second migration run no pending changes, repeat seeder no duplication, invalid FK/duplicate/status rejection, actual Shield type matching, optional office member link, valid date/scope combinations and no real org/role seeds. Record backup and disposable rollback/reapply. API still denies business operations; no public auth. WP02 starts only after schema gate accepted.

## 5. P2-WP02 — Authentication, scoped authorisation, governance and audit

**Objective:** deliver the reusable security boundary before administrative workflows become available.

### A. Identity and session lifecycle

- Integrate Shield session authenticator using official APIs verified against the locked version. Configure public self-registration false, explicitly control remember-me/magic-link options, remove or neutralise starter group authority and disallow unintended authenticator paths. Do not call Shield's full default route bundle blindly.
- Authenticated login/logout, invitation and activation, forced password change where required, recovery/reset, password change, session expiry/revocation, suspended/disabled account handling, rate limits by appropriate account/network signals, generic account enumeration-resistant results. No passwords, reset secrets, MFA seeds, session IDs or bearer tokens in logs or browser storage.
- Invitation uses unpredictable single-use time-limited token, stored hashed, linked to intended account/approved scope, atomic consumption; resend invalidates old token. Use Shield-supported facilities where appropriate. Reset/recovery invalidates affected sessions and secrets; do not invent custom password hashing. Verify delivery through an approved secure channel; local test transport is not production evidence.
- Privileged MFA required according to approved SRS policy: Committee Super Administrator, role administrators, technical administrators and later finance administrators; choose compatible mechanism and approved recovery policy, fail closed until enrolment/challenge succeeds. No pretend MFA or default bypass. If channel/mechanism approval is absent, privileged live provisioning remains blocked; use isolated fixtures for manual testing.
- Secure HttpOnly, SameSite cookies; Secure on HTTPS, documented local loopback exception; session rotation at login and privilege transition; server-enforced idle/absolute expiry policy; per-request revocation/suspension checks and session invalidation across devices. Reauthenticate high-risk operations as appropriate. Document session timing policy rather than silently invent business rules.
- CSRF for every state-changing cookie-authenticated request including login/logout/activation; consistent JSON error/refresh contract, no mutation via GET, no broad exclusions. Do not automatically replay uncertain mutating requests. Disable authenticated page caching, private SW caching, debug leakage, unsafe CORS and open redirects. Use explicit routes and sanitised exception responses.

### B. Dynamic scoped authorisation

- Implement central permission decision service and route guards; deny by default and evaluate active user, MFA state, active permission/role, assignment status, effective UTC time, correct scope and resource ownership from database. For `global` require null scope IDs, `zone` valid zone with no branch, `branch` valid branch and consistent parent zone if present. Global grant does not extend Northern Zone operational mandate or imply unrestricted business authority.
- Permission and scope must come from the same valid grant. Multiple grants are unioned only as authorised tuples; never combine broad scope from one with a permission from another. Apply scope to query lists, counts, search, detail, mutation, audit and file access. UUIDs are not access controls. Reject forged payload scope, cross-branch IDs and missing/ambiguous resource context. Recheck during sensitive writes within transactions. Deny expired/revoked/suspended assignments on next request even when UI menus are stale.
- Protect role edits as well as assignment: prevent indirect self-elevation by changing a held role, bundle or scope or using accomplice/approval paths; enforce actor grant ceiling, target ceiling, protected-role classification, independent checker where required and duplicate/overlap policy. Derive privileged classification from executable permissions, not display name. No Shield group or official office shortcuts. Developer dual membership is allowed only through independently authorised business assignment; technical surface does not inherit Committee authority.

### C. Maker-checker and audit

- Approval service: allowlisted action/subject, exact intended change bound to request, requester/approver distinct where required, unique decisions, scope/permission checks, reason, expiry, immutable decision history, lock and revalidate current target state and authority before executing once. Approval is not itself execution. Reject stale/altered/replayed approvals; preserve denied/rejected history. Routine validated branch activation by Committee Super Administrator is explicitly direct, not a mandatory maker-checker action (ENG-023).
- Implement dedicated audit and security event services from D §9. One correlation ID across request/response/events. Record actor, role/scope snapshot, action, permission, subject, outcome, reason, masked before/after, UTC time, approval reference and appropriately pseudonymised session. Use server allowlists, omit all secrets, minimise IP/user-agent under policy. Audit success, denial, auth transitions, role changes, approval, audit reads and exports; later-phase action keys stay disabled.
- Append-only audit service with restricted DB permissions where feasible; serialise hash-chain append with deterministic canonical payload and locking, verify concurrent writers and rollback. Sensitive mutation and successful audit commit together; fail closed if audit unavailable. Persist denial/failure events separately from rolled-back transaction, alert securely if DB/audit unavailable. No invented assurance of absolute immutability; independently protected checkpoints/copies in WP04. Keep business audit and security events separate; financial ledger out of scope.

### D. Secure bootstrap

- One-time local CLI setup command with explicit authorised execution, concurrency-safe completion marker, no default account/secret, no web bootstrap. Supply first technical operator and owner-designated Committee Super Administrator identity through protected input only. Create dynamic role profiles and separately authorised grants through audited setup, not fixed seeds; record actual actor/approved instruction and reason. Initial highest-grant exception strictly one-time; later protected grants follow maker-checker. No technical self-appointment, no silent recovery backdoor. Validate repeat invocation denies and partial failure can be safely recovered before completion.
- If first Committee identity/contact not yet supplied, finish engineering and use clearly labelled synthetic fixtures; real provisioning is HOLD-01. Ensure distinct technical and Committee users/permissions, safe activation and MFA. Do not lock out recovery by allowing removal of the last eligible administrator without approved recovery procedure.

### E. API integration proposal

Keep `/api/v1` envelope `success/data/meta.request_id` or `success/error/meta.request_id`. Provisional route groups: `/auth` (CSRF, login, logout, session, activation, recovery, password, MFA), `/administration` (user/role/approval), `/technical` (restricted diagnostics). Define exact verb, permission, scope resolver, validation, status, audit and feature gate in a committed route matrix before enabling; later WP03 adds org and audit endpoints. 401 unauthenticated, 403 unauthorised/CSRF/disabled, 404 unknown or policy-approved non-disclosure, 409 stale/conflict, 422 validation, 429 throttling, sanitised 5xx. Preserve current 403 gates for unreleased operations. Public health stays non-sensitive; private readiness guarded.

### Deliverables and exit gate

Auth lifecycle, central policy service, reusable approvals/audit/security services, CLI bootstrap, API contract/permission matrix, secure session/CSRF frontend client, manual evidence. Demonstrate invited account activation, expired/replayed token denial, login/MFA, password reset/change, suspension/logout/revocation, throttling, missing CSRF and unauthenticated denial; scope and grant tests with synthetic two-branch data; audit success/denial, no self-elevation, independent approval, no audit edit/delete, audit failure rollback. No generic unauthorised CRUD is exposed. WP03 begins only after these controls pass.

## 6. P2-WP03 — Organisation and administrative management

**Objective:** convert accepted P1 workspace designs into secure, usable P2 administration, with operational data created through authorised workflows.

### Functional delivery

| Work area | P2 functionality |
|---|---|
| Organisation | Zone reference management, geography and approved coverage; validate locality/state/FCT, distinguish geography from command authority, limit operational scope to approved Northern Zone |
| Branches | Search/pagination, create pending/inactive, edit and record recognition/location evidence; protected activation-ready service and UI with direct Committee Super Administrator authority; real branch activation/completeness remains P3 gate |
| Official offices | Catalogue, scope and terms, holder name snapshots, nullable member link, appointment reference/evidence, overlap prevention with transactions; no implicit authentication or role grant; later link requires reviewed action |
| Roles | Create/edit/deactivate dynamic role profiles, choose release-defined permissions within grant ceiling, inspect effective permissions, assign with scope/start/expiry/reason, approve and revoke; no arbitrary permissions or role-ID constants |
| Accounts | Invite/provision authorised operators, activation status, suspend/revoke sessions, recovery workflow, audit; no public member registration |
| Approvals | Pending queue, detail of proposed change, independent decision with reason/status, expiry and execution result; no self-approval |
| Audit | Protected list, filters (actor, time, scope, category, subject, outcome, severity, approval), paginated safe read, dedicated export permission, trace every search/export; synthetic data only until live processing approved |
| Technical workspace | Restricted health, release/version, migration/backup status, security monitoring and approved provisioning support. Technical operational commands remain CLI/runbook controlled; no arbitrary shell/SQL, env dump, secret disclosure or Committee impersonation |

For branch recognition, master plan P2 requires protected admin workflows while P3 owns live directory and activation. P2 must prove the service with synthetic data and preserve the activation hold for real records. Do not auto-activate optional imported constitutional directory. No hardcoded Chairman identity or fixed zone IDs. Routine direct activation is not escalated to two approvers. Draft privacy and file hooks integrate with WP04; no unapproved upload or notice publication before that package.

### Frontend and integration approach

- Retain official logo, SVG sprite, design tokens, mobile bottom nav, desktop sidebar, native router and locally hosted dependencies. Distinguish public previews from real protected routes; logged-out or unauthorised deep links deny/redirect safely. Public member area remains informational; synthetic member identity can sign in but cannot access canonical claim/profile flows until P4.
- Use central session context from server; menus based on backend-effective capabilities and scope; server checks remain authoritative. Add native same-origin Fetch mutations with CSRF, structured field errors, safe 401/403/409/429 handling, timeouts and no automatic replay of writes. No localStorage/IndexedDB credentials or private records; clear sensitive view state on logout and account switch, prevent history/back-navigation disclosure, exclude protected dynamic routes/API from SW and HTTP caching. Bump worker version when public assets change.
- Provide progressive forms, labels, inline validation, confirmation for sensitive actions, mandatory reasons, accessible focus/error summary, pagination, loading/empty/denied/error states, no fake records or hardcoded chart values. Keep touch-friendly screens and keyboard navigation. Separate technical and Committee navigation even for dual-authorised user; switch surface explicitly and audit protected actions.
- Use module services under `App\\Modules` with thin API controllers and shared security/audit infrastructure. Validate and scope every query, use transaction and record_version/concurrency strategy where specified, audit material changes; deactivation/history instead of destructive delete. DB/policy checks precede any write. Validate import preview and explicit confirmation if optional import implemented.

### Deliverables and exit gate

Working protected screens/API for P2 scope, route-permission matrix, data stewardship/user guide, approved synthetic workflow fixtures (not mandatory seeds), manual mobile/desktop evidence. Accept only when authorised operations persist and audit correctly, unauthorized deep links/API calls fail, branch A cannot see branch B even in lists/counts or audit exports, name-only Chairman gets no privileges, official office and role independent, dual technical/business user has no implicit crossover, role revocation immediate, stale writes conflict safely. P3 membership intake remains disabled.

## 7. P2-WP04 — Privacy, private storage, recovery and end-to-end acceptance

**Objective:** complete supporting P2 security infrastructure and verify the integrated phase on local and supported staging environments.

### A. Account-link and privacy foundation

- Verify D §§5.2–5.3 member link/claim tables, FK types, status transitions, history, token hash uniqueness and expiry. Build protected internal domain invariants only: at most one active user per member and one active member per user via transaction and locked parent rows; optional generated unique active keys only where compatible. Verified member prerequisite for ordinary claims; no creation of duplicate members or bypass of branch review. Demonstrate with isolated synthetic fixtures; no public claim/create/link flow in P2.
- Privacy notice versioning from D §8.1: draft preparation, authorised approval/effective/retired records, immutable published versions; Committee Super Administrator designated approver (ENG-029). Preserve exact schema/approved draft-state mechanism; if schema lacks draft fields, document safe staging design, do not treat a draft as published. No live notices/collection until P3 approval of content, lawful basis and registration rules. No real member processing, retention policy invention or unapproved data exports.

### B. Private file foundation

- `file_assets` metadata, cryptographically random storage key, checksum, validated content/MIME/extension/size, classification, scan state, uploader/time and logical deletion. Private bytes outside `public/`; implement authenticated encryption at rest with key/version management and integrity verification, keys outside Git and independent backup protection. Record any additional metadata required by encryption design explicitly.
- Approved file types and size limits by purpose; deny executable/unsafe content, path traversal, filename spoofing, MIME mismatch and oversized files. Quarantine pending/rejected or unverifiable content under approved policy; no automatic public serving. Scoped authorised retrieval through controller, safe Content-Disposition/Content-Type, no storage path leakage or public URL; CSRF on mutations. Coordinate metadata and bytes with cleanup/compensating actions to handle failures. Log access and changes without raw contents. Test with harmless synthetic files; no real member document upload until approved later gate.

### C. Recovery, tamper evidence and staging

- Database backup and restore runbook: restricted backup account, encrypted DB and filesystem/config backups, consistent snapshot, key custody separate, verify checksums, access logs, rotation and retention per approved policy. Restore to isolated target, verify migrations, row counts/constraints, login, permission/audit continuity and file decryptability. Record measured restore time and recovery point; do not assert unapproved RTO/RPO. No restore-over-live operation; production restore later requires approvals.
- Audit checkpoint generation, verify chain and event range/count; store protected digest/copy separately in IEDPU-controlled location, document custody and verification. Simulate alteration only in disposable restore copy; never mutate source audit trail to test. Failures and maintenance audited. No claim of invulnerability if host controls every copy.
- Supported local/staging data integrity, HTTPS and cookie/CSRF checks, DB least privilege, document root `public/`, private storage, no debug output, no exposed credentials/logs, audit privileges, approved delivery channel for invite/reset/MFA, backup/restore evidence. Ensure no P1 visual or PWA regression and no private offline caching. Hosting limitations must be documented and resolved before applicable gate; actual staging evidence is required for P2 exit, no production activation.

### Deliverables and exit gate

Private storage and notice foundation, account linking invariant checks, backup/restore and checkpoint runbook/results, consolidated manual verification and defect register, deployment/rollback instructions, P2 acceptance and P3 handover. No unresolved critical/high security defects or missing mandatory evidence. All disabled capabilities remain fail-closed.

## 8. End-to-end manual acceptance matrix

Use P §5 standard record: ID, source, requirement, priority, environment/commit/browser/device, synthetic actor and preconditions, steps, expected and observed result, evidence, pass/fail/blocked, tester/date, defect and retest. No prefilled passes. Manual testing only; normal linting, diagnostics and operational backups are permitted. Use H/C priorities as in the master; all P2-required high/critical checks must pass before P2 acceptance. P1 regression applies after UI/worker changes.

| ID | Human-executed verification | Expected | WP |
|---|---|---|---|
| DB-01 | Fresh migration and inspect Shield/custom CREATE TABLE | Actual FK types match, schema intact | 01 |
| DB-02 | Repeat migration and permission seed; inspect rows | No duplicates or required fixed org/role records | 01 |
| DB-03 | Try invalid FK, duplicate UUID, invalid scope/date/status, reverse/reapply disposable migrations | Rejected, integrity preserved | 01 |
| M-011 | Member test account and administrator login/logout | Secure session; logout denies protected request | 02/04 |
| M-012 | Logged-out protected JSON API | 401/appropriate denial, no data | 02 |
| M-013 | Branch A attempts Branch B restricted record | 403/appropriate scope denial; no leakage | 02/03 |
| M-014 | Enumerator forges membership approval | Denied; later-phase operation disabled | 02/04 |
| M-015 | Member attempts another member profile/finance | Denied, later-phase operations disabled | 02/04 |
| M-016 | Financial role fixture attempts highest grant | Denied and audited | 02/03 |
| M-017 | Technical operator tries verify/dues approval | Denied; no implicit business authority | 02/03 |
| M-018 | Expire role and retry | Permission no longer effective | 02 |
| M-019 | Revoke role and retry without page reload | Immediate subsequent-request denial | 02/03 |
| M-020 | Assign official Chairman office only, try approval | Office gives no portal privilege | 03 |
| M-021 | Grant portal role without official office | Scoped portal action works; office unchanged | 03 |
| M-022 | Protected privilege maker attempts own approval; independent checker reviews | Self-approval denied; valid independent decision required | 02/03 |
| M-023 | Password change/reset/session revocation | Correct transitions; tokens not exposed | 02 |
| M-024 | Inspect success and denial events | Full masked actor/permission/scope/target/outcome/time | 02/03 |
| M-025 | Attempt ordinary audit update/delete | Denied, no modification | 02/03 |
| M-026 | Authorised audit search/export, then inspect trail; try without export grant | Read/export audited, unauthorised export denied | 03 |
| SEC-01 | Missing/invalid CSRF, wrong verbs, unexpected content type, forged scope/id | No protected mutation; safe JSON response | 02 |
| SEC-02 | Invite/reset expiry/replay, login throttling, suspension, MFA challenge/recovery | Secure lifecycle, no enumeration/bypass | 02 |
| SEC-03 | Change held role bundle, combine unrelated grant scopes, revoke during stale UI | No self-elevation or scope mixing | 02/03 |
| SEC-04 | Re-run bootstrap, induce partial failure, attempt unauthorised provisioning | One-time and recoverable without backdoor | 02 |
| SEC-05 | Alter or replay approved intent; concurrent approvals/audit writes; force audit failure | No duplicate action; chain valid; business rollback on failure | 02/04 |
| ORG-01 | Create inactive branch, optional reference import, check coverage and authorised activation fixture | No auto-activation; correct direct authorised action and audit | 03 |
| ORG-02 | Overlapping terms and name-only office holder | Overlap rejected; no implicit login/permission | 03 |
| ORG-03 | Search, lists, counts, details, exports across scope | All scoped consistently | 03 |
| LINK-01 | Attempt second active link, revoked history, unverified self-claim | Duplicate/refused claim; history preserved | 04 |
| PRIV-01 | Draft/approve/version notice in test environment | No unapproved live publication; published version preserved | 04 |
| FILE-01 | MIME spoof, traversal, oversized file, unauthorised read, scan pending | Rejected/quarantined; storage never directly exposed | 04 |
| FILE-02 | Encrypt/read test file, corrupt ciphertext, inspect storage and logs | Integrity enforced; authorised read only; no keys/paths leaked | 04 |
| REC-01 | Isolated restore and checkpoint verification | Restored integrity, matching counts, valid chain and file read | 04 |
| PWA-01 | Logout/back/refresh, offline protected routes, Cache Storage/IndexedDB/localStorage inspection | No private content or secrets persisted; public offline works | 04 |
| UX-01 | Phone/desktop, keyboard, labels, direct links, denied/empty/loading/error states | Responsive and accessible, accepted P1 branding retained | 03/04 |
| HOST-01 | Actual staging HTTPS, DB grants, webroot, headers, backup/channel availability | Supported and secure; blockers reported, not assumed | 04 |
| GATE-01 | Attempt registration, verification, live claim, finance, IDs, announcements and offline mutations | Remain unavailable on server, not just hidden UI | 04 |

M-013–015/017 concern later business routes as well as P2 access policy. Verify disabled routes now and exercise the same policy on actual P2 protected resources with synthetic data; record deferred functional retests in P3/P4/P8/P9. Do not pretend a disabled route proves future record-level authorisation. No temporary public test endpoints. Use restricted fixtures/manual CLI or protected real P2 actions.

## 9. End-to-end scenario and definition of done

On isolated data: migrate fresh DB → provision distinct technical and Committee identities under audited bootstrap → activate and MFA → Committee creates test zone, coverage, inactive branches and office catalogue → record name-only holder → create dynamic role from permission catalogue → request branch-scoped grant and obtain independent approval where required → invite/activate operator → verify own scope and deny other branch → revoke assignment and immediately retry → inspect permitted audit search/export and denied export → verify technical isolation → exercise notice/file/link foundations with synthetic fixtures → backup, restore and verify hash checkpoint → verify public PWA and disabled features on staging.

WP acceptance requires source commits, implementation notes, manual records, review of defects and explicit owner acceptance before next package. P2 exit requires all four accepted, all P2-required critical/high checks passed on applicable environments, staging actual migration/security/recovery evidence, traceability to 12 P2 activities, no mandatory holds disguised as passes, no secrets or real member data in repo, no unapproved live capability. Owner acceptance and merge authorisation recorded separately from any public production launch. P3 handover includes P2 limitations, remaining holds, current schema/catalogue and API contracts. No unnecessary new contract approval.

## 10. Delivery management, risk and handover

Use one proposed branch `feature/p2-database-auth-governance` from verified P1 main, one draft PR for WP01–WP04, sequential commits with `feat/fix/docs/build(p2-wpXX)` messages. Plan approval precedes code changes. Commit this plan to `docs/implementation/P2/P2_Strategic_Implementation_Plan_v1.0.md` after approval; each package gets implementation and manual verification record. Preserve P1 baseline and no unreviewed dependency changes. Pull locally with fast-forward only, review `git status`, do not commit `.env`, Composer vendor, database dumps, keys, invitation artifacts or real member data. Before merge record accepted commit and check no uncommitted required assets. If P1 main advances, reconcile before P2 branch creation.

| Risk/hold | Treatment |
|---|---|
| First Committee Super Administrator identity unavailable | No invented/default identity; synthetic test only; securely provision when owner supplies details |
| Exact protected-action approval matrix/MFA/recovery/channel policy unspecified | Build conservative deny-by-default mechanisms, record proposed implementation decision, obtain designated approval before activation |
| Shield FK/current DB incompatibility | Inspect installed table definitions, staged migration; no bypass of integrity |
| Shared-host DB audit privileges, TLS, PHP or backup limits | Document actual capability; alternative hosting required if mandatory controls fail |
| Public repository | Keep credentials/data out; implement repository privacy recommendation with owner, never assume private |
| Role escalation/technical crossover | Central scoped permission checks, grant ceilings, maker-checker, audited provisioning, manual adversarial tests |
| Audit tampering/failure | Atomic successful audit, protected append-only privileges and independent checkpoint; fail closed on privileged writes |
| Privacy, branch recognition, membership activation | P3/P4 holds remain; no real personal data or automatic activation |
| Stale PWA or secure session exposure | Versioned public assets only, no persistent secrets/private HTML/API, logout and offline regression checks |

Order of effort: WP01 schema inspection and migration first; WP02 is highest security complexity; WP03 interface/integration second largest; WP04 recovery and actual staging acceptance. Do not attach unsupported calendar estimates; establish actual duration after schema and host capability review. Project owner provides approvals/identity/hosting inputs, engineers implement and document evidence, authorised IEDPU reviewer confirms business policy and validates operational workflows. Actual hosting access and secure delivery credentials are supplied separately through approved channels only.

### Traceability closure (master P2 activities)

| Activity | Evidence package |
|---|---|
| 1 Shield official migrations | WP01 DB-01 |
| 2 Organisation and geography protected workflows | WP01/WP03 ORG-01/03 |
| 3 Dynamic scoped roles and protected grants | WP01/WP02/WP03 M-016/018/019/022, SEC-03 |
| 4 Independent official offices | WP01/WP03 M-020/021, ORG-02 |
| 5 Reusable approvals | WP02/WP03 M-022, SEC-05 |
| 6 Audit and security events | WP02/WP03/WP04 M-024–026, REC-01 |
| 7 Shield authentication lifecycle | WP02 M-011/023, SEC-02/04 |
| 8 Backend scope enforcement | WP02/WP03 M-012–019, ORG-03 |
| 9 Account link/claim foundation | WP01/WP04 LINK-01 |
| 10 Privacy/private-file foundation | WP01/WP04 PRIV-01, FILE-01/02 |
| 11 Migration integrity | WP01 DB-01–03 |
| 12 Backup/restore/checkpoints | WP01 baseline, WP04 REC-01/HOST-01 |

**Completion:** P2 is the secure identity, organisational administration and governance foundation; P3 is the next membership intake release increment. P2 approval does not authorise P3 registration, production personal-data processing or any later-phase feature.