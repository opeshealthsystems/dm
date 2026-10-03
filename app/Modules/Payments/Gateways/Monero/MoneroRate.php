<?php

namespace App\Modules\Payments\Gateways\Monero;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * XMR price in a fiat currency. Legacy order: HMAC-signed cache (served up to 6h old),
 * CoinGecko with a 3s timeout, then the configured fallback (USD only).
 */
class MoneroRate
{
    public function __construct(private readonly array $config)
    {
    }

    /** Fiat units per 1 XMR. */
    public function rate(string $currency = 'USD'): float
    {
        $cur = strtolower($currency);
        $key = 'monero:rate:'.$cur;
        $now = time();

        $cached = Cache::get($key);
        if (is_array($cached) && isset($cached['rate'], $cached['updated'], $cached['signature'])
            && hash_equals($this->sign($cached['rate'], $cached['updated']), (string) $cached['signature'])
            && ($now - $cached['updated']) < (int) $this->config['exchange_rate_stale_ttl']) {
            return (float) $cached['rate'];
        }

        try {
            $resp = Http::timeout((int) $this->config['rate_http_timeout'])
                ->get($this->config['rate_url'], ['ids' => 'monero', 'vs_currencies' => $cur]);
            $rate = $resp->successful() ? $resp->json("monero.$cur") : null;
            if (is_numeric($rate) && $rate > 0) {
                Cache::put($key, [
                    'rate' => $rate, 'updated' => $now, 'signature' => $this->sign($rate, $now),
                ], (int) $this->config['exchange_rate_stale_ttl']);

                return (float) $rate;
            }
        } catch (\Throwable $e) {
            Log::warning('Monero rate fetch failed: '.$e->getMessage());
        }

        if ($cur === 'usd') {
            Log::error('Failed to fetch Monero exchange rate, using fallback');

            return (float) $this->config['exchange_rate_fallback'];
        }
        throw new MoneroException("No XMR rate available for {$currency}");
    }

    /** Age in seconds of the cached rate, or null. Used by health. */
    public function cacheAge(string $currency = 'USD'): ?int
    {
        $c = Cache::get('monero:rate:'.strtolower($currency));

        return is_array($c) && isset($c['updated']) ? time() - $c['updated'] : null;
    }

    private function sign(mixed $rate, mixed $updated): string
    {
        $key = $this->config['cache_integrity_key'] ?: (string) config('app.key');

        return hash_hmac('sha256', $rate.$updated, $key);
    }
}
