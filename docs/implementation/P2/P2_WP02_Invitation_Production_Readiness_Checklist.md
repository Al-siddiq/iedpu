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
