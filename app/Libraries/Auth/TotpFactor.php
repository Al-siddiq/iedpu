<?php
declare(strict_types=1);

namespace App\Libraries\Auth;

use CodeIgniter\Database\BaseConnection;
use RuntimeException;

/**
 * RFC 6238 SHA-1 / 30-second / six-digit authenticator-app codes.
 * Secrets encrypted with independent, deployment-provided sodium key.
 * Never log secrets, OTPs, provisioning URIs or recovery codes.
 */
final class TotpFactor
{
    private readonly string $key;

    public function __construct(private readonly BaseConnection $db)
    {
        if (!extension_loaded('sodium')) {
            throw new RuntimeException('Sodium extension required for privileged MFA.');
        }
        $encoded = (string) env('IEDPU_TOTP_KEY', '');
        $key = base64_decode($encoded, true);
        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException('A separate 32-byte base64 IEDPU_TOTP_KEY is required.');
        }
        $this->key = $key;
    }

    public function hasFactor(int $userId): bool
    {
        return $this->db->table('portal_totp_factors')->where('user_id', $userId)->countAllResults() === 1;
    }

    public function generateSecret(): string
    {
        return self::encodeBase32(random_bytes(20));
    }

    public function provisioningUri(int $userId, string $secret): string
    {
        $issuer = 'IEDPU Northern Zone';
        $label = $issuer . ':Account-' . $userId;
        return 'otpauth://totp/' . rawurlencode($label) . '?secret=' . rawurlencode($secret)
            . '&issuer=' . rawurlencode($issuer) . '&algorithm=SHA1&digits=6&period=30';
    }

    public function encryptSecret(string $secret): string
    {
        self::decodeBase32($secret);
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        return base64_encode($nonce . sodium_crypto_secretbox($secret, $nonce, $this->key));
    }

    public function decryptSecret(string $encrypted): string
    {
        $bytes = base64_decode($encrypted, true);
        if ($bytes === false || strlen($bytes) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException('Invalid encrypted factor.');
        }
        $nonce = substr($bytes, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plain = sodium_crypto_secretbox_open(substr($bytes, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES), $nonce, $this->key);
        if ($plain === false) {
            throw new RuntimeException('MFA key unavailable or factor integrity failure.');
        }
        self::decodeBase32($plain);
        return $plain;
    }

    public function matchStep(string $secret, string $code, ?int $now = null): ?int
    {
        if (!preg_match('/\A[0-9]{6}\z/D', $code)) {
            return null;
        }
        $binary = self::decodeBase32($secret);
        $current = intdiv($now ?? time(), 30);
        foreach ([$current - 1, $current, $current + 1] as $step) {
            if ($step >= 0 && hash_equals(self::codeAtStep($binary, $step), $code)) {
                return $step;
            }
        }
        return null;
    }

    /** @return list<string> One-time codes, never retrievable after response. */
    public function enrol(int $userId, string $secret, int $initialStep): array
    {
        if ($initialStep < 0) {
            throw new RuntimeException('Invalid TOTP enrolment step.');
        }
        $encrypted = $this->encryptSecret($secret);
        $now = self::utcNow();
        $codes = [];
        $this->db->transBegin();
        try {
            if ($this->hasFactor($userId)) {
                throw new RuntimeException('Factor already enrolled; no overwrite permitted.');
            }
            if (!$this->db->table('portal_totp_factors')->insert([
                'user_id' => $userId, 'encrypted_secret' => $encrypted,
                'last_used_step' => $initialStep, 'enrolled_at' => $now,
            ])) {
                throw new RuntimeException('Could not enrol factor.');
            }
            for ($i = 0; $i < 10; $i++) {
                $code = strtoupper(bin2hex(random_bytes(16)));
                $codes[] = $code;
                if (!$this->db->table('portal_totp_recovery_codes')->insert([
                    'user_id' => $userId, 'code_hash' => $this->recoveryHash($code),
                    'created_at' => $now,
                ])) {
                    throw new RuntimeException('Could not create recovery code.');
                }
            }
            if (!$this->db->transStatus() || !$this->db->transCommit()) {
                throw new RuntimeException('Factor enrolment transaction failed.');
            }
        } catch (\Throwable $error) {
            $this->db->transRollback();
            throw $error;
        }
        return $codes;
    }

    public function consumeTotp(int $userId, string $code): bool
    {
        $row = $this->db->table('portal_totp_factors')
            ->select('encrypted_secret,last_used_step')->where('user_id', $userId)->get()->getRowArray();
        if ($row === null) {
            return false;
        }
        $step = $this->matchStep($this->decryptSecret((string) $row['encrypted_secret']), $code);
        if ($step === null || $step <= (int) $row['last_used_step']) {
            return false;
        }
        $this->db->table('portal_totp_factors')
            ->where('user_id', $userId)->where('last_used_step <', $step)
            ->update(['last_used_step' => $step, 'updated_at' => self::utcNow()]);
        return $this->db->affectedRows() === 1;
    }

    public function consumeRecoveryCode(int $userId, string $code): bool
    {
        $canonical = strtoupper(str_replace('-', '', trim($code)));
        if (!preg_match('/\A[0-9A-F]{32}\z/D', $canonical)) {
            return false;
        }
        $this->db->table('portal_totp_recovery_codes')
            ->where('user_id', $userId)
            ->where('code_hash', $this->recoveryHash($canonical))
            ->where('used_at', null)
            ->update(['used_at' => self::utcNow()]);
        return $this->db->affectedRows() === 1;
    }

    private function recoveryHash(string $code): string
    {
        return hash_hmac('sha256', 'IEDPU:recovery:v1:' . $code, $this->key);
    }

    private static function utcNow(): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u');
    }

    private static function codeAtStep(string $key, int $step): string
    {
        $message = pack('N2', ($step >> 32) & 0xffffffff, $step & 0xffffffff);
        $hash = hash_hmac('sha1', $message, $key, true);
        $offset = ord($hash[19]) & 15;
        $value = ((ord($hash[$offset]) & 127) << 24)
            | ((ord($hash[$offset + 1]) & 255) << 16)
            | ((ord($hash[$offset + 2]) & 255) << 8)
            | (ord($hash[$offset + 3]) & 255);
        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private static function encodeBase32(string $bytes): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $bits = 0;
        $buffer = 0;
        $result = '';
        for ($i = 0, $length = strlen($bytes); $i < $length; $i++) {
            $buffer = ($buffer << 8) | ord($bytes[$i]);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $result .= $alphabet[($buffer >> $bits) & 31];
            }
            $buffer &= (1 << $bits) - 1;
        }
        if ($bits > 0) {
            $result .= $alphabet[($buffer << (5 - $bits)) & 31];
        }
        return $result;
    }

    private static function decodeBase32(string $value): string
    {
        if ($value === '' || !preg_match('/\A[A-Z2-7]+\z/D', $value)) {
            throw new RuntimeException('Invalid base32 TOTP secret.');
        }
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $buffer = 0;
        $bits = 0;
        $decoded = '';
        for ($i = 0, $length = strlen($value); $i < $length; $i++) {
            $digit = strpos($alphabet, $value[$i]);
            $buffer = ($buffer << 5) | $digit;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $decoded .= chr(($buffer >> $bits) & 255);
            }
            $buffer &= (1 << $bits) - 1;
        }
        if (strlen($decoded) !== 20 || $buffer !== 0) {
            throw new RuntimeException('Unsupported TOTP secret size.');
        }
        return $decoded;
    }
}
