<?php

declare(strict_types=1);

/**
 * Read-only database connection check.
 *
 * This script boots the CodeIgniter console environment and executes SELECT 1.
 * It does not create, migrate, seed, update or delete database records.
 */

use CodeIgniter\Boot;
use Config\Paths;

$root = dirname(__DIR__);

if (! defined('FCPATH')) {
    define('FCPATH', $root . '/public/');
}

if (! defined('ENVIRONMENT')) {
    $environment = 'production';
    $environmentFile = $root . '/.env';

    if (is_file($environmentFile)) {
        $contents = (string) file_get_contents($environmentFile);
        if (preg_match('/^\\s*CI_ENVIRONMENT\\s*=\\s*([A-Za-z0-9_-]+)/m', $contents, $matches) === 1) {
            $environment = $matches[1];
        }
    }

    if (! in_array($environment, ['development', 'production'], true)) {
        fwrite(STDERR, "Unsupported CI_ENVIRONMENT value for this bootstrap check.\\n");
        exit(1);
    }

    define('ENVIRONMENT', $environment);
}

require $root . '/app/Config/Paths.php';

$paths = new Paths();
require $paths->systemDirectory . '/Boot.php';

Boot::bootConsole($paths);

try {
    $database = Config\Database::connect();
    $database->initialize();
    $result = $database->query('SELECT 1 AS iedpu_connection_ok, VERSION() AS server_version')->getRowArray();

    if ((int) ($result['iedpu_connection_ok'] ?? 0) !== 1) {
        fwrite(STDERR, "Database connection query returned an unexpected result.\n");
        exit(1);
    }

    echo "PASS: IEDPU database connection is working.\n";
    echo "Driver: " . $database->getPlatform() . "\n";
    echo "Server version: " . ($result['server_version'] ?? 'unknown') . "\n";
    echo "No schema or data changes were made.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "FAIL: Database connection could not be verified.\n");
    fwrite(STDERR, $exception->getMessage() . "\n");
    exit(1);
}
