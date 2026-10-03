<?php

/*
| Search-engine settings. The sitemap is cached for `cache_ttl` seconds and split into chunks of
| `sitemap_chunk` URLs (protocol limit is 50 000 per file) once the site grows past one file.
*/
return [
    'sitemap_chunk' => (int) env('SITEMAP_CHUNK', 5000),
    'sitemap_max_urls' => 50000,
    'cache_ttl' => 3600,
    'legal_updated' => '2026-10-04',
];
