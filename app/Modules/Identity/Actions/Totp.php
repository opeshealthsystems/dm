<?php

namespace App\Modules\Identity\Actions;

/**
 * RFC 6238 TOTP (HMAC-SHA1, 30 second step, 6 digits) with RFC 4648 base32 secrets.
 * Pure functions, no state: replay protection lives in TwoFactorAuthentication.
 */
class Totp
{
    public const STEP = 30;
    public const DIGITS = 6;
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** A new 160-bit secret, base32 encoded (32 characters). */
    public function generateSecret(): string
    {
        return $this->base32Encode(random_bytes(20));
    }

    public function timeStep(?int $time = null): int
    {
        return intdiv($time ?? \Illuminate\Support\Carbon::now()->getTimestamp(), self::STEP);
    }

    public function code(string $secret, int $step): string
    {
        $key = $this->base32Decode($secret);
        $hash = hash_hmac('sha1', pack('J', $step), $key, true);
        $offset = ord($hash[19]) & 0x0F;
        $bin = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($bin % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    /**
     * Find the time-step that `$code` matches within +-$window steps of now, or null.
     * Every candidate is compared in constant time so timing does not leak which step matched.
     */
    public function matchingStep(string $secret, string $code, int $window = 1, ?int $time = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (! preg_match('/^\d{6}$/', $code)) {
            return null;
        }
        $now = $this->timeStep($time);
        $found = null;
        for ($step = $now - $window; $step <= $now + $window; $step++) {
            if (hash_equals($this->code($secret, $step), $code)) {
                $found = $step;
            }
        }

        return $found;
    }

    public function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/' . rawurlencode($issuer . ':' . $account)
            . '?secret=' . $secret . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::STEP;
    }

    public function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $c) {
            $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public function base32Decode(string $text): string
    {
        $bits = '';
        foreach (str_split(strtoupper(rtrim($text, '='))) as $c) {
            $i = strpos(self::ALPHABET, $c);
            if ($i === false) {
                continue;
            }
            $bits .= str_pad(decbin($i), 5, '0', STR_PAD_LEFT);
        }
        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
