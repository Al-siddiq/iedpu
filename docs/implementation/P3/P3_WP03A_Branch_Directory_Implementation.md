# P3-WP03-A — Branch Directory and Organisational Management

**Status:** IMPLEMENTED IN DEVELOPMENT BRANCH — 28 November 2026  
**Branch:** `feature/p3-membership-intake`

## Delivered

- Added `branch_change_history` migration. Every create, edit, activation and deactivation stores the actor, reason, timestamp and before/after record snapshot.
- Added `BranchDirectoryManager` using the existing `branches`, `org_zones` and `geo_states` tables. No duplicate branch master table was introduced.
- Added protected branch API for list/reference data, create, update, activate, deactivate and history.
- Added a protected browser workspace at `/administration/branches` with responsive branch form, record list, status actions and history view.
- Added navigation from the portal top bar.
- Create/update requires `branches.manage`; activation requires the separate `branches.activate` permission. All actions also require an authenticated, current, MFA-verified session.
- New branches are always created inactive. Activation is refused unless recognition is approved and the parent project zone is active.
- Office-holder names and portal-role assignments are not created or modified by branch directory operations.

## Important architecture decisions

The existing P2 permission catalogue already defines `branches.manage` and `branches.activate`; no new permission key or grant was fabricated. Access still depends on an existing active role assignment containing the relevant permission. If the signed-in test administrator lacks that grant, the UI correctly returns an access-denied response; do not bypass the check.

The branch service uses the existing global Committee permission model because the owner-approved P3 plan assigns branch directory administration and activation to the Committee Super Administrator. Branch-scoped branch staff cannot manage the global directory.

## Manual local verification

1. On a disposable local database, apply migrations with `php spark migrate`.
2. Confirm `branch_change_history` exists.
3. Sign in with a synthetic Committee test account that already has `branches.manage` and `branches.activate`, with MFA completed.
4. Open `/administration/branches`.
5. Create a branch in an active project zone; confirm it is inactive and appears in the list.
6. Try activating it while recognition is pending; activation must be denied.
7. Edit the branch and confirm the history contains the before/after snapshots, actor and reason.
8. Set recognition to approved with its reference/date; save, then activate.
9. Deactivate and confirm the history records the status transition.
10. Sign in as an account without the permissions and confirm the page/API denies access.
11. Run `php spark iedpu:p2-audit-verify` and confirm the audit chain verifies.

Use synthetic records only. Do not activate a real branch or collect real member data in this development increment. P2 formal acceptance remains deferred; existing P2 authentication, MFA, authorisation and audit controls remain mandatory.
