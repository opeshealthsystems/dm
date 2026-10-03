<?php

namespace App\Modules\Payments\Gateways\Monero;

/** Port of legacy api/monero_health.php (RPC reachability + rate cache freshness). */
class MoneroHealth
{
    public function __construct(private readonly MoneroRpc $rpc, private readonly MoneroRate $rate, private readonly array $config)
    {
    }

    public function check(): array
    {
        $checks = [];
        try {
            $info = $this->rpc->call('get_version');
            $checks['rpc'] = ['status' => 'pass', 'message' => 'RPC is reachable', 'version' => $info['version'] ?? null];
        } catch (\Throwable $e) {
            $checks['rpc'] = ['status' => 'fail', 'message' => 'RPC is unreachable'];
        }
        $age = $this->rate->cacheAge();
        $fresh = $age !== null && $age < (int) $this->config['exchange_rate_cache_ttl'];
        $checks['exchange_rate_cache'] = ['status' => $fresh ? 'pass' : 'warn', 'age_seconds' => $age,
            'message' => $fresh ? 'Cache is fresh' : 'Cache is stale or missing'];

        return ['status' => $checks['rpc']['status'] === 'pass' ? 'healthy' : 'unhealthy',
            'timestamp' => now()->toIso8601String(), 'checks' => $checks];
    }
}
