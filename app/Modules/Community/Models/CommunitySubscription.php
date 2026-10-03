<?php

namespace App\Modules\Community\Models;

use Illuminate\Database\Eloquent\Model;

class CommunitySubscription extends Model
{
    protected $table = 'community_subscriptions';

    protected $fillable = ['thread_id', 'user_id'];
}
