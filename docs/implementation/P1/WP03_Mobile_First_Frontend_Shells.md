# P1-WP03 — Mobile-first frontend, local vendors and five portal shells

**Project:** IEDPU Northern Zone Digital Membership, Administration and Development Portal
**Phase:** P1 / Work Package 03 of 04
**Branch:** `feature/p1-wp01-environment-bootstrap` (continuing the approved P1 draft PR)
**Status:** Application/UI source and Bootstrap vendor assets committed; Vue vendor asset installation and owner manual verification pending.

## 1. Scope and implementation

P1-WP03 delivers the complete public-facing **visual shell foundation**, not business operations or a functional administrative login.

- Mobile-first public landing page with IEDPU branding, direct links to all four workspace previews and explicit implementation-stage messaging.
- Five spaces: **public**, **member**, **branch administration**, **Committee administration**, and a **separate technical administration** interface.
- Only `/` and `/preview/{member,branch,committee,technical}` are public in P1. These are read-only design previews. Real `/member`, `/branch`, `/committee` and `/technical` access is not mounted until P2 authentication, permissions and scope checks.
- Vue 3 **production global build**, Composition API and ordinary browser JavaScript modules; no Vite, Webpack, Node build, TypeScript, Pinia, Vue Router or Single-File Components.
- Small first-party History API router with allowlisted preview routes, direct-link refresh, Back/Forward support, base-path-aware links and unknown-route rejection.
- Native Fetch API client with same-origin URL construction, `no-store`, abort/timeout, JSON response verification and plain-language errors.
- Real `GET /api/v1/health` connection indicator, with checking, online, offline and error states plus a manual retry control. No fabricated metrics or member data.
- Locally hosted **Bootstrap 5.3.9 CSS and bundle JS**, committed from the official `twbs/bootstrap` release `v5.3.9`.
- Pinned **Vue 3.5.24** production global build installed locally using `scripts/install-frontend-vendors.ps1`. Its resulting file **must be committed** before WP03 acceptance; HTML references the local path only, never a runtime CDN.
- IEDPU design tokens: red `#B4232F`, navy `#17324D`, teal `#0F766E`; system fonts, no remote assets, touch-friendly controls, visible focus, reduced-motion support, small-phone bottom navigation and desktop sidebar.
- Every preview clearly states that sign-in, registration, verification, payments, organisation setup and technical operations are unavailable. Disabled buttons cannot submit data.
- No zone, branch, chairman, office, role profile, member identity or permission assignment is hardcoded. The preview descriptions are static UI copy, not organisational master records.

## 2. Implementation files

| Path | Purpose |
|---|---|
| `app/Controllers/Home.php` | Serves the portal shell with configured base URL |
| `app/Config/Routes.php` | Explicit public preview routes; P2 protected paths remain unavailable |
| `app/Views/portal_shell.php` | Semantic, accessible five-area Vue template and local asset references |
| `public/assets/css/bootstrap.min.css` | Official Bootstrap 5.3.9 production CSS |
| `public/assets/js/vendor/bootstrap.bundle.min.js` | Official Bootstrap 5.3.9 production bundle |
| `public/assets/css/app-theme.css` | Mobile-first design tokens, cards, navigation, responsive layouts |
| `public/assets/js/core/app.js` | Vue Composition API entry and health status |
| `public/assets/js/core/navigation.js` | History API router |
| `public/assets/js/core/api.js` | Native Fetch JSON helper |
| `public/assets/js/core/workspaces.js` | Static P1 preview copy and menu descriptors |
| `scripts/install-frontend-vendors.ps1` | Version-pinned HTTPS download of local Vue asset |

## 3. Install and commit the missing Vue vendor asset

The coding connection cannot retrieve Vue's npm-distributed production build directly. Bootstrap assets were fetched from their official GitHub release and committed. **Do not interpret this as a complete vendor setup until the following is run on the development PC.**

In Windows Command Prompt:

```bat
cd C:\xampp\htdocs\iedpu
git pull --ff-only origin feature/p1-wp01-environment-bootstrap
powershell -ExecutionPolicy Bypass -File scripts/install-frontend-vendors.ps1
```

The script downloads exactly `vue@3.5.24/dist/vue.global.prod.js` over HTTPS (jsDelivr, with unpkg fallback), checks for a plausible production-global file and version, installs it to `public/assets/js/vendor/vue.global.prod.js` and prints its SHA-256. Review that file before committing:

```bat
git status --short
git add public/assets/js/vendor/vue.global.prod.js
git commit -m "build(p1-wp03): lock local Vue 3.5.24 production asset"
git push origin feature/p1-wp01-environment-bootstrap
```

Do not commit `.env`, `vendor/`, real user data or secrets. Once committed, all required WP03 runtime assets will be served from the application's own `public/` directory.

## 4. Start the portal

```bat
C:\xampp\php\php.exe spark routes
C:\xampp\php\php.exe spark serve --host 127.0.0.1 --port 8080
```

Open these paths in a browser:

- `http://127.0.0.1:8080/`
- `http://127.0.0.1:8080/preview/member`
- `http://127.0.0.1:8080/preview/branch`
- `http://127.0.0.1:8080/preview/committee`
- `http://127.0.0.1:8080/preview/technical`

Do **not** run migrations or enable Shield routes in WP03.

## 5. Manual acceptance checklist

| ID | Manual verification | Required result |
|---|---|---|
| WP03-M01 | Open the public landing page at 320, 375 and 390 CSS px widths | Readable content, no horizontal scrolling, hero and four cards visible |
| WP03-M02 | Open the public page at tablet and desktop widths | Responsive cards, appropriate sidebar and layout |
| WP03-M03 | Tap all five bottom navigation destinations on a phone | Correct route/title and visible active state, no lost content |
| WP03-M04 | Use desktop sidebar and keyboard Tab/Enter | Navigation and focus visible; skip link works |
| WP03-M05 | Copy a preview URL to a fresh tab and reload | Correct preview appears after direct-link refresh |
| WP03-M06 | Navigate through several previews, use Back and Forward | Correct route and selected navigation restored |
| WP03-M07 | Open browser DevTools Network | No runtime requests to CDNs, Google Fonts or remote assets; Vue and Bootstrap load locally |
| WP03-M08 | Check `vue.global.prod.js` and Bootstrap files | Vue 3.5.24, Bootstrap 5.3.9 and valid JS/CSS responses; no missing assets |
| WP03-M09 | With server online, click the connection indicator | It reports Connected after a real health response |
| WP03-M10 | Stop the local server, then retry connection | It reports Connection unavailable; no false success |
| WP03-M11 | Disable browser network and refresh after first load | No false offline-data claim; full offline shell is WP04, not WP03 |
| WP03-M12 | Click disabled workspace action buttons | No submission, user creation, permission grant or database mutation |
| WP03-M13 | Open `/member`, `/branch`, `/committee`, `/technical` | No functional protected workspace or authenticated business action |
| WP03-M14 | Inspect page source, local storage and network | No member records, secrets, token persistence or hardcoded branch/role master data |
| WP03-M15 | Enable reduced-motion and zoom to 200% | Layout remains readable, controls usable, animation reduced |
| WP03-M16 | Try browser JavaScript disabled or Vue asset temporarily absent | Clear fallback warning; no insecure functionality exposed |

Record date, browser, device width and observed result for each check. Manual checks must not be marked passed without actual human verification. No automated browser/API test suite is added.

## 6. Security and release gates

This WP creates **public read-only design previews only**. Menu items are not an authorization mechanism. P2 must implement Shield authentication, scope-aware server permission checks, session/CSRF protections, identity provisioning, governance and auditing before enabling real dashboards. The separate developer preview does not confer developer access. The Committee Super Administrator will be provisioned later and may directly activate a validated branch under the owner-approved rules.

WP04 owns manifest, Service Worker, safe offline shell, Dexie/IndexedDB foundation, staging/deployment/rollback and complete P1 end-to-end acceptance. Sensitive offline data remains disabled.

**Exit status:** Source implementation committed. Vue asset pin/commit and manual checks are pending. Keep PR #2 draft; do not merge into `main` yet.

## 7. Vendor and owner confirmation (21 November 2026)

The owner reports successful Vue installation and approves the interface appearance. GitHub inspection confirms public/assets/js/vendor/vue.global.prod.js is present and identifies Vue 3.5.24. The root-only Composer ignore rule is corrected. Remaining device/accessibility checks remain pending final P1 evidence. WP04 authorised to proceed.
