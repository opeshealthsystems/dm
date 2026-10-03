<?php

namespace App\Modules\Orders\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PAID = 'paid';
    public const STATUS_SHIPPED = 'shipped';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    /** Frozen by an open dispute; only an admin resolution (refund / releaseToVendor) moves it on. */
    public const STATUS_DISPUTED = 'disputed';

    public const ESCROW_PENDING = 'pending';
    public const ESCROW_HELD = 'held';
    public const ESCROW_RELEASED = 'released';
    public const ESCROW_REFUNDED = 'refunded';

    public const SHIPMENT_PENDING = 'pending';
    public const SHIPMENT_SHIPPED = 'shipped';
    public const SHIPMENT_DELIVERED = 'delivered';

    public const PAYMENT_METHODS = ['bitcoin', 'monero'];

    // Status and escrow fields are only ever changed by OrderLifecycle, never mass-assigned.
    protected $fillable = [];

    protected function casts(): array
    {
        return [
            'shipping_address' => 'encrypted',
            'subtotal_cents' => 'integer',
            'paid_at' => 'datetime',
            'shipped_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
