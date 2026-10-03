<?php

namespace App\Modules\Wallet\Actions;

use App\Models\User;
use App\Modules\Escrow\Models\VendorFee;
use App\Modules\Orders\Models\Order;
use App\Modules\Wallet\Models\PayoutRequest;
use App\Modules\Wallet\Models\WalletAccount;
use App\Modules\Wallet\Models\WalletEntry;

/** Port of legacy Transaction::getVendorWalletStats, per currency, in integer cents. */
class WalletStats
{
    /** @return array<string, array<string, int>> keyed by currency */
    public function for(User $vendor): array
    {
        $out = [];
        $ensure = function (string $c) use (&$out) {
            $out[$c] ??= ['available_cents' => 0, 'total_sales_cents' => 0, 'sales_count' => 0,
                'commission_cents' => 0, 'pending_escrow_cents' => 0, 'payouts_requested_cents' => 0, 'fees_paid_cents' => 0];
        };

        foreach (WalletAccount::where('user_id', $vendor->id)->get() as $a) {
            $ensure($a->currency);
            $out[$a->currency]['available_cents'] = $a->balance_cents;
        }

        $entries = WalletEntry::where('user_id', $vendor->id)->selectRaw('currency, type, SUM(amount_cents) as s, COUNT(*) as c')
            ->groupBy('currency', 'type')->get();
        foreach ($entries as $e) {
            $ensure($e->currency);
            if ($e->type === WalletEntry::SALE_CREDIT) {
                $out[$e->currency]['total_sales_cents'] = (int) $e->s;
                $out[$e->currency]['sales_count'] = (int) $e->c;
            } elseif ($e->type === WalletEntry::PLATFORM_FEE) {
                $out[$e->currency]['commission_cents'] = -(int) $e->s;
            }
        }

        $paid = PayoutRequest::where('user_id', $vendor->id)->whereIn('status', ['pending', 'approved', 'paid'])
            ->selectRaw('currency, SUM(amount_cents) as s')->groupBy('currency')->get();
        foreach ($paid as $p) {
            $ensure($p->currency);
            $out[$p->currency]['payouts_requested_cents'] = (int) $p->s;
        }

        $held = Order::where(['vendor_id' => $vendor->id, 'escrow_status' => Order::ESCROW_HELD])
            ->selectRaw('currency, SUM(subtotal_cents) as s')->groupBy('currency')->get();
        foreach ($held as $h) {
            $ensure($h->currency);
            $out[$h->currency]['pending_escrow_cents'] = (int) $h->s;
        }

        $fees = VendorFee::where(['vendor_id' => $vendor->id, 'status' => 'paid'])
            ->selectRaw('currency, SUM(amount_cents) as s')->groupBy('currency')->get();
        foreach ($fees as $f) {
            $ensure($f->currency);
            $out[$f->currency]['fees_paid_cents'] = (int) $f->s;
        }

        return $out;
    }
}
