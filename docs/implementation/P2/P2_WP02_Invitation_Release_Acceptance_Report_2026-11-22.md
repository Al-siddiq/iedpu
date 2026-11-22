# IEDPU P2 Invitation Release — Acceptance Report

**Report date:** 2026-11-22  
**Repository:** Al-siddiq/iedpu  
**Branch:** feature/p2-database-auth-governance  
**Pull request:** https://github.com/Al-siddiq/iedpu/pull/3  
**Acceptance classification:** User-reported functional pass; evidence attachment and production sign-off pending.

## Scope and basis

This report covers the consolidated six-gap invitation release: independent approval, operational issuance, SMTP delivery, public activation, failure recovery and acceptance verification. The owner reports completing initial installation, PHP/database/API prechecks, SMTP configuration preflight, and all 13 end-to-end scenarios successfully. This is a record of that report, **not independent inspection of local test logs or SMTP transactions**.

The preflight output supplied by the owner confirms that seven named prerequisite tables exist, encrypted outbox round-trip succeeds, no sending/uncertain rows were present at that moment, and SMTP environment configuration was present. The preflight explicitly states that it **does not test SMTP connectivity**. Subsequent owner report states all 13 scenarios passed, including delivery.

At review, PR #3 was open, draft and reported mergeable. The GitHub Actions workflow **PHP syntax and identifier smoke** associated with head `5d95334a29080bb46a821a939b59a552d353d845` completed successfully. This is a limited CI signal, not a full security test.

## Acceptance scenario register

| # | Scenario | Expected outcome | Reported result | Independent evidence |
|---|---|---|---|---|
| 1 | Invitation request by Officer A | Request recorded | PASS (owner-reported) | Not attached |
| 2 | Approval by independent Officer B | Decision recorded | PASS (owner-reported) | Not attached |
| 3 | Maker self-approval | Denied | PASS (owner-reported) | Not attached |
| 4 | Issuance without verified MFA | Denied | PASS (owner-reported) | Not attached |
| 5 | Issuance with valid approval | Token queued | PASS (owner-reported) | Not attached |
| 6 | SMTP delivery | Exactly one invitation email | PASS (owner-reported) | Not attached |
| 7 | Valid invitation activation | Account activated | PASS (owner-reported) | Not attached |
| 8 | Replay consumed token | Denied | PASS (owner-reported) | Not attached |
| 9 | Revoke issuer permission before issuance | Denied | PASS (owner-reported) | Not attached |
| 10 | Simultaneous issuance | Only one succeeds | PASS (owner-reported) | Not attached |
| 11 | SMTP failure | Failure audited and token revoked | PASS (owner-reported) | Not attached |
| 12 | Interrupted dispatch | Uncertain state reconciled | PASS (owner-reported) | Not attached |
| 13 | Audit-chain integrity | Verification succeeds | PASS (owner-reported) | Not attached |

## Evidence to retain before approval

Record the isolated test environment name, database identity (not credentials), application base URL, test date/time and timezone, exact tested Git commit SHA, PHP/CI4/Shield versions, test operator, independent reviewer, and sanitized HTTP status/response evidence for the 13 scenarios. Attach the final audit verification output and CI run URL. Keep invitation tokens, passwords, MFA secrets, SMTP credentials, session cookies and encryption keys **out** of evidence attachments.

## Findings and release decision

**Functional acceptance: PASS as reported by the owner.**  
**Independent acceptance evidence: PENDING.**  
**Production security and operational readiness: PENDING.**  
**Merge/deployment authorization: HOLD until checklist completion and named approver sign-off.**

A mergeable GitHub PR and green syntax smoke do not imply production approval. Do not infer full concurrency coverage or real delivery success from the preflight command alone. No production rollout is authorized by this report.

## Sign-off

- Test executor: ____________________
- Evidence reviewed by: ____________________
- Security reviewer: ____________________
- Operations owner: ____________________
- Approval date and commit SHA: ____________________
- Final disposition: [ ] APPROVE [ ] HOLD [ ] REJECT
