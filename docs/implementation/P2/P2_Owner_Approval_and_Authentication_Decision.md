# P2 owner acceptance and authentication clarification

Date: 21 November 2026
Owner decision: "plan reviewed and accepted.One important thing: any user should be able to login iether with Email or phone number. We should not force the use of email only. Most nigeria prefere and already have/used to phone number."

The four-work-package P2 plan is accepted with this binding additional requirement. This record supplements the SRS §6, database design §5, P0 ENG-016/024, and the P2 plan. No production activation or later-phase release is authorised.

## P2-AUTH-01 — Alternative login identifiers
- All user types support email/password or phone/password. At least one verified identifier is required; neither email nor phone is individually mandatory. Both identifiers resolve to one Shield account and one password credential.
- Accept Nigerian local and international phone formats, normalise to E.164 using validated numbering rules; allow supported international numbers. Enforce canonical uniqueness transactionally; do not infer login authority from a member profile contact or official office. Account identifiers and membership contact data remain independent.
- WP01: specify a schema extension if required for verified identifiers, unique canonical keys and lifecycle. Preserve Shield-owned tables/migrations and exact FK types. Record the extension before migration.
- WP02: inspect Shield 1.4.1's email/password identity assumptions (Session, UserModel, User entity); use supported extension points and prove real phone-only provisioning, password verification, session/MFA and recovery without requiring a real or fabricated email address. No independent password database, vendor edits, or authentication bypass. A mere validFields change is insufficient.
- Invitation/activation, identifier change and recovery require verified possession or an approved secure assisted process. SMS provider is not selected; integrate a replaceable delivery adapter if approved, use synthetic local delivery only for development. No live SMS, email-only dependency or insecure recovery fallback. Privileged MFA must not require email and retains approved strength; do not equate SMS recovery with privileged MFA.
- Rate limit by account/identifier and network, use generic responses, expire and single-use verification/reset tokens, mask identifiers and omit secrets from logs, reauthenticate/verify changes, revoke sessions on sensitive changes. Handle shared/recycled numbers through reviewed recovery, never silent reassignment.
- WP03: single labelled Email address or phone number field, password, accessible mobile entry and clear recovery; no compulsory email form. WP04: manually test email-only, phone-only, dual identifiers resolving to same account, local/international equivalence, duplicate/race protection, invalid/unverified identifier, suspend/revoke, recovery, changed/recycled number, MFA and unchanged branch/technical/Committee scope.
- Production delivery channel/provider and recovery operating procedure remain activation decisions. No scope expansion to public membership registration or P4 claims.

Status: planning decision recorded; implementation and verification pending.

## P2-AUTH-02 — Approved authenticator-app MFA (21 November 2026)

Owner accepted the recommendation to use authenticator-app TOTP for all privileged accounts, without mandatory TOTP for ordinary members and without a recurring SMS dependency. Google Authenticator is an example, not a vendor lock-in; other RFC6238-compatible apps must work. Guided QR setup, offline recovery codes, and independent approval for lost-device privileged recovery are mandatory. Phone-or-email login from P2-AUTH-01 remains unchanged. Implementation and manual acceptance are recorded in [P2-AUTH-02 TOTP Decision and Acceptance](P2_AUTH02_TOTP_Decision_and_Acceptance.md).

**No SMS fallback or privileged factor reset is authorised by this decision.** Account recovery and high-risk step-up are separate pending gates.
