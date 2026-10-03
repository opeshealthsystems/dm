<?php

namespace App\Modules\DeveloperPlatform\Support;

/**
 * SSRF guard for webhook targets. A URL is safe only if its scheme is http(s), it has no
 * credentials, it uses a normal port, and EVERY address its host resolves to is public
 * (no loopback, private, link-local/metadata, reserved, IPv6 ULA / mapped-private ranges).
 * Called at save time and again at delivery time (DNS may change in between: rebinding).
 * Redirects are never followed by the delivery job.
 */
final class WebhookUrlGuard
{
    /** @var (callable(string): list<string>)|null test hook replacing DNS */
    public static $resolver = null;

    /** Returns null when safe, otherwise a short reason. */
    public static function violation(string $url): ?string
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['host']) || empty($parts['scheme'])) {
            return 'invalid_url';
        }
        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, ['http', 'https'], true)) {
            return 'scheme';
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            return 'credentials';
        }
        if (isset($parts['port']) && ! in_array((int) $parts['port'], [80, 443, 8080, 8443], true)) {
            return 'port';
        }
        if (app()->isProduction() && $scheme !== 'https') {
            return 'https_required';
        }
        $host = strtolower(trim($parts['host'], '[]'));
        if ($host === 'localhost' || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal') || ! str_contains($host, '.') && ! str_contains($host, ':')) {
            return 'internal_host';
        }
        $ips = self::resolve($host);
        if ($ips === []) {
            return 'unresolvable';
        }
        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                return 'private_address';
            }
        }

        return null;
    }

    /** @return list<string> */
    private static function resolve(string $host): array
    {
        $norm = self::normaliseIpLiteral($host);
        if ($norm !== null) {
            return [$norm];
        }
        if (self::$resolver !== null) {
            return (self::$resolver)($host);
        }
        $ips = [];
        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $r) {
            $ips[] = $r['ip'] ?? $r['ipv6'] ?? null;
        }

        return array_values(array_filter($ips));
    }

    /** Handles dotted, decimal, hex and octal IPv4 literals that parsers like curl accept. */
    private static function normaliseIpLiteral(string $host): ?string
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return $host;
        }
        if (preg_match('/^(0x[0-9a-f]+|\d+)(\.(0x[0-9a-f]+|\d+)){0,3}$/i', $host)) {
            $nums = array_map(fn ($p) => stripos($p, '0x') === 0 ? hexdec($p) : (preg_match('/^0\d+$/', $p) ? octdec($p) : (int) $p), explode('.', $host));
            $n = count($nums);
            $val = 0;
            for ($i = 0; $i < $n - 1; $i++) {
                $val += $nums[$i] << (8 * (3 - $i));
            }
            $val += $nums[$n - 1];

            return long2ip($val & 0xFFFFFFFF) ?: '0.0.0.0';
        }

        return null;
    }

    public static function isPublicIp(string $ip): bool
    {
        if (! filter_var($ip, FILTER_VALIDATE_IP)) {
            return false;
        }
        if (str_contains($ip, ':')) {
            $bin = inet_pton($ip);
            // IPv4-mapped (::ffff:a.b.c.d) and NAT64: judge the embedded IPv4 address.
            if (strncmp($bin, str_repeat("\0", 10) . "\xff\xff", 12) === 0) {
                return self::isPublicIp(inet_ntop(substr($bin, 12)));
            }
            if (strncmp($bin, hex2bin('0064ff9b0000000000000000'), 12) === 0) {
                return self::isPublicIp(inet_ntop(substr($bin, 12)));
            }
            $first = ord($bin[0]);
            if (($first & 0xFE) === 0xFC || ($first === 0xFE && (ord($bin[1]) & 0xC0) === 0x80)) {
                return false; // fc00::/7 ULA, fe80::/10 link-local
            }
        }

        return (bool) filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)
            && $ip !== '169.254.169.254';
    }
}
