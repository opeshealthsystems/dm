<?php

namespace App\Modules\Reputation\Models;

use App\Models\User;
use App\Modules\Catalog\Models\Product;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Review extends Model
{
    // Set explicitly by ReputationService only.
    protected $fillable = [];

    protected function casts(): array
    {
        return ['rating' => 'integer', 'helpful_count' => 'integer', 'vendor_replied_at' => 'datetime'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendor_id');
    }

    public function votes(): HasMany
    {
        return $this->hasMany(ReviewHelpfulVote::class);
    }
}
