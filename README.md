# IEDPU North Zone Digital Portal

The IEDPU North Zone Digital Portal is a CodeIgniter 4 modular-monolith application with a same-origin JSON API and a Vue 3 progressive web application.

## Approved technical baseline

| Component | Project baseline |
|---|---|
| PHP | 8.5 preferred; 8.4 accepted fallback |
| Backend | CodeIgniter 4.7.6 |
| Authentication foundation | CodeIgniter Shield 1.4.1 |
| Shield settings dependency | codeigniter4/settings ^2.1, resolved in composer.lock |
| Database | MySQL-compatible / MariaDB through MySQLi; InnoDB and utf8mb4 |
| Frontend | Vue 3 global production build; no Node build chain |
| UI | Bootstrap 5.3.9 |
| Offline storage | Dexie.js / IndexedDB, only for approved low-risk data in later phases |
| Runtime | Apache with document root pointing to public/ |
| Verification | Manual verification only |

Exact package versions and transitive dependencies must be recorded in composer.lock. Do not commit vendor/ or local environment secrets.

## Requirements

- PHP 8.4 or later. PHP 8.5 is preferred.
- PHP extensions: intl, mbstring, mysqli, openssl and fileinfo.
- curl is recommended for Shield password-breach validation and outbound HTTP features.
- Composer 2.x.
- MySQL 8.x/8.4 preferred, or a compatible XAMPP MariaDB version confirmed during WP01.
- Apache rewrite support for Apache deployments.

## Local setup on Windows / XAMPP

1. Clone the repository and check out the active development branch.
2. Ensure XAMPP PHP 8.4 or later is installed.
3. Enable intl, mbstring, mysqli, openssl and fileinfo in the PHP configuration used by both CLI and Apache. Restart Apache after changing php.ini.
4. Install Composer 2.x and configure the Composer installer to use the same XAMPP PHP executable.
5. Copy .env.development.example to .env.
6. Create an empty database named iedpu_dev with character set utf8mb4 and collation utf8mb4_unicode_ci. Do not import real member data.
7. Review the local .env database host, database name, username and password.
8. Run the WP01 bootstrap script:

   powershell -ExecutionPolicy Bypass -File scripts/bootstrap-windows.ps1

   If PHP is not on PATH, provide the XAMPP executable:

   powershell -ExecutionPolicy Bypass -File scripts/bootstrap-windows.ps1 -PhpPath C:\xampp\php\php.exe

9. The script resolves the pinned framework and Shield dependencies, refreshes composer.lock, validates platform requirements, checks the installed framework, and confirms that Spark starts.
10. Start the local application:

    php spark serve --host 127.0.0.1 --port 8080

11. Open http://127.0.0.1:8080/.

The bootstrap script intentionally does not run automated tests. It performs environment and framework installation checks only.

## Environment profiles

Three reviewed templates are provided:

- .env.development.example — local development.
- .env.staging.example — staging profile; uses CI_ENVIRONMENT=production so debug output is disabled.
- .env.production.example — production profile.

Copy only the applicable template to .env on each machine. Replace every CHANGE_ME placeholder. Do not reuse database credentials or encryption keys across environments.

Generate an encryption key locally:

    php -r "echo 'hex2bin:' . bin2hex(random_bytes(32)), PHP_EOL;"

Place the generated hex2bin-prefixed value in the local .env as encryption.key. Never commit or share it.

## Database configuration

The application reads its default MySQLi connection from the machine-specific .env file. The committed config contains no database password. The default local database name is iedpu_dev.

Use a dedicated least-privilege database user for staging and production. Do not use the MySQL root account outside a disposable local XAMPP environment.

## Web server and security boundary

The Apache document root MUST point to the repository's public/ directory only. Never expose the repository root. The app/, vendor/, writable/, docs/, .env, composer.json and composer.lock must not be directly web-accessible.

Staging and production require HTTPS, secure cookies, separate database credentials, private writable storage and debug output disabled. Production deployment is not part of WP01.

## Environment diagnostics

Before Composer installation:

    php scripts/check-environment.php --preflight

After dependencies are installed:

    php scripts/check-environment.php

The full check verifies PHP extensions, environment files, writable directories, exact locked package versions and required vendor packages. It does not execute an application test suite.

## Development policy

- Follow the four foundation documents and the approved P1 work-package plan.
- Do not hardcode zones, branches, office holders, Chairmen or portal role profiles.
- Keep executable permission keys release-controlled.
- Do not add live member data or enable registration during P1.
- Do not introduce PHPUnit, automated browser tests, automated API tests or CI test suites. Use documented manual verification.
- Keep secrets and vendor/ out of Git.
- Use reviewed feature branches and pull requests for phase delivery.

## Current WP01 status

The WP01 branch contains the environment templates, environment-aware application configuration, pinned Composer manifest and Windows bootstrap procedure. The existing lockfile must be regenerated on the actual PHP 8.4+ development machine using the supplied script, and the local XAMPP runtime must pass the manual readiness checks before WP01 can be accepted.
