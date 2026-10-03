<?php

/*
 * Monero payment configuration. Structure follows the legacy platform's config/monero.php.
 * Every value comes from the environment; nothing secret is committed.
 */
return [
    'network' => env('MONERO_NETWORK', 'mainnet'),           // mainnet | stagenet | testnet
    'required_confirmations' => (int) env('MONERO_REQUIRED_CONFIRMATIONS', 10),
    'payment_expiration_hours' => (int) env('MONERO_PAYMENT_EXPIRATION_HOURS', 24),

    // monero-wallet-rpc (view-only wallet recommended). The subaddress per order is created here.
    'rpc_url' => env('MONERO_WALLET_RPC_URL', 'http://127.0.0.1:18083/json_rpc'),
    'rpc_username' => env('MONERO_RPC_USER', ''),
    'rpc_password' => env('MONERO_RPC_PASSWORD', ''),
    'rpc_timeout' => (int) env('MONERO_RPC_TIMEOUT', 30),
    'retry_attempts' => (int) env('MONERO_RETRY_ATTEMPTS', 3),
    'retry_delay_ms' => (int) env('MONERO_RETRY_DELAY_MS', 1000),   // exponential: 2^attempt * delay
    'account_index' => (int) env('MONERO_ACCOUNT_INDEX', 0),

    // XMR/fiat rate: cached copy served up to 6h, then CoinGecko (3s timeout), then fallback (USD).
    'rate_url' => env('MONERO_RATE_URL', 'https://api.coingecko.com/api/v3/simple/price'),
    'rate_http_timeout' => (int) env('MONERO_RATE_HTTP_TIMEOUT', 3),
    'exchange_rate_cache_ttl' => (int) env('MONERO_EXCHANGE_RATE_CACHE_TTL', 3600),
    'exchange_rate_stale_ttl' => (int) env('MONERO_EXCHANGE_RATE_STALE_TTL', 21600),
    'exchange_rate_fallback' => (float) env('MONERO_EXCHANGE_RATE_FALLBACK', 150.00),   // USD per XMR
    'cache_integrity_key' => env('MONERO_CACHE_INTEGRITY_KEY', ''),   // falls back to APP_KEY

    'fingerprint_chars' => (int) env('MONERO_FINGERPRINT_CHARS', 6),
];
