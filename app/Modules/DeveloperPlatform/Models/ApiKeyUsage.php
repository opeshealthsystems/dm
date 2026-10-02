<?php

namespace App\Modules\DeveloperPlatform\Models;

use Illuminate\Database\Eloquent\Model;

class ApiKeyUsage extends Model
{
    public $timestamps = false;
    protected $fillable = ['api_key_id', 'day', 'requests'];
}
