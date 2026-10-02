<?php

namespace App\Modules\Orders\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Events\OrderCancelled;
use App\Modules\Orders\Events\OrderCompleted;
use App\Modules\Orders\Events\OrderPaid;
use App\Modules\Orders\Events\OrderRefunded;
use App\Modules\Orders\Events\OrderShipped;
use App\Modules\Orders\Exceptions\OrderException;
use App\Modules\Orders\Models\Order;
use Illuminate\Support\Facades\DB;

/**
 * The only place an order's status / escrow / shipment fields change.
 * Mirrors the legacy Transaction model: pending -> held -> released | refunded.
 *
 * Every method locks the row and re-checks state, so a repeated or concurrent call can
 * never double-apply (e.g. a payment webhook delivered twice).
 */
class OrderLifecycle
{
    /**
     * Funds arrived: move them into escrow. Called by the Payments module, never by the API.
     * Idempotent: an order that is no longer pending is returned untouched (legacy behaviour).
     */
    public function markPaid(Order $order): Order
    {
        $paid = $this->locked($order, function (Order $o) {
            if ($o->escrow_status !== Order::ESCROW_PENDING || $o->status !== Order::STATUS_PENDING_PAYMENT) {
                return [$o, false];
            }
            $o->forceFill([
                'escrow_status' => Order::ESCROW_HELD,
                'status' => Order::STATUS_PAID,
                'paid_at' => now(),
            ])->save();

            return [$o, true];
        });

        [$fresh, $changed] = $paid;
        if ($changed) {
            event(new OrderPaid($fresh));
        }

        return $fresh;
    }

    /** Vendor ships a paid order. */
    public function ship(Order $order, string $trackingNumber): Order
    {
        [$fresh] = $this->locked($order, function (Order $o) use ($trackingNumber) {
            if ($o->escrow_status !== Order::ESCROW_HELD || $o->shipment_status !== Order::SHIPMENT_PENDING) {
                throw new OrderException('Only a paid order that has not shipped can be shipped.');
            }
            $o->forceFill([
                'shipment_status' => Order::SHIPMENT_SHIPPED,
                'status' => Order::STATUS_SHIPPED,
                'tracking_number' => $trackingNumber,
                'shipped_at' => now(),
            ])->save();

            return [$o, true];
        });

        event(new OrderShipped($fresh));

        return $fresh;
    }

    /** Buyer confirms receipt: escrow is released to the vendor. */
    public function confirmReceipt(Order $order): Order
    {
        [$fresh] = $this->locked($order, function (Order $o) {
            if ($o->escrow_status !== Order::ESCROW_HELD || $o->shipment_status !== Order::SHIPMENT_SHIPPED) {
                throw new OrderException('Receipt can only be confirmed for a shipped order whose funds are held.');
            }
            $o->forceFill([
                'escrow_status' => Order::ESCROW_RELEASED,
                'shipment_status' => Order::SHIPMENT_DELIVERED,
                'status' => Order::STATUS_COMPLETED,
                'confirmed_at' => now(),
            ])->save();

            return [$o, true];
        });

        event(new OrderCompleted($fresh));

        return $fresh;
    }

    /** Buyer cancels before paying: stock is returned. */
    public function cancel(Order $order): Order
    {
        [$fresh] = $this->locked($order, function (Order $o) {
            if ($o->status !== Order::STATUS_PENDING_PAYMENT) {
                throw new OrderException('Only an order that is awaiting payment can be cancelled.');
            }
            $o->forceFill(['status' => Order::STATUS_CANCELLED, 'cancelled_at' => now()])->save();

            foreach ($o->items as $item) {
                if ($item->product_id) {
                    Product::whereKey($item->product_id)->increment('stock', $item->quantity);
                }
            }

            return [$o, true];
        });

        event(new OrderCancelled($fresh));

        return $fresh;
    }

    /** Admin/dispute outcome: return held funds to the buyer. */
    public function refund(Order $order): Order
    {
        [$fresh] = $this->locked($order, function (Order $o) {
            if ($o->escrow_status !== Order::ESCROW_HELD) {
                throw new OrderException('Only an order whose funds are held can be refunded.');
            }
            $o->forceFill(['escrow_status' => Order::ESCROW_REFUNDED, 'status' => Order::STATUS_CANCELLED, 'cancelled_at' => now()])->save();

            return [$o, true];
        });

        event(new OrderRefunded($fresh));

        return $fresh;
    }

    /**
     * Run $change on a row-locked fresh copy of the order, inside a transaction.
     *
     * @param  callable(Order): array{0: Order, 1: bool}  $change
     * @return array{0: Order, 1: bool}
     */
    private function locked(Order $order, callable $change): array
    {
        return DB::transaction(function () use ($order, $change) {
            $fresh = Order::with('items')->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            return $change($fresh);
        });
    }
}
