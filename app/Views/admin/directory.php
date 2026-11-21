<!doctype html>
<html lang="en" data-base-url="<?= esc(base_url(), 'attr') ?>" data-allowed="<?= esc(implode(',', $allowedDirectories), 'attr') ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Administration | IEDPU Northern Zone</title>
  <link rel="stylesheet" href="<?= esc(base_url('assets/css/admin-directory.css'), 'attr') ?>">
  <script type="module" src="<?= esc(base_url('assets/js/pages/admin-directory.js'), 'attr') ?>"></script>
</head>
<body>
<a class="skip" href="#main">Skip to directory</a>
<header class="bar"><a href="<?= esc(base_url(), 'attr') ?>">IEDPU Northern Zone</a><span>Administration</span><a href="<?= esc(base_url('sign-in'), 'attr') ?>">Account</a></header>
<main id="main" class="layout">
  <div class="heading">
    <div><p class="eyebrow">Authorised workspace</p><h1>Organisation directory</h1><p>View the records your role permits. No changes can be made from this workspace yet.</p></div>
    <span class="status">Read-only · P2-WP03</span>
  </div>
  <nav id="directory-nav" aria-label="Organisation sections"></nav>
  <section class="panel" aria-labelledby="directory-title">
    <h2 id="directory-title">Directory</h2>
    <p id="notice" role="status" aria-live="polite">Loading authorised records…</p>
    <div class="table-wrap"><table id="directory-table" hidden><thead><tr id="directory-head"></tr></thead><tbody id="directory-body"></tbody></table></div>
    <div class="pagination"><button type="button" id="previous" disabled>Previous</button><span id="page-label"></span><button type="button" id="next" disabled>Next</button></div>
  </section>
  <p class="note">Only project-scope records are displayed. Official IEDPU offices are separate from portal permissions. Account invitations, approvals, branch activation and all editing remain disabled until the remaining security gates are accepted.</p>
</main>
</body>
</html>
