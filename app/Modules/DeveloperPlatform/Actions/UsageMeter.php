<?php

namespace App\Modules\DeveloperPlatform\Actions;

use App\Modules\DeveloperPlatform\Models\ApiKey;
use App\Modules\DeveloperPlatform\Models\ApiKeyUsage;
use Illuminate\Database\QueryException;

class UsageMeter
{
    public function record(ApiKey $key): void
    {
        $day = now()->toDateString();
        $q = fn () => ApiKeyUsage::where('api_key_id', $key->id)->where('day', $day);

        if ($q()->increment('requests') > 0) {
            return;
        }
        try {
            ApiKeyUsage::create(['api_key_id' => $key->id, 'day' => $day, 'requests' => 1]);
        } catch (QueryException) {
            $q()->increment('requests'); // lost a race with a concurrent first request
        }
    }
}
