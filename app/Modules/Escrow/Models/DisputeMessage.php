<?php

namespace App\Modules\Escrow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DisputeMessage extends Model
{
    public const KINDS = ['message', 'evidence'];

    protected $fillable = [];

    protected function casts(): array
    {
        return ['body' => 'encrypted'];
    }

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }
}
