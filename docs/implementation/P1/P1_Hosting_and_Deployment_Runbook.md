# P1 hosting assessment, deployment and rollback

No staging host, credentials or production domain has been provided or accessed. This runbook defines the evaluation, not a claim that a hosting provider has passed.

## Hosting decision gate

| Requirement | Acceptance evidence |
|---|---|
| Supported security-patched PHP | Host PHP CLI and web runtime versions; Composer platform requirements pass. PHP 8.2.4 local exception is NOT valid for public staging or production. |
| PHP extensions | intl, mbstring, mysqli, openssl, fileinfo, curl and any additional locked requirements |
| Database | Supported MySQL/MariaDB, InnoDB, utf8mb4; isolated staging database/account, privileges and read-only connectivity verified |
| HTTPS | Trusted cert, enforced HTTPS without HTTP downgrade, renewal plan, correct canonical hostname |
| Document root | Only project public/ is served. app, vendor, writable, .env, composer and Git metadata outside document root |
| Private storage | Separate nonpublic writable storage, permissions limited to service account, backup and restore support |
| Routing | Rewrite support, direct preview URLs, API status and static assets; correct MIME for manifest/JS and worker scope |
| Scheduled tasks | Host cron/CLI capability documented; no jobs activated in P1 |
| Callback reachability | Future provider HTTPS callbacks feasible, no finance callback enabled now |
| Operations | Access control, logging, backup/restore, deployment versioning and rollback, separate staging/prod credentials |

If shared hosting fails any mandatory SRS requirement, change host before live data. Never put the entire repository under public_html. If a host cannot set document root to public/, seek an approved layout that physically separates nonpublic project content; otherwise reject the host.

## Staging deployment (manual, no real data)

1. Select host and record the checklist above. Confirm staging domain and isolated credentials using a secure channel; never paste them into GitHub.
2. Prepare a reviewed release directory from a recorded commit. Install using `composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader`, then `composer check-platform-reqs --no-dev` and `composer audit --no-dev`. Investigate any failure or advisory before deployment; do not ignore platform requirements.
3. Copy .env.staging.example to private .env; set real staging HTTPS baseURL (trailing slash), DB credentials, secure cookies and CI_ENVIRONMENT=production. Generate a unique encryption key securely using the WP01 procedure; never overwrite an existing release key.
4. Set document root to release/public; ensure only appropriate writable paths are writable. Configure TLS canonical redirects, no directory browsing, MIME and worker revalidation. Keep staging access restricted as appropriate; if HTTP authentication is used, verify public pre-cache can fetch assets without application credentials. Do not weaken access controls merely to make PWA pass.
5. Run the WP01 preflight/database checks, spark routes, WP02 HTTP checks, then WP03/WP04 browser checks. No Shield migrations or real member data in P1.
6. Inspect production-mode 404/error output and headers, ensure debug toolbar/stack traces absent. From a browser or curl check /.env, /app/Config/App.php, /vendor/autoload.php, /composer.json, /writable/logs/ and /.git/config; none may disclose file contents. Check all under the configured base path.
7. Record commit, deployment time, environment, reviewer, outcomes, dependency audit, backup location and rollback owner. Approve deployment method before P1 merge. Actual production activation remains separate.

## Rollback

Keep the previous reviewed release artifact, environment configuration backup (secure storage), database backup and known worker version. P1 performs no schema migration, so roll back by switching to prior known-good code and retaining appropriate environment secrets. Publish a fresh worker version with the restored assets, verify HTTPS/routes/API and clear only the portal's public cache if needed. Re-run smoke checks and document incident. Later database migrations require separate migration-aware rollback planning.

## Evidence record (fill on real host)

- Host/provider and staging domain:
- Release commit / date / reviewer:
- PHP CLI/web, database and extensions:
- Composer platform and security audit:
- HTTPS / document root / private storage:
- Cron and callback capability:
- WP01–WP04 manual checklist references:
- Backup / rollback result:
- Approval or blocking issues:
