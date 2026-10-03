<?php

namespace App\Modules\Escrow\Models;

use Illuminate\Database\Eloquent\Model;

/** Commission tier: applies once a vendor's lifetime sales reach min_sales_cents. */
class VendorFeeTier extends Model
{
    protected $fillable = ['name', 'min_sales_cents', 'commission_bps'];

    protected function casts(): array
    {
        return ['min_sales_cents' => 'integer', 'commission_bps' => 'integer'];
    }
}
