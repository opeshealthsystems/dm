<?php

namespace App\Modules\DeveloperPlatform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WebhookEndpoint extends Model
{
    public const EVENTS = ['order.placed', 'order.paid', 'order.shipped', 'order.completed', 'order.cancelled', 'order.refunded'];

    protected $fillable = ['user_id', 'url', 'secret', 'events', 'active'];
    protected $hidden = ['secret'];

    protected function casts(): array
    {
        return ['secret' => 'encrypted', 'events' => 'array', 'active' => 'boolean'];
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }
}
