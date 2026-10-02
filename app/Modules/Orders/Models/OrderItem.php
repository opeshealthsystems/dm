<?php

namespace App\Modules\Orders\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $fillable = ['product_id', 'title', 'unit_price_cents', 'quantity', 'line_total_cents'];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
