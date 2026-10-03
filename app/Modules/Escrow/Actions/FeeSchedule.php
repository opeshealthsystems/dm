<?php

namespace App\Modules\Escrow\Actions;

use App\Modules\Escrow\Models\VendorFeeTier;

/**
 * Platform commission rules. Legacy had one global `platform_fee_percent` (default 5%);
 * tiers extend that: the tier with the highest `min_sales_cents` not above the vendor's lifetime
 * sales sets the rate, falling back to `marketplace.default_commission_bps`.
 */
class FeeSchedule
{
    /** Commission in basis points (500 = 5%). */
    public function commissionBps(int $lifetimeSalesCents): int
    {
        $tier = VendorFeeTier::where('min_sales_cents', '<=', $lifetimeSalesCents)
            ->orderByDesc('min_sales_cents')->first();

        return $tier ? $tier->commission_bps : (int) config('marketplace.default_commission_bps');
    }

    /** Integer commission, rounded half up (never a float amount). */
    public function commissionCents(int $grossCents, int $bps): int
    {
        return intdiv($grossCents * $bps + 5000, 10000);
    }
}
