<?php

namespace App\Modules\Wallet\Actions;

use App\Modules\Escrow\Actions\FeeSchedule;
use App\Modules\Orders\Models\Order;
use App\Modules\Wallet\Models\WalletEntry;
use Illuminate\Support\Facades\DB;

/**
 * Escrow release -> vendor wallet. Legacy `confirmReceipt` credited the vendor with the order
 * amount; the platform commission (legacy `platform_fee_percent`, 5% default) is deducted from it.
 *
 * Idempotent per order: keys `sale:{order}` and `fee:{order}`, so replaying OrderCompleted
 * (or racing two workers) credits exactly once.
 */
class CreditVendorForOrder
{
    public function __construct(private readonly Ledger $ledger, private readonly FeeSchedule $fees)
    {
    }

    /** @return bool true when this call credited the vendor, false when it was already credited */
    public function handle(Order $order): bool
    {
        if ($order->escrow_status !== Order::ESCROW_RELEASED) {
            return false;
        }

        return DB::transaction(function () use ($order) {
            // Lock first so the tier lookup (lifetime sales) cannot race another credit.
            $this->ledger->lockAccount($order->vendor_id, $order->currency);

            [, $created] = $this->ledger->post(
                $order->vendor_id, $order->currency, WalletEntry::SALE_CREDIT,
                (int) $order->subtotal_cents, "sale:{$order->id}", orderId: $order->id,
                description: "Sale, order {$order->number}",
            );
            if (! $created) {
                return false;
            }

            $lifetime = (int) WalletEntry::where([
                'user_id' => $order->vendor_id, 'currency' => $order->currency, 'type' => WalletEntry::SALE_CREDIT,
            ])->where('order_id', '!=', $order->id)->sum('amount_cents');

            $fee = $this->fees->commissionCents((int) $order->subtotal_cents, $this->fees->commissionBps($lifetime));
            if ($fee > 0) {
                $this->ledger->post(
                    $order->vendor_id, $order->currency, WalletEntry::PLATFORM_FEE,
                    -$fee, "fee:{$order->id}", orderId: $order->id,
                    description: "Platform commission, order {$order->number}",
                );
            }

            return true;
        });
    }
}
