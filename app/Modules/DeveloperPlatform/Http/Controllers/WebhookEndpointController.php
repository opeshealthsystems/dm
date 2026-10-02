<?php

namespace App\Modules\DeveloperPlatform\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Modules\DeveloperPlatform\Http\Requests\WebhookEndpointRequest;
use App\Modules\DeveloperPlatform\Models\WebhookEndpoint;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class WebhookEndpointController extends Controller
{
    /** List your webhook endpoints. */
    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => WebhookEndpoint::where('user_id', $request->user()->id)->latest('id')->get()]);
    }

    /**
     * Register a webhook endpoint.
     *
     * Returns the signing `secret` ONCE. Each POST carries `X-DM-Signature: t=<unix>,v1=<hex>`
     * where hex = HMAC-SHA256(secret, "<t>.<raw body>"). Failed deliveries are retried with
     * exponential backoff. Events: order.placed|paid|shipped|completed|cancelled|refunded.
     */
    public function store(WebhookEndpointRequest $request): JsonResponse
    {
        $secret = 'whsec_' . Str::random(40);
        $endpoint = WebhookEndpoint::create($request->safe()->only(['url', 'events']) + [
            'user_id' => $request->user()->id, 'secret' => $secret, 'active' => $request->boolean('active', true),
        ])->refresh();

        return response()->json(['data' => $endpoint, 'secret' => $secret], 201);
    }

    /** Show one of your webhook endpoints. */
    public function show(WebhookEndpoint $endpoint): JsonResponse
    {
        $this->authorize('manage', $endpoint);

        return response()->json(['data' => $endpoint]);
    }

    /** Update a webhook endpoint (url, events, active). */
    public function update(WebhookEndpointRequest $request, WebhookEndpoint $endpoint): JsonResponse
    {
        $this->authorize('manage', $endpoint);
        $endpoint->update($request->validated());

        return response()->json(['data' => $endpoint->refresh()]);
    }

    /** Delete a webhook endpoint and its delivery log. */
    public function destroy(WebhookEndpoint $endpoint): JsonResponse
    {
        $this->authorize('manage', $endpoint);
        $endpoint->delete();

        return response()->json(['message' => 'Deleted.']);
    }

    /** Delivery log for an endpoint (newest first, paginated). */
    public function deliveries(Request $request, WebhookEndpoint $endpoint): JsonResponse
    {
        $this->authorize('manage', $endpoint);

        return response()->json($endpoint->deliveries()->latest('id')->paginate(min($request->integer('per_page', 20), 100)));
    }
}
