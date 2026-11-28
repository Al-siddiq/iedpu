# P2 Invitation Workspace — Local Browser Guide

This page is part of the disposable local invitation acceptance workflow.

## Open the workspace

After pulling the latest `feature/p2-database-auth-governance` branch and signing in with a TOTP-verified officer account, open:

`http://localhost/iedpu/public/administration/invitations`

If your XAMPP virtual host or project URL differs, use the same host/path you normally use for the IEDPU sign-in page and append:

`administration/invitations`

A shortcut labelled **Invitation workspace** is also available in the portal top bar.

## Officer A — submit and issue

1. Sign in as Officer A.
2. Open **Invitation workspace**.
3. Under **Submit an invitation request**, select the pre-created recipient account and submit the reason.
4. Sign out and sign in as Officer B.
5. After Officer B approves, sign back in as Officer A.
6. Under **My invitation requests**, click **Issue invitation**.
7. Click **Get local activation token** if the token panel does not appear automatically.

## Officer B — approve

1. Sign in as Officer B and complete the authenticator challenge.
2. Open **Invitation workspace**.
3. Under **Requests awaiting your approval**, review the recipient and reason.
4. Enter a short decision note and click **Approve request**.

Officer B cannot approve their own request. Officer B does not need the Maker's `users.invite` permission; the Checker role uses `approvals.view` and `approvals.decide`.

## Activate the test recipient

The current fixture recipient uses a reserved `.invalid` email address, so SMTP delivery is deliberately not used for this local exercise.

After Officer A issues the invitation, the workspace can reveal the activation token only on the local `iedpu_disposable_invitation` database. Open the activation page from the workspace, enter the displayed account ID and token, and set the test recipient's password.

No email or SMS is sent by the local preview.

## If the page does not open

- Confirm the latest branch has been pulled.
- Confirm you are signed in and have completed MFA.
- Hard-refresh the browser with `Ctrl + F5`.
- Confirm the active database is still `iedpu_disposable_invitation`.

Do not switch to `iedpu_dev` during this acceptance exercise.
