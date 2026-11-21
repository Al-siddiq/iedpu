# P2-WP01 — Relational Database and Persistence Foundation

Status: source committed; local and staging database execution/acceptance pending.
Authority: Database Schema Design §§2, 4–6, 7.1, 8.1, 8.4, 9, 24–26; strategic P2 activities 1–6, 9–12; owner decision P2-AUTH-01.
No routes, auth configuration, dependency lock, P1 UI or service worker changed.

## Migration manifest
Run official Shield and Settings migrations using CI4's migration runner; never manually clone vendor auth tables. Locked Shield 1.4.1 source declares users.id INT UNSIGNED; each app migration checks the actual database metadata. All 22 app tables use BIGINT UNSIGNED primary keys, InnoDB and utf8mb4_unicode_ci. No table prefix. Restrictive FK deletion/update is MySQL's default (implicit to avoid CHECK compatibility issues).
- 090001 Organisation: org_zones, geo_states, project_coverage_states, branches, emirate_ancestral_areas.
- 090002 Governance: portal_roles, portal_permissions, portal_role_permissions, approval_requests, approval_decisions, portal_user_role_assignments, official_office_catalog.
- 090003 IdentityDependencies: file_assets, privacy_notice_versions, members, member_user_links, member_account_claims, official_office_assignments.
- 090004 AccountIdentifiers: portal_login_identifiers.
- 090005 Accountability: audit_events, security_events, audit_chain_checkpoints.

Self-referencing FKs are inline; other parents precede dependents. Approval subject is a controlled polymorphic reference, not a fake FK. File metadata precedes member/office references. Application services in WP02+ must enforce scope consistency, duplicate assignments, approval separation, office term overlap, active approved branch/member status, verification, UUID generation, UTC timestamps, audit integrity and file encryption. The schema does not alone enforce those cross-row requirements. No private operations are activated.

## P2-AUTH-01 schema extension
portal_login_identifiers: BIGINT id, UUID public_id, Shield INT UNSIGNED user_id, identifier_type email/phone, normalised_value VARCHAR(190), verified_at, pending/active/revoked status, revoked_at, created_at/updated_at. Generated current_value/current_type are NULL for revoked rows. Unique (identifier_type,current_value) prevents a pending/active value belonging to multiple accounts; unique (user_id,current_type) prevents two current identifiers of one type. Retain revoked history. WP02 must expire pending reservations and implement reviewed release/transfer policy; never silently reassign a recycled phone number.
Active identifiers require verified_at and no revoked_at. At least one active verified identifier before account activation, one password per Shield user, and transactional updates are WP02 service rules. LoginIdentifier::normalise supports Nigerian mobile local, bare +234 equivalent and E.164-shaped international numbers, ASCII email canonicalised to lowercase. Format checks are not proof of number allocation or ownership. International local/trunk number conversion needs explicit country context. No synthetic email or competing password store created.
member_user_links generated active_member_id/active_user_id unique guards permit history but at most one active link per member/user; services must still lock and validate.

## Seed policy
P2PermissionCatalogueSeeder inserts 27 release-defined P2 keys, repeats without duplication, fails on metadata drift rather than changing grants. Grant domain labels are engineering metadata, not implemented authorisation. No role records, assignments, users, branches, zones, office records or real member records seeded. Future action keys added through reviewed releases. P2AncestralReferenceSeeder is an optional reviewed import of 11 constitution-derived labels, inactive pending confirmation; verify town/district classifications before import and never infer member eligibility. No mandatory geographic coverage imports.

## Local procedure (Windows XAMPP CMD)
Stop Spark; start MySQL in XAMPP. Confirm .env points only to your disposable/local development DB, not staging/production. Back up first, or use a new empty test DB. Never commit .env, SQL dumps or keys.
```bat
cd C:\xampp\htdocs\iedpu
git switch main
git pull --ff-only origin main
git fetch origin
git switch -c feature/p2-database-auth-governance --track origin/feature/p2-database-auth-governance
C:\xampp\php\php.exe spark migrate:status
C:\xampp\php\php.exe spark migrate --all
C:\xampp\php\php.exe spark migrate:status
C:\xampp\php\php.exe spark db:seed P2PermissionCatalogueSeeder
C:\xampp\php\php.exe spark iedpu:p2-schema
```
If the local branch already exists use git switch feature/p2-database-auth-governance then git pull --ff-only origin feature/p2-database-auth-governance instead of creating it.
Minimum DDL compatibility guard MySQL 8.0.16+ / MariaDB 10.4+; unsupported/end-of-life local server not approved for production. Verify strict mode, CHECK and FK enforcement on actual server. Run migrations sequentially with no concurrent deploys. If migrate --all reports an ordering error, inspect status; apply official namespaces before App via migrate -n (see spark help migrate), not by recreating auth tables. Stop on any error. MySQL DDL commits implicitly, so partial failures require a verified backup restore or a fresh disposable database; do not disable FKs or hand-edit migration history.

## Manual acceptance
Record actual outputs and version, reviewer and date. No automated test suite.
| ID | Manual inspection | Expected |
|---|---|---|
| DB-01 | Fresh migrate --all; migrate:status; inspect users.id and references with iedpu:p2-schema | All vendor/app migrations applied; 22 app tables present; INT UNSIGNED Shield FKs |
| DB-02 | Repeat migrate --all and permission seed; count portal_permissions | No pending migrations; 27 catalogue rows on fresh DB; no roles/users/zones/branches seeded |
| DB-03 | SHOW CREATE TABLE branches, portal_user_role_assignments, member_user_links, portal_login_identifiers | InnoDB, FKs, CHECK, generated unique guards |
| DB-04 | On disposable DB insert zone with invalid status (see SQL below); attempt branch with missing parent | Both rejected; no inserted records |
| DB-05 | On synthetic fixtures try duplicate active member link, duplicate current identifier, invalid scope/expiry, unverified active identifier | Rejected; revoked history can coexist |
| DB-06 | Create synthetic official office assignment with NULL member_id using valid office/actor; inspect no role assignment | Accepted independently of portal roles |
| DB-07 | Run rollback/reapply only on separate empty unseeded DB, use migration status; no data retained | Reverse dependency order, successful reapply |
| DB-08 | Backup and restore into separate disposable DB, inspect same schema/counts | Restored metadata and counts match |
| DB-09 | P1 health/bootstrap and disabled routes smoke checks | 200/200/403; registration and real auth still unavailable |
| DB-10 | Inspect optional reference import only if authorised, repeat | Exact reference spelling, no activation or privileges |

SQL examples in phpMyAdmin SQL tab, on disposable database only:
```sql
SELECT COUNT(*) FROM portal_permissions;
SELECT COUNT(*) FROM portal_roles;
SELECT COUNT(*) FROM users;
SHOW CREATE TABLE portal_login_identifiers;
SHOW CREATE TABLE member_user_links;
SHOW CREATE TABLE portal_user_role_assignments;
INSERT INTO org_zones (public_id,zone_code,zone_name,status,created_at)
VALUES (UUID(),'SYNTHETIC_INVALID','Synthetic invalid','bad_status',UTC_TIMESTAMP(6));
INSERT INTO branches (public_id,zone_id,branch_name,created_at)
VALUES (UUID(),999999999,'Synthetic invalid',UTC_TIMESTAMP(6));
```
The last two statements must fail separately. If either succeeds, stop: investigate constraint enforcement and discard disposable database. Use explicit synthetic transactional fixtures for other tests and rollback; no production data. Actual runtime, seed counts and rollback are not claimed until observed.
 
## Database privileges and backup baseline
Migration operator: isolated authorised account with schema DDL/DML necessary for vendor/app migrations; never public web account.
Runtime: SELECT/INSERT/UPDATE only as needed by table; DELETE only specific ephemeral Shield/session/token operations after WP02 review; no DDL or GRANT. On audit_events permit SELECT/INSERT, deny UPDATE/DELETE; independent checkpoint copies beyond runtime account. Seeder uses authorised deployment account. Backup account: SELECT and required dump metadata permissions; restore account on isolated database only. Exact grants depend on host and must be verified with SHOW GRANTS; never claim shared host supports per-table controls without evidence.
Before migration use phpMyAdmin export or XAMPP mysqldump to a protected directory outside webroot/repo, confirm nonempty dump, protect access, record checksum; no plaintext dumps in Git. Import into a separate empty DB via phpMyAdmin or mysql client and inspect. WP04 requires demonstrable full encrypted file/database recovery and independent audit checkpoints.
Rollback migration down refuses populated application tables; this is deliberate protection, not general destructive rollback. Test reverse only on an EMPTY unseeded disposable database, not the active dev/staging DB. Never rollback all vendor migrations with real accounts. If group fails partly restore verified backup or recreate disposable DB, since DDL auto commits.

## Exit gate
Source inspection is not execution. Local owner must return migration/seed/inspection results and manual evidence. No WP02 authentication routes or privileged grants before WP01 acceptance. Production hosting remains separately gated.
