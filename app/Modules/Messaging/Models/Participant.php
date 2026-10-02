<?php

namespace App\Modules\Messaging\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Participant extends Model
{
    protected $table = 'conversation_participants';

    protected $fillable = ['conversation_id', 'user_id'];

    protected function casts(): array
    {
        return ['last_read_at' => 'datetime', 'last_read_message_id' => 'integer'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
