<!doctype html>
<html lang="en" data-base-url="<?= esc(base_url(), 'attr') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Account security | IEDPU Northern Zone</title>
  <link rel="stylesheet" href="<?= esc(base_url('assets/css/account-security.css'), 'attr') ?>">
  <script type="module" src="<?= esc(base_url('assets/js/pages/account-security.js'), 'attr') ?>"></script>
</head>
<body>
<main>
  <p class="brand">IEDPU Northern Zone</p>
  <h1>Change your password</h1>
  <p>For your security, enter your current password. Your authenticator must already be verified in this session.</p>
  <form id="password-form" autocomplete="off">
    <label for="current">Current password</label>
    <input id="current" type="password" name="current_password" autocomplete="current-password" required maxlength="1024">
    <label for="next">New password</label>
    <input id="next" type="password" name="new_password" autocomplete="new-password" required minlength="12" maxlength="128">
    <label for="confirm">Confirm new password</label>
    <input id="confirm" type="password" name="confirm_password" autocomplete="new-password" required minlength="12" maxlength="128">
    <p class="hint">Use 12–128 characters, including letters and numbers. Use a unique password.</p>
    <button id="submit" type="submit">Change password and sign out all sessions</button>
  </form>
  <p id="status" role="status" aria-live="polite"></p>
  <p><a href="<?= esc(base_url('sign-in'), 'attr') ?>">Back to sign-in</a></p>
</main>
</body>
</html>
