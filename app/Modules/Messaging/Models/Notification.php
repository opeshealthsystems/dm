<?php

namespace App\Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;

class Notification extends Model
{
    protected $table = 'notifications';

    protected $fillable = ['user_id', 'type', 'title', 'body', 'order_id', 'data'];

    protected function casts(): array
    {
        return ['data' => 'array', 'read_at' => 'datetime'];
    }
}
