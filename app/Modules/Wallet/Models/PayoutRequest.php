<?php

namespace App\Modules\Wallet\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutRequest extends Model
{
    public const PENDING = 'pending';
    public const APPROVED = 'approved';
    public const REJECTED = 'rejected';
    public const PAID = 'paid';

    public const METHODS = ['bitcoin', 'monero'];

    // Status fields change only through PayoutService.
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'destination_address' => 'encrypted',
            'amount_cents' => 'integer',
            'approved_at' => 'datetime',
            'paid_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
