# P1-WP02 — Backend architecture, API contract and security boundaries

**Project:** IEDPU North Zone Digital Portal
**Baseline:** P1 Strategic Implementation Plan v1.0, section 2, WP02
**Branch:** `feature/p1-wp01-environment-bootstrap` (continuation of the single P1 delivery branch)
**Pull request:** #2, kept draft until all P1 work packages and evidence are complete
**Status:** Implementation committed; local manual verification pending

## 1. Delivered backend foundation

- Explicit same-origin `/api/v1/` route group. CodeIgniter automatic routing is disabled.
- Public `GET /api/v1/health` returns application-process status only. It does **not** claim database or downstream service health.
- Public `GET /api/v1/bootstrap` returns truthful `false` flags for not-yet-implemented P2+ functions.
- Reusable JSON success/error envelopes with safe request correlation IDs, status codes, `Cache-Control: no-store, private` and `X-Content-Type-Options: nosniff`.
- Shared JSON object parser and strict required-text-field validation helper for future authorised write endpoints. No P1 mutation endpoint uses it.
- Explicit `403 feature_not_enabled` responses for known disabled business prefixes and `404 not_found` for unknown `/api/v1/` paths.
- **Removed the default Shield route registration** from `app/Config/Routes.php`. Installing Shield in WP01 must not expose self-registration, login or account administration before P2 identity/governance controls are ready.
- Global secure HTTP response headers enabled; common credentials marked sensitive for CI4 debug traces.
- Modular-monolith domain ownership and dependency rules documented in `app/Modules/README.md`.

## 2. JSON contract

Successful response:

```json
{
  "success": true,
  "data": {
    "status": "ok",
    "service": "iedpu-north-zone-portal",
    "api_version": "v1",
    "database_checked": false
  },
  "meta": {
    "request_id": "server-generated-32-hex-character-id"
  }
}
```

Denied response:

```json
{
  "success": false,
  "error": {
    "code": "feature_not_enabled",
    "message": "This function is not available at the current implementation stage."
  },
  "meta": {
    "request_id": "server-generated-32-hex-character-id"
  }
}
```

The `X-Request-ID` response header matches `meta.request_id`. A caller-supplied
`X-Request-ID` is accepted only when it is 8–64 characters of letters,
numbers, periods, underscores or hyphens; other values are replaced.
No response includes PHP version, environment variables, SQL credentials,
session state, user records, exception traces or filesystem paths.

## 3. Public and disabled endpoints

| Method | Path | Expected HTTP status | Contract |
|---|---|---|---|
| GET | `/api/v1/health` | 200 | `success=true`, process alive; `database_checked=false` |
| GET | `/api/v1/bootstrap` | 200 | `success=true`, all P2+ capabilities disabled |
| Any | `/api/v1/registration` | 403 | `feature_not_enabled` |
| Any | `/api/v1/members` and nested paths | 403 | `feature_not_enabled` |
| Any | `/api/v1/verification`, `branches`, `offices`, `administration` | 403 | `feature_not_enabled` |
| Any | `/api/v1/technical`, `finance`, `payments`, `official-ids`, `announcements` | 403 | `feature_not_enabled` |
| Any | Unknown `/api/v1/...` | 404 | `not_found` |
| GET | `/register` and `/login` | 404 | Shield default routes not mounted |

`/api/v1/bootstrap` is a capability report, **not** a user/session bootstrap
or a substitute for P2 authentication.

## 4. Security and lifecycle boundaries

- The app's session driver, HTTP-only cookies, SameSite=Lax, HTTPS-in-production and session rotation settings remain as established in WP01.
- No active P1 API write endpoint exists. CSRF enforcement for authenticated state-changing endpoints must be enabled and manually verified in P2 before such endpoints are exposed; do not treat the currently inactive global CSRF filter as sufficient protection.
- CI4 production mode disables detailed exception output. The explicit API controllers never log request bodies, credentials or exception objects. Framework-wide unexpected-error JSON formatting and log-redaction policy require production-mode manual inspection before P1 final acceptance; do not claim that every possible framework exception is normalised.
- Unknown web paths continue to use CodeIgniter's normal web 404 behaviour; unknown API paths are explicitly JSON 404.
- A `403` disabled response is a phase gate, not proof of full P2 role-based access control.
- No organisational master records, role profiles, user identities, Shield migrations, real personal data, payment services or developer bypasses are introduced.

## 5. Manual verification — run after pulling the latest P1 branch

In one terminal:

```bat
cd C:\xampp\htdocs\iedpu
C:\xampp\php\php.exe spark routes
C:\xampp\php\php.exe spark serve --host 127.0.0.1 --port 8080
```

In a second terminal:

```bat
curl.exe -i http://127.0.0.1:8080/api/v1/health
curl.exe -i http://127.0.0.1:8080/api/v1/bootstrap
curl.exe -i -X POST http://127.0.0.1:8080/api/v1/registration
curl.exe -i http://127.0.0.1:8080/api/v1/branches/example
curl.exe -i http://127.0.0.1:8080/api/v1/unknown
curl.exe -i http://127.0.0.1:8080/register
curl.exe -i http://127.0.0.1:8080/login
curl.exe -i -H "X-Request-ID: wp02-manual-001" http://127.0.0.1:8080/api/v1/health
```

| ID | Human check | Pass criterion |
|---|---|---|
| WP02-M01 | Route list | Only declared public API endpoints; no Shield register/login routes |
| WP02-M02 | Health | HTTP 200, JSON, `database_checked=false`, no environment details |
| WP02-M03 | Bootstrap | HTTP 200, all deferred capabilities false |
| WP02-M04 | Denied write | POST registration returns 403, no database mutation |
| WP02-M05 | Denied nested path | Branch path returns 403, no administrative action |
| WP02-M06 | Unknown API path | HTTP 404 JSON with `not_found` |
| WP02-M07 | Shield route boundary | `/register` and `/login` are not accessible |
| WP02-M08 | Correlation/header checks | Header ID matches JSON meta; valid supplied ID echoed; no-store and nosniff present |
| WP02-M09 | Production-mode errors | With a safe isolated staging configuration, no stack traces or secrets in error responses or debug tooling |
| WP02-M10 | Protected webroot | `.env`, `app/`, `vendor/` are not served from the configured `public/` root |

Record actual results, environment, reviewer and date. Do not mark any unrun
manual check as passed. No automated test suite is authorised.

## 6. Remaining work and next package

WP02 source implementation is committed, but its exit gate remains pending
until WP02-M01–M10 are manually verified. WP03 implements locally hosted
Vue 3 and Bootstrap 5.3.9, five responsive shells, the browser router,
Fetch client and mobile-first design system. P1-WP04 handles PWA, staging
deployment and end-to-end manual acceptance.

**Do not merge PR #2 into main yet.** The approved P1 plan requires all
four work packages and project-owner review before merge.
