<?php

namespace App\Modules\Payments\Actions;

use App\Modules\Orders\Actions\OrderLifecycle;
use App\Modules\Orders\Models\Order;
use App\Modules\Payments\Contracts\PaymentGateway;
use App\Modules\Payments\Models\Payment;
use App\Modules\Payments\Support\PaymentLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Creates payments and applies chain observations. The ONLY caller of OrderLifecycle::markPaid.
 *
 * Legacy rules pinned here (PaymentService::checkPaymentStatus):
 *  - paid when confirmed >= expected AND expected > 0 (overpayment is accepted as paid);
 *  - confirmed balance below expected keeps the order pending (underpaid), nothing is auto-refunded;
 *  - a late payment (after expiry) still counts; expiry only marks an untouched address.
 */
class PaymentService
{
    /** @var array<string, PaymentGateway> */
    private array $gateways = [];

    /** @param iterable<PaymentGateway> $gateways */
    public function __construct(iterable $gateways, private readonly OrderLifecycle $lifecycle)
    {
        foreach ($gateways as $gateway) {
            $this->gateways[$gateway->method()] = $gateway;
        }
    }

    public function gateway(string $method): PaymentGateway
    {
        return $this->gateways[$method]
            ?? throw new InvalidArgumentException("No payment gateway for method [$method].");
    }

    /** Idempotent: one payment per order and method. */
    public function createForOrder(Order $order): Payment
    {
        $method = $order->payment_method;
        $gateway = $this->gateway((string) $method);

        for ($attempt = 1; ; $attempt++) {
            try {
                return DB::transaction(function () use ($order, $gateway, $method) {
                    $existing = Payment::where('order_id', $order->id)->where('method', $method)->lockForUpdate()->first();
                    if ($existing) {
                        return $existing;
                    }

                    $i = $gateway->createPayment($order);

                    $payment = new Payment();
                    $payment->forceFill([
                        'order_id' => $order->id,
                        'method' => $i->method,
                        'address' => $i->address,
                        'derivation_index' => $i->derivationIndex,
                        'expected_atomic' => $i->expectedAtomic,
                        'status' => Payment::PENDING,
                        'meta' => $i->meta ?: null,
                        'expires_at' => $i->expiresAt,
                    ])->save();

                    PaymentLogger::info('Payment created', [
                        'payment_id' => $payment->id, 'order_id' => $order->id, 'method' => $i->method,
                        'index' => $i->derivationIndex, 'expected_atomic' => $i->expectedAtomic,
                        'address_fp' => $i->fingerprint,
                    ]);

                    return $payment;
                });
            } catch (UniqueConstraintViolationException $e) {
                // Concurrent creator won (same order) or index collision: re-read / re-derive.
                $existing = Payment::where('order_id', $order->id)->where('method', $method)->first();
                if ($existing) {
                    return $existing;
                }
                if ($attempt >= 3) {
                    throw $e;
                }
            }
        }
    }

    /** Look at the chain and apply the result. Safe to call any number of times. */
    public function poll(Payment $payment): Payment
    {
        if ($payment->isFinal()) {
            return $payment;
        }

        try {
            $obs = $this->gateway($payment->method)->observe($payment->address, $payment->expected_atomic);
        } catch (\Throwable $e) {
            PaymentLogger::error('Chain lookup failed', ['payment_id' => $payment->id, 'error' => $e->getMessage()]);

            return $payment;
        }

        $markPaidFor = null;

        $payment = DB::transaction(function () use ($payment, $obs, &$markPaidFor) {
            $p = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();
            if ($p->isFinal()) {
                return $p;
            }

            $expected = $p->expected_atomic;
            $min = $this->minConfirmations($p->method);
            $confirmedEnough = $expected > 0                                  // legacy: expected must be > 0
                && $obs->confirmedAtomic >= $expected
                && $obs->confirmations >= $min;

            $previous = $p->status;
            if ($confirmedEnough) {
                $status = Payment::CONFIRMED;
            } elseif ($obs->detectedAtomic > 0 && $obs->detectedAtomic < $expected) {
                $status = Payment::UNDERPAID;
            } elseif ($obs->detectedAtomic > 0) {
                $status = Payment::DETECTED;      // enough seen, waiting for confirmations
            } elseif ($p->expires_at && $p->expires_at->isPast()) {
                $status = Payment::EXPIRED;
            } else {
                $status = Payment::PENDING;
            }

            $p->forceFill([
                'detected_atomic' => $obs->detectedAtomic,
                'confirmed_atomic' => $obs->confirmedAtomic,
                'confirmations' => $obs->confirmations,
                'status' => $status,
                'last_checked_at' => now(),
                'meta' => array_merge($p->meta ?? [], $obs->txids ? ['txids' => $obs->txids] : []) ?: null,
            ]);
            if ($status === Payment::CONFIRMED) {
                $p->confirmed_at = now();
                $markPaidFor = $p->order_id;
            }
            $p->save();

            if ($status !== $previous) {
                PaymentLogger::info('Payment status changed', [
                    'payment_id' => $p->id, 'order_id' => $p->order_id, 'from' => $previous, 'to' => $status,
                    'expected_atomic' => $expected, 'detected_atomic' => $obs->detectedAtomic,
                    'confirmed_atomic' => $obs->confirmedAtomic, 'confirmations' => $obs->confirmations,
                    'overpaid_atomic' => $status === Payment::CONFIRMED ? max(0, $obs->confirmedAtomic - $expected) : 0,
                ]);
            }

            return $p;
        });

        if ($markPaidFor !== null) {
            // Idempotent: a no-op if the order is no longer pending (legacy markPaid behaviour).
            $before = Order::findOrFail($markPaidFor);
            $after = $this->lifecycle->markPaid($before);
            if ($before->status !== Order::STATUS_PENDING_PAYMENT) {
                // Funds arrived for an order that was already cancelled/paid: needs manual reconciliation.
                PaymentLogger::warning('Payment confirmed for an order that is not awaiting payment', [
                    'payment_id' => $payment->id, 'order_id' => $markPaidFor, 'order_status' => $before->status,
                ]);
            }
            PaymentLogger::info('Order marked paid', ['payment_id' => $payment->id, 'order_id' => $markPaidFor]);
        }

        return $payment;
    }

    /** Payments that still need watching: open ones, plus expired ones within the late-payment grace. */
    public function pollable()
    {
        $grace = now()->subSeconds((int) config('payments.late_grace_seconds'));

        return Payment::query()->where(function ($q) use ($grace) {
            $q->whereIn('status', [Payment::PENDING, Payment::DETECTED, Payment::UNDERPAID])
                ->orWhere(fn ($e) => $e->where('status', Payment::EXPIRED)->where('expires_at', '>', $grace));
        });
    }

    private function minConfirmations(string $method): int
    {
        return max(1, (int) config("payments.$method.min_confirmations", 1));
    }
}
