<?php

namespace App\Modules\Payments\Gateways\Monero;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** monero-wallet-rpc JSON-RPC client: digest auth, timeout, retry with exponential backoff (legacy makeRpcRequest). */
class MoneroRpc
{
    public function __construct(private readonly array $config)
    {
    }

    public function call(string $method, array $params = []): array
    {
        $url = $this->config['rpc_url'] ?? '';
        if ($url === '') {
            throw new MoneroException('MONERO_WALLET_RPC_URL not configured');
        }
        $retries = max(1, (int) ($this->config['retry_attempts'] ?? 3));
        $delayMs = (int) ($this->config['retry_delay_ms'] ?? 1000);
        $last = null;

        for ($attempt = 1; $attempt <= $retries; $attempt++) {
            try {
                $req = Http::timeout((int) ($this->config['rpc_timeout'] ?? 30))->acceptJson()->asJson();
                if (! empty($this->config['rpc_username']) && ! empty($this->config['rpc_password'])) {
                    $req = $req->withDigestAuth($this->config['rpc_username'], $this->config['rpc_password']);
                }
                $response = $req->post($url, ['jsonrpc' => '2.0', 'id' => '0', 'method' => $method, 'params' => (object) $params]);

                if ($response->status() !== 200) {
                    throw new MoneroException("HTTP Error: Status {$response->status()}");
                }
                $json = $response->json();
                if (! is_array($json)) {
                    throw new MoneroException('Invalid JSON from wallet RPC');
                }
                if (isset($json['error'])) {
                    throw new MoneroException('RPC Error: '.json_encode($json['error']));
                }

                return (array) ($json['result'] ?? []);
            } catch (\Throwable $e) {
                $last = $e;
                Log::warning("Monero RPC retry {$attempt}/{$retries} method={$method}: ".$e->getMessage());
                if ($attempt < $retries && $delayMs > 0) {
                    usleep((2 ** $attempt) * $delayMs * 1000);
                }
            }
        }

        Log::error('Monero RPC FAILED', ['method' => $method, 'error' => $last?->getMessage()]);
        throw new MoneroException("Monero RPC Error ($method): ".$last?->getMessage(), 0, $last);
    }
}
