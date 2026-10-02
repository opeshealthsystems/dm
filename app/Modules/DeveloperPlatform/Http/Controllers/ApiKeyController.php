<?php

namespace App\Modules\DeveloperPlatform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\DeveloperPlatform\Actions\ApiKeyService;
use App\Modules\DeveloperPlatform\Http\Requests\StoreApiKeyRequest;
use App\Modules\DeveloperPlatform\Models\ApiKey;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApiKeyController extends Controller
{
    /** List your API keys (never includes the secret; only the prefix). */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => ApiKey::where('user_id', $request->user()->id)->latest('id')->get()]);
    }

    /**
     * Create an API key.
     *
     * Scopes are limited to `catalog:read` and `orders:read`. The full key (`dm_live_...`) is
     * returned ONCE in `key`; only a hash is stored.
     */
    public function store(StoreApiKeyRequest $request, ApiKeyService $keys): JsonResponse
    {
        [$key, $plain] = $keys->create(
            $request->user(), $request->validated('name'), $request->validated('scopes'),
            $request->validated('expires_at') ? new \DateTimeImmutable($request->validated('expires_at')) : null,
        );

        return response()->json(['data' => $key, 'key' => $plain], 201);
    }

    /** Revoke an API key immediately. */
    public function destroy(ApiKey $key, ApiKeyService $keys): JsonResponse
    {
        $this->authorize('manage', $key);

        return response()->json(['data' => $keys->revoke($key)]);
    }
}
