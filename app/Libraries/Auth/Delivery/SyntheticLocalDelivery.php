<?php
declare(strict_types=1);

namespace App\Libraries\Auth\Delivery;

use RuntimeException;

/**
 * Disposable local tests only. Captures one token in process memory, never
 * sends a message, writes a file, or logs a token.
 * Not suitable for production or a web request that needs later redemption.
 */
final class SyntheticLocalDelivery implements AccountActionDelivery
{
    /** @var list<array{purpose:string,recipient:string,token:string}> */
    private array $messages = [];

    public function __construct(
        private readonly string $environment,
        private readonly string $databaseName,
        private readonly string $host,
        private readonly bool $explicitlyEnabled
    ) {
        $this->assertLocal();
    }

    public function deliver(string $purpose, string $recipient, string $token): void
    {
        $this->assertLocal();
        if (!in_array($purpose, ['invitation_activation', 'password_reset', 'identifier_verification'], true)
            || !preg_match('/\A[a-f0-9]{64}\z/D', $token)
            || $recipient === ''
            || strlen($recipient) > 255) {
            throw new RuntimeException('Synthetic delivery rejected invalid input.');
        }

        $this->messages[] = compact('purpose', 'recipient', 'token');
    }

    /** For a local test harness only; never return this from an HTTP controller. */
    public function takeMessages(): array
    {
        $this->assertLocal();
        $messages = $this->messages;
        $this->messages = [];
        return $messages;
    }

    private function assertLocal(): void
    {
        if ($this->environment !== 'development'
            || !$this->explicitlyEnabled
            || !in_array(strtolower($this->host), ['localhost', '127.0.0.1', '::1'], true)
            || !preg_match('/\Aiedpu_(?:dev|test|local|disposable)[a-z0-9_]*\z/iD', $this->databaseName)) {
            throw new RuntimeException('Synthetic delivery is disabled outside disposable local development.');
        }
    }
}
