# IEDPU North Zone — P0 Engineering Decision Register

**Version:** 1.1 — 20 November 2026  
**Status:** P0 engineering decisions settled; P1 may begin. Live-data and feature activation remain subject to their respective gates.  
**Basis:** Business Blueprint, Technical Blueprint/SRS v1.0, Database Schema Design v1.0, Strategic Phase Implementation Plan, and project-owner clarifications.

## Decision hierarchy

1. The commissioned and partially funded project is authorised to proceed with engineering. P0 is requirements reconciliation, not a second contract approval gate.
2. The Constitution and properly delegated IEDPU authorities control organisational, membership and official-ID decisions. Developer access cannot confer Committee authority.
3. The development team decides technical implementation details where those decisions do not change constitutional, legal, financial or membership policy.
4. Outstanding operational records and release approvals are *activation holds*, not blockers for unrelated P1 coding.
5. The later project-owner decisions recorded here clarify the four foundation documents wherever older wording conflicts.

## Resolved engineering decisions

| ID | Subject | Binding implementation decision | Phase |
|---|---|---|---|
| ENG-001 | Commencement | Begin technical implementation; do not seek redundant project approval. | P0–P1 |
| ENG-002 | Zones | Maintain zone records through protected admin UI; no hardcoded names or IDs. Only approved Northern Zone operations are in scope. | P2–P3 |
| ENG-003 | Branches | Administrators create, edit, confirm and activate branches; no mandatory branch seed or hardcoded branch names. | P2–P3 |
| ENG-004 | Historic schedule | Constitutional branch list is optional historical reference/import data; imported entries default pending/inactive. | P2–P3 |
| ENG-005 | Offices | Administrators manage office types, Chairmen, office holders and term dates; office assignment grants no portal access. | P2–P3 |
| ENG-006 | Unlinked holders | `official_office_assignments.member_id` nullable; `holder_name_snapshot` required. Later member linking is reviewed and audited. | P2–P4 |
| ENG-007 | Portal roles | Role profiles, names and permission bundles are database-managed by authorised administrators; no role name or numeric ID is a business-logic constant. | P2 |
| ENG-008 | Permission catalogue | Executable permission keys and protected-grant metadata are release-controlled. Admins compose roles only within grant ceilings. | P2 |
| ENG-009 | Privilege safety | No self-elevation; protected grants retain independent approval where required. Routine branch activation by the Committee Super Administrator needs no second approver. | P2–P3 |
| ENG-010 | Bootstrap | One-time audited CLI/setup technical bootstrap; first Committee Super Administrator details supplied at P2 provisioning; no default credentials. | P1–P2 |
| ENG-011 | Geography | State/FCT and LGA are geographic descriptors and coverage records, not command tiers. | P2–P3 |
| ENG-012 | Membership | Existing IEDPU members only; individual registration, branch-linked and branch-verified. | P3–P4 |
| ENG-013 | Member numbers | Internal registration reference is distinct from official NEC membership number/ID. | P3/P10 |
| ENG-014 | Registration | Versioned fields and server-side rules; no live personal-data collection before form/privacy approval. | P3 |
| ENG-015 | Offline | Public app shell/fallback first; private member cache and offline edits remain disabled until approved. | P1/P5 |
| ENG-016 | Authentication | CodeIgniter Shield, with compatible pinned framework/PHP versions; no custom authentication table system. | P1–P2 |
| ENG-017 | Frontend | Vue 3 global production build, Bootstrap 5.3.9, Dexie where authorised, native Fetch/History API, pinned locally hosted assets; no production JS build chain. | P1 |
| ENG-018 | Testing | Human-executed manual verification only; no PHPUnit, automated browser/API/regression suites as project verification. Linting and normal operational automation are permitted. | P0–P11 |
| ENG-019 | Data integrity | CI4 migrations, foreign keys, transactions, uniqueness, immutable history/audit; never disable FKs merely to force migration success. | P2–P11 |
| ENG-020 | Release boundaries | Foundation may proceed without payment, messaging or ID providers; disabled features remain server-enforced. | P1–P11 |
| ENG-021 | Environments | Keep secrets out of Git; `public/` document root; separate local/staging/production; no live-data launch without privacy/hosting/backup review. | P1/P11 |
| ENG-022 | Repository | Repository is currently public; recommend private before sensitive implementation; never commit secrets or member data. | P0–P1 |
| ENG-023 | Branch activation | **Owner confirmed Option A:** Committee Super Administrator directly activates validated branches with recognition/location evidence and an audit event. | P3 |
| ENG-024 | Identity separation | Shield login identity is distinct from canonical verified membership; self-claim never bypasses branch review. | P2–P4 |
| ENG-025 | Finance | No fixed dues or payee, no unverified payment confirmation or mutable ledger balance; activate only after finance decisions. | P8–P9 |
| ENG-026 | Chairman review | Chairman remains responsible for final branch review, but backend action requires separately authorised authenticated scoped role. | P3–P4 |
| ENG-027 | Developer interface | **Owner confirmed:** Separate technical interface for deployments, configuration, migrations, backups, diagnostics, monitoring and security. No implied Committee business powers. | P1–P11 |
| ENG-028 | Developer membership | **Owner confirmed:** Developers are also IEDPU members and may receive separately authorised organisational roles, without implicit privilege crossover or unlogged impersonation. | P2–P11 |
| ENG-029 | Form/privacy approver | **Owner confirmed Option A:** Committee Super Administrator approves versioned registration rules and privacy notice. Approval does not waive lawful-basis or other privacy obligations. | P3–P4 |

## Operational and release holds — not blockers for P1

| ID | Remaining input or approval | Safe default | Gate |
|---|---|---|---|
| HOLD-01 | First Committee Super Administrator identity and secure contact, supplied by owner when needed | No default operational super-admin; technical operator cannot self-appoint | P2 provisioning |
| HOLD-02 | **Resolved:** Committee Super Administrator directly activates branches | Recognition/location checks, reason and audit required | P3 activation |
| HOLD-03 | **Approver resolved:** Committee Super Administrator; exact form, notice, lawful basis and optional-data consent still to be finalised | Draft/versioned only; live registration disabled | P3 live registration |
| HOLD-04 | Current branch recognition, Chairmen, office-holder terms | Admin entry pending confirmation; no automatic access | Branch activation/review |
| HOLD-05 | Data controller, retention, correction, exports, breach and backup governance | No live personal data, private offline cache or exports | Production processing |
| HOLD-06 | Production hosting, domain ownership and compatibility | Local/staging development only | P11 |
| HOLD-07 | NEC official member number/card issuing delegation | Internal references only; official ID disabled | P10 |
| HOLD-08 | Payment provider, settlement, dues and branch accounts | Finance server-disabled | P8/P9 |
| HOLD-09 | External communication provider and consent policy | In-app notices only | P7 external delivery |
| HOLD-10 | Offline editable fields, retention and device safeguards | Offline public shell only | P5 private offline |
| HOLD-11 | Eligibility for existing members residing outside covered geography | Do not automatically exclude solely on residence; disputed cases routed for policy decision | P3 live registration |

## Owner decisions — 20 November 2026

**First administrator:** The owner will supply the first Committee Super Administrator's details when P2 secure provisioning requires them. Portal administrators are nontechnical Committee operators. The separate developer interface preserves specialist technical duties for the developers.

**Branch activation:** The Committee Super Administrator may directly activate a branch. No mandatory second Committee approver is required for that action; recognition, scope, location, permission and audit checks still apply.

**Registration and privacy:** The Committee Super Administrator is the designated operational approver for versioned registration-form rules and privacy notices. The development team prepares the technical drafts and validation. Legal/privacy obligations remain in force.

**Developer/Committee separation:** Developers are part of IEDPU and may also be organisational role holders through independently authorised grants. Technical access never silently grants business powers.

## P0 disposition

P0 engineering reconciliation is complete enough for P1. Safe empty organisational datasets and disabled live capabilities are acceptable starting conditions. Missing official branch names, Chairmen, finance providers and NEC delegation do not block P1. Manual verification and phase-specific activation holds remain mandatory. No unreceived external approval is represented as granted.
