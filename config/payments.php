<?php

/*
| Payment settings. Secrets (xpub, API tokens) come from .env only; never hardcode them.
| Defaults mirror the legacy platform (config.php SiteSetting defaults).
*/
return [
    // Legacy PAYMENT_EXPIRY_SECONDS (default 2h) and BTC_MIN_CONFIRMATIONS (default 1).
    'expiry_seconds' => (int) env('PAYMENT_EXPIRY_SECONDS', 7200),
    // Legacy recycled unpaid addresses 24h after expiry; we keep polling that long for late payments.
    'late_grace_seconds' => (int) env('PAYMENT_LATE_GRACE_SECONDS', 86400),

    'bitcoin' => [
        'xpub' => env('BITCOIN_XPUB'),                       // xpub/zpub (public only)
        'blockcypher_token' => env('BLOCKCYPHER_TOKEN'),
        'blockcypher_base' => 'https://api.blockcypher.com/v1/btc/main',
        'min_confirmations' => (int) env('BTC_MIN_CONFIRMATIONS', 1),
        'fallback_rate' => (float) env('BTC_FALLBACK_RATE', 96500.00), // legacy emergency fallback (USD)
        'rate_fresh_seconds' => 3600,    // legacy: <1h cache returned immediately
        'rate_usable_seconds' => 21600,  // legacy: <6h cache still returned without refetch
        'rate_stale_seconds' => 604800,  // legacy: stale cache usable up to 7 days
    ],
];
