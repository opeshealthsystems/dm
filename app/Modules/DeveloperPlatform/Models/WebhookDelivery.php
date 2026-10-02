<?php

namespace App\Modules\DeveloperPlatform\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookDelivery extends Model
{
    protected $fillable = ['webhook_endpoint_id', 'event_id', 'event', 'payload', 'status', 'attempts', 'response_code', 'error', 'delivered_at'];

    protected function casts(): array
    {
        return ['payload' => 'array', 'delivered_at' => 'datetime'];
    }

    public function endpoint(): BelongsTo
    {
        return $this->belongsTo(WebhookEndpoint::class, 'webhook_endpoint_id');
    }
}
