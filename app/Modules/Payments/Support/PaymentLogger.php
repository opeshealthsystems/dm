<?php

namespace App\Modules\Payments\Support;

use Illuminate\Support\Facades\Log;

/**
 * Audit log for every money event (legacy PaymentLogger -> storage/logs/payments-*.log).
 * Legacy rule: log only payment events, never keys; addresses are masked (legacy logged the
 * derivation index only). Context keys that could leak secrets are stripped.
 */
final class PaymentLogger
{
    private const FORBIDDEN = ['xpub', 'zpub', 'token', 'key', 'seed', 'address'];

    public static function info(string $message, array $context = []): void
    {
        Log::channel('payments')->info($message, self::clean($context));
    }

    public static function warning(string $message, array $context = []): void
    {
        Log::channel('payments')->warning($message, self::clean($context));
    }

    public static function error(string $message, array $context = []): void
    {
        Log::channel('payments')->error($message, self::clean($context));
    }

    /** bc1qab...wxyz style fingerprint; never the full address. */
    public static function mask(?string $address): string
    {
        if ($address === null || strlen($address) < 12) {
            return '***';
        }

        return substr($address, 0, 6) . '...' . substr($address, -4);
    }

    private static function clean(array $context): array
    {
        foreach ($context as $k => $v) {
            if (in_array(strtolower((string) $k), self::FORBIDDEN, true)) {
                unset($context[$k]);
            }
        }

        return $context;
    }
}
