<?php

namespace App\Modules\Payments\DTO;

/** What the buyer is told to pay. Returned by PaymentGateway::createPayment(). */
final class PaymentInstruction
{
    public function __construct(
        public readonly string $method,          // bitcoin | monero
        public readonly string $address,         // receiving address
        public readonly int $expectedAtomic,     // satoshi / piconero, integer
        public readonly ?int $derivationIndex = null,
        public readonly ?string $fingerprint = null,   // short address fingerprint shown to the buyer
        public readonly ?\DateTimeInterface $expiresAt = null,
        public readonly array $meta = [],
    ) {
    }
}
