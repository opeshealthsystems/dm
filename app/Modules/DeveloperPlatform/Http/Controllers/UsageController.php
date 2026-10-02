<?php

namespace App\Modules\DeveloperPlatform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\DeveloperPlatform\Models\ApiKeyUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UsageController extends Controller
{
    /**
     * API usage per key per day for your keys.
     *
     * Query: `days` (1-90, default 30).
     */
    public function index(Request $request): JsonResponse
    {
        $days = max(1, min($request->integer('days', 30), 90));
        $rows = ApiKeyUsage::query()
            ->join('api_keys', 'api_keys.id', '=', 'api_key_usages.api_key_id')
            ->where('api_keys.user_id', $request->user()->id)
            ->where('api_key_usages.day', '>=', now()->subDays($days - 1)->toDateString())
            ->orderBy('api_key_usages.day')
            ->get(['api_keys.id as api_key_id', 'api_keys.prefix', 'api_key_usages.day', 'api_key_usages.requests']);

        return response()->json(['data' => $rows, 'total_requests' => (int) $rows->sum('requests')]);
    }
}
