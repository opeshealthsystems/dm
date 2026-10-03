<?php

namespace App\Modules\Payments\DTO;

/** What the chain currently shows for an address. Returned by PaymentGateway::observe(). */
final class PaymentObservation
{
    public function __construct(
        public readonly int $detectedAtomic,     // seen in mempool or blocks (unconfirmed counts)
        public readonly int $confirmedAtomic,    // amount with enough confirmations
        public readonly int $confirmations,      // confirmations of the oldest relevant transaction
        public readonly array $txids = [],
    ) {
    }
}
