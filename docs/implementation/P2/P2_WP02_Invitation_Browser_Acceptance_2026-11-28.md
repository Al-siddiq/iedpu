# P2-WP02 — Invitation Request, Approval, Issuance and Activation Acceptance

**Status:** OWNER-REPORTED LOCAL ACCEPTANCE — 28 November 2026  
**Environment:** Local disposable development database `iedpu_disposable_invitation`  
**Workflow:** Officer A (maker) → Officer B (independent checker) → Officer A (issuer) → synthetic recipient activation  
**Scope:** Browser end-to-end invitation workflow only. This does not constitute full WP02 or P2 acceptance, production email delivery acceptance, or production account provisioning.

## Owner-confirmed browser result

The project owner reports completing all eight browser steps successfully:

1. Signed in as Officer B and opened the Invitation Management workspace.
2. Signed out and signed in as Officer A.
3. Opened the same workspace and submitted an invitation request for User 2.
4. Signed out and signed back in as Officer B.
5. Approved Officer A's request.
6. Signed out and signed back in as Officer A.
7. Issued the approved invitation and retrieved the local activation token.
8. Opened the activation page and activated User 2.

**Outcome: PASS — owner-confirmed.** The maker/checker separation and return-to-maker issuance sequence worked in the local browser test. The synthetic recipient account was activated using the local invitation token.

No screenshot, browser trace, database export or delivery-provider receipt was supplied; this record therefore captures the owner's direct confirmation and does not claim independent inspection of local runtime evidence.

## Evidence boundary and remaining controls

- The local activation-token preview is restricted to the development environment and the `iedpu_disposable_invitation` database.
- The test recipient uses a reserved `.invalid` email address; local activation-token retrieval does not prove delivery to a real inbox or production SMTP acceptance.
- This test does not verify real Committee Super Administrator provisioning, privileged lost-device recovery, production recovery channels, production delivery provider, or staging deployment.
- The local test must not be repeated against `iedpu_dev`, staging or production.
- User 2 is a synthetic test account. Do not treat it as an actual IEDPU member or grant it a real organisational role.

## Next engineering work

Continue WP02 in sequence: close remaining account lifecycle and privileged governance requirements, including the owner-designated initial Committee Super Administrator/technical bootstrap, controlled recovery and MFA recovery policy, role/grant ceiling and revocation acceptance, full audit/checkpoint verification, and the remaining applicable security acceptance. Then complete WP03 protected administrative workflows and WP04 privacy/private-storage/recovery/staging evidence before requesting full P2 acceptance.

P3 membership intake follows only after the P2 exit gate and the separate registration/privacy approval requirements are met.
