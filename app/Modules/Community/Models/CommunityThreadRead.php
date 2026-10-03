<?php

namespace App\Modules\Community\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityThreadRead extends Model
{
    protected $table = 'community_thread_reads';

    protected $fillable = ['thread_id', 'user_id', 'last_read_post_id'];
}
