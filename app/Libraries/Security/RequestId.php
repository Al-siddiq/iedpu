<?php
declare(strict_types=1);

namespace App\Libraries\Security;

/** Server-generated correlation ID; never trust a caller's X-Request-ID. */
final class RequestId
{
    private static ?string $current = null;

    public static function current(): string
    {
        return self::$current ??= bin2hex(random_bytes(16));
    }
}
