<?php

namespace App\Modules\DeveloperPlatform\Actions;

class WebhookSigner
{
    /** Signature over "{timestamp}.{body}", HMAC-SHA256, hex. */
    public static function sign(string $secret, int $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }

    /** Header value: `t=<unix>,v1=<hex>`. */
    public static function header(string $secret, int $timestamp, string $body): string
    {
        return 't=' . $timestamp . ',v1=' . self::sign($secret, $timestamp, $body);
    }
}
