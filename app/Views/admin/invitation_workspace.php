<!doctype html>
<html lang="en" data-base-url="<?= esc(base_url(), 'attr') ?>"
      data-can-request="<?= $canRequest ? '1' : '0' ?>"
      data-can-approve="<?= $canApprove ? '1' : '0' ?>"
      data-user-id="<?= (int) $userId ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Invitation Management | IEDPU</title>
  <link rel="stylesheet" href="<?= esc(base_url('assets/css/invitation-workspace.css'), 'attr') ?>">
  <script type="module" src="<?= esc(base_url('assets/js/pages/invitation-workspace.js'), 'attr') ?>"></script>
</head>
<body>
<header class="topbar">
  <a class="brand" href="<?= esc(base_url(), 'attr') ?>">IEDPU <span>Northern Zone</span></a>
  <nav><a href="<?= esc(base_url('administration/workspace'), 'attr') ?>">Administration</a><a href="<?= esc(base_url('sign-in'), 'attr') ?>">Account</a></nav>
</header>
<main class="layout">
  <div class="heading">
    <div><p class="eyebrow">P2 · LOCAL ACCEPTANCE</p><h1>Invitation management</h1>
      <p>Submit an invitation request, approve it with a different officer, then issue the invitation.</p></div>
    <span class="secure-label">Secure session · MFA verified</span>
  </div>
  <div id="notice" class="notice" role="status" aria-live="polite">Loading your invitation workspace…</div>

  <?php if ($canRequest): ?>
  <section class="panel" id="request-panel">
    <div class="panel-heading"><div><p class="eyebrow">OFFICER A · MAKER</p><h2>Submit an invitation request</h2>
      <p>Select the pre-created account that should receive an invitation.</p></div><span class="step">01</span></div>
    <form id="request-form">
      <label for="candidate">Invitation recipient</label>
      <select id="candidate" name="candidate" required><option value="">Loading eligible accounts…</option></select>
      <label for="request-reason">Reason for invitation</label>
      <textarea id="request-reason" name="reason" minlength="10" maxlength="500" required>Local IEDPU invitation workflow acceptance test.</textarea>
      <button class="primary" type="submit">Submit invitation request</button>
    </form>
  </section>
  <?php endif; ?>

  <?php if ($canApprove): ?>
  <section class="panel" id="approval-panel">
    <div class="panel-heading"><div><p class="eyebrow">OFFICER B · CHECKER</p><h2>Requests awaiting your approval</h2>
      <p>You cannot approve a request submitted by your own account.</p></div><span class="step">02</span></div>
    <div id="pending-list" class="record-list"><p class="empty">Loading requests…</p></div>
  </section>
  <?php endif; ?>

  <?php if ($canRequest): ?>
  <section class="panel" id="my-requests-panel">
    <div class="panel-heading"><div><p class="eyebrow">OFFICER A · MAKER</p><h2>My invitation requests</h2>
      <p>Once Officer B approves, return here to issue the invitation.</p></div><span class="step">03</span></div>
    <div id="my-requests-list" class="record-list"><p class="empty">Loading your requests…</p></div>
  </section>
  <?php endif; ?>

  <section class="panel local-panel">
    <p class="eyebrow">LOCAL TESTING ONLY</p><h2>Activate the invitation without sending email</h2>
    <p>The current recipient fixture uses a reserved <code>.invalid</code> email address. After Officer A issues an approved invitation, use the local preview button to obtain its activation token. No email or SMS will be sent.</p>
    <a class="secondary" href="<?= esc(site_url('invitation/activate'), 'attr') ?>">Open invitation activation page</a>
    <p class="small">Keep this workflow on <code>iedpu_disposable_invitation</code>. Do not use it with the permanent development account database.</p>
  </section>
  <footer><a href="<?= esc(base_url(), 'attr') ?>">Return to portal home</a><span>IEDPU Northern Zone · Development acceptance</span></footer>
</main>
</body>
</html>
