# IEDPU P2 Invitation Release — Production Readiness Checklist

**Prepared:** 2026-11-22  
**Status:** HOLD — review and evidence outstanding.  
**PR:** https://github.com/Al-siddiq/iedpu/pull/3

Use [x] only after actual inspection. Do not substitute the owner's 13 successful functional scenarios for infrastructure or security review.

## A. Change and evidence control
- [ ] Pin and record the exact commit tested, after any subsequent code changes.
- [ ] Attach sanitized results for all 13 acceptance scenarios, including independent maker/checker sessions, simultaneous requests, SMTP failure and interrupted dispatch.
- [ ] Confirm the deployment artifact is built from that exact commit and no unreviewed changes were introduced.
- [ ] Obtain independent code review for authentication, MFA, authorization, CSRF, token handling, encryption, audit and database locking.
- [ ] Confirm CI syntax smoke, all applicable unit/integration tests and database migration/rollback safety.

## B. Security controls
- [ ] Confirm production HTTPS, correct base URL, secure cookies, proxy trust configuration, HSTS and CSP.
- [ ] Verify the activation page and API use correct paths when hosted under a subdirectory or virtual host.
- [ ] Verify same-origin CSRF protection on all mutation routes, including public activation.
- [ ] Confirm independent approver and maker permissions are current, correctly scoped and cannot be bypassed through another endpoint.
- [ ] Review lock ordering and concurrent permission changes across **all** governance writers, not only the issuer.
- [ ] Verify token expiry, one-time consumption, rate limiting, password policy and absence of token/password leakage in application logs.
- [ ] Confirm that invitation activation cannot assign roles, create administrators or bypass MFA.
- [ ] Confirm no synthetic fixture, synthetic delivery or development-only authorization is enabled in production.

## C. SMTP and secrets
- [ ] Use an approved SMTP service and verified sender identity; verify SPF/DKIM/DMARC as appropriate.
- [ ] Prove authenticated TLS connectivity and successful delivery to a controlled mailbox from the intended deployment host.
- [ ] Keep SMTP credentials and the 32-byte sodium outbox encryption key in a secrets manager or protected environment; no Git commit or logs contain them.
- [ ] Store a recoverable, access-controlled backup of the outbox key and document key rotation.
- [ ] Confirm recipient email contains only intended data and activation URL points to the correct externally accessible HTTPS host.
- [ ] Confirm scheduler runs the SMTP dispatcher with a restricted service account and monitored execution.

## D. Durable delivery and failure recovery
- [ ] Verify no stranded `sending`/`uncertain` rows before launch.
- [ ] Demonstrate explicit SMTP failure revokes the token and records an audit event.
- [ ] Demonstrate process crash after SMTP claim is not automatically resent; investigate and reconcile under a documented operator procedure.
- [ ] Confirm incident reconciliation requires approval, preserves evidence and does not create a new invitation without fresh approval.
- [ ] Establish alerts for delivery backlog, repeated SMTP errors, uncertain states, audit verification failure and key decryption errors.
- [ ] Confirm database backups, restore drill, rollback plan and operator runbook.

## E. Release controls
- [ ] Confirm an independent security reviewer has approved outstanding findings.
- [ ] Confirm operations owner has accepted monitoring, SMTP quotas, backup and incident responsibilities.
- [ ] Confirm deployment window, rollback owner and communication plan.
- [ ] Record final PASS/HOLD/FAIL decision, date and exact commit.
- [ ] Only after all mandatory checks pass, approve PR merge and controlled production enablement.

## Known review concerns

The SMTP dispatcher treats a process crash after claiming a message as an uncertain state requiring manual reconciliation; this is intentional fail-closed behavior but requires an operational procedure. The preflight validates SMTP configuration presence, **not connection**. The structural acceptance command does not substitute for the user's browser, mail and two-session tests. The invitation page and email URL must be verified under the actual deployment base path.

**Current decision: HOLD for production enablement; no indication that the user's reported functional acceptance failed.**


## Source-code security review — 2026-11-22

**Review scope:** Direct inspection of `Routes.php`, `Filters.php`, `InvitationPage.php`, `ApprovedInvitationRelease.php`, `InvitationActivation.php`, `P2InvitationSmtpDispatch.php` and `P2InvitationReconcile.php` on the feature branch. This is a targeted static review, **not** a comprehensive independent audit, penetration test, deployment inspection or live SMTP check.

### Findings requiring resolution or verification

1. **P2-SR-01 — Activation page deployment path (release blocker for subdirectory deployments).** `InvitationPage.php` hardcodes `/api/v1/auth/csrf`, `/api/v1/auth/invitations/activate`, and `/sign-in`. Those resolve against the host root, not necessarily the CI4 application base path. Use framework-generated URLs or a validated application base path and verify both root-hosted and subdirectory deployments.
2. **P2-SR-02 — CSP hardening (security review required).** The activation page declares `script-src 'unsafe-inline'` and `style-src 'unsafe-inline'`. Move script/style to same-origin static assets or use a per-response nonce, and retest activation under the hardened policy.
3. **P2-SR-03 — SMTP completion semantics (operational evidence).** `Email::send(false)` success means the configured SMTP transport accepted the send, not proof of recipient inbox delivery. Verify controlled-mailbox receipt, sender authentication, bounce handling and monitoring; avoid treating the `sent` database status as delivery confirmation.
4. **P2-SR-04 — Interrupted-dispatch handling (operational release blocker).** Dispatcher intentionally leaves `sending` state on an uncertain exception; operator reconciliation is required and no automatic resend should occur. Establish alerts, incident ownership, evidence preservation and an explicit revoke procedure; verify the procedure in the deployment environment.
5. **P2-SR-05 — Deployment controls (not remotely verified).** HTTPS/proxy/cookie configuration, secret storage and backup, database restore, least-privilege scheduler, production SMTP connectivity, and deployment rollback cannot be certified from repository source or user-reported functional tests.
6. **P2-SR-06 — Exact revision and independent review.** Record the SHA actually tested; changes after that SHA need review and proportionate regression testing. Obtain a named independent security reviewer before final approval.

### Positive controls observed in code

- Routes explicitly declare invitation operations; global CSRF filter is configured.
- `ApprovedInvitationRelease` requires a separate approver, checks permission scope, revalidates eligibility and binds the invitation to the approved intent.
- `InvitationActivation` checks inactive account state, excludes preassigned roles and MFA factors, and uses single-use token redemption in an audited mutation.
- SMTP dispatcher encrypts pending outbox token material at rest, revokes on explicit send failure and does not automatically retry uncertain sends.

**Disposition:** Functional acceptance remains owner-reported PASS; static security review is **OPEN** with findings above; production authorization remains **HOLD**.
