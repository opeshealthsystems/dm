<?php

namespace App\Modules\Escrow\Models;

use App\Models\User;
use App\Modules\Orders\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Dispute extends Model
{
    public const OPEN = 'open';
    public const RESOLVED = 'resolved';

    public const OUTCOME_BUYER = 'buyer';
    public const OUTCOME_VENDOR = 'vendor';

    // Status/outcome change only through DisputeService.
    protected $fillable = [];

    protected function casts(): array
    {
        return ['reason' => 'encrypted', 'resolution' => 'encrypted', 'resolved_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function opener(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opened_by');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(DisputeMessage::class)->orderBy('id');
    }

    public function involves(User $user): bool
    {
        return $user->id === $this->opened_by || $user->id === $this->against_user;
    }
}
