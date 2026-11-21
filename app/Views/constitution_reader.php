<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="theme-color" content="#17324D">
    <meta name="description" content="Read the 2015 IEDPU Constitution by section. Transcription pending editorial verification against the source.">
    <title><?= esc($part['title'] ?? 'Constitution of IEDPU, 2015') ?> | IEDPU Northern Zone</title>
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/bootstrap.min.css'), 'attr') ?>">
    <link rel="stylesheet" href="<?= esc(base_url('assets/css/constitution-reader.css'), 'attr') ?>">
</head>
<body>
<a class="reader-skip" href="#reader-main">Skip to constitution</a>
<header class="reader-header">
    <a class="reader-brand" href="<?= esc(base_url(), 'attr') ?>">
        <img src="<?= esc(base_url('assets/IEDPU.png'), 'attr') ?>" width="46" height="46" alt="">
        <span><strong>IEDPU Northern Zone</strong><small>Digital Portal · Constitution</small></span>
    </a>
    <a class="reader-home" href="<?= esc(base_url(), 'attr') ?>">Portal home</a>
</header>
<div class="reader-wrap">
    <div class="reader-banner" role="note">
        <strong>2015 source-derived reading copy — proofreading pending.</strong>
        The section text is extracted from the supplied 30-page document. Apparent spelling errors,
        inconsistent cross-references and omissions are retained. Verify consequential provisions
        against the signed constitution; this online copy is not an amendment or an authoritative interpretation.
        The source does not print a Section 16 heading; its navigation label is editorial.
    </div>
    <div class="reader-layout">
        <nav class="reader-nav" aria-label="Constitution table of contents">
            <h2>Contents</h2>
            <a href="<?= esc(base_url('constitution/2015'), 'attr') ?>" <?= $part === null ? 'aria-current="page"' : '' ?>>Overview and search</a>
            <?php foreach ($document['parts'] as $item): ?>
                <a href="<?= esc(base_url('constitution/2015/' . $item['key']), 'attr') ?>"
                   <?= $part !== null && $part['key'] === $item['key'] ? 'aria-current="page"' : '' ?>>
                    <?= esc($item['title']) ?>
                </a>
            <?php endforeach ?>
        </nav>
        <main id="reader-main" class="reader-main" tabindex="-1">
            <?php if ($part === null): ?>
                <p class="reader-eyebrow">THE CONSTITUTION · 2015 EDITION</p>
                <h1>The Constitution of Ilorin Emirate Descendants Progressive Union</h1>
                <p class="reader-intro">Browse the preamble, Sections 1–17, schedules, citation and commencement as individual readable pages.</p>
                <p class="reader-source">Source: uploaded 2015 document · 30 PDF pages · effective date stated in source: 19 April 2015.</p>
                <form action="<?= esc(base_url('constitution/2015'), 'attr') ?>" method="get" role="search" class="reader-search">
                    <label for="constitution-query">Find words in the constitution</label>
                    <div class="reader-search-row">
                        <input id="constitution-query" name="q" type="search" maxlength="80" value="<?= esc($query, 'attr') ?>" placeholder="For example: branch, member, elections">
                        <button type="submit">Search</button>
                    </div>
                </form>
                <?php if ($query !== ''): ?>
                    <h2>Search results for “<?= esc($query) ?>”</h2>
                    <p><?= count($matches) ?> matching <?= count($matches) === 1 ? 'part' : 'parts' ?>. Open a part to read the full provision.</p>
                    <ul class="reader-results">
                    <?php foreach ($matches as $match): ?>
                        <li><a href="<?= esc(base_url('constitution/2015/' . $match['key']), 'attr') ?>"><?= esc($match['title']) ?></a></li>
                    <?php endforeach ?>
                    </ul>
                <?php else: ?>
                    <h2>Read by section</h2>
                    <div class="reader-cards">
                        <?php foreach ($document['parts'] as $item): ?>
                            <a href="<?= esc(base_url('constitution/2015/' . $item['key']), 'attr') ?>">
                                <strong><?= esc($item['title']) ?></strong>
                                <span>Source page<?= $item['source_start_page'] === $item['source_end_page'] ? '' : 's' ?>
                                <?= (int) $item['source_start_page'] ?><?= $item['source_start_page'] === $item['source_end_page'] ? '' : '–' . (int) $item['source_end_page'] ?></span>
                            </a>
                        <?php endforeach ?>
                    </div>
                <?php endif ?>
            <?php else: ?>
                <p class="reader-eyebrow">2015 EDITION · SOURCE PAGE<?= $part['source_start_page'] === $part['source_end_page'] ? '' : 'S' ?>
                <?= (int) $part['source_start_page'] ?><?= $part['source_start_page'] === $part['source_end_page'] ? '' : '–' . (int) $part['source_end_page'] ?></p>
                <h1><?= esc($part['title']) ?></h1>
                <?php if (!empty($part['source_heading_missing'])): ?>
                    <p class="reader-warning">Editorial navigation label: “SECTION 16” is not printed in the supplied document. The text below begins with “STANDING ORDERS”.</p>
                <?php endif ?>
                <div class="reader-provision" aria-label="Extracted constitution text"><?= esc($part['text']) ?></div>
                <?php
                    $index = array_search($part['key'], array_column($document['parts'], 'key'), true);
                    $previous = $index > 0 ? $document['parts'][$index - 1] : null;
                    $next = $index !== false && $index < count($document['parts']) - 1 ? $document['parts'][$index + 1] : null;
                ?>
                <nav class="reader-prev-next" aria-label="Adjacent constitution parts">
                    <?php if ($previous !== null): ?>
                        <a href="<?= esc(base_url('constitution/2015/' . $previous['key']), 'attr') ?>">← <?= esc($previous['title']) ?></a>
                    <?php endif ?>
                    <?php if ($next !== null): ?>
                        <a href="<?= esc(base_url('constitution/2015/' . $next['key']), 'attr') ?>"><?= esc($next['title']) ?> →</a>
                    <?php endif ?>
                </nav>
            <?php endif ?>
        </main>
    </div>
    <footer class="reader-footer">IEDPU Constitution, 2015 · Source PDF SHA-256: <code><?= esc($document['source_pdf_sha256']) ?></code> · <a href="<?= esc(base_url(), 'attr') ?>">Return to portal</a></footer>
</div>
</body>
</html>
