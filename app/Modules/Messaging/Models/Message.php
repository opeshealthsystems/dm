<?php

namespace App\Modules\Messaging\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Message extends Model
{
    public const TYPE_USER = 'user';
    public const TYPE_SYSTEM = 'system';

    protected $fillable = ['conversation_id', 'sender_id', 'type', 'body'];

    protected function casts(): array
    {
        return ['body' => 'encrypted'];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
