<?php
declare(strict_types=1);

namespace App\Libraries\Security;

use CodeIgniter\Database\BaseConnection;

/**
 * Best-effort security telemetry. Never accepts arbitrary context, credentials,
 * raw identifier, session ID, request body or token. Database outage must be
 * reported by monitoring; failure events cannot be guaranteed in an outage.
 */
final class SecurityEventWriter
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function record(string $code, string $severity = 'medium', ?int $userId = null): void
    {
        if (!preg_match('/\A[a-z][a-z0-9_.-]{2,99}\z/D', $code)
            || !in_array($severity, ['low','medium','high','critical'], true)) {
            throw new \InvalidArgumentException('Invalid security event.');
        }
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 15) | 64);
        $bytes[8] = chr((ord($bytes[8]) & 63) | 128);
        $hex = bin2hex($bytes);
        $uuid = substr($hex,0,8) . '-' . substr($hex,8,4) . '-' . substr($hex,12,4)
            . '-' . substr($hex,16,4) . '-' . substr($hex,20);
        $this->db->table('security_events')->insert([
            'event_uuid' => $uuid,
            'event_code' => $code,
            'severity' => $severity,
            'user_id' => $userId,
            'context_data' => json_encode(['request_id' => RequestId::current()], JSON_THROW_ON_ERROR),
            'occurred_at' => (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))->format('Y-m-d H:i:s.u'),
        ]);
    }
}
