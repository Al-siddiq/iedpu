# DB-10 — Constitutional reference import and 2015 reading copy

Status: owner accepted P2-WP01 local tests on 21 Nov 2026; DB-10 source committed, local execution and editorial proofreading pending.
Source: user-supplied `iedpu_constitution.pdf`, 30 pages; title: The Constitution of Ilorin Emirate Descendants Progressive Union (I.E.D.P.U), 2015; citation/commencement printed on PDF p30. PDF SHA-256: `c1d91b8841c1acf07dbca8da1d6cc053609a25749a9d692e581ace3d37665d29`.
The PDF is not committed as a binary repository asset; the original upload remains the comparison authority. This is NOT evidence that this is the latest legally operative amendment.

## A. DB-10 import: Section 2(a) ONLY
Section 2(a), PDF page 2, names Ilorin Town and ten districts: Iponrin, Afon, Malete, Akanbi, Ejidongari, Oloru, Owode, Onire, Lanwa, Ipaiye. `P2AncestralReferenceSeeder` inserts exactly 11 reference labels with `area_type=town` for Ilorin Town, `district` for the ten others. All new entries have `is_active=0` and a source reference. Repeating seed preserves existing records and rejects conflicting same-name types without overwriting. These references do not establish anyone's descent, eligibility or membership. They do not create or activate zones, geographic coverage, branches, offices, members, user accounts or portal privileges.

From CMD in the checked-out P2 branch, with XAMPP MySQL running and the correct development .env:
```bat
cd C:\xampp\htdocs\iedpu
git switch feature/p2-database-auth-governance
git pull --ff-only origin feature/p2-database-auth-governance
C:\xampp\php\php.exe spark db:seed P2AncestralReferenceSeeder
C:\xampp\php\php.exe spark db:seed P2AncestralReferenceSeeder
C:\xampp\php\php.exe spark iedpu:p2-schema
```
Check phpMyAdmin:
```sql
SELECT area_name, area_type, is_active, source_reference
FROM emirate_ancestral_areas
WHERE area_name IN ('Ilorin Town','Iponrin','Afon','Malete','Akanbi',
 'Ejidongari','Oloru','Owode','Onire','Lanwa','Ipaiye')
ORDER BY FIELD(area_name,'Ilorin Town','Iponrin','Afon','Malete','Akanbi',
 'Ejidongari','Oloru','Owode','Onire','Lanwa','Ipaiye');
SELECT COUNT(*) AS total FROM emirate_ancestral_areas;
SELECT COUNT(*) AS active_branches FROM branches WHERE operational_status='active';
```
For a clean database with no prior ancestry records, exactly 11 matching rows are expected, all inactive. If previously populated, compare only the 11 named records, do not expect total=11 or change approved statuses. No branch count should change from the import. Repeat must not create duplicates. Preserve a backup before seeding any non-disposable database.

## B. Read-only structured reader
- Routes: `GET /constitution/2015` (overview and full-text section search) and `GET /constitution/2015/{part}` (preamble, 17 section pages, first and second schedules, citation/commencement).
- Data: `app/Content/Constitution/2015/constitution.json` contains 21 ordered text parts, each with source PDF page range, original text extracted from the uploaded PDF, provenance hash and editorial-status flag. The PDF is 30 pages; this is a convenience reading copy, not a constitutional amendment.
- Rendering: `Constitution.php` + `constitution_reader.php` + local CSS. Mobile responsive, semantic headings, table of contents, prev/next, basic search, keyboard focus, text selection, no external CDN, no registration or member data.
- The source PDF does NOT print a `SECTION 16` heading before `(a) STANDING ORDERS`. The reader labels it editorially, with a warning. Section 12 has an apparent incorrect cross-reference to Section 11(a); preserve rather than silently amend. Historical branch names and zone labels in the First Schedule are documentary only, never live branch data. The original text has typographical and formatting anomalies; a responsible reviewer must proofread against the original PDF page by page before marking as authoritative.
- Access: **temporarily public, read-only and noindex** because Shield authentication is not yet enabled. This is not member-only access. The owner must decide before production whether the final constitution reader is public or restricted to authenticated members; WP02 access policy and WP03 navigation will apply. Do not imply member authentication exists now.
- Offline: not precached by P1 service worker; network required. Future versioned content caching needs a separate approval. No private data or mutations.
- Source PDF itself is not served from the portal because it is not committed; to offer a PDF link later, the owner must approve and deploy a verified original binary and a provenance-matched URL. No broken PDF link is displayed.

## C. Manual acceptance
| ID | Check | Expected |
|---|---|---|
| DB10-01 | Seed on backed-up dev DB | 11 exact Section 2(a) names; no user/branch/role creation |
| DB10-02 | Repeat seed | No duplicate rows, statuses preserved |
| DB10-03 | Compare source and data | One town, ten districts, source hash matches uploaded PDF |
| CON-01 | Open `http://127.0.0.1:8080/constitution/2015` | Overview, 21 parts, search, navigation |
| CON-02 | Open `/constitution/2015/section-02` | Ilorin Town + ten districts, source p2–3 |
| CON-03 | Open `/constitution/2015/section-16` | Editorial missing-heading warning and original text |
| CON-04 | Open `/constitution/2015/first-schedule` | Historical branches clearly presented as documentary |
| CON-05 | Open `/constitution/2015/not-real` | HTTP 404, no leaked paths |
| CON-06 | Mobile 320/375/768 px, keyboard-only and 200% zoom | No horizontal clipping; navigable TOC, search and text |
| CON-07 | Proofread all 30 source PDF pages vs online transcription | Record discrepancies and obtain authorised corrections before final publication |
| CON-08 | Verify P1 routes and disabled API actions | No regression; no new privileged route |
| CON-09 | Review visibility with management | Confirm whether final access should be public or authenticated-member only |

No automated tests or browser execution have been performed by the assistant. Do not mark DB10/CON checks complete without the operator's evidence.

## D. Constitutional governance boundary
The constitution's Section 6 recognises multiple zones and the First Schedule lists historical branches. Neither a documentary reference nor a reading copy authorises new live organisational records. Current recognised/active branches remain subject to verified approval, role scope, and auditable Committee Super Admin actions in later work packages. Any amended constitution must be introduced as a separate reviewed version, not silently overwrite 2015 text. Keep original text, source hash, effective date and editorial change log for traceability.
