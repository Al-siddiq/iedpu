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
    <div class="password-field"><input id="current" type="password" name="current_password" autocomplete="current-password" data-password-label="current password" required maxlength="1024"><button type="button" class="password-toggle" data-password-toggle aria-controls="current" aria-label="Show current password" aria-pressed="false">Show</button></div>
    <label for="next">New password</label>
    <div class="password-field"><input id="next" type="password" name="new_password" autocomplete="new-password" data-password-label="new password" required minlength="6" maxlength="72"><button type="button" class="password-toggle" data-password-toggle aria-controls="next" aria-label="Show new password" aria-pressed="false">Show</button></div>
    <label for="confirm">Confirm new password</label>
    <div class="password-field"><input id="confirm" type="password" name="confirm_password" autocomplete="new-password" data-password-label="confirm password" required minlength="6" maxlength="72"><button type="button" class="password-toggle" data-password-toggle aria-controls="confirm" aria-label="Show confirm password" aria-pressed="false">Show</button></div>
    <p class="hint">Minimum 6 characters. Numbers only, letters only, or a combination are accepted. Longer, unique passwords are safer.</p>
    <button id="submit" type="submit">Change password and sign out all sessions</button>
  </form>
  <p id="status" role="status" aria-live="polite"></p>
  <p><a href="<?= esc(base_url('sign-in'), 'attr') ?>">Back to sign-in</a></p>
</main>
</body>
</html>
