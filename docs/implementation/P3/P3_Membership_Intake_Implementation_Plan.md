# IEDPU P3 — Branch Directory, Offices, Registration and Enumerator Workflow

**Status:** DEVELOPMENT STARTED — 28 November 2026  
**Branch:** `feature/p3-membership-intake`  
**Base:** P2 development branch at `10a7d86faa19907f7b253c66d1145dbfb0249a19`  
**P2 disposition:** Formal P2 acceptance is deferred by project-owner direction. P2 is not marked fully accepted. Existing P2 controls remain in force; no unfinished security control may be bypassed to accelerate P3.

## 1. Objective

Deliver an online, auditable workflow for registering **existing IEDPU members**, with correct branch attribution and individual accountability. A registration submission is not proof of membership verification, an official membership number, or authority to access member services.

Development and synthetic acceptance may proceed locally while formal P2 acceptance is deferred. Do not use real member records in the disposable test environment.

## 2. Delivery approach

Deliver P3 as one coordinated implementation package where practical, then perform one consolidated local browser and database acceptance. Avoid exposing unfinished features incrementally to real users. Work in the P3 branch and preserve the P2 branch.

### WP03-A — Branch directory and organisational records

1. Add Committee-facing branch directory administration, using the existing protected workspace and backend permission/scope model.
2. Support create, edit, review, activate, deactivate and lookup of branches; preserve change history and record actor, timestamp, reason/reference and audit event.
3. Capture state/FCT and locality, branch identity and recognition/reference information. Inactive or unconfirmed branches must not be selectable for new registration.
4. Keep branch recognition evidence and current office-holder records as information; a name-only office-holder record grants no portal login or permission.
5. Maintain official office records and term history separately from software role assignments.
6. Enforce branch/zone/global scope consistently in lists, counts, details and mutations. Do not rely on hidden UI controls as authorization.

### WP03-B — Enumerator allocation and access

1. Provide controlled assignment of enumerators to an approved branch and validity period.
2. Enumerators can submit assisted registrations only for their assigned active branch.
3. Record the submitting account, channel (self or assisted), branch, form version and submission timestamp.
4. Prevent enumerators from approving/verifying registrations or changing branch authority.
5. Make assignment expiry and revocation effective on the next protected request.

### WP03-C — Versioned registration and privacy capture

1. Implement versioned registration forms and field rules; retain the exact form version used for every submission.
2. Provide a draft/review/approval/publish lifecycle for form and privacy-notice versions. The Committee Super Administrator is the designated approver under ENG-029.
3. Prepare field definitions for name, gender, age/date of birth where approved, contact, ancestry, residence, existing IEDPU relationship and selected branch.
4. Keep professional information optional and non-blocking.
5. Capture privacy notice version and acknowledgement, and separate optional professional-data consent.
6. Do not publish a live form or collect real member data until mandatory fields, notice wording, lawful basis, retention/correction/export and data-controller responsibilities are approved.
7. Approval of the notice does not itself establish lawful basis or satisfy privacy compliance.

### WP03-D — Self and assisted registration

1. Provide mobile-first online self-registration and authorised branch/enumerator-assisted registration.
2. Validate all fields server-side against the active approved form version.
3. Accept submissions only for active approved branches.
4. Generate a collision-safe internal registration reference; do not label it an NEC-issued official membership number.
5. Prevent accidental duplicate submission and canonical identity creation. Flag possible duplicates for human review; do not automatically reject shared family phone numbers or delete records.
6. Store pending/submitted status and route the record to the correct branch queue for the later P4 verification process.
7. Provide a plain-language receipt and next-step explanation.
8. Registration remains online-only; no private registration data or mutations are cached offline.

## 3. Consolidated acceptance scenarios

Use synthetic records and accounts on `iedpu_disposable_invitation` or a fresh disposable database only.

| ID | Scenario | Expected |
|---|---|---|
| P3-01 | Committee administrator creates an unconfirmed branch | Saved as inactive/pending; audited |
| P3-02 | Unconfirmed branch selected for registration | Rejected server-side |
| P3-03 | Authorised administrator confirms/activates a branch | Active with reason/reference and audit |
| P3-04 | Office-holder name is entered | No portal account or permission is created |
| P3-05 | Official office record and software role are inspected | Independent records |
| P3-06 | Enumerator is assigned to Branch A | Assignment and validity recorded |
| P3-07 | Enumerator submits for assigned Branch A | Accepted and attributed to enumerator |
| P3-08 | Same enumerator submits for Branch B | Denied server-side |
| P3-09 | Enumerator attempts to verify a submission | Denied; verification belongs to P4 |
| P3-10 | Registration form is drafted, reviewed and approved | Immutable version history; only approved version publishable |
| P3-11 | Required field missing or invalid | Clear validation error; no submission |
| P3-12 | Optional professional field omitted | Submission still succeeds |
| P3-13 | Privacy acknowledgement missing | Submission denied when required by approved rules |
| P3-14 | Self-registration from mobile browser | Responsive, online submission succeeds |
| P3-15 | Assisted registration from branch workspace | Correct branch and actor attribution |
| P3-16 | Repeated/double submission | No duplicate canonical identity; idempotent or flagged |
| P3-17 | Similar existing member/contact detected | Review flag; no automatic deletion/rejection |
| P3-18 | Valid submission completed | Internal reference and plain-language receipt |
| P3-19 | Submitted registration is inspected | Pending/submitted, not verified or official-ID-issued |
| P3-20 | Browser goes offline during submission | No offline mutation; clear retry guidance |
| P3-21 | User attempts cross-branch list/detail/API access | Denied with no data leakage |
| P3-22 | Audit verification after workflow | Chain verifies; actions attributable |

## 4. Dependencies and boundaries

- Continue using the existing P2 authentication, MFA, permission and audit infrastructure. Do not create alternate authentication or bypass authorization because the full P2 sign-off is deferred.
- Use synthetic Officer A/Officer B and synthetic member identities for local development. Do not promote the temporary Officer B fixture into a permanent administrator.
- If an unfinished P2 role/grant workflow prevents a needed P3 test, use a controlled local-only fixture or command with explicit disposable-database safeguards; never add a public bypass or weaken production authorization.
- P3 can build draft branch, office, form and registration functionality. Live registration remains disabled until the form/privacy/data-governance approvals and the necessary authorised Committee identity are available.
- Branch activation follows the owner-confirmed authority: the Committee Super Administrator may directly activate a branch after recognition and location checks, with reason/reference and audit event; no mandatory second approver is required.
- Registration captures existing-member information only. Verification, member claims, corrections and member lifecycle belong to P4.
- Payments, dues, external communications, official membership ID issuance and private offline edits remain outside P3.

## 5. Completion criteria

P3 development is complete when the branch and office directory, enumerator allocation, versioned form/privacy foundation, self/assisted online registration, internal reference generation, duplicate flagging, submission queue and audit trail work together in local acceptance.

P3 acceptance must clearly distinguish:
- locally tested synthetic workflow;
- approved form/privacy configuration;
- live personal-data collection authorisation;
- production deployment readiness.

No one of these statuses implies the others. Formal P2 acceptance remains deferred and can be completed separately without blocking continued P3 development.
