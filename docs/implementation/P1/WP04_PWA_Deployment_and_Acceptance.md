# P1-WP04 — PWA, deployment readiness and final acceptance

**Status:** source implementation committed; local browser, device and hosting verification pending.
**Delivery:** PR #2, branch `feature/p1-wp01-environment-bootstrap`.
**Authority:** Strategic Phase Implementation Plan P1 items 9–10 and SRS §§5.10–5.12, 14–15, 18–19.

## 1. Delivered

- Relative-scope web manifest with standalone display, theme, and official-logo 192/512 PNGs plus a separate maskable 512 PNG. Raster generation on the development PC is pending. Installation availability varies by browser; target Android Chrome installation must be verified.
- Service worker at `public/sw.js`: version `p1-wp04-002`, local static public allowlist pre-cached at install; same-origin GET only. Network-first public navigation, static offline fallback upon network failure, no server-rendered HTML cached. Public static assets use network first with installed-cache fallback. No runtime cache writes.
- Only the public homepage and four preview paths receive the navigation fallback. API, login, registration, finance, private/admin pages, query URLs and all unlisted requests bypass worker handling. No authenticated responses or personal records are written to Cache Storage.
- Locally hosted Dexie 4.2.1, Vue 3.5.24, Bootstrap 5.3.9 with license notices. Public IndexedDB schema version 1 holds only a fixed schema marker. Private offline feature flag is false. No profile/queue schema, tokens, user data, sync or mutation operations. A module-level helper can delete this public metadata database; browser site-data controls can also clear it.
- Browser installation prompt when supported, update notice for waiting workers and user-initiated activation/reload. No unconditional skipWaiting on install. Old application caches removed by scope-specific prefix. Browser lifecycle may also activate a waiting worker after old tabs close.
- Offline page communicates that there are no submitted changes or locally stored member records. The full interactive portal is not available offline; only the safe public fallback is promised in P1.
- Apache MIME/worker revalidation guidance and removal of inherited HTTP canonical redirect. Hosting must provide canonical HTTPS redirection outside application rules.

## 2. Local run (existing XAMPP)

```bat
cd C:\xampp\htdocs\iedpu
git pull --ff-only origin feature/p1-wp01-environment-bootstrap
C:\xampp\php\php.exe spark serve --host 127.0.0.1 --port 8080
```

Open http://127.0.0.1:8080/ in Chrome or Edge. No additional dependency installation is required if the committed assets are present. Loopback supports secure-context service workers; insecure LAN HTTP on a phone does not. Use a trusted HTTPS staging origin for real phone testing.

In DevTools > Application, inspect Manifest, Service Workers, Cache Storage and IndexedDB. Wait until the worker activates, reload online once and confirm the page is controlled. Try browser install menu or the portal install prompt if offered. Under Network choose Offline, then reload the homepage or a preview URL; expect the static offline page. Restore Online and use its return link. Do not expect offline access on a first-ever visit.

## 3. WP04 manual verification

Mark each actual observation with date, browser/device, environment, reviewer and pass/fail; blank means not verified.

| ID | Check | Expected |
|---|---|---|
| M01 | Manifest in DevTools | Correct name, start URL, scope, standalone and icons; no manifest errors |
| M02 | Worker | Installed, activated, controls page after reload, correct scope |
| M03 | Install | Supported desktop and target Android browser install and launch; record unsupported browsers separately |
| M04 | Offline after first online visit | Public homepage and preview navigation fall back to offline.html; CSS/icon loads |
| M05 | Reconnect | Offline page return link loads homepage online |
| M06 | Offline API and protected paths | No cached API response or private dashboard; offline request fails normally |
| M07 | Cache inspection | Only exact manifest/static public allowlist; no dynamic HTML, tokens, secrets, personal data |
| M08 | IndexedDB inspection | Public metadata DB, schema marker only, no accounts/queues |
| M09 | Update | On isolated test deployment bump VERSION and alter a harmless public static file; old worker stays active while waiting; explicit update prompts and reloads; obsolete cache removed |
| M10 | Multiple tabs | Update only when convenient; old tabs may need refresh; no real operations present |
| M11 | No network first visit | No claim of offline availability until installed |
| M12 | Clear storage | Clear site data via DevTools, reload online; public-only cache and marker recreated |
| M13 | Local vendor requests | All Vue, Bootstrap and Dexie requests same origin, expected versions |
| M14 | Staging HTTPS | Trusted certificate, canonical HTTPS, correct baseURL and worker scope; no mixed content |
| M15 | Public root | App, .env, composer files, vendor and writable private content inaccessible |
| M16 | Rollback drill | Previous reviewed artifact/config restored on isolated staging, worker version advanced or worker cleared, routes/assets healthy |

## 4. Consolidated P1 exit

| Work package | Evidence currently available | Remaining acceptance |
|---|---|---|
| WP01 | Composer lock committed; owner reports local bootstrap successful | Record original environment, DB, headers and public-root checks |
| WP02 | Owner reports expected 200/200/403/404 API smoke results | Complete remaining WP02 security/manual checks |
| WP03 | Owner likes the interface; Vue 3.5.24 vendor file present on branch | Complete responsive, accessibility, navigation, direct-link and network checklist |
| WP04 | Source and local dependencies committed | M01–M16; actual hosting capability review and deployment approval |

No source inspection constitutes a human browser or staging test. No automated test suite introduced. No live personal information introduced. The phase exits only when manual evidence and deployment method are approved. PR #2 remains draft until then.

## 5. Update and rollback operating rules

Bump the worker VERSION for every shell/static allowlist change; never reuse a live cache version. Deploy an internally consistent release (prefer atomic directory/symlink switch), not individual asset changes on live service. Public caches are scope-isolated; do not delete unrelated origins or caches. If rollback is needed, publish previous reviewed source with a fresh worker version so clients can update; isolated browser site data can be cleared for recovery. Future private offline data will need explicit retention, consent, logout/device cleanup, encryption review and schema-preserving updates before activation.

See `P1_Hosting_and_Deployment_Runbook.md` for the deployment gate.


## 6. Owner-requested final branding refinement

Bootstrap Icons 1.13.1 selected SVG symbols are self-hosted in public/assets/icons/bootstrap-icons.svg with MIT license; the public preview navigation, cards, notices and actions use SVG rather than decorative glyphs. The official public/assets/IEDPU.png is used unchanged in the header and offline page. No CDN or icon font needed.

The GitHub connection cannot read the binary PNG for raster conversion. Run the committed Windows generator before opening the latest PWA:

```bat
powershell -ExecutionPolicy Bypass -File scripts/generate-brand-assets.ps1
git add public/assets/icons/favicon-16.png public/assets/icons/favicon-32.png public/assets/icons/favicon-48.png public/assets/icons/apple-touch-icon.png public/assets/icons/portal-192.png public/assets/icons/portal-512.png public/assets/icons/portal-maskable-512.png public/favicon.ico
git commit -m "build(p1): commit official IEDPU PWA and favicon assets"
git push origin feature/p1-wp01-environment-bootstrap
```

The script creates 7 PNGs plus a multi-resolution ICO using proportional contain sizing and white backing; maskable artwork fits inside the safe circle. All eight are required for the updated worker to install. Regenerate when the official source changes and bump the worker version. Verify dimensions, appearance, maskable crop preview, mobile/desktop navigation icons, local sprite network request, offline logo, cache contents and installed icon after update. Existing installed shortcuts may require uninstall/reinstall to show a changed OS icon. Record actual results before P1 closure. No private data caching changes.
