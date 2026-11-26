# Local invitation acceptance test actors

Use only on the local `iedpu_disposable_invitation` database. This creates no production accounts and sends no email or SMS.

## Existing fixtures confirmed

- User 1: synthetic phone-only account, active, TOTP enrolled; use as Officer A (Maker).
- User 2: inactive invitation recipient with pending `iedpu-invite-fixture@example.invalid`; retain as the recipient fixture.
- Existing role `synthetic_invite_acceptance`: contains `users.invite`; its assignment to User 1 was revoked and is restored temporarily by the fixture command.

## Create Officer B

Pull the branch, then run:

```bat
git pull --ff-only origin feature/p2-database-auth-governance
php -l app\Commands\P2InvitationTestActors.php
php spark iedpu:p2-invitation-test-actors --confirm-synthetic --confirm-disposable
```

The command is restricted to local `iedpu_disposable_invitation` in development with `IEDPU_ALLOW_SYNTHETIC_AUTH=1`. It creates User 3 with a synthetic phone login, assigns only `approvals.view` and `approvals.decide`, and sets an eight-hour expiry on both temporary role assignments. It restores User 1's existing `users.invite` test assignment for the same period. All changes are made in one audited transaction.

The command prints a one-time password for Officer B. Save it locally; do not paste it into chat or screenshots. The synthetic phone is a login identifier only; no SMS is sent.

## Enrol Officer B MFA

1. Sign out of Officer A.
2. Sign in using Officer B's synthetic phone and one-time password.
3. The app should require authenticator enrolment.
4. Open the MFA setup route shown by the application, scan the QR code with an authenticator app, and submit the current six-digit code.
5. Save the recovery codes locally.
6. Sign out and back in as Officer B to confirm normal MFA challenge.

Then use Officer A to request and issue the invitation, and Officer B to independently approve it. The invitation recipient remains User 2.

## Notes

- No changes are made to `iedpu_dev`.
- No production administrator or role-provisioning route is enabled.
- The temporary role assignments expire after eight hours. The account and role records remain in this disposable database for auditability; switch back to `iedpu_dev` after the acceptance exercise.
- If the command reports that the Checker fixture already exists, do not rerun or delete records manually; inspect the existing User 3 fixture first.
