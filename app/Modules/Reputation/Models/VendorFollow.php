<?php

namespace App\Modules\Reputation\Models;

use Illuminate\Database\Eloquent\Model;

class VendorFollow extends Model
{
    protected $fillable = ['follower_id', 'vendor_id'];
}
