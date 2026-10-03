<?php

// Bearer-token API: no credentialed cross-origin access. Set CORS_ALLOWED_ORIGINS (comma separated)
// to the first-party origins that need browser access; empty means same-origin only.
return [
    'paths' => ['api/*', 'oauth/token'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Authorization', 'Content-Type', 'Accept', 'X-Requested-With'],
    'exposed_headers' => [],
    'max_age' => 600,
    'supports_credentials' => false,
];
