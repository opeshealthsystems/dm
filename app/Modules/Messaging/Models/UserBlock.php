<?php

namespace App\Modules\Messaging\Models;

use Illuminate\Database\Eloquent\Model;

class UserBlock extends Model
{
    protected $fillable = ['blocker_id', 'blocked_id'];

    /** True when either user has blocked the other. */
    public static function between(int $a, int $b): bool
    {
        return static::where(fn ($q) => $q->where('blocker_id', $a)->where('blocked_id', $b))
            ->orWhere(fn ($q) => $q->where('blocker_id', $b)->where('blocked_id', $a))
            ->exists();
    }
}
