<?php

namespace App\Modules\Payments\Models;

use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    public const PENDING = 'pending';
    public const DETECTED = 'detected';
    public const CONFIRMED = 'confirmed';
    public const EXPIRED = 'expired';
    public const UNDERPAID = 'underpaid';

    protected $fillable = [];

    protected $hidden = ['address'];

    protected function casts(): array
    {
        return [
            'address' => 'encrypted',
            'derivation_index' => 'integer',
            'expected_atomic' => 'integer',
            'detected_atomic' => 'integer',
            'confirmed_atomic' => 'integer',
            'confirmations' => 'integer',
            'meta' => 'array',
            'expires_at' => 'datetime',
            'last_checked_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function isFinal(): bool
    {
        return $this->status === self::CONFIRMED;
    }
}
