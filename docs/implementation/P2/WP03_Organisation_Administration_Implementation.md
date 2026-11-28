# P2-WP03 — Organisation and Administrative Management

**Status:** STARTED — first read-only, security-gated vertical slice committed; **NOT owner-accepted or complete**. The previous local verification confirmed MFA configuration and a one-event audit chain, **not** the remaining P2-WP02 account lifecycle, independent approvals, role bootstrap and revocation gates.

**Authority:** `P2_Strategic_Implementation_Plan_v1.0.md` §6; `IEDPU_North_Zone_Strategic_Phase_Implementation_Plan.md`; `Database_schema_Design.md`; P0 Engineering Decision Register. Do not weaken the WP02 completion gate or enable any unapproved live administrative mutations.

## Implemented first increment (read-only)

1. `GET /administration/workspace`: server-side Shield session, active account, verified identifier, active role, successful TOTP and at least one relevant permission are all required before returning protected HTML. Denies 401/403, no private HTML cache, restrictive CSP.
2. `GET /api/v1/administration/directory/{zones|branches|offices|roles|permissions}?page=1&per_page=20`: JSON; 401 anonymous or missing MFA; 403 insufficient permission; 422 invalid pagination; 404 unknown directory; 503 audit/DB failure. No create/edit/delete verbs enabled.
3. `ScopedDirectory`: list and count filtered by the **same active assignment tuple** and release-defined permission. Global, zone and branch grants are checked in the same SQL EXISTS clause; never merge scope from one role with permission from another. Zone and branch lists include only active project-scope zones, including inactive/pending branches visible to authorised administrators. Page size maximum 50; no unrestricted counts or broad member data.
4. Protected global-only catalogues: official office catalogue (`offices.view`), role profiles (`roles.view`) and permission keys (`permissions.view`). Office holders are NOT shown; official office never implies portal privilege.
5. Every successful protected directory read and workspace view is appended to the audit hash chain before returning data. Denied API reads record best-effort security telemetry. Sensitive error responses omit SQL, credentials, personal identifiers and environment variables.
6. Local, responsive, keyboard-accessible directory workspace with server-authorised tabs, pagination, empty/denied/error states and no storage of private records in localStorage/IndexedDB or service worker. This is a **working read-only foundation**, not a mock administrative CRUD workflow.

## Binding holds — not delivered

- **WP02 security dependencies:** independently authorised Committee Super Administrator and technical bootstrap, account invitation/activation and possession verification, password reset and controlled privileged recovery, independent approval execution, account/session revocation and full privilege-ceiling checks.
- **WP03 writes:** zone/coverage creation and edits; branch create/edit/validation/activation; official office catalogue/appointment management; role creation, permission assignment, scoped role grants/revocation; invitation UI; approval queue and decisions; protected audit search/export; technical diagnostics workspace. These must not be enabled until the prerequisite WP02 controls are implemented and manually verified.
- Real branch activation is still subject to the P3 recognition and completeness gate. No imported constitutional branch is automatically activated. No hardcoded Committee or Chairman identity. No member registration, finance or official ID issuance.

## Local manual verification

1. Update local branch: `git switch feature/p2-database-auth-governance`, then `git pull --ff-only origin feature/p2-database-auth-governance`. No migration is introduced in this increment.
2. Run `php spark iedpu:p2-auth-diagnose` and `php spark iedpu:p2-audit-verify`; inspect the output. Run Spark as usual.
3. **Unauthenticated** browser: `/administration/workspace` redirects to sign-in; `/api/v1/administration/directory/zones` returns 401 JSON.
4. **Existing synthetic MFA fixture with zero-permission role:** complete TOTP and sign in. `/administration/workspace` returns 403; `/api/v1/administration/directory/branches` returns 403. This is the expected secure result; **do not manually grant it administrator permissions** to bypass the missing WP02 governance gate.
5. `POST /api/v1/administration/directory/branches` and `POST /api/v1/branches` remain disabled (403). Public Constitution and P1 preview routes continue working.
6. Inspect browser Network/Storage: no cached authenticated directory responses, no external scripts, no directory data in localStorage/IndexedDB, no unauthorised personal data. Observe layout at 320px and 200% zoom.
7. Run `php spark iedpu:p2-audit-verify` again. The zero-permission fixture is not expected to produce a successful directory-view audit event because access was denied.
8. **Authorised positive and cross-scope tests remain on HOLD** until independent administrator bootstrap, scoped grants and approvals are implemented in WP02. Once those prerequisites are accepted, manually create two synthetic branches in different zones and compare counts/lists for a branch-only role, zone-only role, global role, revoked role, and role without the required permission. Audit search/export and all mutation scenarios remain future increments.

## WP03 completion gate

Do not mark WP03 complete until protected administration CRUD, independent approval, audited branch activation, official-office independence, role/grant ceiling, scope isolation for all lists/counts/details/exports, technical separation, invitations and account revocation, manual desktop/mobile evidence and owner sign-off have passed. **This first increment is not an alternative to those gates.**

## Owner manual acceptance — 21 November 2026

**Owner-reported outcome: ACCEPTED — first read-only WP03 increment only.**

The project owner confirms that they ran the local manual verification described above, checked the observable results, and found exactly the expected behaviour. The owner explicitly accepted the results.

**Evidence boundary:** This records the owner's direct report; no screenshots, local log exports, or execution transcript were supplied with the acceptance message. In particular, the accepted negative-access behaviour of the synthetic zero-permission fixture is **not** evidence of a working privileged administrator role or a successful scoped positive-access workflow.

**Release status unchanged:** WP03 is IN PROGRESS; WP02 outstanding security prerequisites, the protected mutation endpoints, real account bootstrap, approval workflows, cross-scope positive cases, and WP04 remain unaccepted. PR #3 remains draft and must not be merged or deployed to live users based on this increment.


## Invitation workspace integration update — local acceptance (28 November 2026)

A protected Invitation Management page is now implemented at `/administration/invitations`, with a top-bar navigation link. It provides the maker request form, independent checker approval/rejection queue, maker's request status and issue action, plus a disposable-database-only local activation-token preview. The owner completed and confirmed the eight-step Officer A / Officer B / User 2 browser acceptance sequence; see [P2-WP02 Invitation Browser Acceptance](P2_WP02_Invitation_Browser_Acceptance_2026-11-28.md).

This closes the invitation UI gap identified during local acceptance. It does **not** complete WP03: zone/branch/office CRUD, scoped role and grant administration, audit search/export, technical workspace, full cross-scope positive/negative matrix and owner acceptance remain outstanding. Production invitation delivery and real Committee Super Administrator provisioning remain separately gated.
