<?php

namespace App\Modules\Payments\Contracts;

use App\Modules\Orders\Models\Order;
use App\Modules\Payments\DTO\PaymentInstruction;
use App\Modules\Payments\DTO\PaymentObservation;

/**
 * One implementation per payment method ('bitcoin', 'monero').
 *
 * Contract rules (ported from the legacy platform's behaviour):
 *  - Amounts are integers in the smallest unit (satoshi / piconero). Never floats.
 *  - createPayment() is called once per order and must be idempotent for the same order.
 *  - observe() is read-only against the chain; the Payments module decides what to do
 *    with the observation and is the only caller of OrderLifecycle::markPaid().
 *  - Gateways never touch Order status or escrow fields themselves.
 */
interface PaymentGateway
{
    /** The Order::PAYMENT_METHODS value this gateway serves. */
    public function method(): string;

    /** Allocate a unique receiving address and the exact amount the buyer must send. */
    public function createPayment(Order $order): PaymentInstruction;

    /**
     * Look up what has actually arrived for a previously created payment.
     *
     * @param  string  $address  receiving address from the PaymentInstruction
     */
    public function observe(string $address, int $expectedAtomic): PaymentObservation;
}
