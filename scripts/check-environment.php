<?php

declare(strict_types=1);

/**
 * IEDPU environment readiness checker.
 *
 * Before dependency installation:
 *   php scripts/check-environment.php --preflight
 *
 * After Composer dependencies have been installed:
 *   php scripts/check-environment.php
 *
 * This is an environment diagnostic, not an automated application test suite.
 */

$root = dirname(__DIR__);
$preflightOnly = in_array('--preflight', $argv, true);
$failures = 0;
$warnings = 0;

function report(string $label, bool $ok, string $detail, bool $warning = false): void
{
    global $failures, $warnings;

    $status = $ok ? 'PASS' : ($warning ? 'WARN' : 'FAIL');

    if (! $ok) {
        if ($warning) {
            $warnings++;
        } else {
            $failures++;
        }
    }

    printf("[%s] %-34s %s\n", $status, $label, $detail);
}

echo "IEDPU P1-WP01 environment readiness\n";
echo "PHP " . PHP_VERSION . " (" . PHP_SAPI . ")\n";
echo str_repeat('-', 76) . "\n";

$phpSupported = version_compare(PHP_VERSION, '8.4.0', '>=');
report(
    'PHP runtime',
    $phpSupported,
    $phpSupported
        ? (version_compare(PHP_VERSION, '8.5.0', '>=') ? '8.5+ preferred baseline satisfied.' : '8.4 fallback accepted; 8.5 is preferred.')
        : 'PHP 8.4 or later is required by the approved IEDPU baseline.'
);

foreach (['intl', 'mbstring', 'mysqli', 'openssl', 'fileinfo'] as $extension) {
    report(
        "PHP extension: {$extension}",
        extension_loaded($extension),
        extension_loaded($extension) ? 'Loaded.' : 'Enable this extension in the active PHP CLI and Apache PHP configuration.'
    );
}

report(
    'PHP extension: curl',
    extension_loaded('curl'),
    extension_loaded('curl') ? 'Loaded.' : 'Recommended for Shield password-breach validation and outbound HTTP features.',
    true
);

report(
    'Project environment file',
    is_file($root . DIRECTORY_SEPARATOR . '.env'),
    is_file($root . DIRECTORY_SEPARATOR . '.env')
        ? '.env exists locally.'
        : 'Copy one of the committed .env.*.example profiles to .env and edit its values.'
);

foreach ([
    'public/index.php',
    'app/Config/Paths.php',
    'composer.json',
    'composer.lock',
] as $relativePath) {
    report(
        "Project file: {$relativePath}",
        is_file($root . DIRECTORY_SEPARATOR . $relativePath),
        is_file($root . DIRECTORY_SEPARATOR . $relativePath) ? 'Present.' : 'Missing.'
    );
}

foreach (['writable/cache', 'writable/logs', 'writable/session', 'writable/uploads'] as $relativePath) {
    $absolutePath = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
    $exists = is_dir($absolutePath);
    report(
        "Writable path: {$relativePath}",
        $exists && is_writable($absolutePath),
        ! $exists ? 'Directory is missing.' : (is_writable($absolutePath) ? 'Directory is writable.' : 'Grant write access to the PHP process.')
    );
}

if (! $preflightOnly) {
    $manifestPath = $root . DIRECTORY_SEPARATOR . 'composer.json';
    $lockPath = $root . DIRECTORY_SEPARATOR . 'composer.lock';
    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    $lock = json_decode((string) file_get_contents($lockPath), true);

    foreach ([
        'codeigniter4/framework' => '4.7.6',
        'codeigniter4/shield' => '1.4.1',
    ] as $packageName => $expectedVersion) {
        $manifestVersion = $manifest['require'][$packageName] ?? null;
        report(
            "Manifest requirement: {$packageName}",
            $manifestVersion === $expectedVersion,
            $manifestVersion === $expectedVersion
                ? "Pinned to {$expectedVersion}."
                : "Found " . ($manifestVersion ?? 'missing') . "; expected exact {$expectedVersion}."
        );
    }

    report(
        'No automated test dependencies',
        empty($manifest['require-dev']) && empty($manifest['scripts']['test']),
        'The project verification policy is manual-only.'
    );

    $lockedPackages = [];
    foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $package) {
        $lockedPackages[$package['name']] = ltrim((string) ($package['version'] ?? ''), 'v');
    }

    report(
        'No locked development packages',
        empty($lock['packages-dev']),
        'composer.lock contains no development-only test packages.'
    );

    $expected = [
        'codeigniter4/framework' => '4.7.6',
        'codeigniter4/shield' => '1.4.1',
    ];

    foreach ($expected as $packageName => $expectedVersion) {
        $actual = $lockedPackages[$packageName] ?? null;
        report(
            "Locked package: {$packageName}",
            $actual === $expectedVersion,
            $actual === null
                ? "Not present in composer.lock; expected {$expectedVersion}. Run scripts/bootstrap-windows.ps1."
                : ($actual === $expectedVersion ? "Version {$actual} is locked." : "Found {$actual}; expected {$expectedVersion}. Refresh composer.lock.")
        );
    }

    $settingsVersion = $lockedPackages['codeigniter4/settings'] ?? null;
    $settingsCompatible = $settingsVersion !== null
        && version_compare($settingsVersion, '2.1.0', '>=')
        && version_compare($settingsVersion, '3.0.0', '<');

    report(
        'Locked package: codeigniter4/settings',
        $settingsCompatible,
        $settingsCompatible
            ? "Compatible version {$settingsVersion} is locked."
            : 'Shield requires codeigniter4/settings ^2.1; refresh and verify the lockfile.'
    );

    $vendorChecks = [
        'vendor/autoload.php' => is_file($root . '/vendor/autoload.php'),
        'CodeIgniter framework' => is_file($root . '/vendor/codeigniter4/framework/system/Boot.php'),
        'CodeIgniter Shield' => is_dir($root . '/vendor/codeigniter4/shield/src'),
        'CodeIgniter Settings' => is_dir($root . '/vendor/codeigniter4/settings/src'),
    ];

    foreach ($vendorChecks as $label => $ok) {
        report(
            "Installed: {$label}",
            $ok,
            $ok ? 'Installed locally.' : 'Not found in vendor/. Run the approved Composer bootstrap.'
        );
    }

    if (is_file($root . '/.env')) {
        $envContent = (string) file_get_contents($root . '/.env');
        $hasPlaceholder = str_contains($envContent, 'CHANGE_ME');
        report(
            'Environment placeholders',
            ! $hasPlaceholder,
            $hasPlaceholder ? 'Replace all CHANGE_ME values before using this profile.' : 'No CHANGE_ME placeholders found.',
            true
        );
    }
}

echo str_repeat('-', 76) . "\n";
printf("Result: %d failure(s), %d warning(s).\n", $failures, $warnings);

if ($preflightOnly) {
    echo "Preflight mode checks PHP and the local filesystem before Composer installation.\n";
}

exit($failures === 0 ? 0 : 1);
