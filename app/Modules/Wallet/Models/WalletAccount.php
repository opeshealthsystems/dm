<?php

namespace App\Modules\Wallet\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Cached balance. Only {@see \App\Modules\Wallet\Actions\Ledger} changes it, under a row lock. */
class WalletAccount extends Model
{
    protected $fillable = [];

    protected function casts(): array
    {
        return ['balance_cents' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
