# IEDPU NORTH ZONE DIGITAL MEMBERSHIP, ADMINISTRATION AND DEVELOPMENT PORTAL

# DATABASE SCHEMA DESIGN

**Document ID:** IEDPU-NZ-DBS-001\
**Version:** 1.0\
**Status:** Draft for database implementation and technical review\
**Prepared:** 19 November 2026\
**Database:** MySQL-compatible relational database, InnoDB, `utf8mb4`\
**Application:** CodeIgniter 4 + CodeIgniter Shield\
**Document:** Database entities, relationships, tables, fields,
constraints and implementation rules

------------------------------------------------------------------------

# 1. PURPOSE AND SOURCE AUTHORITY

This document defines the database structure for the IEDPU North Zone
Digital Membership, Administration and Development Portal. It translates
the source project documents, the approved project decisions and the
Technical Blueprint/SRS into a relational database design.

The source baseline is:

1.  **Constitution of the Ilorin Emirate Descendants Progressive Union
    (IEDPU), 2015.**
2.  **IEDPU North Zone --- Population Registration, Verification,
    Membership, Communication and Development Initiative.**
3.  **IEDPU North Zone Technical Blueprint and Software Requirements
    Specification (SRS), Version 1.0.**
4.  The user's explicit decisions narrowing and approving the initial
    implementation.

The Constitution establishes IEDPU's recognised organisational
structure, membership concepts, branch registration, official offices,
finances and membership identity-card authority. The initiative proposal
establishes the registration information, geographical coverage, data
quality, privacy, mobilisation and reporting context. Subsequent
approved decisions narrow the first release to existing IEDPU members
only.

Where the original initiative proposal describes a broader descendant
population-registration exercise, the later approved decision takes
precedence for the first release: **only existing IEDPU members are
eligible for registration in this initial portal.**

## 1.1 Source-to-database decisions

  -----------------------------------------------------------------------
  Source decision or fact             Database consequence
  ----------------------------------- -----------------------------------
  A member is a registered financial  Registration/verification and
  member of the Union.                financial standing are separate
                                      concepts.

  Membership is registered through a  Each member record and registration
  Branch.                             submission must identify an
                                      approved branch.

  The Constitution recognises         Organisational structure is stored
  National Conference, NEC, Zones and separately from portal permissions.
  Branches.                           

  The Constitution recognises         A zone reference table is required;
  Central, Northern, Western and      Northern Zone is the current
  South-South Zones.                  initiative's operational zone.

  Branches remain individual          Each branch has a direct `zone_id`;
  organisational units within a zone. no state coordinator hierarchy is
                                      created.

  Branches have Executive Committees. Official IEDPU offices and
                                      office-holder assignments are
                                      represented independently from
                                      software roles.

  The Constitution provides for       Configurable obligations, payments,
  monthly dues, levies, donations,    receipts, allocations and ledgers
  receipts and financial records.     are required.

  The Constitution says the NEC       Portal ID issuance must retain the
  issues membership identity cards.   NEC/delegated authorisation
                                      reference.

  Initiative coverage is 19 Northern  Project coverage is stored
  States and FCT.                     explicitly and is not inferred from
                                      a state name.

  Information includes name, gender,  Member profile tables support these
  age/date of birth, contact,         fields; optional professional
  ancestry, residence, IEDPU          details are consent-controlled.
  relationship and optional           
  professional details.               

  The initiative requires duplicate   Duplicate cases and data-quality
  detection and data improvement.     issues are stored with their
                                      resolution history.

  The initiative requires access      Privacy acknowledgements, consent
  control, correction, retention and  records, data-subject requests and
  accountability.                     comprehensive audit are required.

  Initial eligibility is existing     No non-member population register
  IEDPU members only.                 is included in this release.

  Registration is individual, not     No household tables are created.
  household-based.                    

  Branches report directly to the     No state/LGA coordinator
  zone for this project.              organisational tier is created.

  The Initiative Committee is the     Committee authority is represented
  highest project-level portal        by portal roles and permissions,
  administrator, subject to delegated not a new constitutional organ.
  IEDPU authority.                    

  Official IEDPU office differs from  Separate office and portal-role
  portal role.                        tables are mandatory.

  All portal actions are predefined   Permission catalogue is controlled
  and expandable.                     by application releases.

  Dues are not hardcoded.             Amount, reason, deadline and target
                                      are stored in obligation records.

  Member needs a bank-statement-like  Append-only member financial ledger
  financial history.                  is required.

  Announcements target all, branch,   Audience rules and recipient
  individual, group, official office  snapshots are required.
  or portal role.                     

  Branch can request automated dues   Separate due requests and approved
  and provide remittance account      collection-account records are
  details.                            required.

  Sensitive actions require rigorous  Audit events, security events and
  auditing.                           approval records are required.

  PWA allows limited offline profile  IndexedDB remains client-side;
  view/edit.                          server stores idempotency receipts
                                      and accepted revisions.

  Sensitive actions are online-only.  No offline approval, payment
                                      confirmation, role assignment or
                                      verification is permitted.
  -----------------------------------------------------------------------

## 1.2 Deliberate exclusions from the first database release

-   Non-member descendant registration.
-   Household/family-unit registration.
-   State-coordinator and LGA-coordinator organisational tiers.
-   Community-mobiliser organisational tier.
-   Automatic conversion of portal role into official office.
-   Automatic conversion of financial standing into verification status.
-   Hardcoded dues amounts or fixed payment schedules.
-   Official membership-card activation without NEC/delegated
    authorisation.
-   Offline approval of sensitive actions.
-   Public exposure of private member information.

The schema is extensible, but excluded functions must not be activated
merely because the schema can later accommodate them.

------------------------------------------------------------------------

# 2. DATABASE-WIDE STANDARDS

## 2.1 Database and engine

Recommended database name: `iedpu_northzone`.

Use a supported MySQL 8.x release or compatible MariaDB version, InnoDB
tables, `utf8mb4` character set and a compatible collation such as
`utf8mb4_unicode_ci`. Confirm the exact versions in XAMPP and on the
selected shared host before deployment.

## 2.2 Keys and references

-   Custom business-table primary keys:
    `BIGINT UNSIGNED AUTO_INCREMENT`.
-   External/public identifiers: UUID v4 in `CHAR(36)` where records are
    referenced in URLs, QR codes or public APIs.
-   Human-readable registration reference and membership number are
    separate from internal primary keys.
-   Never expose sequential internal IDs as public identifiers.
-   Foreign-key columns referencing Shield's `users.id` MUST use the
    exact type created by the installed Shield migration. Do not assume
    it is the same as the custom `BIGINT` keys.

## 2.3 Standard timestamps and audit columns

Mutable business tables normally include `created_at DATETIME(6)` and
`updated_at DATETIME(6)`. Where relevant they also include
`created_by_user_id` and `updated_by_user_id` referencing Shield users.

Store timestamps in UTC and convert to `Africa/Lagos` for display. Use
`DATE` for dates that do not need a time-of-day.

These columns do not replace the dedicated append-only audit event
table.

## 2.4 Money and status fields

-   Money: `DECIMAL(15,2)`, never `FLOAT` or `DOUBLE`.
-   Currency: explicit `CHAR(3)`, initially `NGN`.
-   Status values: readable `VARCHAR` codes with database `CHECK`
    constraints where supported and application validation in all cases.
-   Use `JSON` only for snapshots, audit payloads, variable request
    details and report filters---not as a substitute for relational
    member, financial or permission tables.

## 2.5 Foreign-key deletion policy

Use `ON DELETE RESTRICT` by default for historical, financial and
organisational records. Prefer deactivation, revocation, superseding or
anonymisation over physical deletion. Use `CASCADE` only for dependent
join rows that have no independent historical value.

------------------------------------------------------------------------

# 3. ENTITY RELATIONSHIP OVERVIEW

``` mermaid
erDiagram
    ORG_ZONES ||--o{ BRANCHES : contains
    GEO_STATES ||--o{ BRANCHES : locates
    GEO_STATES ||--o{ PROJECT_COVERAGE_STATES : included
    BRANCHES ||--o{ MEMBERS : registers
    MEMBERS ||--o{ MEMBER_REGISTRATIONS : has
    MEMBERS ||--o{ MEMBER_VERIFICATION_REVIEWS : reviewed
    MEMBERS ||--o{ MEMBER_CONTACT_POINTS : has
    MEMBERS ||--o| MEMBER_USER_LINKS : linked
    USERS ||--o{ PORTAL_USER_ROLE_ASSIGNMENTS : receives
    PORTAL_ROLES ||--o{ PORTAL_ROLE_PERMISSIONS : contains
    PORTAL_PERMISSIONS ||--o{ PORTAL_ROLE_PERMISSIONS : included
    MEMBERS ||--o{ OFFICIAL_OFFICE_ASSIGNMENTS : holds
    FINANCIAL_OBLIGATIONS ||--o{ OBLIGATION_TARGET_RULES : targets
    FINANCIAL_OBLIGATIONS ||--o{ MEMBER_OBLIGATIONS : assesses
    PAYMENT_TRANSACTIONS ||--o{ PAYMENT_ALLOCATIONS : allocates
    MEMBERS ||--o{ MEMBER_FINANCIAL_ENTRIES : statement
    ANNOUNCEMENTS ||--o{ ANNOUNCEMENT_RECIPIENTS : snapshots
    CONSTITUTIONAL_DOCUMENTS ||--o{ DOCUMENT_VERSIONS : versions
    DOCUMENT_VERSIONS ||--o{ DOCUMENT_SECTIONS : contains
    APPROVAL_REQUESTS ||--o{ APPROVAL_DECISIONS : receives
    USERS ||--o{ AUDIT_EVENTS : acts
```

------------------------------------------------------------------------

# 4. ORGANISATION AND GEOGRAPHY TABLES

## 4.1 `org_zones`

Stores recognised IEDPU zones. Seed Central, Northern, Western and
South-South Zones from the Constitution. Only Northern Zone is in this
initiative's operational scope.

  -------------------------------------------------------------------------------------
  Field                Type                        Null Key / rule    Meaning
  -------------------- -------------- ----------------- ------------- -----------------
  `id`                 BIGINT                        No PK, AI        Internal ID
                       UNSIGNED                                       

  `public_id`          CHAR(36)                      No UNIQUE        External
                                                                      reference

  `zone_code`          VARCHAR(30)                   No UNIQUE        Stable code,
                                                                      e.g. `NORTHERN`

  `zone_name`          VARCHAR(120)                  No               Canonical
                                                                      recognised name

  `display_alias`      VARCHAR(120)                 Yes               Optional alias
                                                                      such as "North
                                                                      Zone"

  `description`        TEXT                         Yes               Description

  `is_project_scope`   TINYINT(1)                    No DEFAULT 0     In current
                                                                      project scope

  `status`             VARCHAR(20)                   No CHECK         `active`,
                                                                      `inactive`

  `created_at`         DATETIME(6)                   No               Creation

  `updated_at`         DATETIME(6)                  Yes               Update
  -------------------------------------------------------------------------------------

**Rules:** Do not create both "North Zone" and "Northern Zone" as
separate zones. Use the canonical constitutional name and optional
alias. Other zone records do not grant the North Zone Committee
authority over those zones.

## 4.2 `geo_states`

Geographical reference values only; they do not create organisational
reporting tiers.

  Field            Type                Null Key / rule     Meaning
  ---------------- ----------------- ------ -------------- ----------------
  `id`             BIGINT UNSIGNED       No PK, AI         ID
  `state_code`     VARCHAR(10)           No UNIQUE         State/FCT code
  `state_name`     VARCHAR(100)          No UNIQUE         State/FCT name
  `geo_type`       VARCHAR(20)           No CHECK          `state`, `fct`
  `country_code`   CHAR(2)               No DEFAULT `NG`   Country
  `is_active`      TINYINT(1)            No DEFAULT 1      Lookup status
  `created_at`     DATETIME(6)           No                Creation

The table may contain all Nigerian states and FCT as geographic lookup
values. Only the states linked through `project_coverage_states` are in
this project's approved operational coverage.

## 4.3 `project_coverage_states`

Stores the 19 Northern States and FCT approved for this initiative.

  -----------------------------------------------------------------------------------------
  Field                   Type                        Null Key / rule        Meaning
  ----------------------- -------------- ----------------- ----------------- --------------
  `id`                    BIGINT                        No PK, AI            ID
                          UNSIGNED                                           

  `state_id`              BIGINT                        No FK →              Covered state
                          UNSIGNED                         `geo_states.id`   

  `zone_id`               BIGINT                        No FK →              Project zone
                          UNSIGNED                         `org_zones.id`    

  `coverage_status`       VARCHAR(20)                   No CHECK             `active`,
                                                                             `suspended`,
                                                                             `closed`

  `effective_from`        DATE                          No                   Start

  `effective_to`          DATE                         Yes                   End

  `approved_by_user_id`   Shield user ID               Yes FK → `users.id`   Approver
                          type                                               

  `approval_reference`    VARCHAR(150)                 Yes                   Approval
                                                                             reference

  `created_at`            DATETIME(6)                   No                   Creation
  -----------------------------------------------------------------------------------------

UNIQUE (`state_id`, `zone_id`). Seed exactly the approved 19 Northern
States and FCT. A geographic state record does not create a state
coordinator role.

## 4.4 `branches`

Each branch reports directly to its zone for this initiative. State and
LGA fields are geographical attributes, not organisational layers.

  ------------------------------------------------------------------------------------------------------
  Field                     Type                        Null Key / rule        Meaning
  ------------------------- -------------- ----------------- ----------------- -------------------------
  `id`                      BIGINT                        No PK, AI            Branch ID
                            UNSIGNED                                           

  `public_id`               CHAR(36)                      No UNIQUE            External reference

  `zone_id`                 BIGINT                        No FK →              Parent zone
                            UNSIGNED                         `org_zones.id`    

  `state_id`                BIGINT                       Yes FK →              State/FCT
                            UNSIGNED                         `geo_states.id`   

  `branch_code`             VARCHAR(40)                  Yes UNIQUE            Approved branch code

  `branch_name`             VARCHAR(160)                  No                   Canonical name

  `source_name`             VARCHAR(160)                 Yes                   Name in source schedule

  `locality`                VARCHAR(160)                 Yes                   Town/locality

  `lga_or_district`         VARCHAR(160)                 Yes                   Geographic description

  `office_address`          VARCHAR(500)                 Yes                   Address

  `contact_phone`           VARCHAR(40)                  Yes                   Official contact

  `contact_email`           VARCHAR(190)                 Yes                   Official contact

  `recognition_status`      VARCHAR(30)                   No CHECK             `approved`,
                                                                               `provisional`,
                                                                               `pending_confirmation`,
                                                                               `not_recognised`

  `operational_status`      VARCHAR(30)                   No CHECK             `active`, `inactive`,
                                                                               `suspended`,
                                                                               `deregistered`

  `recognition_reference`   VARCHAR(150)                 Yes                   NEC/Conference reference

  `recognition_date`        DATE                         Yes                   Recognition date

  `notes`                   TEXT                         Yes                   Notes

  `created_at`              DATETIME(6)                   No                   Creation

  `updated_at`              DATETIME(6)                  Yes                   Update

  `created_by_user_id`      Shield user ID               Yes FK → `users.id`   Creator
                            type                                               

  `updated_by_user_id`      Shield user ID               Yes FK → `users.id`   Updater
                            type                                               
  ------------------------------------------------------------------------------------------------------

**Rules:** Each branch belongs to one zone. A branch must not be
activated merely because it appears in the Constitution's First
Schedule. Import the Northern Zone schedule names as a reference
baseline and validate current name, state, recognition and operational
status with the appropriate authority. Preserve source spelling in
`source_name`. The Constitution's figure of 128 branches refers to the
Union's total, not the Northern Zone alone.

## 4.5 `emirate_ancestral_areas`

Controlled ancestry lookup based on the Constitution's definition of
Ilorin Emirate.

  -------------------------------------------------------------------------------------
  Field                   Type                        Null Key / rule    Meaning
  ----------------------- -------------- ----------------- ------------- --------------
  `id`                    BIGINT                        No PK, AI        ID
                          UNSIGNED                                       

  `area_name`             VARCHAR(160)                  No UNIQUE with   Area name
                                                           `area_type`   

  `area_type`             VARCHAR(30)                   No CHECK         `town`,
                                                                         `district`,
                                                                         `ward`,
                                                                         `other`

  `parent_area_id`        BIGINT                       Yes FK → same     Parent area
                          UNSIGNED                         table         

  `associated_lga_name`   VARCHAR(120)                 Yes               LGA
                                                                         association

  `source_reference`      VARCHAR(150)                 Yes               Constitution
                                                                         reference

  `is_active`             TINYINT(1)                    No DEFAULT 1     Status

  `created_at`            DATETIME(6)                   No               Creation
  -------------------------------------------------------------------------------------

Seed the source areas using the Constitution's exact wording: Ilorin
Town, Iponrin, Afon, Malete, Akanbi, Ejidongari, Oloru, Owode, Onire,
Lanwa and Ipaiye. Ancestral area alone does not determine eligibility.

------------------------------------------------------------------------

# 5. SHIELD AUTHENTICATION AND ACCOUNT LINKING

## 5.1 Shield-managed tables

Install CodeIgniter Shield and run its official migrations. Do not
manually recreate, rename or modify Shield's internal authentication
tables. The exact tables and column types must come from the locked
Shield release used by the project.

Custom foreign keys referencing `users.id` must match the actual Shield
primary-key type.

## 5.2 `member_user_links`

A login account and a member record are separate. A member may exist
before claiming an account. A technical account may exist without a
member record if authorised.

  ----------------------------------------------------------------------------------------
  Field                 Type                        Null Key / rule      Meaning
  --------------------- -------------- ----------------- --------------- -----------------
  `id`                  BIGINT                        No PK, AI          ID
                        UNSIGNED                                         

  `member_id`           BIGINT                        No FK →            Member
                        UNSIGNED                         `members.id`,   
                                                         INDEX          

  `user_id`             Shield user ID                No FK →            Shield account
                        type                             `users.id`,     
                                                         INDEX          

  `link_status`         VARCHAR(20)                   No CHECK           `active`,
                                                                         `suspended`,
                                                                         `revoked`

  `linked_at`           DATETIME(6)                   No                 Link time

  `linked_by_user_id`   Shield user ID               Yes FK → `users.id` Actor
                        type                                             

  `link_method`         VARCHAR(30)                   No CHECK           `member_claim`,
                                                                         `admin_link`,
                                                                         `migration`

  `revoked_at`          DATETIME(6)                  Yes                 Revocation

  `revocation_reason`   VARCHAR(500)                 Yes                 Reason

  `created_at`          DATETIME(6)                   No                 Creation
  ----------------------------------------------------------------------------------------

This is a historical link table, so do not impose a global UNIQUE constraint on `member_id` or `user_id`; that would prevent retaining old account links. Enforce at most one active link per member and at most one active member link per user inside a transaction using row locks. Where the selected MySQL/MariaDB version supports it safely, generated active-key columns with unique indexes may provide an additional database-level guard. A member must be verified before ordinary self-service account claiming. Technical/operational accounts that are not members remain unlinked. Revoked or superseded links remain in history; do not delete them.

## 5.3 `member_account_claims`

Controls secure claiming of an existing verified member record.

  --------------------------------------------------------------------------------------
  Field                    Type                        Null Key / rule     Meaning
  ------------------------ -------------- ----------------- -------------- -------------
  `id`                     BIGINT                        No PK, AI         ID
                           UNSIGNED                                        

  `public_id`              CHAR(36)                      No UNIQUE         Reference

  `member_id`              BIGINT                        No FK →           Member
                           UNSIGNED                         `members.id`   

  `requested_by_user_id`   Shield user ID                No FK →           Requester
                           type                             `users.id`     

  `claim_token_hash`       CHAR(64)                     Yes UNIQUE         Hashed
                                                                           one-time
                                                                           token

  `status`                 VARCHAR(25)                   No CHECK          `pending`,
                                                                           `approved`,
                                                                           `rejected`,
                                                                           `expired`,
                                                                           `cancelled`

  `verification_method`    VARCHAR(40)                  Yes                Identity
                                                                           check

  `reviewed_by_user_id`    Shield user ID               Yes FK →           Reviewer
                           type                             `users.id`     

  `review_reason`          VARCHAR(500)                 Yes                Reason

  `expires_at`             DATETIME(6)                  Yes                Expiry

  `reviewed_at`            DATETIME(6)                  Yes                Review time

  `created_at`             DATETIME(6)                   No                Creation
  --------------------------------------------------------------------------------------

Store only the token hash. A claim must not create another member record
or link an already-linked member to another account.

------------------------------------------------------------------------

# 6. PORTAL ROLES AND OFFICIAL IEDPU OFFICES

## 6.1 Separation rule

An official IEDPU office describes organisational position. A portal
role describes software permissions. A Branch Chairman may have a
limited portal role; a user may have a portal role without being Branch
Chairman. The database must never infer one from the other.

## 6.2 `portal_roles`

  -------------------------------------------------------------------------------------------
  Field                        Type                        Null Key / rule    Meaning
  ---------------------------- -------------- ----------------- ------------- ---------------
  `id`                         BIGINT                        No PK, AI        ID
                               UNSIGNED                                       

  `role_code`                  VARCHAR(80)                   No UNIQUE        Stable code

  `role_name`                  VARCHAR(120)                  No               Display name

  `description`                TEXT                         Yes               Purpose

  `is_system_role`             TINYINT(1)                    No DEFAULT 0     Protected
                                                                              built-in role

  `is_assignable`              TINYINT(1)                    No DEFAULT 1     Assignable

  `requires_second_approval`   TINYINT(1)                    No DEFAULT 0     Maker-checker

  `status`                     VARCHAR(20)                   No CHECK         `active`,
                                                                              `inactive`,
                                                                              `retired`

  `created_at`                 DATETIME(6)                   No               Creation

  `updated_at`                 DATETIME(6)                  Yes               Update
  -------------------------------------------------------------------------------------------

## 6.3 `portal_permissions`

Stores the predefined application action catalogue.

  -----------------------------------------------------------------------------------------------
  Field                    Type                        Null Key / rule    Meaning
  ------------------------ -------------- ----------------- ------------- -----------------------
  `id`                     BIGINT                        No PK, AI        ID
                           UNSIGNED                                       

  `permission_key`         VARCHAR(120)                  No UNIQUE        e.g. `members.verify`

  `module_code`            VARCHAR(60)                   No INDEX         Module

  `action_code`            VARCHAR(60)                   No               Action

  `permission_name`        VARCHAR(150)                  No               Label

  `description`            TEXT                         Yes               Meaning

  `risk_level`             VARCHAR(20)                   No CHECK         `low`, `medium`,
                                                                          `high`, `critical`

  `requires_reason`        TINYINT(1)                    No DEFAULT 0     Reason required

  `requires_online`        TINYINT(1)                    No DEFAULT 1     Online-only

  `is_system_permission`   TINYINT(1)                    No DEFAULT 1     Controlled permission

  `status`                 VARCHAR(20)                   No CHECK         `active`, `retired`

  `created_at`             DATETIME(6)                   No               Creation
  -----------------------------------------------------------------------------------------------

Illustrative permission keys include `members.view`, `members.register`,
`members.verify`, `branches.manage`, `roles.assign`, `dues.create`,
`dues.approve`, `payments.reconcile`, `announcements.publish`,
`audit.view`, `audit.export` and `constitution.publish`. The final
catalogue must be seeded through controlled application migrations, not
improvised by inserting arbitrary actions.

## 6.4 `portal_role_permissions`

Many-to-many role/permission relation.

  ----------------------------------------------------------------------------------------------
  Field                  Type                       Null Key / rule                Meaning
  ---------------------- ------------- ----------------- ------------------------- -------------
  `id`                   BIGINT                       No PK, AI                    ID
                         UNSIGNED                                                  

  `role_id`              BIGINT                       No FK → `portal_roles.id`    Role
                         UNSIGNED                                                  

  `permission_id`        BIGINT                       No FK →                      Permission
                         UNSIGNED                        `portal_permissions.id`   

  `granted_at`           DATETIME(6)                  No                           Time

  `granted_by_user_id`   Shield user                 Yes FK → `users.id`           Grantor
                         ID type                                                   
  ----------------------------------------------------------------------------------------------

UNIQUE (`role_id`, `permission_id`). Retired permissions cannot be newly
assigned.

## 6.5 `portal_user_role_assignments`

Assigns roles to users with global, zone or branch scope.

  ------------------------------------------------------------------------------------------------
  Field                   Type                        Null Key / rule               Meaning
  ----------------------- -------------- ----------------- ------------------------ --------------
  `id`                    BIGINT                        No PK, AI                   ID
                          UNSIGNED                                                  

  `public_id`             CHAR(36)                      No UNIQUE                   Reference

  `user_id`               Shield user ID                No FK → `users.id`          Account
                          type                                                      

  `role_id`               BIGINT                        No FK → `portal_roles.id`   Role
                          UNSIGNED                                                  

  `scope_type`            VARCHAR(20)                   No CHECK                    `global`,
                                                                                    `zone`,
                                                                                    `branch`

  `zone_id`               BIGINT                       Yes FK → `org_zones.id`      Zone scope
                          UNSIGNED                                                  

  `branch_id`             BIGINT                       Yes FK → `branches.id`       Branch scope
                          UNSIGNED                                                  

  `starts_at`             DATETIME(6)                   No                          Start

  `expires_at`            DATETIME(6)                  Yes                          Expiry

  `assignment_status`     VARCHAR(20)                   No CHECK                    `active`,
                                                                                    `suspended`,
                                                                                    `revoked`,
                                                                                    `expired`

  `assignment_reason`     VARCHAR(500)                  No                          Reason

  `assigned_by_user_id`   Shield user ID                No FK → `users.id`          Assignor
                          type                                                      

  `approval_request_id`   BIGINT                       Yes FK →                     Approval
                          UNSIGNED                         `approval_requests.id`   

  `revoked_at`            DATETIME(6)                  Yes                          Revocation

  `revoked_by_user_id`    Shield user ID               Yes FK → `users.id`          Revoker
                          type                                                      

  `created_at`            DATETIME(6)                   No                          Creation
  ------------------------------------------------------------------------------------------------

**Scope checks:** `global` requires both scope IDs null; `zone` requires
`zone_id` and null `branch_id`; `branch` requires `branch_id`. The
backend must enforce scope on every protected query. A branch-scoped
officer must not see another branch's records unless separately
authorised. Expired/revoked assignments stop granting access. Users
cannot elevate themselves.

## 6.6 `official_office_catalog`

  --------------------------------------------------------------------------------------
  Field                    Type                        Null Key / rule    Meaning
  ------------------------ -------------- ----------------- ------------- --------------
  `id`                     BIGINT                        No PK, AI        ID
                           UNSIGNED                                       

  `office_code`            VARCHAR(80)                   No UNIQUE        Stable code

  `office_name`            VARCHAR(150)                  No               Official title

  `organisational_level`   VARCHAR(20)                   No CHECK         `national`,
                                                                          `zone`,
                                                                          `branch`

  `description`            TEXT                         Yes               Description

  `source_section`         VARCHAR(80)                  Yes               Constitution
                                                                          reference

  `is_active`              TINYINT(1)                    No DEFAULT 1     Status

  `created_at`             DATETIME(6)                   No               Creation
  --------------------------------------------------------------------------------------

Seed offices supported by the Constitution and approved organisational
records. National offices may exist as reference data without making
national administration part of the North Zone project.

## 6.7 `official_office_assignments`

  ----------------------------------------------------------------------------------------------------------------
  Field                     Type                        Null Key / rule                     Meaning
  ------------------------- -------------- ----------------- ------------------------------ ----------------------
  `id`                      BIGINT                        No PK, AI                         ID
                            UNSIGNED                                                        

  `public_id`               CHAR(36)                      No UNIQUE                         Reference

  `member_id`               BIGINT                        No FK → `members.id`              Office holder
                            UNSIGNED                                                        

  `office_id`               BIGINT                        No FK →                           Office
                            UNSIGNED                         `official_office_catalog.id`   

  `zone_id`                 BIGINT                       Yes FK → `org_zones.id`            Zonal scope
                            UNSIGNED                                                        

  `branch_id`               BIGINT                       Yes FK → `branches.id`             Branch scope
                            UNSIGNED                                                        

  `term_start_date`         DATE                          No                                Term start

  `term_end_date`           DATE                         Yes                                Term end

  `assignment_status`       VARCHAR(20)                   No CHECK                          `current`,
                                                                                            `completed`,
                                                                                            `vacated`,
                                                                                            `suspended`, `revoked`

  `appointment_method`      VARCHAR(30)                  Yes                                Election/appointment

  `appointment_reference`   VARCHAR(150)                 Yes                                Minutes/resolution

  `evidence_file_id`        BIGINT                       Yes FK → `file_assets.id`          Evidence
                            UNSIGNED                                                        

  `recorded_by_user_id`     Shield user ID                No FK → `users.id`                Recorder
                            type                                                            

  `approved_by_user_id`     Shield user ID               Yes FK → `users.id`                Approver
                            type                                                            

  `created_at`              DATETIME(6)                   No                                Creation
  ----------------------------------------------------------------------------------------------------------------

A branch office requires a branch; a zonal office requires a zone; a
national office has neither. Prevent overlapping current holders for the
same office and scope through a transaction and application validation.
Official office assignment does not create a portal role.

------------------------------------------------------------------------

# 7. MEMBER RECORDS AND REGISTRATION

## 7.1 `members`

Canonical individual member record. No household table is included.

  -----------------------------------------------------------------------------------------------------------------
  Field                          Type                        Null Key / rule                     Meaning
  ------------------------------ -------------- ----------------- ------------------------------ ------------------
  `id`                           BIGINT                        No PK, AI                         Internal ID
                                 UNSIGNED                                                        

  `public_id`                    CHAR(36)                      No UNIQUE                         External reference

  `membership_number`            VARCHAR(60)                  Yes UNIQUE                         Official number
                                                                                                 when authorised

  `first_name`                   VARCHAR(100)                  No INDEX                          First name

  `middle_name`                  VARCHAR(100)                 Yes                                Middle name

  `last_name`                    VARCHAR(100)                  No INDEX                          Last name

  `other_names`                  VARCHAR(150)                 Yes                                Other names

  `gender`                       VARCHAR(20)                  Yes Controlled value               As collected under
                                                                                                 approved form

  `date_of_birth`                DATE                         Yes                                DOB; age is
                                                                                                 derived

  `ancestral_area_id`            BIGINT                       Yes FK →                           Ancestral area
                                 UNSIGNED                         `emirate_ancestral_areas.id`   

  `ancestry_details`             TEXT                         Yes                                Additional
                                                                                                 ancestry

  `current_branch_id`            BIGINT                        No FK → `branches.id`             Current branch
                                 UNSIGNED                                                        

  `current_residence_country`    VARCHAR(100)                 Yes                                Country

  `current_residence_state_id`   BIGINT                       Yes FK → `geo_states.id`           State/FCT
                                 UNSIGNED                                                        

  `current_residence_city`       VARCHAR(160)                 Yes                                City/town

  `current_residence_lga`        VARCHAR(160)                 Yes                                Geographic
                                                                                                 LGA/district

  `current_residence_address`    VARCHAR(500)                 Yes                                Address

  `relationship_to_iedpu`        VARCHAR(120)                 Yes                                Existing IEDPU
                                                                                                 relationship

  `profile_photo_file_id`        BIGINT                       Yes FK → `file_assets.id`          Photo
                                 UNSIGNED                                                        

  `verification_status`          VARCHAR(25)                   No CHECK                          `not_submitted`,
                                                                                                 `pending`,
                                                                                                 `verified`,
                                                                                                 `disputed`,
                                                                                                 `rejected`

  `membership_status`            VARCHAR(25)                   No CHECK                          `active`,
                                                                                                 `suspended`,
                                                                                                 `expelled`,
                                                                                                 `inactive`

  `duplicate_of_member_id`       BIGINT                       Yes FK → same table                Canonical record
                                 UNSIGNED                                                        if duplicate

  `record_version`               BIGINT                        No DEFAULT 1                      Optimistic
                                 UNSIGNED                                                        concurrency
                                                                                                 version

  `verified_at`                  DATETIME(6)                  Yes                                Verification time

  `created_at`                   DATETIME(6)                   No                                Creation

  `updated_at`                   DATETIME(6)                  Yes                                Update

  `created_by_user_id`           Shield user ID               Yes FK → `users.id`                Creator
                                 type                                                            

  `updated_by_user_id`           Shield user ID               Yes FK → `users.id`                Updater
                                 type                                                            

  `anonymised_at`                DATETIME(6)                  Yes                                Controlled
                                                                                                 anonymisation
  -----------------------------------------------------------------------------------------------------------------

**Rules:** Every member belongs to an approved branch. Verification and
membership status are separate. Financial standing is derived from
financial records, not stored as a substitute status. Non-payment does
not automatically invalidate verification. Membership number is separate
from the primary key and is assigned only under the approved
numbering/ID process. Branch transfer preserves history. Date of birth
may remain optional until mandatory fields are formally approved; age
should be derived, not stored as a permanent value. Professional details
are stored separately and consent-controlled.

## 7.2 `member_contact_points`

  Field                   Type                Null Key / rule          Meaning
  ----------------------- ----------------- ------ ------------------- ------------------
  `id`                    BIGINT UNSIGNED       No PK, AI              ID
  `member_id`             BIGINT UNSIGNED       No FK → `members.id`   Member
  `contact_type`          VARCHAR(20)           No CHECK               `phone`, `email`
  `contact_value`         VARCHAR(190)          No                     Display value
  `normalised_value`      VARCHAR(190)          No INDEX               Search value
  `is_primary`            TINYINT(1)            No DEFAULT 0           Primary of type
  `is_verified`           TINYINT(1)            No DEFAULT 0           Verified
  `verified_at`           DATETIME(6)          Yes                     Time
  `verification_method`   VARCHAR(30)          Yes                     Method
  `is_active`             TINYINT(1)            No DEFAULT 1           Active
  `created_at`            DATETIME(6)           No                     Creation
  `updated_at`            DATETIME(6)          Yes                     Update

A member may have multiple contact points. Enforce one active primary
phone and one active primary email per member in application
transactions. Shared family contact values must not automatically be
treated as duplicates. Shield authentication identities remain separate.

## 7.3 `member_professional_details`

  ---------------------------------------------------------------------------------------
  Field                    Type                        Null Key / rule      Meaning
  ------------------------ -------------- ----------------- --------------- -------------
  `id`                     BIGINT                        No PK, AI          ID
                           UNSIGNED                                         

  `member_id`              BIGINT                        No FK →            Member
                           UNSIGNED                         `members.id`,   
                                                            UNIQUE          

  `education_level`        VARCHAR(120)                 Yes                 Optional

  `occupation`             VARCHAR(160)                 Yes                 Optional

  `profession`             VARCHAR(160)                 Yes                 Optional

  `professional_details`   TEXT                         Yes                 Optional

  `updated_at`             DATETIME(6)                  Yes                 Update

  `updated_by_user_id`     Shield user ID               Yes FK → `users.id` Updater
                           type                                             
  ---------------------------------------------------------------------------------------

These fields are optional and must not be required for basic
registration. They may be collected only where the corresponding
optional consent exists.

## 7.4 `member_registrations`

Stores registration submissions separately from the canonical member
record.

  ---------------------------------------------------------------------------------------------------------------
  Field                         Type                       Null Key / rule                     Meaning
  ----------------------------- ------------- ----------------- ------------------------------ ------------------
  `id`                          BIGINT                       No PK, AI                         ID
                                UNSIGNED                                                       

  `public_id`                   CHAR(36)                     No UNIQUE                         Reference

  `registration_reference`      VARCHAR(70)                  No UNIQUE                         Submission
                                                                                               reference

  `member_id`                   BIGINT                       No FK → `members.id`              Member
                                UNSIGNED                                                       

  `branch_id`                   BIGINT                       No FK → `branches.id`             Receiving branch
                                UNSIGNED                                                       

  `submission_channel`          VARCHAR(25)                  No CHECK                          `self`,
                                                                                               `assisted`,
                                                                                               `field_activity`

  `submitted_by_user_id`        Shield user                 Yes FK → `users.id`                Submitter
                                ID type                                                        

  `enumerator_user_id`          Shield user                 Yes FK → `users.id`                Enumerator
                                ID type                                                        

  `submission_status`           VARCHAR(25)                  No CHECK                          `submitted`,
                                                                                               `pending`,
                                                                                               `verified`,
                                                                                               `disputed`,
                                                                                               `rejected`,
                                                                                               `withdrawn`

  `submission_snapshot`         JSON                        Yes                                Submitted data
                                                                                               snapshot

  `privacy_notice_version_id`   BIGINT                      Yes FK →                           Notice
  `registration_form_version_id` BIGINT                      Yes FK → `registration_form_versions.id` Approved form version
                                UNSIGNED                        `privacy_notice_versions.id`   

  `submitted_at`                DATETIME(6)                  No                                Submission

  `last_status_at`              DATETIME(6)                 Yes                                Last status change

  `created_at`                  DATETIME(6)                  No                                Creation
  ---------------------------------------------------------------------------------------------------------------

Assisted submissions identify the responsible enumerator or authorised
submitter. Preserve snapshots. Rejected/withdrawn submissions remain
historical records. Duplicate controls must prevent uncontrolled
parallel member identities.

## 7.5 `member_verification_reviews`

  ------------------------------------------------------------------------------------------------------------
  Field                   Type                       Null Key / rule                  Meaning
  ----------------------- ------------- ----------------- --------------------------- ------------------------
  `id`                    BIGINT                       No PK, AI                      ID
                          UNSIGNED                                                    

  `member_id`             BIGINT                       No FK → `members.id`           Member
                          UNSIGNED                                                    

  `registration_id`       BIGINT                      Yes FK →                        Submission
                          UNSIGNED                        `member_registrations.id`   

  `branch_id`             BIGINT                       No FK → `branches.id`          Reviewing branch
                          UNSIGNED                                                    

  `review_stage`          VARCHAR(25)                  No CHECK                       `preliminary`, `final`,
                                                                                      `committee_escalation`

  `decision`              VARCHAR(25)                  No CHECK                       `approved`,
                                                                                      `request_correction`,
                                                                                      `disputed`, `rejected`,
                                                                                      `escalated`

  `decision_reason`       TEXT                         No                             Reason

  `reviewed_by_user_id`   Shield user                  No FK → `users.id`             Reviewer
                          ID type                                                     

  `reviewed_at`           DATETIME(6)                  No                             Time

  `approval_request_id`   BIGINT                      Yes FK → `approval_requests.id` Additional approval
                          UNSIGNED                                                    

  `created_at`            DATETIME(6)                  No                             Creation
  ------------------------------------------------------------------------------------------------------------

Branch is the first verification authority. Branch Chairman retains
final branch-level responsibility subject to delegated permissions;
authorised executives may perform preliminary review. Unresolved cases
can be escalated to the Initiative Committee. Each decision is a
historical row; verification is online-only.

## 7.6 `member_status_events`

Records authorised membership-status changes, including suspension,
expulsion and readmission.

  --------------------------------------------------------------------------------------------
  Field                   Type                        Null Key / rule     Meaning
  ----------------------- -------------- ----------------- -------------- --------------------
  `id`                    BIGINT                        No PK, AI         ID
                          UNSIGNED                                        

  `member_id`             BIGINT                        No FK →           Member
                          UNSIGNED                         `members.id`   

  `previous_status`       VARCHAR(25)                  Yes                Previous

  `new_status`            VARCHAR(25)                   No CHECK          New

  `decision_reason`       TEXT                          No                Reason

  `decision_authority`    VARCHAR(80)                   No                Authorised IEDPU
                                                                          body

  `decision_reference`    VARCHAR(150)                 Yes                Minutes/resolution

  `effective_date`        DATE                          No                Effective date

  `recorded_by_user_id`   Shield user ID                No FK →           Recorder
                          type                             `users.id`     

  `approved_by_user_id`   Shield user ID               Yes FK →           Approver
                          type                             `users.id`     

  `created_at`            DATETIME(6)                   No                Creation
  --------------------------------------------------------------------------------------------

Do not automatically suspend or expel a member for unpaid dues.
Readmission must preserve the previous status history.

## 7.7 `member_profile_revisions`

Records accepted profile changes, including accepted
offline-synchronised changes.

  ---------------------------------------------------------------------------------------------
  Field                       Type                        Null Key / rule     Meaning
  --------------------------- -------------- ----------------- -------------- -----------------
  `id`                        BIGINT                        No PK, AI         ID
                              UNSIGNED                                        

  `member_id`                 BIGINT                        No FK →           Member
                              UNSIGNED                         `members.id`   

  `actor_user_id`             Shield user ID               Yes FK →           Actor
                              type                             `users.id`     

  `change_source`             VARCHAR(20)                   No CHECK          `online`,
                                                                              `offline_sync`,
                                                                              `admin`,
                                                                              `migration`

  `changed_fields`            JSON                          No                Field names

  `before_values`             JSON                         Yes                Masked prior
                                                                              values

  `after_values`              JSON                         Yes                Accepted values

  `sync_mutation_reference`   CHAR(36)                     Yes INDEX          Sync ID

  `reason`                    VARCHAR(500)                 Yes                Reason

  `created_at`                DATETIME(6)                   No                Revision time
  ---------------------------------------------------------------------------------------------

Create a revision only after server acceptance. A local-only change is
not an accepted revision.

## 7.8 `member_profile_change_requests`

Protected-field correction requests.

  -------------------------------------------------------------------------------------------------
  Field                    Type                       Null Key / rule               Meaning
  ------------------------ ------------- ----------------- ------------------------ ---------------
  `id`                     BIGINT                       No PK, AI                   ID
                           UNSIGNED                                                 

  `public_id`              CHAR(36)                     No UNIQUE                   Reference

  `member_id`              BIGINT                       No FK → `members.id`        Member
                           UNSIGNED                                                 

  `requested_by_user_id`   Shield user                  No FK → `users.id`          Requester
                           ID type                                                  

  `requested_fields`       JSON                         No                          Requested
                                                                                    fields/values

  `reason`                 TEXT                         No                          Reason

  `request_status`         VARCHAR(25)                  No CHECK                    `pending`,
                                                                                    `approved`,
                                                                                    `rejected`,
                                                                                    `returned`,
                                                                                    `cancelled`

  `reviewed_by_user_id`    Shield user                 Yes FK → `users.id`          Reviewer
                           ID type                                                  

  `review_reason`          TEXT                        Yes                          Decision

  `approval_request_id`    BIGINT                      Yes FK →                     Approval
                           UNSIGNED                        `approval_requests.id`   

  `submitted_at`           DATETIME(6)                  No                          Submitted

  `reviewed_at`            DATETIME(6)                 Yes                          Reviewed
  -------------------------------------------------------------------------------------------------

Protected fields include legal/verified name, date of birth,
authentication contact, branch, membership status, official office,
financial data and ID status. Approval is online-only.

## 7.9 `member_duplicate_cases`

  ---------------------------------------------------------------------------------------------------
  Field                            Type                        Null Key / rule     Meaning
  -------------------------------- -------------- ----------------- -------------- ------------------
  `id`                             BIGINT                        No PK, AI         ID
                                   UNSIGNED                                        

  `member_id`                      BIGINT                        No FK →           First record
                                   UNSIGNED                         `members.id`   

  `possible_duplicate_member_id`   BIGINT                        No FK →           Candidate
                                   UNSIGNED                         `members.id`   

  `case_status`                    VARCHAR(25)                   No CHECK          `open`,
                                                                                   `confirmed`,
                                                                                   `not_duplicate`,
                                                                                   `merged`, `closed`

  `match_reason`                   VARCHAR(500)                  No                Reason

  `review_notes`                   TEXT                         Yes                Notes

  `reviewed_by_user_id`            Shield user ID               Yes FK →           Reviewer
                                   type                             `users.id`     

  `reviewed_at`                    DATETIME(6)                  Yes                Review

  `canonical_member_id`            BIGINT                       Yes FK →           Record retained
                                   UNSIGNED                         `members.id`   

  `created_at`                     DATETIME(6)                   No                Creation
  ---------------------------------------------------------------------------------------------------

A record cannot be compared with itself. UNIQUE (`member_id`,
`possible_duplicate_member_id`). Similarity is a review aid, not proof.
Preserve the duplicate record and its history.

## 7.10 `member_data_quality_issues`

  ----------------------------------------------------------------------------------------------------------
  Field                   Type                       Null Key / rule                  Meaning
  ----------------------- ------------- ----------------- --------------------------- ----------------------
  `id`                    BIGINT                       No PK, AI                      ID
                          UNSIGNED                                                    

  `member_id`             BIGINT                       No FK → `members.id`           Member
                          UNSIGNED                                                    

  `registration_id`       BIGINT                      Yes FK →                        Submission
                          UNSIGNED                        `member_registrations.id`   

  `issue_type`            VARCHAR(30)                  No CHECK                       `missing_data`,
                                                                                      `inconsistent_data`,
                                                                                      `duplicate`,
                                                                                      `invalid_value`,
                                                                                      `other`

  `field_code`            VARCHAR(80)                 Yes                             Field

  `issue_description`     TEXT                         No                             Issue

  `issue_status`          VARCHAR(20)                  No CHECK                       `open`, `in_review`,
                                                                                      `resolved`,
                                                                                      `dismissed`

  `resolved_by_user_id`   Shield user                 Yes FK → `users.id`             Resolver
                          ID type                                                     

  `resolution_notes`      TEXT                        Yes                             Resolution

  `created_at`            DATETIME(6)                  No                             Creation

  `resolved_at`           DATETIME(6)                 Yes                             Resolution
  ----------------------------------------------------------------------------------------------------------

## 7.11 `member_branch_assignments`

**Purpose:** Preserves the full branch history when a member transfers.
`members.current_branch_id` is the current-state convenience field; this
table is the authoritative history of branch membership assignments.

  --------------------------------------------------------------------------------------------------
  Field                   Type                       Null Key / rule               Meaning
  ----------------------- ------------- ----------------- ------------------------ -----------------
  `id`                    BIGINT                       No PK, AI                   Assignment ID
                          UNSIGNED                                                 

  `member_id`             BIGINT                       No FK → `members.id`        Member
                          UNSIGNED                                                 

  `branch_id`             BIGINT                       No FK → `branches.id`       Assigned branch
                          UNSIGNED                                                 

  `starts_on`             DATE                         No                          Assignment start

  `ends_on`               DATE                        Yes                          Assignment end

  `assignment_status`     VARCHAR(20)                  No CHECK                    `current`,
                                                                                   `previous`,
                                                                                   `transferred`,
                                                                                   `cancelled`

  `transfer_reason`       TEXT                        Yes                          Reason for
                                                                                   transfer/change

  `approval_request_id`   BIGINT                      Yes FK →                     Approval, where
                          UNSIGNED                        `approval_requests.id`   required

  `recorded_by_user_id`   Shield user                  No FK → `users.id`          Recorder
                          ID type                                                  

  `approved_by_user_id`   Shield user                 Yes FK → `users.id`          Approver
                          ID type                                                  

  `created_at`            DATETIME(6)                  No                          Creation
  --------------------------------------------------------------------------------------------------

**Rules:** A member must have one current branch assignment. Initial
registration creates the first assignment. A transfer closes the
previous assignment and creates a new current assignment; it must not
overwrite the previous row. `members.current_branch_id` must be updated
in the same database transaction. The application must prevent
overlapping current assignments and preserve all financial and
verification history.

## 7.12 `identifier_sequences`

**Purpose:** Generates human-readable registration references and
membership numbers without hardcoding the final visual format into
application logic.

  -------------------------------------------------------------------------------------------
  Field             Type                       Null Key / rule    Meaning
  ----------------- ------------- ----------------- ------------- ---------------------------
  `id`              BIGINT                       No PK, AI        ID
                    UNSIGNED                                      

  `sequence_code`   VARCHAR(60)                  No UNIQUE        e.g. `MEMBERSHIP_NUMBER`,
                                                                  `REGISTRATION_REFERENCE`

  `prefix`          VARCHAR(30)                  No               Approved prefix

  `period_key`      VARCHAR(20)                  No UNIQUE with   Period/series key
                                                    sequence code 

  `last_value`      BIGINT                       No DEFAULT 0     Last issued number
                    UNSIGNED                                      

  `number_width`    TINYINT                      No DEFAULT 6     Zero-padding width
                    UNSIGNED                                      

  `reset_period`    VARCHAR(20)                  No CHECK         `never`, `yearly`

  `is_active`       TINYINT(1)                   No DEFAULT 1     Active

  `updated_at`      DATETIME(6)                  No               Last update
  -------------------------------------------------------------------------------------------

**Rules:** Generate a number inside a database transaction using a row
lock (`SELECT ... FOR UPDATE`) so simultaneous registrations cannot
receive the same number. The final member-number format must be approved
before production ID issuance. A membership number is stable once
assigned and must not change on branch transfer or card replacement.

## 7.13 `registration_form_versions`

**Purpose:** Versions the registration form and its approved field
rules. The initiative proposal lists information to collect but does not
specify every field's mandatory/optional status; this table allows that
decision to be formally approved and versioned.

  ------------------------------------------------------------------------------------
  Field                   Type                        Null Key / rule    Meaning
  ----------------------- -------------- ----------------- ------------- -------------
  `id`                    BIGINT                        No PK, AI        Form version
                          UNSIGNED                                       ID

  `version_code`          VARCHAR(40)                   No UNIQUE        Version

  `title`                 VARCHAR(180)                  No               Form title

  `description`           TEXT                         Yes               Description

  `form_status`           VARCHAR(20)                   No CHECK         `draft`,
                                                                         `approved`,
                                                                         `active`,
                                                                         `retired`

  `approved_by_user_id`   Shield user ID               Yes FK →          Approver
                          type                             `users.id`    

  `approval_reference`    VARCHAR(150)                 Yes               Approval
                                                                         reference

  `effective_from`        DATETIME(6)                  Yes               Start

  `effective_to`          DATETIME(6)                  Yes               End

  `created_at`            DATETIME(6)                   No               Creation
  ------------------------------------------------------------------------------------

## 7.14 `registration_field_rules`

**Purpose:** Defines which registration fields are required, optional or
included in the basic completeness measure for a particular approved
form version.

  -------------------------------------------------------------------------------------------------------------------
  Field                       Type                        Null Key / rule                        Meaning
  --------------------------- -------------- ----------------- --------------------------------- --------------------
  `id`                        BIGINT                        No PK, AI                            Rule ID
                              UNSIGNED                                                           

  `form_version_id`           BIGINT                        No FK →                              Form version
                              UNSIGNED                         `registration_form_versions.id`   

  `field_code`                VARCHAR(80)                   No UNIQUE with form version          Stable field key

  `field_label`               VARCHAR(160)                  No                                   Display label

  `data_type`                 VARCHAR(30)                   No                                   Expected field type

  `is_required`               TINYINT(1)                    No DEFAULT 0                         Required for
                                                                                                 submission

  `counts_for_completeness`   TINYINT(1)                    No DEFAULT 1                         Included in
                                                                                                 completeness

  `optional_consent_type`     VARCHAR(60)                  Yes                                   Required consent
                                                                                                 category, if
                                                                                                 optional/sensitive

  `sequence_number`           SMALLINT                      No                                   Display order
                              UNSIGNED                                                           

  `is_active`                 TINYINT(1)                    No DEFAULT 1                         Active

  `created_at`                DATETIME(6)                   No                                   Creation
  -------------------------------------------------------------------------------------------------------------------

A form version and its field rules must be approved before activation.
Optional professional fields must not be made mandatory without an
approved change and consent arrangement.

------------------------------------------------------------------------

# 8. PRIVACY, CONSENT AND FILE RECORDS

## 8.1 `privacy_notice_versions`

Stores each approved privacy notice version.

  ------------------------------------------------------------------------------------
  Field                   Type                        Null Key / rule    Meaning
  ----------------------- -------------- ----------------- ------------- -------------
  `id`                    BIGINT                        No PK, AI        ID
                          UNSIGNED                                       

  `version_code`          VARCHAR(40)                   No UNIQUE        Version

  `title`                 VARCHAR(180)                  No               Title

  `notice_text`           LONGTEXT                      No               Approved
                                                                         notice

  `effective_at`          DATETIME(6)                   No               Effective
                                                                         time

  `retired_at`            DATETIME(6)                  Yes               Retirement

  `approved_by_user_id`   Shield user ID               Yes FK →          Approver
                          type                             `users.id`    

  `created_at`            DATETIME(6)                   No               Creation
  ------------------------------------------------------------------------------------

## 8.2 `member_privacy_acknowledgements`

Records which notice version was shown and acknowledged.

  --------------------------------------------------------------------------------------------------------------
  Field                         Type                       Null Key / rule                     Meaning
  ----------------------------- ------------- ----------------- ------------------------------ -----------------
  `id`                          BIGINT                       No PK, AI                         ID
                                UNSIGNED                                                       

  `member_id`                   BIGINT                       No FK → `members.id`              Member
                                UNSIGNED                                                       

  `privacy_notice_version_id`   BIGINT                       No FK →                           Notice
                                UNSIGNED                        `privacy_notice_versions.id`   

  `acknowledgement_method`      VARCHAR(30)                  No                                Online/assisted
                                                                                               method

  `acknowledged_by_user_id`     Shield user                 Yes FK → `users.id`                Actor
                                ID type                                                        

  `acknowledged_at`             DATETIME(6)                  No                                Time

  `source_registration_id`      BIGINT                      Yes FK → `member_registrations.id` Registration
                                UNSIGNED                                                       
  --------------------------------------------------------------------------------------------------------------

An acknowledgement is not automatically consent to optional professional
data.

## 8.3 `member_consents`

Records purpose-specific optional consent.

  --------------------------------------------------------------------------------------------------------------------------
  Field                         Type                       Null Key / rule                     Meaning
  ----------------------------- ------------- ----------------- ------------------------------ -----------------------------
  `id`                          BIGINT                       No PK, AI                         ID
                                UNSIGNED                                                       

  `member_id`                   BIGINT                       No FK → `members.id`              Member
                                UNSIGNED                                                       

  `consent_type`                VARCHAR(60)                  No INDEX                          e.g. `professional_profile`

  `purpose_description`         TEXT                         No                                Purpose

  `privacy_notice_version_id`   BIGINT                      Yes FK →                           Notice
                                UNSIGNED                        `privacy_notice_versions.id`   

  `consent_status`              VARCHAR(20)                  No CHECK                          `granted`, `withdrawn`,
                                                                                               `expired`

  `granted_at`                  DATETIME(6)                 Yes                                Grant

  `withdrawn_at`                DATETIME(6)                 Yes                                Withdrawal

  `recorded_by_user_id`         Shield user                 Yes FK → `users.id`                Actor
                                ID type                                                        

  `consent_method`              VARCHAR(30)                  No                                Method

  `created_at`                  DATETIME(6)                  No                                Creation
  --------------------------------------------------------------------------------------------------------------------------

Consent must be specific to a purpose. Withdrawal preserves history. A
consent table does not itself establish the lawful basis for every
processing activity; IEDPU must approve the data-governance framework.

## 8.4 `file_assets`

Stores metadata for files whose bytes reside in protected file storage.

  ----------------------------------------------------------------------------------------
  Field                   Type                        Null Key / rule    Meaning
  ----------------------- -------------- ----------------- ------------- -----------------
  `id`                    BIGINT                        No PK, AI        ID
                          UNSIGNED                                       

  `public_id`             CHAR(36)                      No UNIQUE        Reference

  `storage_disk`          VARCHAR(40)                   No               Storage location
                                                                         code

  `storage_key`           VARCHAR(500)                  No UNIQUE        Private storage
                                                                         key

  `original_filename`     VARCHAR(255)                  No               Original filename

  `stored_filename`       VARCHAR(255)                  No               Generated
                                                                         filename

  `mime_type`             VARCHAR(120)                  No               Validated MIME

  `file_extension`        VARCHAR(15)                   No               Extension

  `file_size_bytes`       BIGINT                        No               Size
                          UNSIGNED                                       

  `sha256_hash`           CHAR(64)                      No INDEX         Checksum

  `classification`        VARCHAR(25)                   No CHECK         `public`,
                                                                         `internal`,
                                                                         `confidential`,
                                                                         `restricted`

  `scan_status`           VARCHAR(20)                   No DEFAULT       `pending`,
                                                           `pending`     `clean`,
                                                                         `rejected`,
                                                                         `not_available`

  `uploaded_by_user_id`   Shield user ID               Yes FK →          Uploader
                          type                             `users.id`    

  `uploaded_at`           DATETIME(6)                   No               Upload time

  `deleted_at`            DATETIME(6)                  Yes               Logical deletion
  ----------------------------------------------------------------------------------------

Validate actual file content, MIME type, extension and size. Never
execute uploaded files. Keep private files outside the public web root.
Do not expose `storage_key` through public APIs.

## 8.5 `member_documents`

Links approved member-related files.

  ----------------------------------------------------------------------------------------------------
  Field                   Type                        Null Key / rule         Meaning
  ----------------------- -------------- ----------------- ------------------ ------------------------
  `id`                    BIGINT                        No PK, AI             ID
                          UNSIGNED                                            

  `member_id`             BIGINT                        No FK → `members.id`  Member
                          UNSIGNED                                            

  `file_asset_id`         BIGINT                        No FK →               File
                          UNSIGNED                         `file_assets.id`   

  `document_type`         VARCHAR(40)                   No                    `profile_photo`,
                                                                              `supporting_document`,
                                                                              approved type

  `purpose`               VARCHAR(250)                 Yes                    Purpose

  `visibility`            VARCHAR(20)                   No CHECK              `member_only`,
                                                                              `branch_authorised`,
                                                                              `committee_authorised`

  `uploaded_by_user_id`   Shield user ID               Yes FK → `users.id`    Uploader
                          type                                                

  `created_at`            DATETIME(6)                   No                    Creation
  ----------------------------------------------------------------------------------------------------

Do not collect identity documents unless the Committee approves their
purpose, access rules and retention period.

## 8.6 `data_subject_requests`

Records member requests about their personal information.

  ---------------------------------------------------------------------------------------
  Field                   Type                       Null Key / rule     Meaning
  ----------------------- ------------- ----------------- -------------- ----------------
  `id`                    BIGINT                       No PK, AI         ID
                          UNSIGNED                                       

  `public_id`             CHAR(36)                     No UNIQUE         Reference

  `member_id`             BIGINT                       No FK →           Member
                          UNSIGNED                        `members.id`   

  `request_type`          VARCHAR(30)                  No CHECK          `access`,
                                                                         `correction`,
                                                                         `deletion`,
                                                                         `restriction`,
                                                                         `other`

  `request_description`   TEXT                         No                Request

  `request_status`        VARCHAR(25)                  No CHECK          `received`,
                                                                         `in_review`,
                                                                         `approved`,
                                                                         `rejected`,
                                                                         `completed`

  `assigned_to_user_id`   Shield user                 Yes FK →           Reviewer
                          ID type                         `users.id`     

  `decision_reason`       TEXT                        Yes                Reason

  `received_at`           DATETIME(6)                  No                Received

  `completed_at`          DATETIME(6)                 Yes                Completed
  ---------------------------------------------------------------------------------------

------------------------------------------------------------------------

# 9. APPROVAL WORKFLOW AND AUDIT

## 9.1 `approval_requests`

Reusable maker-checker approval mechanism for sensitive actions.

`subject_type` and `subject_id` are a controlled polymorphic reference.
MySQL cannot enforce a normal foreign key across several possible target
tables; the application service must validate the subject type and
record before creating or completing the approval.

  --------------------------------------------------------------------------------------
  Field                    Type                        Null Key / rule    Meaning
  ------------------------ -------------- ----------------- ------------- --------------
  `id`                     BIGINT                        No PK, AI        ID
                           UNSIGNED                                       

  `public_id`              CHAR(36)                      No UNIQUE        Reference

  `action_key`             VARCHAR(120)                  No INDEX         Action

  `subject_type`           VARCHAR(60)                   No INDEX         Entity code

  `subject_id`             BIGINT                        No INDEX         Target ID
                           UNSIGNED                                       

  `requested_by_user_id`   Shield user ID                No FK →          Initiator
                           type                             `users.id`    

  `request_reason`         TEXT                          No               Reason

  `required_approvals`     TINYINT                       No DEFAULT 1     Number
                           UNSIGNED                                       required

  `approval_status`        VARCHAR(25)                   No CHECK         `pending`,
                                                                          `approved`,
                                                                          `rejected`,
                                                                          `cancelled`,
                                                                          `expired`

  `submitted_at`           DATETIME(6)                   No               Submitted

  `completed_at`           DATETIME(6)                  Yes               Completed

  `expires_at`             DATETIME(6)                  Yes               Expiry
  --------------------------------------------------------------------------------------

The requester must not approve their own request where maker-checker
separation applies. Approval does not itself perform the business
action; the relevant service revalidates and executes it.

## 9.2 `approval_decisions`

  ----------------------------------------------------------------------------------------------
  Field                   Type                       Null Key / rule               Meaning
  ----------------------- ------------- ----------------- ------------------------ -------------
  `id`                    BIGINT                       No PK, AI                   ID
                          UNSIGNED                                                 

  `approval_request_id`   BIGINT                       No FK →                     Request
                          UNSIGNED                        `approval_requests.id`   

  `approver_user_id`      Shield user                  No FK → `users.id`          Approver
                          ID type                                                  

  `decision`              VARCHAR(20)                  No CHECK                    `approved`,
                                                                                   `rejected`,
                                                                                   `returned`

  `decision_reason`       TEXT                         No                          Reason

  `decided_at`            DATETIME(6)                  No                          Time
  ----------------------------------------------------------------------------------------------

UNIQUE (`approval_request_id`, `approver_user_id`).

## 9.3 `audit_events`

Principal append-only business audit trail.

  --------------------------------------------------------------------------------------------
  Field                      Type                        Null Key / rule       Meaning
  -------------------------- -------------- ----------------- ---------------- ---------------
  `id`                       BIGINT                        No PK, AI           Event ID
                             UNSIGNED                                          

  `event_uuid`               CHAR(36)                      No UNIQUE           Event reference

  `occurred_at`              DATETIME(6)                   No INDEX            UTC event time

  `actor_user_id`            Shield user ID               Yes FK → `users.id`  Actor
                             type                                              

  `actor_member_id`          BIGINT                       Yes FK →             Linked member
                             UNSIGNED                         `members.id`     

  `actor_role_snapshot`      JSON                         Yes                  Effective
                                                                               role/scope
                                                                               snapshot

  `action_key`               VARCHAR(120)                  No INDEX            Action

  `permission_key`           VARCHAR(120)                 Yes INDEX            Permission

  `module_code`              VARCHAR(60)                   No INDEX            Module

  `event_category`           VARCHAR(30)                   No INDEX            `membership`,
                                                                               `finance`,
                                                                               `security`,
                                                                               `role`,
                                                                               `content`,
                                                                               `system`,
                                                                               `data_access`

  `severity`                 VARCHAR(20)                   No CHECK            `info`,
                                                                               `warning`,
                                                                               `high`,
                                                                               `critical`

  `subject_type`             VARCHAR(60)                  Yes INDEX            Affected entity

  `subject_id`               BIGINT                       Yes INDEX            Affected record
                             UNSIGNED                                          

  `zone_id`                  BIGINT                       Yes FK →             Zone
                             UNSIGNED                         `org_zones.id`   

  `branch_id`                BIGINT                       Yes FK →             Branch
                             UNSIGNED                         `branches.id`    

  `outcome`                  VARCHAR(20)                   No CHECK            `success`,
                                                                               `failure`,
                                                                               `denied`,
                                                                               `deferred`

  `reason`                   TEXT                         Yes                  Reason

  `before_snapshot`          JSON                         Yes                  Masked before
                                                                               values

  `after_snapshot`           JSON                         Yes                  Masked after
                                                                               values

  `request_id`               VARCHAR(100)                 Yes INDEX            API correlation
                                                                               ID

  `session_reference_hash`   CHAR(64)                     Yes                  Pseudonymised
                                                                               session
                                                                               reference

  `ip_address`               VARCHAR(45)                  Yes                  IP address,
                                                                               subject to
                                                                               policy

  `user_agent`               VARCHAR(500)                 Yes                  Client

  `previous_event_hash`      CHAR(64)                     Yes                  Previous hash

  `event_hash`               CHAR(64)                      No INDEX            Current event
                                                                               hash
  --------------------------------------------------------------------------------------------

Audit rules:

-   Write through a dedicated server-side audit service.
-   Ordinary application screens cannot update/delete audit events.
-   Restrict database `UPDATE` and `DELETE` privileges on this table
    where feasible.
-   Mask or omit secrets and unnecessary personal data.
-   Audit viewing and exporting are themselves audited.
-   Audit-write failure must generate an operational/security event.
-   Hash chaining is tamper evidence, not a complete guarantee if one
    attacker controls the database and every backup. Independent
    protected copies/checkpoints are required.

## 9.4 `security_events`

  ------------------------------------------------------------------------------------
  Field                   Type                        Null Key / rule    Meaning
  ----------------------- -------------- ----------------- ------------- -------------
  `id`                    BIGINT                        No PK, AI        ID
                          UNSIGNED                                       

  `event_uuid`            CHAR(36)                      No UNIQUE        Reference

  `event_code`            VARCHAR(100)                  No INDEX         Event code

  `severity`              VARCHAR(20)                   No CHECK         `low`,
                                                                         `medium`,
                                                                         `high`,
                                                                         `critical`

  `user_id`               Shield user ID               Yes FK →          Account
                          type                             `users.id`    

  `ip_address`            VARCHAR(45)                  Yes INDEX         IP

  `user_agent`            VARCHAR(500)                 Yes               Client

  `context_data`          JSON                         Yes               Sanitised
                                                                         context

  `occurred_at`           DATETIME(6)                   No INDEX         Time

  `resolved_at`           DATETIME(6)                  Yes               Resolution

  `resolved_by_user_id`   Shield user ID               Yes FK →          Resolver
                          type                             `users.id`    
  ------------------------------------------------------------------------------------

Examples: repeated failed logins, suspicious privilege changes, access
denials, session anomalies and audit-write failures.

## 9.5 `audit_chain_checkpoints`

  ----------------------------------------------------------------------------------------
  Field                       Type                        Null Key / rule    Meaning
  --------------------------- -------------- ----------------- ------------- -------------
  `id`                        BIGINT                        No PK, AI        ID
                              UNSIGNED                                       

  `first_event_id`            BIGINT                        No               First event
                              UNSIGNED                                       

  `last_event_id`             BIGINT                        No               Last event
                              UNSIGNED                                       

  `event_count`               BIGINT                        No               Count
                              UNSIGNED                                       

  `chain_hash`                CHAR(64)                      No UNIQUE        Final chain
                                                                             hash

  `checkpoint_at`             DATETIME(6)                   No               Time

  `external_copy_reference`   VARCHAR(500)                 Yes               Independent
                                                                             copy

  `verification_status`       VARCHAR(20)                   No DEFAULT       `pending`,
                                                               `pending`     `verified`,
                                                                             `failed`
  ----------------------------------------------------------------------------------------

Store independent checkpoints outside the same database account and,
where practical, outside the same hosting account.

------------------------------------------------------------------------

# 10. BRANCH BASELINE AND COVERAGE

## 10.1 `branch_member_baselines`

Stores each branch's submitted estimate of its existing member
population.

  ---------------------------------------------------------------------------------------------
  Field                         Type                        Null Key / rule      Meaning
  ----------------------------- -------------- ----------------- --------------- --------------
  `id`                          BIGINT                        No PK, AI          ID
                                UNSIGNED                                         

  `branch_id`                   BIGINT                        No FK →            Branch
                                UNSIGNED                         `branches.id`   

  `expected_existing_members`   INT UNSIGNED                  No CHECK \>= 0     Estimated
                                                                                 existing
                                                                                 members

  `baseline_as_of_date`         DATE                          No                 Estimate date

  `submitted_by_user_id`        Shield user ID                No FK → `users.id` Submitter
                                type                                             

  `approved_by_user_id`         Shield user ID               Yes FK → `users.id` Approver
                                type                                             

  `baseline_status`             VARCHAR(20)                   No CHECK           `draft`,
                                                                                 `submitted`,
                                                                                 `approved`,
                                                                                 `rejected`,
                                                                                 `superseded`

  `source_notes`                TEXT                         Yes                 Basis of
                                                                                 estimate

  `approval_reference`          VARCHAR(150)                 Yes                 Reference

  `submitted_at`                DATETIME(6)                  Yes                 Submission

  `approved_at`                 DATETIME(6)                  Yes                 Approval

  `created_at`                  DATETIME(6)                   No                 Creation
  ---------------------------------------------------------------------------------------------

Keep previous baselines. Only an approved baseline is used in official
coverage reporting. A branch baseline is a branch-reported estimate, not
a verified member list.

## 10.2 Coverage and completeness calculations

These measures should be calculated by the reporting layer from the
underlying records.

**Registration coverage**

``` text
Unique members registered for a branch
÷ Approved expected existing members for that branch
× 100
```

**Verification completion**

``` text
Unique verified members
÷ Unique submitted member records
× 100
```

**Data completeness**

Calculate against the approved registration field requirements. The
initiative proposal identifies fields to collect but does not make every
field's mandatory/optional status explicit. Approve the required-field
list before launch. Optional education, occupation and professional
details must not reduce basic completeness when the member has not
consented to provide them.

Show registered, verified and baseline counts separately. Do not
describe this first-release member register as a complete statistical
census of all Ilorin Emirate descendants.

------------------------------------------------------------------------

# 11. CONFIGURABLE FINANCIAL OBLIGATIONS

## 11.1 Financial design principles

The Constitution refers to monthly dues, branch levies, National
Headquarters levies on branches, annual branch dues, donations and other
lawful income. The portal must support configurable financial
obligations without hardcoding amounts or a universal payment schedule.

Distinguish: - Financial obligation. - Member/branch assessment. -
Payment transaction. - Payment allocation. - Ledger entry.

## 11.2 `financial_obligation_types`

  ------------------------------------------------------------------------------
  Field             Type                        Null Key / rule    Meaning
  ----------------- -------------- ----------------- ------------- -------------
  `id`              BIGINT                        No PK, AI        ID
                    UNSIGNED                                       

  `type_code`       VARCHAR(60)                   No UNIQUE        Code

  `type_name`       VARCHAR(120)                  No               Name

  `description`     TEXT                         Yes               Explanation

  `subject_scope`   VARCHAR(20)                   No CHECK         `member`,
                                                                   `branch`,
                                                                   `either`

  `is_active`       TINYINT(1)                    No DEFAULT 1     Active

  `created_at`      DATETIME(6)                   No               Creation
  ------------------------------------------------------------------------------

Categories may include monthly dues, member levy, branch annual due,
branch levy, conference-related payment, donation and other approved
income. Categories are labels only; they do not contain amounts.

## 11.3 `obligation_series`

Stores recurring rules. Each actual occurrence is a separate obligation
record.

  --------------------------------------------------------------------------------------
  Field                    Type                        Null Key / rule    Meaning
  ------------------------ -------------- ----------------- ------------- --------------
  `id`                     BIGINT                        No PK, AI        ID
                           UNSIGNED                                       

  `public_id`              CHAR(36)                      No UNIQUE        Reference

  `series_name`            VARCHAR(180)                  No               Name

  `subject_scope`          VARCHAR(20)                   No CHECK         `member`,
                                                                          `branch`

  `recurrence_frequency`   VARCHAR(20)                   No CHECK         `monthly`,
                                                                          `quarterly`,
                                                                          `annual`,
                                                                          `custom`

  `recurrence_interval`    SMALLINT                      No DEFAULT 1     Every N
                           UNSIGNED                                       periods

  `start_date`             DATE                          No               Start

  `end_date`               DATE                         Yes               End

  `next_generation_date`   DATE                         Yes               Next
                                                                          occurrence

  `series_status`          VARCHAR(20)                   No CHECK         `active`,
                                                                          `paused`,
                                                                          `completed`,
                                                                          `cancelled`

  `created_by_user_id`     Shield user ID                No FK →          Creator
                           type                             `users.id`    

  `created_at`             DATETIME(6)                   No               Creation
  --------------------------------------------------------------------------------------

A series does not itself charge a member. Each occurrence is a distinct
approved obligation with its own amount, reason, deadline and target
snapshot. Recurrence generation must be idempotent.

## 11.4 `financial_obligations`

Each actual configurable due, levy or other financial obligation.

  --------------------------------------------------------------------------------------------------------------
  Field                     Type                         Null Key / rule                        Meaning
  ------------------------- --------------- ----------------- --------------------------------- ----------------
  `id`                      BIGINT UNSIGNED                No PK, AI                            ID

  `public_id`               CHAR(36)                       No UNIQUE                            Reference

  `obligation_reference`    VARCHAR(70)                    No UNIQUE                            Human-readable
                                                                                                reference

  `obligation_type_id`      BIGINT UNSIGNED                No FK →                              Type
                                                              `financial_obligation_types.id`   

  `series_id`               BIGINT UNSIGNED               Yes FK → `obligation_series.id`       Recurring series

  `subject_scope`           VARCHAR(20)                    No CHECK                             `member`,
                                                                                                `branch`

  `title`                   VARCHAR(180)                   No                                   Title

  `reason`                  TEXT                           No                                   Purpose

  `amount`                  DECIMAL(15,2)                  No CHECK \> 0                        Amount per
                                                                                                subject

  `currency_code`           CHAR(3)                        No DEFAULT `NGN`                     Currency

  `issued_at`               DATETIME(6)                   Yes                                   Published

  `due_date`                DATE                           No                                   Deadline

  `obligation_status`       VARCHAR(25)                    No CHECK                             `draft`,
                                                                                                `submitted`,
                                                                                                `approved`,
                                                                                                `active`,
                                                                                                `closed`,
                                                                                                `cancelled`

  `collection_account_id`   BIGINT UNSIGNED               Yes FK →                              Approved
                                                              `branch_collection_accounts.id`   destination

  `created_by_user_id`      Shield user ID                 No FK → `users.id`                   Creator
                            type                                                                

  `approval_request_id`     BIGINT UNSIGNED               Yes FK → `approval_requests.id`       Approval

  `approved_by_user_id`     Shield user ID                Yes FK → `users.id`                   Approver
                            type                                                                

  `approved_at`             DATETIME(6)                   Yes                                   Approval

  `closed_at`               DATETIME(6)                   Yes                                   Close

  `cancelled_at`            DATETIME(6)                   Yes                                   Cancel

  `cancellation_reason`     TEXT                          Yes                                   Reason

  `created_at`              DATETIME(6)                    No                                   Creation

  `updated_at`              DATETIME(6)                   Yes                                   Update
  --------------------------------------------------------------------------------------------------------------

Amounts are database values, never application constants. Draft
obligations are not payable. Published terms must be snapshotted into
assessments. Changes after publication are authorised amendments, not
silent overwrites.

## 11.5 `obligation_target_rules`

  ----------------------------------------------------------------------------------------------------------
  Field                  Type                       Null Key / rule                     Meaning
  ---------------------- ------------- ----------------- ------------------------------ --------------------
  `id`                   BIGINT                       No PK, AI                         ID
                         UNSIGNED                                                       

  `obligation_id`        BIGINT                       No FK →                           Obligation
                         UNSIGNED                        `financial_obligations.id`     

  `target_type`          VARCHAR(25)                  No CHECK                          `all_members`,
                                                                                        `all_branches`,
                                                                                        `member`, `branch`,
                                                                                        `portal_role`,
                                                                                        `official_office`,
                                                                                        `member_group`

  `member_id`            BIGINT                      Yes FK → `members.id`              Individual
                         UNSIGNED                                                       

  `branch_id`            BIGINT                      Yes FK → `branches.id`             Branch
                         UNSIGNED                                                       

  `portal_role_id`       BIGINT                      Yes FK → `portal_roles.id`         Portal role
                         UNSIGNED                                                       

  `official_office_id`   BIGINT                      Yes FK →                           Official office
                         UNSIGNED                        `official_office_catalog.id`   

  `member_group_id`      BIGINT                      Yes FK → `member_groups.id`        Group
                         UNSIGNED                                                       

  `include_or_exclude`   VARCHAR(10)                  No CHECK                          `include`, `exclude`

  `created_at`           DATETIME(6)                  No                                Creation
  ----------------------------------------------------------------------------------------------------------

Target-specific FK fields must match `target_type`; enforce with `CHECK`
where supported and always in the application. A member-level obligation
targeted to a branch expands into member assessments for that branch. A
branch-level obligation creates branch assessments. Role/office targets
are resolved and snapshotted at publication.

## 11.6 `member_obligations`

Materialised assessment for each individual member.

  --------------------------------------------------------------------------------------------------------------
  Field                     Type                         Null Key / rule                     Meaning
  ------------------------- --------------- ----------------- ------------------------------ -------------------
  `id`                      BIGINT UNSIGNED                No PK, AI                         ID

  `public_id`               CHAR(36)                       No UNIQUE                         Reference

  `obligation_id`           BIGINT UNSIGNED                No FK →                           Parent
                                                              `financial_obligations.id`     

  `member_id`               BIGINT UNSIGNED                No FK → `members.id`              Assessed member

  `source_target_rule_id`   BIGINT UNSIGNED               Yes FK →                           Target rule
                                                              `obligation_target_rules.id`   

  `title_snapshot`          VARCHAR(180)                   No                                Original title

  `reason_snapshot`         TEXT                           No                                Original reason

  `assessed_amount`         DECIMAL(15,2)                  No CHECK \>= 0                    Amount

  `currency_code`           CHAR(3)                        No DEFAULT `NGN`                  Currency

  `due_date_snapshot`       DATE                           No                                Original deadline

  `assessment_status`       VARCHAR(25)                    No CHECK                          `open`,
                                                                                             `partially_paid`,
                                                                                             `paid`, `overdue`,
                                                                                             `waived`,
                                                                                             `cancelled`

  `assessed_at`             DATETIME(6)                    No                                Assessment time

  `created_at`              DATETIME(6)                    No                                Creation
  --------------------------------------------------------------------------------------------------------------

UNIQUE (`obligation_id`, `member_id`). The balance is derived from
ledger entries, not manually typed into this table. Preserve assessment
history when a member changes branch.

## 11.7 `branch_obligations`

Materialised branch-level assessment.

  --------------------------------------------------------------------------------------------------------------
  Field                     Type                         Null Key / rule                     Meaning
  ------------------------- --------------- ----------------- ------------------------------ -------------------
  `id`                      BIGINT UNSIGNED                No PK, AI                         ID

  `public_id`               CHAR(36)                       No UNIQUE                         Reference

  `obligation_id`           BIGINT UNSIGNED                No FK →                           Parent
                                                              `financial_obligations.id`     

  `branch_id`               BIGINT UNSIGNED                No FK → `branches.id`             Assessed branch

  `source_target_rule_id`   BIGINT UNSIGNED               Yes FK →                           Target rule
                                                              `obligation_target_rules.id`   

  `title_snapshot`          VARCHAR(180)                   No                                Title

  `reason_snapshot`         TEXT                           No                                Reason

  `assessed_amount`         DECIMAL(15,2)                  No CHECK \>= 0                    Amount

  `currency_code`           CHAR(3)                        No DEFAULT `NGN`                  Currency

  `due_date_snapshot`       DATE                           No                                Deadline

  `assessment_status`       VARCHAR(25)                    No CHECK                          `open`,
                                                                                             `partially_paid`,
                                                                                             `paid`, `overdue`,
                                                                                             `waived`,
                                                                                             `cancelled`

  `assessed_at`             DATETIME(6)                    No                                Assessment

  `created_at`              DATETIME(6)                    No                                Creation
  --------------------------------------------------------------------------------------------------------------

UNIQUE (`obligation_id`, `branch_id`). Parent obligation must have
`subject_scope='branch'`.

## 11.8 `obligation_adjustments`

  ------------------------------------------------------------------------------------------------------------
  Field                    Type                         Null Key / rule                Meaning
  ------------------------ --------------- ----------------- ------------------------- -----------------------
  `id`                     BIGINT UNSIGNED                No PK, AI                    ID

  `member_obligation_id`   BIGINT UNSIGNED               Yes FK →                      Member assessment
                                                             `member_obligations.id`   

  `branch_obligation_id`   BIGINT UNSIGNED               Yes FK →                      Branch assessment
                                                             `branch_obligations.id`   

  `adjustment_type`        VARCHAR(30)                    No CHECK                     `waiver`, `increase`,
                                                                                       `decrease`,
                                                                                       `write_off`,
                                                                                       `deadline_extension`,
                                                                                       `correction`

  `amount_delta`           DECIMAL(15,2)                 Yes                           Amount change

  `new_due_date`           DATE                          Yes                           Revised deadline

  `reason`                 TEXT                           No                           Explanation

  `requested_by_user_id`   Shield user ID                 No FK → `users.id`           Requester
                           type                                                        

  `approval_request_id`    BIGINT UNSIGNED               Yes FK →                      Approval
                                                             `approval_requests.id`    

  `approved_by_user_id`    Shield user ID                Yes FK → `users.id`           Approver
                           type                                                        

  `approved_at`            DATETIME(6)                   Yes                           Approval

  `created_at`             DATETIME(6)                    No                           Creation
  ------------------------------------------------------------------------------------------------------------

Exactly one assessment FK must be populated. Approved adjustments create
ledger entries; they do not rewrite the original assessment.

------------------------------------------------------------------------

# 12. BRANCH COLLECTION ACCOUNTS AND DUE AUTOMATION

## 12.1 `branch_collection_account_requests`

Stores proposed account details before approval.

  ----------------------------------------------------------------------------------------------------------
  Field                        Type                          Null Key / rule               Meaning
  ---------------------------- ---------------- ----------------- ------------------------ -----------------
  `id`                         BIGINT UNSIGNED                 No PK, AI                   ID

  `public_id`                  CHAR(36)                        No UNIQUE                   Reference

  `branch_id`                  BIGINT UNSIGNED                 No FK → `branches.id`       Branch

  `bank_name`                  VARCHAR(150)                    No                          Bank

  `bank_code`                  VARCHAR(20)                    Yes                          Code

  `account_name`               VARCHAR(180)                    No                          Account name

  `account_number_encrypted`   VARBINARY(512)                  No                          Encrypted account
                                                                                           number

  `account_number_last4`       CHAR(4)                         No                          Masked digits

  `currency_code`              CHAR(3)                         No DEFAULT `NGN`            Currency

  `submitted_by_user_id`       Shield user ID                  No FK → `users.id`          Submitter
                               type                                                        

  `request_status`             VARCHAR(25)                     No CHECK                    `submitted`,
                                                                                           `under_review`,
                                                                                           `approved`,
                                                                                           `rejected`,
                                                                                           `returned`,
                                                                                           `superseded`

  `verification_method`        VARCHAR(40)                    Yes                          Verification
                                                                                           method

  `verification_reference`     VARCHAR(150)                   Yes                          Reference

  `approval_request_id`        BIGINT UNSIGNED                Yes FK →                     Approval
                                                                  `approval_requests.id`   

  `reviewed_by_user_id`        Shield user ID                 Yes FK → `users.id`          Reviewer
                               type                                                        

  `reviewed_at`                DATETIME(6)                    Yes                          Review

  `decision_reason`            TEXT                           Yes                          Reason

  `created_at`                 DATETIME(6)                     No                          Creation
  ----------------------------------------------------------------------------------------------------------

Encrypt account numbers at application level using a key stored outside
the database. Do not store encryption keys in the database. A proposed
account is not an approved collection destination.

## 12.2 `branch_collection_accounts`

Approved, effective collection destinations.

  ------------------------------------------------------------------------------------------------------------------------
  Field                        Type                          Null Key / rule                                Meaning
  ---------------------------- ---------------- ----------------- ----------------------------------------- --------------
  `id`                         BIGINT UNSIGNED                 No PK, AI                                    ID

  `public_id`                  CHAR(36)                        No UNIQUE                                    Reference

  `branch_id`                  BIGINT UNSIGNED                 No FK → `branches.id`                        Branch

  `source_request_id`          BIGINT UNSIGNED                 No FK →                                      Approved
                                                                  `branch_collection_account_requests.id`   request

  `bank_name`                  VARCHAR(150)                    No                                           Bank

  `bank_code`                  VARCHAR(20)                    Yes                                           Code

  `account_name`               VARCHAR(180)                    No                                           Name

  `account_number_encrypted`   VARBINARY(512)                  No                                           Encrypted
                                                                                                            number

  `account_number_last4`       CHAR(4)                         No                                           Mask

  `currency_code`              CHAR(3)                         No DEFAULT `NGN`                             Currency

  `account_status`             VARCHAR(20)                     No CHECK                                     `active`,
                                                                                                            `inactive`,
                                                                                                            `suspended`,
                                                                                                            `replaced`

  `effective_from`             DATETIME(6)                     No                                           Start

  `effective_to`               DATETIME(6)                    Yes                                           End

  `approved_by_user_id`        Shield user ID                  No FK → `users.id`                           Approver
                               type                                                                         

  `approved_at`                DATETIME(6)                     No                                           Approval

  `created_at`                 DATETIME(6)                     No                                           Creation
  ------------------------------------------------------------------------------------------------------------------------

Only approved accounts may be used. Account changes create new rows; old
rows remain historical. Enforce one active default account per
branch/currency transactionally.

## 12.3 `branch_due_automation_requests`

  ---------------------------------------------------------------------------------------------------------------------------------
  Field                               Type                         Null Key / rule                                Meaning
  ----------------------------------- --------------- ----------------- ----------------------------------------- -----------------
  `id`                                BIGINT UNSIGNED                No PK, AI                                    ID

  `public_id`                         CHAR(36)                       No UNIQUE                                    Reference

  `branch_id`                         BIGINT UNSIGNED                No FK → `branches.id`                        Branch

  `requested_by_user_id`              Shield user ID                 No FK → `users.id`                           Requester
                                      type                                                                        

  `title`                             VARCHAR(180)                   No                                           Due title

  `reason`                            TEXT                           No                                           Purpose

  `amount`                            DECIMAL(15,2)                  No CHECK \> 0                                Proposed amount

  `currency_code`                     CHAR(3)                        No DEFAULT `NGN`                             Currency

  `recurrence_frequency`              VARCHAR(20)                    No CHECK                                     `one_time`,
                                                                                                                  `monthly`,
                                                                                                                  `quarterly`,
                                                                                                                  `annual`,
                                                                                                                  `custom`

  `proposed_due_date`                 DATE                           No                                           Deadline

  `collection_account_request_id`     BIGINT UNSIGNED               Yes FK →                                      New account
                                                                        `branch_collection_account_requests.id`   request

  `approved_collection_account_id`    BIGINT UNSIGNED               Yes FK → `branch_collection_accounts.id`      Existing approved
                                                                                                                  account

  `request_status`                    VARCHAR(25)                    No CHECK                                     `draft`,
                                                                                                                  `submitted`,
                                                                                                                  `under_review`,
                                                                                                                  `approved`,
                                                                                                                  `rejected`,
                                                                                                                  `returned`,
                                                                                                                  `activated`,
                                                                                                                  `cancelled`

  `approval_request_id`               BIGINT UNSIGNED               Yes FK → `approval_requests.id`               Approval

  `reviewed_by_user_id`               Shield user ID                Yes FK → `users.id`                           Reviewer
                                      type                                                                        

  `reviewed_at`                       DATETIME(6)                   Yes                                           Review

  `decision_reason`                   TEXT                          Yes                                           Decision

  `created_financial_obligation_id`   BIGINT UNSIGNED               Yes FK → `financial_obligations.id`           Created
                                                                                                                  obligation

  `created_at`                        DATETIME(6)                    No                                           Creation
  ---------------------------------------------------------------------------------------------------------------------------------

Only authorised branch users may submit. The request is not payable
until reviewed, approved and activated. The receiving account must be
approved first. If the branch due assesses individual branch members,
create a member-level obligation targeted to that branch. If the
approved arrangement assesses the branch itself, use a branch-level
obligation. The reviewer must see this distinction before approval.

------------------------------------------------------------------------

# 13. PAYMENTS, RECEIPTS AND RECONCILIATION

## 13.1 `payment_transactions`

  --------------------------------------------------------------------------------------------------------------
  Field                              Type                         Null Key / rule      Meaning
  ---------------------------------- --------------- ----------------- --------------- -------------------------
  `id`                               BIGINT UNSIGNED                No PK, AI          ID

  `public_id`                        CHAR(36)                       No UNIQUE          Reference

  `payment_reference`                VARCHAR(80)                    No UNIQUE          Portal reference

  `payer_user_id`                    Shield user ID                Yes FK → `users.id` Account
                                     type                                              

  `payer_member_id`                  BIGINT UNSIGNED               Yes FK →            Member payer
                                                                       `members.id`    

  `payer_branch_id`                  BIGINT UNSIGNED               Yes FK →            Branch payer
                                                                       `branches.id`   

  `payment_provider_code`            VARCHAR(60)                   Yes INDEX           Provider

  `payment_channel`                  VARCHAR(30)                    No CHECK           `online`, `manual_bank`,
                                                                                       `other_approved`

  `provider_transaction_reference`   VARCHAR(150)                  Yes INDEX           Provider reference

  `idempotency_key`                  VARCHAR(100)                   No UNIQUE          Duplicate prevention

  `amount`                           DECIMAL(15,2)                  No CHECK \> 0      Amount

  `currency_code`                    CHAR(3)                        No DEFAULT `NGN`   Currency

  `payment_status`                   VARCHAR(20)                    No CHECK           `initiated`, `pending`,
                                                                                       `successful`, `failed`,
                                                                                       `reversed`, `refunded`

  `provider_status`                  VARCHAR(80)                   Yes                 Provider status

  `initiated_at`                     DATETIME(6)                    No                 Start

  `confirmed_at`                     DATETIME(6)                   Yes                 Confirmation

  `failed_at`                        DATETIME(6)                   Yes                 Failure

  `failure_reason`                   VARCHAR(500)                  Yes                 Reason

  `verified_by_method`               VARCHAR(30)                   Yes                 Webhook/provider/manual
                                                                                       reconciliation

  `created_at`                       DATETIME(6)                    No                 Creation

  `updated_at`                       DATETIME(6)                   Yes                 Update
  --------------------------------------------------------------------------------------------------------------

A payment is successful only after server-side provider verification or
authorised reconciliation. A browser success page is not proof. Never
store raw card numbers, CVV, PINs or provider secrets. Payments are
online-only.

## 13.2 `payment_provider_events`

  ------------------------------------------------------------------------------------------------------
  Field                      Type                        Null Key / rule                  Meaning
  -------------------------- -------------- ----------------- --------------------------- --------------
  `id`                       BIGINT                        No PK, AI                      ID
                             UNSIGNED                                                     

  `provider_code`            VARCHAR(60)                   No                             Provider

  `provider_event_id`        VARCHAR(180)                  No UNIQUE with provider        Event

  `payment_transaction_id`   BIGINT                       Yes FK →                        Payment
                             UNSIGNED                         `payment_transactions.id`   

  `event_type`               VARCHAR(80)                   No                             Type

  `payload_hash`             CHAR(64)                      No                             Hash

  `sanitised_payload`        JSON                         Yes                             Sanitised
                                                                                          payload

  `signature_verified`       TINYINT(1)                    No DEFAULT 0                   Signature
                                                                                          check

  `processing_status`        VARCHAR(20)                   No CHECK                       `received`,
                                                                                          `processed`,
                                                                                          `rejected`,
                                                                                          `failed`

  `received_at`              DATETIME(6)                   No                             Received

  `processed_at`             DATETIME(6)                  Yes                             Processed
  ------------------------------------------------------------------------------------------------------

UNIQUE (`provider_code`, `provider_event_id`). Verify provider
signatures before processing. Repeated events must not duplicate a
payment.

## 13.3 `payment_allocations`

One payment may be allocated to one or more obligations or authorised
receipts.

  ---------------------------------------------------------------------------------------------------------------
  Field                      Type                         Null Key / rule                  Meaning
  -------------------------- --------------- ----------------- --------------------------- ----------------------
  `id`                       BIGINT UNSIGNED                No PK, AI                      ID

  `payment_transaction_id`   BIGINT UNSIGNED                No FK →                        Payment
                                                               `payment_transactions.id`   

  `allocation_type`          VARCHAR(30)                    No CHECK                       `member_obligation`,
                                                                                           `branch_obligation`,
                                                                                           `member_receipt`,
                                                                                           `branch_receipt`

  `member_obligation_id`     BIGINT UNSIGNED               Yes FK →                        Member due
                                                               `member_obligations.id`     

  `branch_obligation_id`     BIGINT UNSIGNED               Yes FK →                        Branch due
                                                               `branch_obligations.id`     

  `member_id`                BIGINT UNSIGNED               Yes FK → `members.id`           Member target

  `branch_id`                BIGINT UNSIGNED               Yes FK → `branches.id`          Branch target

  `allocation_amount`        DECIMAL(15,2)                  No CHECK \> 0                  Amount

  `currency_code`            CHAR(3)                        No DEFAULT `NGN`               Currency

  `allocation_status`        VARCHAR(20)                    No CHECK                       `pending`, `posted`,
                                                                                           `reversed`

  `allocated_at`             DATETIME(6)                    No                             Time

  `created_at`               DATETIME(6)                    No                             Creation
  ---------------------------------------------------------------------------------------------------------------

The `allocation_type` determines which target fields must be populated.
Exactly one valid target path is permitted. Posted allocations cannot
exceed the confirmed payment amount. Posting an allocation creates a
ledger credit.

## 13.4 `payment_refunds`

  -----------------------------------------------------------------------------------------------------------
  Field                         Type                         Null Key / rule                  Meaning
  ----------------------------- --------------- ----------------- --------------------------- ---------------
  `id`                          BIGINT UNSIGNED                No PK, AI                      ID

  `public_id`                   CHAR(36)                       No UNIQUE                      Reference

  `payment_transaction_id`      BIGINT UNSIGNED                No FK →                        Original
                                                                  `payment_transactions.id`   payment

  `refund_reference`            VARCHAR(80)                    No UNIQUE                      Refund
                                                                                              reference

  `provider_refund_reference`   VARCHAR(150)                  Yes INDEX                       Provider
                                                                                              reference

  `refund_amount`               DECIMAL(15,2)                  No CHECK \> 0                  Amount

  `currency_code`               CHAR(3)                        No DEFAULT `NGN`               Currency

  `refund_reason`               TEXT                           No                             Reason

  `refund_status`               VARCHAR(20)                    No CHECK                       `requested`,
                                                                                              `approved`,
                                                                                              `processing`,
                                                                                              `successful`,
                                                                                              `failed`,
                                                                                              `cancelled`

  `requested_by_user_id`        Shield user ID                 No FK → `users.id`             Requester
                                type                                                          

  `approval_request_id`         BIGINT UNSIGNED               Yes FK → `approval_requests.id` Approval

  `approved_by_user_id`         Shield user ID                Yes FK → `users.id`             Approver
                                type                                                          

  `processed_at`                DATETIME(6)                   Yes                             Processed

  `created_at`                  DATETIME(6)                    No                             Creation
  -----------------------------------------------------------------------------------------------------------

A refund cannot exceed the unrefunded original amount. Refund completion
must be verified and creates financial reversal entries. Never delete
the original payment.

## 13.5 `payment_refund_allocations`

  ----------------------------------------------------------------------------------------------------
  Field                     Type                         Null Key / rule                 Meaning
  ------------------------- --------------- ----------------- -------------------------- -------------
  `id`                      BIGINT UNSIGNED                No PK, AI                     ID

  `payment_refund_id`       BIGINT UNSIGNED                No FK → `payment_refunds.id`  Refund

  `payment_allocation_id`   BIGINT UNSIGNED                No FK →                       Original
                                                              `payment_allocations.id`   allocation

  `refund_amount`           DECIMAL(15,2)                  No CHECK \> 0                 Amount

  `created_at`              DATETIME(6)                    No                            Creation
  ----------------------------------------------------------------------------------------------------

UNIQUE (`payment_refund_id`, `payment_allocation_id`). Total refunds
against an allocation cannot exceed its original amount.

## 13.6 `payment_reconciliation_runs`

  --------------------------------------------------------------------------------------------
  Field                    Type                        Null Key / rule    Meaning
  ------------------------ -------------- ----------------- ------------- --------------------
  `id`                     BIGINT                        No PK, AI        ID
                           UNSIGNED                                       

  `public_id`              CHAR(36)                      No UNIQUE        Reference

  `source_type`            VARCHAR(25)                   No CHECK         `provider_report`,
                                                                          `bank_statement`,
                                                                          `manual`

  `provider_code`          VARCHAR(60)                  Yes               Provider

  `statement_reference`    VARCHAR(150)                 Yes               Statement

  `period_start`           DATE                          No               Start

  `period_end`             DATE                          No               End

  `run_status`             VARCHAR(20)                   No CHECK         `draft`,
                                                                          `processing`,
                                                                          `completed`,
                                                                          `failed`

  `started_by_user_id`     Shield user ID                No FK →          Starter
                           type                             `users.id`    

  `completed_by_user_id`   Shield user ID               Yes FK →          Completer
                           type                             `users.id`    

  `started_at`             DATETIME(6)                   No               Start time

  `completed_at`           DATETIME(6)                  Yes               Completion
  --------------------------------------------------------------------------------------------

## 13.7 `payment_reconciliation_items`

  ----------------------------------------------------------------------------------------------------------------------------
  Field                              Type                         Null Key / rule                         Meaning
  ---------------------------------- --------------- ----------------- ---------------------------------- --------------------
  `id`                               BIGINT UNSIGNED                No PK, AI                             ID

  `reconciliation_run_id`            BIGINT UNSIGNED                No FK →                               Run
                                                                       `payment_reconciliation_runs.id`   

  `external_reference`               VARCHAR(180)                   No INDEX                              Source reference

  `transaction_date`                 DATETIME(6)                    No                                    Date

  `amount`                           DECIMAL(15,2)                  No CHECK \> 0                         Amount

  `currency_code`                    CHAR(3)                        No DEFAULT `NGN`                      Currency

  `matched_payment_transaction_id`   BIGINT UNSIGNED               Yes FK → `payment_transactions.id`     Match

  `match_status`                     VARCHAR(25)                    No CHECK                              `matched`,
                                                                                                          `unmatched`,
                                                                                                          `duplicate`,
                                                                                                          `amount_mismatch`,
                                                                                                          `under_review`

  `reviewed_by_user_id`              Shield user ID                Yes FK → `users.id`                    Reviewer
                                     type                                                                 

  `review_notes`                     TEXT                          Yes                                    Notes

  `created_at`                       DATETIME(6)                    No                                    Creation
  ----------------------------------------------------------------------------------------------------------------------------

## 13.8 `payment_receipts`

  -----------------------------------------------------------------------------------------------------
  Field                      Type                       Null Key / rule                   Meaning
  -------------------------- ------------- ----------------- ---------------------------- -------------
  `id`                       BIGINT                       No PK, AI                       ID
                             UNSIGNED                                                     

  `receipt_number`           VARCHAR(80)                  No UNIQUE                       Receipt
                                                                                          reference

  `payment_transaction_id`   BIGINT                       No FK →                         Payment
                             UNSIGNED                        `payment_transactions.id`,   
                                                             UNIQUE                       

  `issued_to_member_id`      BIGINT                      Yes FK → `members.id`            Member
                             UNSIGNED                                                     

  `issued_to_branch_id`      BIGINT                      Yes FK → `branches.id`           Branch
                             UNSIGNED                                                     

  `receipt_status`           VARCHAR(20)                  No CHECK                        `issued`,
                                                                                          `voided`,
                                                                                          `replaced`

  `issued_at`                DATETIME(6)                  No                              Issue time

  `issued_by_user_id`        Shield user                 Yes FK → `users.id`              Issuer
                             ID type                                                      
  -----------------------------------------------------------------------------------------------------

Issue receipts only for confirmed payments. Voiding a receipt does not
delete its payment.

------------------------------------------------------------------------

# 14. MEMBER AND BRANCH FINANCIAL LEDGERS

## 14.1 `member_financial_entries`

Append-only member sub-ledger. The statement is generated from these
entries; do not maintain a manually editable member balance.

  -------------------------------------------------------------------------------------------------------------
  Field                        Type                         Null Key / rule                    Meaning
  ---------------------------- --------------- ----------------- ----------------------------- ----------------
  `id`                         BIGINT UNSIGNED                No PK, AI                        Entry ID

  `entry_uuid`                 CHAR(36)                       No UNIQUE                        Reference

  `member_id`                  BIGINT UNSIGNED                No FK → `members.id`             Member

  `entry_type`                 VARCHAR(30)                    No CHECK                         `obligation`,
                                                                                               `payment`,
                                                                                               `refund`,
                                                                                               `reversal`,
                                                                                               `waiver`,
                                                                                               `adjustment`,
                                                                                               `receipt`

  `member_obligation_id`       BIGINT UNSIGNED               Yes FK → `member_obligations.id`  Obligation

  `payment_allocation_id`      BIGINT UNSIGNED               Yes FK → `payment_allocations.id` Payment
                                                                                               allocation

  `obligation_adjustment_id`   BIGINT UNSIGNED               Yes FK →                          Adjustment
                                                                 `obligation_adjustments.id`   

  `reversal_of_entry_id`       BIGINT UNSIGNED               Yes FK → same table               Original entry

  `transaction_reference`      VARCHAR(100)                   No INDEX                         Reference

  `description`                VARCHAR(300)                   No                               Statement
                                                                                               narration

  `debit_amount`               DECIMAL(15,2)                  No DEFAULT 0, CHECK \>= 0        Charge/debit

  `credit_amount`              DECIMAL(15,2)                  No DEFAULT 0, CHECK \>= 0        Payment/credit

  `currency_code`              CHAR(3)                        No DEFAULT `NGN`                 Currency

  `effective_at`               DATETIME(6)                    No INDEX                         Financial time

  `posted_at`                  DATETIME(6)                    No                               Posting time

  `posted_by_user_id`          Shield user ID                Yes FK → `users.id`               Actor
                               type                                                            

  `entry_status`               VARCHAR(20)                    No CHECK                         `posted`,
                                                                                               `reversed`

  `created_at`                 DATETIME(6)                    No                               Creation
  -------------------------------------------------------------------------------------------------------------

Normal entries have either debit or credit, not both. Reversal entries
reference the original. Entries are append-only. Corrections create
adjustments/reversals. The same source event must not post twice.
Statement ordering is `effective_at`, then `id`. The running balance is
cumulative debits less credits.

## 14.2 `branch_financial_entries`

Branch-level equivalent of the member ledger.

  ------------------------------------------------------------------------------------------------------------
  Field                        Type                         Null Key / rule                    Meaning
  ---------------------------- --------------- ----------------- ----------------------------- ---------------
  `id`                         BIGINT UNSIGNED                No PK, AI                        Entry ID

  `entry_uuid`                 CHAR(36)                       No UNIQUE                        Reference

  `branch_id`                  BIGINT UNSIGNED                No FK → `branches.id`            Branch

  `entry_type`                 VARCHAR(30)                    No CHECK                         `obligation`,
                                                                                               `payment`,
                                                                                               `refund`,
                                                                                               `reversal`,
                                                                                               `waiver`,
                                                                                               `adjustment`,
                                                                                               `receipt`

  `branch_obligation_id`       BIGINT UNSIGNED               Yes FK → `branch_obligations.id`  Obligation

  `payment_allocation_id`      BIGINT UNSIGNED               Yes FK → `payment_allocations.id` Allocation

  `obligation_adjustment_id`   BIGINT UNSIGNED               Yes FK →                          Adjustment
                                                                 `obligation_adjustments.id`   

  `reversal_of_entry_id`       BIGINT UNSIGNED               Yes FK → same table               Original

  `transaction_reference`      VARCHAR(100)                   No INDEX                         Reference

  `description`                VARCHAR(300)                   No                               Description

  `debit_amount`               DECIMAL(15,2)                  No DEFAULT 0                     Debit

  `credit_amount`              DECIMAL(15,2)                  No DEFAULT 0                     Credit

  `currency_code`              CHAR(3)                        No DEFAULT `NGN`                 Currency

  `effective_at`               DATETIME(6)                    No INDEX                         Time

  `posted_at`                  DATETIME(6)                    No                               Posting

  `posted_by_user_id`          Shield user ID                Yes FK → `users.id`               Actor
                               type                                                            

  `entry_status`               VARCHAR(20)                    No CHECK                         `posted`,
                                                                                               `reversed`

  `created_at`                 DATETIME(6)                    No                               Creation
  ------------------------------------------------------------------------------------------------------------

Member and branch statements remain separate. A member payment to a
branch due is credited to the member's ledger; it must not create
duplicate member credits.

------------------------------------------------------------------------

# 15. MEMBER GROUPS AND ANNOUNCEMENTS

## 15.1 `member_groups`

  -----------------------------------------------------------------------------------------
  Field                  Type                        Null Key / rule    Meaning
  ---------------------- -------------- ----------------- ------------- -------------------
  `id`                   BIGINT                        No PK, AI        ID
                         UNSIGNED                                       

  `public_id`            CHAR(36)                      No UNIQUE        Reference

  `group_code`           VARCHAR(80)                   No UNIQUE        Code

  `group_name`           VARCHAR(150)                  No               Name

  `description`          TEXT                         Yes               Purpose

  `group_type`           VARCHAR(20)                   No CHECK         `static`,
                                                                        `saved_selection`

  `group_status`         VARCHAR(20)                   No CHECK         `active`,
                                                                        `inactive`,
                                                                        `archived`

  `created_by_user_id`   Shield user ID                No FK →          Creator
                         type                             `users.id`    

  `created_at`           DATETIME(6)                   No               Creation

  `updated_at`           DATETIME(6)                  Yes               Update
  -----------------------------------------------------------------------------------------

## 15.2 `member_group_members`

  ---------------------------------------------------------------------------------------
  Field                Type                       Null Key / rule           Meaning
  -------------------- ------------- ----------------- -------------------- -------------
  `id`                 BIGINT                       No PK, AI               ID
                       UNSIGNED                                             

  `member_group_id`    BIGINT                       No FK →                 Group
                       UNSIGNED                        `member_groups.id`   

  `member_id`          BIGINT                       No FK → `members.id`    Member
                       UNSIGNED                                             

  `added_by_user_id`   Shield user                  No FK → `users.id`      Actor
                       ID type                                              

  `added_at`           DATETIME(6)                  No                      Added

  `removed_at`         DATETIME(6)                 Yes                      Removed
  ---------------------------------------------------------------------------------------

A group is not a portal role or official office.

## 15.3 `announcements`

  -----------------------------------------------------------------------------------------------------
  Field                        Type                        Null Key / rule               Meaning
  ---------------------------- -------------- ----------------- ------------------------ --------------
  `id`                         BIGINT                        No PK, AI                   ID
                               UNSIGNED                                                  

  `public_id`                  CHAR(36)                      No UNIQUE                   Reference

  `announcement_reference`     VARCHAR(80)                   No UNIQUE                   Reference

  `title`                      VARCHAR(200)                  No                          Title

  `body`                       LONGTEXT                      No                          Content

  `announcement_type`          VARCHAR(30)                   No                          Category

  `status`                     VARCHAR(25)                   No CHECK                    `draft`,
                                                                                         `scheduled`,
                                                                                         `published`,
                                                                                         `cancelled`,
                                                                                         `archived`

  `created_by_user_id`         Shield user ID                No FK → `users.id`          Author
                               type                                                      

  `approved_by_user_id`        Shield user ID               Yes FK → `users.id`          Approver
                               type                                                      

  `approval_request_id`        BIGINT                       Yes FK →                     Approval
                               UNSIGNED                         `approval_requests.id`   

  `scheduled_at`               DATETIME(6)                  Yes                          Scheduled

  `published_at`               DATETIME(6)                  Yes                          Published

  `cancelled_at`               DATETIME(6)                  Yes                          Cancelled

  `recipient_count_snapshot`   INT UNSIGNED                 Yes                          Count

  `created_at`                 DATETIME(6)                   No                          Creation

  `updated_at`                 DATETIME(6)                  Yes                          Update
  -----------------------------------------------------------------------------------------------------

## 15.4 `announcement_target_rules`

  -----------------------------------------------------------------------------------------------------------
  Field                  Type                       Null Key / rule                     Meaning
  ---------------------- ------------- ----------------- ------------------------------ ---------------------
  `id`                   BIGINT                       No PK, AI                         ID
                         UNSIGNED                                                       

  `announcement_id`      BIGINT                       No FK → `announcements.id`        Announcement
                         UNSIGNED                                                       

  `target_type`          VARCHAR(25)                  No CHECK                          `all_members`,
                                                                                        `verified_members`,
                                                                                        `branch`, `member`,
                                                                                        `portal_role`,
                                                                                        `official_office`,
                                                                                        `member_group`

  `branch_id`            BIGINT                      Yes FK → `branches.id`             Branch
                         UNSIGNED                                                       

  `member_id`            BIGINT                      Yes FK → `members.id`              Individual
                         UNSIGNED                                                       

  `portal_role_id`       BIGINT                      Yes FK → `portal_roles.id`         Portal role
                         UNSIGNED                                                       

  `official_office_id`   BIGINT                      Yes FK →                           Office
                         UNSIGNED                        `official_office_catalog.id`   

  `member_group_id`      BIGINT                      Yes FK → `member_groups.id`        Group
                         UNSIGNED                                                       

  `include_or_exclude`   VARCHAR(10)                  No CHECK                          `include`, `exclude`

  `created_at`           DATETIME(6)                  No                                Creation
  -----------------------------------------------------------------------------------------------------------

Official office and portal role remain distinct target dimensions.
Preview recipients before publication.

## 15.5 `announcement_recipients`

Recipient snapshot created at publication.

  --------------------------------------------------------------------------------------------
  Field                  Type                        Null Key / rule           Meaning
  ---------------------- -------------- ----------------- -------------------- ---------------
  `id`                   BIGINT                        No PK, AI               ID
                         UNSIGNED                                              

  `announcement_id`      BIGINT                        No FK →                 Announcement
                         UNSIGNED                         `announcements.id`   

  `member_id`            BIGINT                       Yes FK → `members.id`    Member
                         UNSIGNED                                              

  `user_id`              Shield user ID               Yes FK → `users.id`      Account if
                         type                                                  available

  `recipient_status`     VARCHAR(20)                   No CHECK                `pending`,
                                                                               `queued`,
                                                                               `delivered`,
                                                                               `failed`,
                                                                               `read`,
                                                                               `unavailable`

  `resolved_from`        VARCHAR(25)                  Yes                      Target source

  `resolved_reference`   VARCHAR(100)                 Yes                      Source
                                                                               reference

  `published_at`         DATETIME(6)                   No                      Snapshot time

  `read_at`              DATETIME(6)                  Yes                      Read time
  --------------------------------------------------------------------------------------------

Each row identifies at least a member or user. Prevent duplicate
recipient rows for the same announcement/member or announcement/user.
Preserve the snapshot even after later branch/role changes.

## 15.6 `announcement_delivery_attempts`

  ------------------------------------------------------------------------------------------------------------
  Field                         Type                        Null Key / rule                     Meaning
  ----------------------------- -------------- ----------------- ------------------------------ --------------
  `id`                          BIGINT                        No PK, AI                         ID
                                UNSIGNED                                                        

  `announcement_recipient_id`   BIGINT                        No FK →                           Recipient
                                UNSIGNED                         `announcement_recipients.id`   

  `channel`                     VARCHAR(20)                   No CHECK                          `in_app`,
                                                                                                `email`,
                                                                                                `sms`, `push`

  `destination_masked`          VARCHAR(190)                 Yes                                Masked
                                                                                                destination

  `provider_reference`          VARCHAR(150)                 Yes                                Provider

  `delivery_status`             VARCHAR(20)                   No CHECK                          `queued`,
                                                                                                `sent`,
                                                                                                `delivered`,
                                                                                                `failed`

  `failure_reason`              VARCHAR(500)                 Yes                                Failure

  `attempted_at`                DATETIME(6)                   No                                Attempt
  ------------------------------------------------------------------------------------------------------------

------------------------------------------------------------------------

# 16. DIGITAL MEMBERSHIP IDENTIFICATION

## 16.1 `membership_cards`

The Constitution states that a duly registered member is issued an
identity card by the National Executive Council. The portal must retain
the appropriate NEC or delegated authorisation and must not claim an
authority that has not been granted.

  ----------------------------------------------------------------------------------------------------------------------
  Field                       Type                        Null Key / rule                     Meaning
  --------------------------- -------------- ----------------- ------------------------------ --------------------------
  `id`                        BIGINT                        No PK, AI                         ID
                              UNSIGNED                                                        

  `public_id`                 CHAR(36)                      No UNIQUE                         Reference

  `member_id`                 BIGINT                        No FK → `members.id`              Holder
                              UNSIGNED                                                        

  `membership_number`         VARCHAR(60)                   No UNIQUE                         Stable member number

  `card_serial_number`        VARCHAR(80)                   No UNIQUE                         Card issue serial

  `card_version`              SMALLINT                      No DEFAULT 1                      Replacement sequence
                              UNSIGNED                                                        

  `qr_token_hash`             CHAR(64)                      No UNIQUE                         Hash of random QR token

  `issuer_body`               VARCHAR(100)                  No DEFAULT                        Issuer authority
                                                               `National Executive Council`   

  `authorisation_reference`   VARCHAR(150)                  No                                NEC/delegation reference

  `approval_request_id`       BIGINT                       Yes FK → `approval_requests.id`    Approval
                              UNSIGNED                                                        

  `issued_by_user_id`         Shield user ID               Yes FK → `users.id`                Operator
                              type                                                            

  `issued_at`                 DATETIME(6)                  Yes                                Issue time

  `expires_at`                DATETIME(6)                  Yes                                Expiry

  `card_status`               VARCHAR(20)                   No CHECK                          `pending_authorisation`,
                                                                                              `active`, `suspended`,
                                                                                              `revoked`, `replaced`,
                                                                                              `expired`

  `replaces_card_id`          BIGINT                       Yes FK → same table                Previous card
                              UNSIGNED                                                        

  `revoked_at`                DATETIME(6)                  Yes                                Revoked

  `revocation_reason`         TEXT                         Yes                                Reason

  `created_at`                DATETIME(6)                   No                                Creation
  ----------------------------------------------------------------------------------------------------------------------

Only one current active card per member. Reissue creates a new row. QR
verification reveals minimal information. Offline card copies must say
"not live-verified". Issuance, suspension, replacement and revocation
are audited.

------------------------------------------------------------------------

# 17. CONSTITUTION AND READABLE DOCUMENT REPOSITORY

## 17.1 `constitutional_documents`

  ---------------------------------------------------------------------------------------------------
  Field                    Type                        Null Key / rule    Meaning
  ------------------------ -------------- ----------------- ------------- ---------------------------
  `id`                     BIGINT                        No PK, AI        ID
                           UNSIGNED                                       

  `document_code`          VARCHAR(60)                   No UNIQUE        e.g. `IEDPU_CONSTITUTION`

  `document_title`         VARCHAR(200)                  No               Title

  `document_description`   TEXT                         Yes               Description

  `document_status`        VARCHAR(20)                   No CHECK         `active`, `archived`

  `created_at`             DATETIME(6)                   No               Creation
  ---------------------------------------------------------------------------------------------------

## 17.2 `document_versions`

  -----------------------------------------------------------------------------------------------------------------
  Field                          Type                        Null Key / rule                      Meaning
  ------------------------------ -------------- ----------------- ------------------------------- -----------------
  `id`                           BIGINT                        No PK, AI                          ID
                                 UNSIGNED                                                         

  `constitutional_document_id`   BIGINT                        No FK →                            Document
                                 UNSIGNED                         `constitutional_documents.id`   

  `version_code`                 VARCHAR(60)                   No                                 Version

  `effective_date`               DATE                         Yes                                 Effective date

  `publication_date`             DATE                         Yes                                 Publication

  `source_file_asset_id`         BIGINT                       Yes FK → `file_assets.id`           Original source
                                 UNSIGNED                                                         

  `source_checksum`              CHAR(64)                     Yes                                 Checksum

  `version_status`               VARCHAR(20)                   No CHECK                           `draft`,
                                                                                                  `under_review`,
                                                                                                  `approved`,
                                                                                                  `published`,
                                                                                                  `superseded`

  `approved_by_user_id`          Shield user ID               Yes FK → `users.id`                 Approver
                                 type                                                             

  `approval_reference`           VARCHAR(150)                 Yes                                 Authority

  `published_at`                 DATETIME(6)                  Yes                                 Publication

  `created_at`                   DATETIME(6)                   No                                 Creation
  -----------------------------------------------------------------------------------------------------------------

## 17.3 `document_sections`

  -------------------------------------------------------------------------------------------------
  Field                   Type                        Null Key / rule               Meaning
  ----------------------- -------------- ----------------- ------------------------ ---------------
  `id`                    BIGINT                        No PK, AI                   ID
                          UNSIGNED                                                  

  `document_version_id`   BIGINT                        No FK →                     Version
                          UNSIGNED                         `document_versions.id`   

  `parent_section_id`     BIGINT                       Yes FK → same table          Parent
                          UNSIGNED                                                  

  `section_code`          VARCHAR(80)                   No                          Section number

  `section_title`         VARCHAR(220)                  No                          Heading

  `slug`                  VARCHAR(220)                  No UNIQUE within version    URL

  `sequence_number`       INT UNSIGNED                  No                          Order

  `content_markdown`      LONGTEXT                      No                          Authoritative
                                                                                    text

  `source_page_start`     SMALLINT                     Yes                          Source PDF page
                          UNSIGNED                                                  

  `source_page_end`       SMALLINT                     Yes                          Source PDF page
                          UNSIGNED                                                  

  `section_status`        VARCHAR(20)                   No CHECK                    `draft`,
                                                                                    `published`,
                                                                                    `archived`

  `published_at`          DATETIME(6)                  Yes                          Publication

  `created_at`            DATETIME(6)                   No                          Creation

  `updated_at`            DATETIME(6)                  Yes                          Update
  -------------------------------------------------------------------------------------------------

The Constitution must be readable as ordered webpages, not PDF-only.
Preserve authoritative wording. Explanatory notes must be clearly
separated from official text. Published versions are immutable;
corrections create a controlled new version or correction record. The
original PDF may remain a supporting download and public pages may be
cached by the PWA.

------------------------------------------------------------------------

# 18. OFFLINE SYNCHRONISATION

The offline cache and pending edit queue reside in browser IndexedDB
through Dexie.js, not in MySQL while the device is offline. The server
stores accepted mutation receipts and profile revisions to make sync
safe and auditable.

## 18.1 `sync_mutation_receipts`

  ---------------------------------------------------------------------------------------
  Field                     Type                        Null Key / rule     Meaning
  ------------------------- -------------- ----------------- -------------- -------------
  `id`                      BIGINT                        No PK, AI         ID
                            UNSIGNED                                        

  `user_id`                 Shield user ID                No FK →           Account
                            type                             `users.id`     

  `client_mutation_id`      CHAR(36)                      No UNIQUE with    Client
                                                             user           mutation

  `member_id`               BIGINT                        No FK →           Member
                            UNSIGNED                         `members.id`   

  `mutation_type`           VARCHAR(60)                   No Controlled     Approved
                                                                            mutation

  `payload_hash`            CHAR(64)                      No                Hash

  `base_record_version`     BIGINT                        No                Version seen
                            UNSIGNED                                        by client

  `result_record_version`   BIGINT                       Yes                Result
                            UNSIGNED                                        version

  `processing_status`       VARCHAR(20)                   No CHECK          `received`,
                                                                            `accepted`,
                                                                            `rejected`,
                                                                            `conflict`

  `result_code`             VARCHAR(60)                  Yes                Code

  `result_message`          VARCHAR(500)                 Yes                Message

  `processed_at`            DATETIME(6)                  Yes                Processing

  `created_at`              DATETIME(6)                   No                Receipt
  ---------------------------------------------------------------------------------------

UNIQUE (`user_id`, `client_mutation_id`). Only approved low-risk profile
fields may sync. The server checks current session, permission,
allowlist and base record version. Repeated requests return the original
result. Stale edits return a conflict. Accepted mutations create profile
revisions and audit events.

## 18.2 Optimistic concurrency

The `members.record_version` column is incremented after every accepted
profile update. Use compare-and-swap logic:

``` sql
UPDATE members
SET current_residence_city = :new_city,
    record_version = record_version + 1,
    updated_at = UTC_TIMESTAMP(6)
WHERE id = :member_id
  AND record_version = :expected_version;
```

If no row is updated, return a conflict. Do not silently overwrite a
newer server value.

## 18.3 Offline-prohibited actions

No offline queue may approve membership verification, branch transfers,
role changes, official-office changes, dues creation/approval, payment
confirmation, bank-account approval, ID issuance/revocation,
announcement publication, refunds or financial adjustments.

------------------------------------------------------------------------

# 19. SUPPORT AND REPORT EXPORTS

## 19.1 `support_requests`

  -----------------------------------------------------------------------------------------
  Field                   Type                        Null Key / rule      Meaning
  ----------------------- -------------- ----------------- --------------- ----------------
  `id`                    BIGINT                        No PK, AI          ID
                          UNSIGNED                                         

  `public_id`             CHAR(36)                      No UNIQUE          Reference

  `requester_user_id`     Shield user ID                No FK → `users.id` Requester
                          type                                             

  `member_id`             BIGINT                       Yes FK →            Member
                          UNSIGNED                         `members.id`    

  `branch_id`             BIGINT                       Yes FK →            Branch
                          UNSIGNED                         `branches.id`   

  `request_category`      VARCHAR(40)                   No                 Category

  `subject`               VARCHAR(180)                  No                 Subject

  `description`           TEXT                          No                 Request

  `request_status`        VARCHAR(25)                   No CHECK           `open`,
                                                                           `assigned`,
                                                                           `in_progress`,
                                                                           `resolved`,
                                                                           `closed`

  `assigned_to_user_id`   Shield user ID               Yes FK → `users.id` Assignee
                          type                                             

  `resolution_notes`      TEXT                         Yes                 Resolution

  `created_at`            DATETIME(6)                   No                 Creation

  `resolved_at`           DATETIME(6)                  Yes                 Resolution
  -----------------------------------------------------------------------------------------

This does not replace the dedicated profile correction workflow.

## 19.2 `report_exports`

  -------------------------------------------------------------------------------------------
  Field                    Type                        Null Key / rule         Meaning
  ------------------------ -------------- ----------------- ------------------ --------------
  `id`                     BIGINT                        No PK, AI             ID
                           UNSIGNED                                            

  `public_id`              CHAR(36)                      No UNIQUE             Reference

  `report_code`            VARCHAR(100)                  No INDEX              Report type

  `requested_by_user_id`   Shield user ID                No FK → `users.id`    Requester
                           type                                                

  `filter_snapshot`        JSON                         Yes                    Filters

  `data_period_start`      DATE                         Yes                    Period

  `data_period_end`        DATE                         Yes                    Period

  `file_asset_id`          BIGINT                       Yes FK →               File
                           UNSIGNED                         `file_assets.id`   

  `export_status`          VARCHAR(20)                   No CHECK              `queued`,
                                                                               `generated`,
                                                                               `failed`,
                                                                               `expired`

  `record_count`           BIGINT                       Yes                    Count
                           UNSIGNED                                            

  `generated_at`           DATETIME(6)                  Yes                    Time

  `expires_at`             DATETIME(6)                  Yes                    Expiry
  -------------------------------------------------------------------------------------------

Exports require a dedicated permission, create an audit event and are
stored privately.

------------------------------------------------------------------------

# 20. RELATIONSHIP AND CARDINALITY REFERENCE

  --------------------------------------------------------------------------------------------------
  Parent                       Child                            Relationship      Rule
  ---------------------------- -------------------------------- ----------------- ------------------
  `org_zones`                  `branches`                       1:M               A branch belongs
                                                                                  to one zone

  `geo_states`                 `branches`                       1:M               A state can locate
                                                                                  many branches

  `geo_states`                 `project_coverage_states`        1:M               A state may be in
                                                                                  project coverage

  `branches`                   `members`                        1:M               A branch has many
                                                                                  current members

  `members`                    `member_registrations`           1:M               Multiple
                                                                                  submission
                                                                                  attempts/history

  `members`                    `member_verification_reviews`    1:M               Every review
                                                                                  retained

  `members`                    `member_contact_points`          1:M               Multiple contact
                                                                                  points

  `members`                    `member_professional_details`    1:0..1            Optional profile

  `members`                    `member_user_links`              1:0..1 active     At most one linked
                                                                                  account

  `users`                      `portal_user_role_assignments`   1:M               Multiple
                                                                                  roles/scopes

  `portal_roles`               `portal_role_permissions`        1:M               Role has
                                                                                  permissions

  `portal_permissions`         `portal_role_permissions`        1:M               Permission can
                                                                                  belong to roles

  `members`                    `official_office_assignments`    1:M               Office history

  `financial_obligations`      `obligation_target_rules`        1:M               Multiple target
                                                                                  rules

  `financial_obligations`      `member_obligations`             1:M               Member assessments

  `financial_obligations`      `branch_obligations`             1:M               Branch assessments

  `member_obligations`         `member_financial_entries`       1:M               Ledger events

  `payment_transactions`       `payment_allocations`            1:M               Payment split
                                                                                  across targets

  `branches`                   `branch_collection_accounts`     1:M               Historical
                                                                                  approved accounts

  `announcements`              `announcement_recipients`        1:M               Publication
                                                                                  snapshot

  `constitutional_documents`   `document_versions`              1:M               Version history

  `document_versions`          `document_sections`              1:M               Ordered pages

  `approval_requests`          `approval_decisions`             1:M               Approval decisions
  --------------------------------------------------------------------------------------------------

------------------------------------------------------------------------

# 21. CRITICAL CONSTRAINTS

## 21.1 Membership

1.  `members.public_id` is unique.
2.  `members.membership_number` is unique when populated.
3.  Each member has one current branch.
4.  Each registration identifies an approved branch.
5.  Assisted registration identifies its enumerator/submitter.
6.  Verification and membership status are separate.
7.  Financial standing is derived from financial records.
8.  A member has at most one active account link.
9.  A portal account has at most one active member link.
10. Duplicate merge preserves original record and history.

## 21.2 Roles and offices

1.  Official offices and portal roles use separate tables.
2.  Scope fields must match `scope_type`.
3.  Branch-scoped roles require a branch.
4.  Zone-scoped roles require a zone.
5.  Global roles have no branch or zone scope.
6.  Expired/revoked assignments do not grant access.
7.  Users cannot elevate themselves.
8.  High-privilege assignment uses maker-checker.
9.  Official office assignments match the office's organisational level.
10. An office assignment never inserts a portal role automatically.

## 21.3 Financial records

1.  Use `DECIMAL(15,2)`.
2.  Payment allocations cannot exceed confirmed payment.
3.  Allocation must identify exactly one valid target.
4.  Refund cannot exceed the unrefunded amount.
5.  Completed payments cannot be deleted.
6.  Corrections use adjustments/reversals.
7.  Member obligation references a member-level obligation.
8.  Branch obligation references a branch-level obligation.
9.  Ledger source events cannot post twice.
10. Ledger totals must reconcile with assessments and allocations.
11. Payment requires server verification or authorised reconciliation.
12. Branch account must be approved before use.
13. Account changes create new rows, not overwrites.
14. Financial statement entries are append-only.

## 21.4 Announcements

1.  Every announcement has an author.
2.  Publication preserves targeting rules and recipient snapshot.
3.  A member must not appear twice for one announcement.
4.  Office and portal-role targeting remain distinct.
5.  Published history is retained.

## 21.5 Audit and offline

1.  Sensitive actions create server-side audit events.
2.  Audit events identify actor, action, target, time and outcome.
3.  Secrets are never written to audit logs.
4.  Audit viewing/export is itself audited.
5.  Audit failure generates an operational alert.
6.  Only approved low-risk profile changes may be queued offline.
7.  Every sync mutation has a unique ID and is idempotent.
8.  Stale sync changes create a conflict.
9.  A queued change is not approved until server acceptance.
10. Sensitive actions are never accepted from an offline queue.

------------------------------------------------------------------------

# 22. INDEXING STRATEGY

Create indexes for foreign keys, common filters and chronological
lookups. Do not index every column.

Recommended indexes:

``` text
members:
  UNIQUE(public_id)
  UNIQUE(membership_number)
  INDEX(last_name, first_name)
  INDEX(current_branch_id, verification_status)
  INDEX(current_branch_id, membership_status)
  INDEX(verification_status, created_at)
  INDEX(duplicate_of_member_id)

member_registrations:
  UNIQUE(registration_reference)
  INDEX(branch_id, submission_status, submitted_at)
  INDEX(member_id, submitted_at)
  INDEX(enumerator_user_id, submitted_at)

member_user_links:
  INDEX(member_id, link_status)
  INDEX(user_id, link_status)

member_contact_points:
  INDEX(member_id, contact_type, is_primary)
  INDEX(contact_type, normalised_value)

member_verification_reviews:
  INDEX(member_id, reviewed_at)
  INDEX(branch_id, decision, reviewed_at)
  INDEX(reviewed_by_user_id, reviewed_at)

portal_user_role_assignments:
  INDEX(user_id, assignment_status, starts_at, expires_at)
  INDEX(role_id, assignment_status)
  INDEX(branch_id, assignment_status)
  INDEX(zone_id, assignment_status)

financial_obligations:
  UNIQUE(obligation_reference)
  INDEX(obligation_status, due_date)
  INDEX(subject_scope, issued_at)

member_obligations:
  UNIQUE(obligation_id, member_id)
  INDEX(member_id, assessment_status, due_date_snapshot)
  INDEX(obligation_id, assessment_status)

payment_transactions:
  UNIQUE(payment_reference)
  UNIQUE(idempotency_key)
  INDEX(payment_status, initiated_at)
  INDEX(payer_member_id, initiated_at)
  INDEX(provider_transaction_reference)

payment_allocations:
  INDEX(payment_transaction_id, allocation_status)
  INDEX(member_obligation_id)
  INDEX(branch_obligation_id)
  INDEX(member_id)
  INDEX(branch_id)

member_financial_entries:
  UNIQUE(entry_uuid)
  INDEX(member_id, effective_at, id)
  INDEX(member_obligation_id)
  INDEX(payment_allocation_id)

announcements:
  INDEX(status, scheduled_at, published_at)

announcement_recipients:
  INDEX(user_id, recipient_status, published_at)
  INDEX(member_id, published_at)

audit_events:
  UNIQUE(event_uuid)
  INDEX(occurred_at)
  INDEX(actor_user_id, occurred_at)
  INDEX(action_key, occurred_at)
  INDEX(module_code, occurred_at)
  INDEX(subject_type, subject_id)
  INDEX(branch_id, occurred_at)
  INDEX(event_category, severity, occurred_at)
```

Use `EXPLAIN` against real dashboard/report queries after pilot data is
available. Avoid excessive indexes on high-write audit and ledger
tables.

------------------------------------------------------------------------

# 23. RETENTION AND HISTORICAL PRESERVATION

Do not physically delete through ordinary administration:

-   Member records with registration/verification history.
-   Membership-number history.
-   Office and portal-role assignments.
-   Approval requests and decisions.
-   Financial obligations and assessments.
-   Payments, allocations, refunds and reversals.
-   Member/branch ledger entries.
-   Receipts.
-   Audit/security events.
-   Published announcements and recipient snapshots.
-   Constitution versions and sections.

Use inactive, suspended, revoked, archived, superseded or expired
states. Where an authorised privacy decision requires
anonymisation/deletion, verify the requester, check retention
obligations, obtain approval, perform the approved operation and record
it in the audit trail. The exact retention period must be approved by
IEDPU; it is not invented here.

------------------------------------------------------------------------

# 24. CODEIGNITER MIGRATION ORDER

Create parent tables before dependent tables. Resolve circular references with staged migrations where necessary.

1. Install CodeIgniter Shield and run its official migrations.
2. Confirm the actual Shield `users.id` type.
3. `org_zones`
4. `geo_states`
5. `project_coverage_states`
6. `branches`
7. `emirate_ancestral_areas`
8. `portal_roles`
9. `portal_permissions`
10. `portal_role_permissions`
11. `approval_requests`
12. `approval_decisions`
13. `portal_user_role_assignments`
14. `official_office_catalog`
15. `file_assets`
16. `privacy_notice_versions`
17. `identifier_sequences`
18. `registration_form_versions`
19. `registration_field_rules`
20. `members`
21. `member_contact_points`
22. `member_professional_details`
23. `member_branch_assignments`
24. `member_registrations`
25. `member_verification_reviews`
26. `member_status_events`
27. `member_user_links`
28. `member_account_claims`
29. `member_profile_revisions`
30. `member_profile_change_requests`
31. `member_duplicate_cases`
32. `member_data_quality_issues`
33. `member_privacy_acknowledgements`
34. `member_consents`
35. `member_documents`
36. `data_subject_requests`
37. `official_office_assignments`
38. `branch_member_baselines`
39. `financial_obligation_types`
40. `obligation_series`
41. `branch_collection_account_requests`
42. `branch_collection_accounts`
43. `financial_obligations`
44. `member_groups`
45. `member_group_members`
46. `obligation_target_rules`
47. `member_obligations`
48. `branch_obligations`
49. `branch_due_automation_requests`
50. `obligation_adjustments`
51. `payment_transactions`
52. `payment_provider_events`
53. `payment_allocations`
54. `payment_refunds`
55. `payment_refund_allocations`
56. `payment_reconciliation_runs`
57. `payment_reconciliation_items`
58. `payment_receipts`
59. `member_financial_entries`
60. `branch_financial_entries`
61. `announcements`
62. `announcement_target_rules`
63. `announcement_recipients`
64. `announcement_delivery_attempts`
65. `constitutional_documents`
66. `document_versions`
67. `document_sections`
68. `membership_cards`
69. `audit_events`
70. `security_events`
71. `audit_chain_checkpoints`
72. `sync_mutation_receipts`
73. `support_requests`
74. `report_exports`

This is database dependency order, not the strategic delivery schedule.

## 24.1 Migration standards

Every migration must have a clear responsibility, primary keys, foreign
keys, unique constraints, indexes, supported check constraints and a
tested rollback where practical. Commit migrations to Git. Do not edit
an already-applied production migration; create a new migration for
changes.

Seeders must be repeatable and idempotent. Use stable codes rather than
assuming auto-increment IDs match across environments.

------------------------------------------------------------------------

# 25. INITIAL SEED DATA

## 25.1 Zones

Seed the four recognised zones: Central, Northern, Western and
South-South. Mark only Northern Zone as in scope for this initiative.

## 25.2 Geography

Seed the approved 19 Northern States and FCT into
`project_coverage_states`. Confirm the exact approved list before
production.

## 25.3 Branch directory

Use the Northern Zone First Schedule list in the Constitution as a
source baseline, not as proof every branch is currently active. Import
source names into `branches.source_name`, initially set
`recognition_status='pending_confirmation'`, validate
spelling/location/recognition/status, record approval references and
activate only confirmed branches. Do not silently correct source
spellings.


## 25.3.1 Northern Zone branch names in the Constitution's First Schedule

The following names are transcribed from the supplied Constitution's First Schedule under “Northern Zone”. Preserve them as `source_name` values during initial import. The list is a source baseline, not confirmation that every branch is currently active or that each spelling/location remains current.

1. Kano
2. Maiduguri
3. Kaduna
4. Zaria
5. Sokoto
6. Jos
7. Bauchi
8. Birnin Gwari
9. Ilela
10. Buruku
11. Wudu
12. Kafanchan
13. Katsina
14. Gusau
15. Dutse
16. Kazaure
17. Tamburawa
18. Minna
19. Yola
20. Kotangora
21. Abuja
22. Azare
23. Binchi
24. Bodinga
25. Karimo
26. Bukuru
27. Gombe
28. Jalingo
29. Bida
30. Lafia
31. Bwari
32. Garki
33. Markurdi
34. Funtua
35. Keffi
36. Maje
37. Gwagwalada
38. Saminaka
39. Kuje
40. Malun Fashi
41. Daura
42. Makarfi

Before activation, the Committee/appropriate IEDPU authority must validate each entry's current official name, geographical state, recognition status and operational status. Do not interpret the Constitution's separate statement of 128 Union branches as the number of Northern Zone branches.


## 25.4 Ancestral areas

Seed the Constitution's defined town/district values with their exact
source wording.

## 25.5 Portal roles

Seed only the minimum approved first-release roles, such as Initiative
Committee Super Administrator, Committee Administrator, Membership
Verification Officer, Branch Registration Administrator, Financial
Records Officer, Announcement Administrator, Branch Reporting Officer,
Enumerator, Member and Technical Administrator. These are software
roles, not constitutional offices. Final role names and permission
bundles require approval.

## 25.6 Financial obligation types

Seed category labels only. Do not seed a universal amount or automatic
schedule.

------------------------------------------------------------------------

# 26. REQUIRED DATABASE TRANSACTIONS

## 26.1 Member verification

Within one transaction: validate reviewer scope; lock/check current
state; insert verification review; update member verification projection
and registration status; create audit event; commit only if all steps
succeed.

## 26.2 Role assignment

Within one transaction: validate assignor permission/scope; validate
role; check duplicate assignment; create approval request where
required; create assignment only after approval; write audit event;
commit.

## 26.3 Obligation publication

Within one transaction or controlled idempotent batch: validate
obligation/targets; confirm approval; resolve recipients; create
member/branch assessments; snapshot title/reason/amount/deadline; create
initial debit ledger entries; mark active; audit. Repeated publication
must not duplicate assessments.

## 26.4 Payment confirmation

Within one transaction: verify provider signature or reconciliation
evidence; check event idempotency; lock payment; validate
amount/currency; update status; create allocations; post ledger credits;
update assessment projections; issue receipt; audit; commit. Provider
callbacks may be safely retried.

## 26.5 Refund

Within one transaction: verify permission/approval; lock original
payment and allocations; verify refundable amount; record provider
result; create refund allocations; create ledger reversals; update
projections; audit; commit.

## 26.6 Offline sync

Within one transaction: authenticate; check mutation idempotency;
validate field allowlist; compare record version; apply permitted
update; increment version; create profile revision; create audit event
and sync receipt; commit.

------------------------------------------------------------------------

# 27. REPORTING DATA SOURCES

  -------------------------------------------------------------------------------
  Report                              Authoritative source
  ----------------------------------- -------------------------------------------
  Total registrations                 `members`, `member_registrations`

  Verified members                    `members.verification_status`,
                                      `member_verification_reviews`

  Pending verification                `members`, `member_registrations`

  Branch register                     `members.current_branch_id`

  Branch coverage                     `branch_member_baselines`, `members`

  Data completeness                   `members`, `member_contact_points`,
                                      approved field rules

  Duplicate records                   `member_duplicate_cases`,
                                      `member_data_quality_issues`

  Enumerator activity                 `member_registrations.enumerator_user_id`

  Portal roles                        `portal_user_role_assignments`

  Official office holders             `official_office_assignments`

  Dues assessed                       `financial_obligations`,
                                      `member_obligations`, `branch_obligations`

  Amount collected                    `payment_transactions`,
                                      `payment_allocations`, ledger

  Outstanding obligations             Assessments and ledger entries

  Member statement                    `member_financial_entries`

  Branch statement                    `branch_financial_entries`

  Payment reconciliation              Reconciliation run/items

  Announcement audience               Target rules and recipient snapshots

  Membership ID status                `membership_cards`

  Audit report                        `audit_events`, `security_events`

  Offline sync report                 `sync_mutation_receipts`
  -------------------------------------------------------------------------------

Reports must identify date range, branch/zone scope, last update and
whether the figures are submitted, estimated, verified or reconciled. Do
not present a branch estimate as an independently verified population
figure.

------------------------------------------------------------------------

# 28. DATABASE SECURITY

1.  Shield manages password identities; never store plaintext passwords.
2.  Do not store raw session secrets or access tokens in custom tables.
3.  Never store card numbers, CVV, PINs or provider secrets.
4.  Encrypt bank account numbers at application level; keep keys outside
    the database and public web root.
5.  Use a restricted application database user, not MySQL root.
6.  Restrict database and backup credentials separately.
7.  Restrict update/delete privileges on audit and ledger tables where
    feasible.
8.  Enforce branch/zone row access in application services; do not
    assume shared-hosting MySQL provides row-level security.
9.  Use parameterised queries.
10. Do not trust client-supplied member, branch, role, amount or payment
    status.
11. Audit sensitive exports and access.
12. Keep production member data out of unprotected development
    databases.
13. Test backups and restoration.

------------------------------------------------------------------------

# 29. DATABASE ACCEPTANCE TESTS

## Organisation and geography

-   A branch cannot exist without a zone.
-   State/LGA geography does not create organisational roles.
-   Only approved coverage states are operational.
-   A branch outside project scope cannot be used for North Zone
    registration without authorised configuration.
-   Deactivated branches remain available for historical references.

## Membership

-   A registration cannot be submitted without an approved branch.
-   Assisted registration identifies the enumerator/submitter.
-   A member may exist without a login account.
-   One member cannot have two active account links.
-   One user cannot claim two member records.
-   Pending is not verified.
-   Financial status does not change verification.
-   Branch transfer preserves history.
-   Duplicate resolution does not delete history.

## Roles and offices

-   Official Branch Chairman assignment does not automatically grant
    portal role.
-   Portal Membership Verification Officer role does not make the person
    Branch Chairman.
-   Branch-scoped user cannot access another branch's restricted
    records.
-   Expired/revoked roles stop granting access.
-   User cannot approve their own high-risk role assignment.
-   Developer does not automatically receive Committee authority.

## Finance

-   Super Administrator can create an obligation with a new
    amount/reason without code deployment.
-   Obligation can target one member, branch members, all members or a
    branch as subject.
-   Published terms are preserved.
-   Partial payment updates balance correctly.
-   Repeated callback does not duplicate payment.
-   Browser redirect alone does not mark payment successful.
-   Refund creates traceable reversal.
-   Member statement is chronological and complete.
-   Branch transfer does not erase financial history.
-   Unapproved bank account cannot be used.

## Communication, ID and Constitution

-   Announcement can target everyone, branch, individual, group,
    official office or portal role.
-   Recipient snapshot survives later role/branch changes.
-   ID cannot become active without required authority.
-   Card replacement preserves previous card.
-   QR verification reveals minimal information.
-   Constitution is stored as readable ordered sections and versioned.
-   New constitutional version does not overwrite old published version.

## Audit and offline

-   Sensitive actions create audit events.
-   Audit access/export is audited.
-   Ordinary application permissions cannot modify audit rows.
-   Checkpoints can be verified.
-   Offline mutation is applied once.
-   Repeated sync returns original result.
-   Stale mutation creates conflict.
-   Prohibited sensitive action cannot sync offline.
-   Pending local change is never shown as server-approved.

------------------------------------------------------------------------

# 30. ITEMS REQUIRING FORMAL CONFIRMATION

The schema provides extension points but does not invent decisions where
the source documents are silent.

  -----------------------------------------------------------------------
  Item                                Confirmation required
  ----------------------------------- -----------------------------------
  Current branch directory            Which Northern Zone branches are
                                      currently recognised/operational
                                      and their approved names/locations

  Branch recognition                  Current NEC/Conference references

  Membership number                   Approved format and issuing
                                      authority

  Digital ID                          NEC authorisation or formal
                                      delegation

  Official office catalogue           Current recognised offices and
                                      office holders

  Mandatory registration fields       Which personal, ancestry, residence
                                      and contact fields are required

  Optional professional data          Exact fields, consent wording and
                                      permitted purpose

  Residence outside operational area  Whether existing North Zone members
                                      residing outside the 19 states/FCT
                                      may register

  Branch collection account           Verification method and authorised
                                      approvers

  Branch due meaning                  Whether request assesses branch
                                      members or the branch itself

  Payment provider                    Provider, webhook, settlement and
                                      refund arrangements

  Currency                            NGN-only or other approved
                                      currencies

  Retention                           Approved retention and
                                      anonymisation periods

  Privacy governance                  Data controller, lawful basis,
                                      privacy notice and request handling

  Audit protection                    Independent audit-copy location and
                                      checkpoint responsibility

  Backup                              Frequency, retention, recovery
                                      objectives and responsible officers

  Approval matrix                     Which sensitive operations require
                                      one or two approvals
  -----------------------------------------------------------------------

------------------------------------------------------------------------

# 31. NON-NEGOTIABLE IMPLEMENTATION RULES

1.  One canonical member record per person; reconcile duplicates without
    deleting history.
2.  Member record and login account are separate.
3.  Each member record belongs to an approved branch.
4.  Branches report directly to their zone; states/LGAs are geography
    only.
5.  Official office and portal role are independent.
6.  Permissions are predefined and controlled.
7.  Financial standing and verification are separate.
8.  Dues are data-driven; amount, reason, target and deadline are
    records.
9.  Financial history is append-only; corrections use
    adjustments/reversals.
10. Member statement is ledger-based, not a manually editable balance.
11. Payments require server verification.
12. Branch collection account requires approval.
13. Announcement recipient snapshots are preserved.
14. Official membership ID requires the appropriate authority.
15. Sensitive actions are comprehensively audited and tamper-evident.
16. Offline support is limited to approved low-risk profile changes.
17. Initial release is existing-member-only and individual-based.
18. Historical records are preserved; deactivation is preferred to
    deletion.
19. Production schema changes use CodeIgniter migrations.
20. IEDPU retains ownership and control of its production data.

------------------------------------------------------------------------

# 32. OFFICIAL TECHNICAL REFERENCES

The database design follows the technology decisions in the approved
SRS. The developer must consult the documentation for the exact locked
versions installed:

-   CodeIgniter 4: https://codeigniter.com/user_guide/
-   CodeIgniter Shield: https://shield.codeigniter.com/
-   MySQL 8.4 Reference Manual: https://dev.mysql.com/doc/refman/8.4/en/
-   MariaDB documentation: https://mariadb.com/kb/en/documentation/
-   OWASP Logging Cheat Sheet:
    https://cheatsheetseries.owasp.org/cheatsheets/Logging_Cheat_Sheet.html

------------------------------------------------------------------------

# END OF DATABASE SCHEMA DESIGN

**Document ID:** IEDPU-NZ-DBS-001\
**Version:** 1.0\
**Project:** IEDPU North Zone Digital Membership, Administration and
Development Portal\
**Prepared:** 19 November 2026
