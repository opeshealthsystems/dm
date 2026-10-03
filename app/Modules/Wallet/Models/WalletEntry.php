<?php

namespace App\Modules\Wallet\Models;

use Illuminate\Database\Eloquent\Model;
use LogicException;

/** One immutable ledger line. Updates and deletes are refused. */
class WalletEntry extends Model
{
    public const UPDATED_AT = null;

    public const SALE_CREDIT = 'sale_credit';
    public const PLATFORM_FEE = 'platform_fee';
    public const PAYOUT_DEBIT = 'payout_debit';
    public const PAYOUT_REVERSAL = 'payout_reversal';

    protected $fillable = [];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'balance_after_cents' => 'integer', 'created_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Wallet entries are append-only.'));
        static::deleting(fn () => throw new LogicException('Wallet entries are append-only.'));
    }
}
