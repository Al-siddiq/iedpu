<!doctype html>
<html lang="en" data-base-url="<?= esc(base_url(), 'attr') ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Secure sign in | IEDPU Northern Zone</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/sign-in.css'), 'attr') ?>">
    <script defer src="<?= esc(base_url('assets/js/vendor/qrcode.min.js'), 'attr') ?>"></script>
    <script type="module" src="<?= esc(base_url('assets/js/pages/sign-in.js'), 'attr') ?>"></script>
</head>
<body>
<a class="skip" href="#main">Skip to sign in</a>
<header class="top"><a href="<?= esc(base_url(), 'attr') ?>">← IEDPU Northern Zone Portal</a></header>
<main id="main" class="card">
    <div class="mark" aria-hidden="true">IEDPU</div>
    <p class="eyebrow">Secure account access</p>
    <h1>Welcome back</h1>
    <p class="sub">Use your email address <strong>or</strong> phone number. An email address is not compulsory.</p>
    <p id="notice" class="notice" role="status" aria-live="polite"></p>
    <form id="login-form">
        <label for="identifier">Email address or phone number</label>
        <input id="identifier" name="identifier" autocomplete="username" inputmode="text" required maxlength="190" placeholder="0803 123 4567 or you@example.com">
        <label for="password">Password</label>
        <div class="password-field"><input id="password" name="password" type="password" autocomplete="current-password" data-password-label="password" required maxlength="1024"><button type="button" class="password-toggle" data-password-toggle aria-controls="password" aria-label="Show password" aria-pressed="false">Show</button></div>
        <button id="login-submit" type="submit">Sign in</button>
    </form>
    <section id="mfa-section" hidden>
        <h2 id="mfa-title">Secure your account</h2>
        <div id="setup-section" hidden>
            <p>Install Google Authenticator or another authenticator app. In the app, choose <strong>Add account</strong>, then scan this QR code.</p>
            <div id="qr" role="img" aria-label="Authenticator setup QR code"></div>
            <p>If scanning does not work, enter this setup key manually:</p>
            <code id="setup-key"></code>
            <p class="hint">This setup key is private. Do not share or screenshot it. Codes work without mobile data or SMS.</p>
        </div>
        <form id="mfa-form">
            <label for="mfa-code">Six-digit authenticator code or saved recovery code</label>
            <input id="mfa-code" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" autocapitalize="characters" spellcheck="false" required maxlength="40" placeholder="123456">
            <button id="mfa-submit" type="submit">Verify and continue</button>
        </form>
        <button id="restart" class="secondary" type="button">Start again</button>
    </section>
    <section id="complete-section" hidden>
        <h2>You're signed in</h2>
        <p>Your account session is active. Administrative features remain unavailable until the remaining P2 security work is approved.</p>
        <section id="recovery-section" hidden>
            <h3>Save your recovery codes now</h3>
            <p>These one-use codes can help if you lose your phone. Keep them in a safe place. They cannot be shown again.</p>
            <pre id="recovery-codes"></pre>
            <p class="hint">Do not email or share your codes. Never send them to support staff.</p>
        </section>
        <button id="logout" class="secondary" type="button">Sign out</button>
    </section>
    <p class="foot">Invitation-only access. No public registration or SMS fees. If you have not received an account invitation, contact the authorised IEDPU office through its established channels.</p>
</main>
</body>
</html>
