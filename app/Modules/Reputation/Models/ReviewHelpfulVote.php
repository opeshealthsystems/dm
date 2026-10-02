<?php

namespace App\Modules\Reputation\Models;

use Illuminate\Database\Eloquent\Model;

class ReviewHelpfulVote extends Model
{
    protected $fillable = ['review_id', 'user_id'];
}
