<?php

namespace App\Modules\DeveloperPlatform\Jobs;

use App\Modules\DeveloperPlatform\Actions\WebhookSigner;
use App\Modules\DeveloperPlatform\Models\WebhookDelivery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;

/** POSTs one signed delivery. Any non-2xx or network error is retried with exponential backoff. */
class DeliverWebhook implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 6;

    public function __construct(public int $deliveryId)
    {
    }

    /** @return list<int> seconds: 10s, 1m, 5m, 30m, 2h */
    public function backoff(): array
    {
        return [10, 60, 300, 1800, 7200];
    }

    public function handle(): void
    {
        $delivery = WebhookDelivery::with('endpoint')->find($this->deliveryId);
        if (! $delivery || $delivery->status === 'delivered' || ! $delivery->endpoint) {
            return;
        }
        $endpoint = $delivery->endpoint;
        $body = json_encode($delivery->payload, JSON_UNESCAPED_SLASHES);
        $ts = time();
        $delivery->increment('attempts');

        // Re-check at send time (DNS may have changed since saving: rebinding); never follow redirects.
        if (\App\Modules\DeveloperPlatform\Support\WebhookUrlGuard::violation($endpoint->url) !== null) {
            $delivery->update(['status' => 'failed', 'response_code' => null, 'error' => 'Target URL is not allowed.']);

            return;
        }
        try {
            $response = Http::withOptions(['allow_redirects' => false])->withBody($body, 'application/json')->timeout(10)->withHeaders([
                'X-DM-Event' => $delivery->event,
                'X-DM-Delivery' => $delivery->event_id,
                'X-DM-Signature' => WebhookSigner::header($endpoint->secret, $ts, $body),
            ])->post($endpoint->url);
        } catch (\Throwable $e) {
            $delivery->update(['status' => 'retrying', 'response_code' => null, 'error' => 'Delivery failed (connection error).']);
            throw $e;
        }

        if ($response->successful()) {
            $delivery->update(['status' => 'delivered', 'response_code' => $response->status(), 'error' => null, 'delivered_at' => now()]);

            return;
        }
        $delivery->update(['status' => 'retrying', 'response_code' => $response->status(), 'error' => 'HTTP ' . $response->status()]);
        throw new \RuntimeException('Webhook returned HTTP ' . $response->status());
    }

    public function failed(\Throwable $e): void
    {
        WebhookDelivery::whereKey($this->deliveryId)->update(['status' => 'failed']);
    }
}
