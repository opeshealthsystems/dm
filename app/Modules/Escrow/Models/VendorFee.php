<?php

namespace App\Modules\Escrow\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Legacy vendor_fees row: a one-off entry bond or yearly bond, unpaid until an admin confirms payment. */
class VendorFee extends Model
{
    public const ENTRY_FEE = 'entry_fee';
    public const YEARLY_BOND = 'yearly_bond';
    public const TYPES = [self::ENTRY_FEE, self::YEARLY_BOND];

    public const UNPAID = 'unpaid';
    public const PAID = 'paid';

    protected $fillable = [];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'paid_at' => 'datetime'];
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }
}
