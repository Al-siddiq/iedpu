<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#17324D">
    <link rel="manifest" href="<?= esc(base_url('manifest.webmanifest'), 'attr') ?>">
    <link rel="icon" type="image/png" sizes="32x32" href="<?= esc(base_url('assets/icons/favicon-32.png'), 'attr') ?>">
    <link rel="icon" type="image/png" sizes="16x16" href="<?= esc(base_url('assets/icons/favicon-16.png'), 'attr') ?>">
    <link rel="apple-touch-icon" sizes="180x180" href="<?= esc(base_url('assets/icons/apple-touch-icon.png'), 'attr') ?>">
    <meta name="description" content="IEDPU Northern Zone membership and administration portal — public information and interface previews.">
    <title>IEDPU Northern Zone | Digital Portal</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/bootstrap.min.css'), 'attr') ?>">
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/app-theme.css'), 'attr') ?>">
</head>
<body>
<a class="skip-link" href="#main-content">Skip to main content</a>
<div id="app" v-cloak data-base-url="<?= esc($portalBaseUrl, 'attr') ?>">
    <div class="portal-layout">
        <header class="portal-topbar">
            <a class="brand" :href="href('/')" @click.prevent="navigate('/')">
                <span class="brand-mark"><img src="<?= esc(base_url('assets/IEDPU.png'), 'attr') ?>" alt="" width="47" height="47"></span>
                <span class="brand-name"><strong>IEDPU</strong><small>Northern Zone · Digital Portal</small></span>
            </a>
            <div class="topbar-actions">
                <button type="button" class="connection-chip" :class="connectionClass"
                    :disabled="checking" @click="refreshHealth" :aria-label="'Check connection. ' + connectionLabel"
                    :title="connectionLabel">
                    <span class="status-dot" aria-hidden="true"></span><span class="connection-label">{{ connectionLabel }}</span>
                </button>
                <a class="btn btn-outline-secondary btn-sm topbar-home" :href="href('/')" @click.prevent="navigate('/')">Public home</a>
            </div>
        </header>

        <div class="portal-body">
            <aside class="portal-sidebar" aria-label="Portal navigation">
                <div class="sidebar-heading">EXPLORE THE PORTAL</div>
                <nav aria-label="Main navigation">
                    <a v-for="item in navigationItems" :key="item.path"
                       :href="href(item.path)" @click.prevent="navigate(item.path)"
                       class="nav-entry" :class="{ active: route === item.path }"
                       :aria-current="route === item.path ? 'page' : null">
                        <span class="nav-symbol" aria-hidden="true"><svg class="bi" aria-hidden="true" focusable="false"><use :href="'<?= esc(base_url('assets/icons/bootstrap-icons.svg'), 'attr') ?>#' + item.icon"></use></svg></span>
                        <span>{{ item.label }}</span>
                        <span v-if="item.preview" class="nav-preview">Preview</span>
                    </a>
                </nav>
                <div class="sidebar-notice">
                    <span class="notice-icon" aria-hidden="true"><svg class="bi" aria-hidden="true" focusable="false"><use href="<?= esc(base_url('assets/icons/bootstrap-icons.svg'), 'attr') ?>#info-circle"></use></svg></span>
                    <p>These are design previews. Member sign-in and administrative functions are not yet available.</p>
                </div>
            </aside>

            <main id="main-content" class="portal-main" tabindex="-1">
                <div class="main-inner">
                    <div class="breadcrumb-line" aria-label="Current location">
                        <span>IEDPU Northern Zone</span><span aria-hidden="true">/</span><strong>{{ current.title }}</strong>
                    </div>

                    <template v-if="route === '/'">
                        <section class="hero-panel" aria-labelledby="home-heading">
                            <div class="hero-copy">
                                <span class="eyebrow">THE NORTHERN ZONE · ONE CONNECTED COMMUNITY</span>
                                <h1 id="home-heading">A simpler way to serve our members.</h1>
                                <p>A digital home for membership information, branch coordination and accountable administration — designed for the people who use it every day.</p>
                                <div class="hero-actions">
                                    <a class="btn btn-light btn-lg" :href="href('/preview/member')" @click.prevent="navigate('/preview/member')">Explore member portal <svg class="bi" aria-hidden="true" focusable="false"><use href="<?= esc(base_url('assets/icons/bootstrap-icons.svg'), 'attr') ?>#arrow-right"></use></svg></a>
                                    <a class="hero-text-link" :href="href('/preview/committee')" @click.prevent="navigate('/preview/committee')">See how administration works <svg class="bi" aria-hidden="true" focusable="false"><use href="<?= esc(base_url('assets/icons/bootstrap-icons.svg'), 'attr') ?>#arrow-right"></use></svg></a>
                                </div>
                            </div>
                            <div class="hero-art" aria-hidden="true">
                                <div class="art-circle art-circle-one"></div><div class="art-circle art-circle-two"></div>
                                <div class="art-tile"><span class="art-icon" aria-hidden="true"><svg class="bi" aria-hidden="true" focusable="false"><use href="<?= esc(base_url('assets/icons/bootstrap-icons.svg'), 'attr') ?>#people-fill"></use></svg></span><span>People first</span><strong>One community</strong></div>
                                <div class="art-tile art-tile-secondary"><span class="art-icon" aria-hidden="true"><svg class="bi" aria-hidden="true" focusable="false"><use href="<?= esc(base_url('assets/icons/bootstrap-icons.svg'), 'attr') ?>#shield-check"></use></svg></span><span>Clear records</span><strong>Trusted service</strong></div>
                            </div>
                        </section>

                        <div class="section-intro">
                            <div><span class="eyebrow eyebrow-dark">BUILT FOR EVERY RESPONSIBILITY</span><h2>One portal. Clear spaces for everyone.</h2></div>
                            <p>Explore each area before live services are enabled.</p>
                        </div>
                        <div class="preview-grid">
                            <a v-for="item in previewCards" :key="item.path" class="preview-card" :href="href(item.path)" @click.prevent="navigate(item.path)">
                                <span class="preview-icon" :class="item.tone" aria-hidden="true"><svg class="bi" aria-hidden="true" focusable="false"><use :href="'<?= esc(base_url('assets/icons/bootstrap-icons.svg'), 'attr') ?>#' + item.icon"></use></svg></span>
                                <span class="preview-card-type">DESIGN PREVIEW</span>
                                <strong>{{ item.label }}</strong>
                                <span class="preview-description">{{ item.description }}</span>
                                <span class="preview-arrow">Explore interface <svg class="bi" aria-hidden="true" focusable="false"><use href="<?= esc(base_url('assets/icons/bootstrap-icons.svg'), 'attr') ?>#arrow-right"></use></svg></span>
                            </a>
                        </div>

                        <div class="info-strip" role="note"><span class="info-strip-icon" aria-hidden="true"><svg class="bi" aria-hidden="true" focusable="false"><use href="<?= esc(base_url('assets/icons/bootstrap-icons.svg'), 'attr') ?>#info-circle"></use></svg></span><p><strong>We're building this carefully.</strong> These pages demonstrate the planned interface only. Registration, verification, payments and administrative decisions are not yet active.</p></div>
                    </template>

                    <template v-else>
                        <section class="workspace-heading" aria-labelledby="workspace-title">
                            <div>
                                <span class="eyebrow eyebrow-dark">{{ current.overline }}</span>
                                <h1 id="workspace-title">{{ current.title }}</h1>
                                <p>{{ current.description }}</p>
                            </div>
                            <span class="preview-badge">Interface preview · No live access</span>
                        </section>

                        <div class="workspace-banner" role="note">
                            <span class="banner-icon" aria-hidden="true"><svg class="bi" aria-hidden="true" focusable="false"><use href="<?= esc(base_url('assets/icons/bootstrap-icons.svg'), 'attr') ?>#info-circle"></use></svg></span>
                            <div><strong>This area is not yet active</strong><p>What you see is a layout preview. No account is signed in, no personal records are displayed and no changes can be submitted.</p></div>
                        </div>

                        <div class="workspace-grid">
                            <section class="workspace-card workspace-card-primary" aria-labelledby="work-main-heading">
                                <div class="card-label">YOUR WORKSPACE</div>
                                <h2 id="work-main-heading">{{ current.welcome }}</h2>
                                <p>{{ current.guidance }}</p>
                                <div class="feature-list">
                                    <div v-for="feature in current.features" :key="feature.title" class="feature-row">
                                        <span class="feature-check" aria-hidden="true"><svg class="bi" aria-hidden="true" focusable="false"><use href="<?= esc(base_url('assets/icons/bootstrap-icons.svg'), 'attr') ?>#check-circle"></use></svg></span>
                                        <div><strong>{{ feature.title }}</strong><small>{{ feature.description }}</small></div>
                                        <span class="feature-pending">Coming later</span>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-primary btn-lg" disabled aria-disabled="true">{{ current.action }}</button>
                                <p class="action-help">Available after secure account and permissions are implemented.</p>
                            </section>

                            <aside class="workspace-side" aria-label="Workspace information">
                                <div class="side-card">
                                    <span class="side-card-icon" aria-hidden="true"><svg class="bi" aria-hidden="true" focusable="false"><use href="<?= esc(base_url('assets/icons/bootstrap-icons.svg'), 'attr') ?>#signpost-split"></use></svg></span>
                                    <h2>What happens next?</h2>
                                    <p>{{ current.next }}</p>
                                    <span class="muted-tag">Not accepting information yet</span>
                                </div>
                                <div class="side-card side-card-light">
                                    <h2>Designed for your phone</h2>
                                    <p>Everything you need will remain available on small screens, tablets and computers.</p>
                                    <a :href="href('/')" @click.prevent="navigate('/')" class="text-link">Return to public home <svg class="bi" aria-hidden="true" focusable="false"><use href="<?= esc(base_url('assets/icons/bootstrap-icons.svg'), 'attr') ?>#arrow-right"></use></svg></a>
                                </div>
                            </aside>
                        </div>
                    </template>
                    <footer class="portal-footer"><span>© {{ year }} IEDPU Northern Zone · Digital Portal</span><span>Built for clarity, accessibility and accountability</span></footer>
                </div>
            </main>
        </div>

        <nav class="mobile-bottom-nav" aria-label="Mobile portal navigation">
            <a v-for="item in mobileItems" :key="item.path" :href="href(item.path)"
               @click.prevent="navigate(item.path)" :aria-current="route === item.path ? 'page' : null"
               :class="{ active: route === item.path }">
                <span class="mobile-nav-symbol" aria-hidden="true"><svg class="bi" aria-hidden="true" focusable="false"><use :href="'<?= esc(base_url('assets/icons/bootstrap-icons.svg'), 'attr') ?>#' + item.icon"></use></svg></span><span>{{ item.shortLabel }}</span>
            </a>
        </nav>
    </div>
</div>
<noscript><div class="no-script-warning">JavaScript is required to display the interactive portal preview. Please enable JavaScript in your browser.</div></noscript>
<div id="frontend-error" class="frontend-error" role="alert" hidden>
    The interface could not start. Install the local Vue vendor asset using <code>scripts/install-frontend-vendors.ps1</code>, then reload this page.
</div>
<section id="pwa-notice" class="pwa-notice" hidden aria-label="Portal installation and updates">
    <p id="pwa-message" role="status"></p>
    <button id="pwa-install" type="button" class="btn btn-primary btn-sm" hidden><svg class="bi" aria-hidden="true" focusable="false"><use href="<?= esc(base_url('assets/icons/bootstrap-icons.svg'), 'attr') ?>#download"></use></svg> Install portal</button>
    <button id="pwa-update" type="button" class="btn btn-primary btn-sm" hidden><svg class="bi" aria-hidden="true" focusable="false"><use href="<?= esc(base_url('assets/icons/bootstrap-icons.svg'), 'attr') ?>#arrow-clockwise"></use></svg> Refresh to update</button>
    <button id="pwa-dismiss" type="button" class="btn btn-outline-secondary btn-sm"><svg class="bi" aria-hidden="true" focusable="false"><use href="<?= esc(base_url('assets/icons/bootstrap-icons.svg'), 'attr') ?>#x-lg"></use></svg> Dismiss</button>
</section>
<script defer src="<?= esc(base_url('assets/js/vendor/dexie.min.js'), 'attr') ?>"></script>
<script defer src="<?= esc(base_url('assets/js/vendor/vue.global.prod.js'), 'attr') ?>"></script>
<script defer type="module" src="<?= esc(base_url('assets/js/core/app.js'), 'attr') ?>"></script>
<script type="module" src="<?= esc(base_url('assets/js/core/pwa.js'), 'attr') ?>"></script>
</body>
</html>
