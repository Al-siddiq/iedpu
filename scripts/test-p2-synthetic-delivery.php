<?php
declare(strict_types=1);

/**
 * Standalone, no-DB synthetic delivery boundary check.
 * Does not print tokens or recipients. Does not send any messages.
 */
require_once dirname(__DIR__) . '/app/Libraries/Auth/Delivery/AccountActionDelivery.php';
require_once dirname(__DIR__) . '/app/Libraries/Auth/Delivery/SyntheticLocalDelivery.php';

use App\Libraries\Auth\Delivery\SyntheticLocalDelivery;

function check(bool $condition, string $label): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL " . $label . PHP_EOL);
        exit(1);
    }
    echo "OK   " . $label . PHP_EOL;
}

function rejected(callable $callback): bool
{
    try {
        $callback();
    } catch (RuntimeException $exception) {
        return true;
    }
    return false;
}

$token = bin2hex(random_bytes(32));
$factory = static fn (string $env, string $db, string $host, bool $enabled):
    SyntheticLocalDelivery => new SyntheticLocalDelivery($env, $db, $host, $enabled);

check(rejected(static fn () => $factory('production', 'iedpu_dev_test', 'localhost', true))
    && rejected(static fn () => $factory('staging', 'iedpu_dev_test', 'localhost', true))
    && rejected(static fn () => $factory('testing', 'iedpu_dev_test', 'localhost', true)),
    'Non-development environments rejected');

check(rejected(static fn () => $factory('development', 'iedpu_prod', 'localhost', true))
    && rejected(static fn () => $factory('development', 'iedpu', 'localhost', true)),
    'Non-disposable database names rejected');

check(rejected(static fn () => $factory('development', 'iedpu_dev_test', 'db.example.org', true))
    && rejected(static fn () => $factory('development', 'iedpu_dev_test', '192.168.1.2', true)),
    'Non-loopback hosts rejected');

check(rejected(static fn () => $factory('development', 'iedpu_dev_test', 'localhost', false)),
    'Explicit opt-in required');

$delivery = $factory('development', 'iedpu_dev_test', '127.0.0.1', true);
check(rejected(static fn () => $delivery->deliver('admin_bootstrap', 'synthetic@example.invalid', $token))
    && rejected(static fn () => $delivery->deliver('password_reset', 'synthetic@example.invalid', 'bad'))
    && rejected(static fn () => $delivery->deliver('password_reset', '', $token)),
    'Unknown purpose, malformed token and empty recipient rejected');

$delivery->deliver('invitation_activation', 'synthetic@example.invalid', $token);
$messages = $delivery->takeMessages();
check(count($messages) === 1
    && $messages[0]['purpose'] === 'invitation_activation'
    && $messages[0]['recipient'] === 'synthetic@example.invalid'
    && hash_equals($token, $messages[0]['token']),
    'Synthetic message captured only in process memory');
unset($messages);
check($delivery->takeMessages() === [], 'In-memory queue cleared after retrieval');

echo "HOLD: No real delivery, HTTP activation/reset, privileged recovery or role mutation enabled." . PHP_EOL;
