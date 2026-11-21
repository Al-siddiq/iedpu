<?php
declare(strict_types=1);

namespace App\Libraries\Auth\Delivery;

/**
 * Transport boundary only. Caller must issue the bound token in an audited
 * transaction and must not perform delivery inside that transaction.
 */
interface AccountActionDelivery
{
    public function deliver(string $purpose, string $recipient, string $token): void;
}
