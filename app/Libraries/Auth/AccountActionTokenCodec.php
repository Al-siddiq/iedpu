<?php
declare(strict_types=1);

namespace App\Libraries\Auth;

use InvalidArgumentException;

/**
 * Cryptographic token primitive for future invitation and recovery flows.
 * No DB writes, delivery, HTTP route or account state change.
 * Token plaintext must exist only transiently in the approved delivery path.
 */
final class AccountActionTokenCodec
{
    /** 256 random bits, encoded as 64 lowercase hexadecimal characters. */
    public static function generate(): string
    {
        return bin2hex(random_bytes(32));
    }

    /** Store only this digest, never the raw token. */
    public static function digest(string $token): string
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/D', $token)) {
            throw new InvalidArgumentException('Invalid token format.');
        }
        return hash('sha256', $token);
    }

    /** Constant-time digest comparison after strict format validation. */
    public static function matches(string $token, string $storedHash): bool
    {
        if (!preg_match('/\A[a-f0-9]{64}\z/D', $token)
            || !preg_match('/\A[a-f0-9]{64}\z/D', $storedHash)) {
            return false;
        }
        return hash_equals($storedHash, hash('sha256', $token));
    }

    /**
     * This primitive cannot verify recipient possession or approve recovery.
     * Caller MUST validate purpose, account, identifier, channel, expiry,
     * single-use status and authorisation under row locks before consumption.
     */
}
