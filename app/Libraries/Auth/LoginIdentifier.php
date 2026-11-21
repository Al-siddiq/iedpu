<?php
declare(strict_types=1);

namespace App\Libraries\Auth;

use InvalidArgumentException;

/**
 * Login identifier canonicalisation, not proof of ownership or allocation.
 * Verification and credential resolution belong to WP02.
 */
final class LoginIdentifier
{
    /** @return array{type: string, value: string} */
    public static function normalise(string $input): array
    {
        $input = trim($input);
        if (strlen($input) > 190) {
            throw new InvalidArgumentException('Invalid login identifier.');
        }
        if (str_contains($input, '@')) {
            $email = strtolower($input);
            if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
                throw new InvalidArgumentException('Invalid login identifier.');
            }
            return ['type' => 'email', 'value' => $email];
        }
        // Accept presentation separators only; never strip arbitrary letters.
        if (!preg_match('/\A[+0-9 ().-]+\z/D', $input)) {
            throw new InvalidArgumentException('Invalid login identifier.');
        }
        $phone = str_replace([' ', '(', ')', '.', '-'], '', $input);
        if (preg_match('/\A0[789][0-9]{9}\z/D', $phone)) {
            $phone = '+234' . substr($phone, 1);
        } elseif (preg_match('/\A234[789][0-9]{9}\z/D', $phone)) {
            $phone = '+' . $phone;
        } elseif (str_starts_with($phone, '00')) {
            $phone = '+' . substr($phone, 2);
        }
        if (!preg_match('/\A\+[1-9][0-9]{6,14}\z/D', $phone)) {
            throw new InvalidArgumentException('Invalid login identifier.');
        }
        if (str_starts_with($phone, '+234') && !preg_match('/\A\+234[1-9][0-9]{9}\z/D', $phone)) {
            throw new InvalidArgumentException('Invalid Nigerian phone number.');
        }
        return ['type' => 'phone', 'value' => $phone];
    }
}
