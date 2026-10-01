<?php

declare(strict_types=1);

namespace Src;

/**
 * The address a request really comes from, for rate limits and IP bans.
 *
 * X-Forwarded-For, Client-IP and CF-Connecting-IP are ordinary request headers:
 * whoever sends the request can put anything in them. They are believed only when
 * the connection itself (REMOTE_ADDR) comes from a proxy listed in TRUSTED_PROXIES.
 * With nothing listed, the connection address is used.
 *
 * TRUSTED_PROXIES: comma-separated IPs and CIDR ranges. The word "cloudflare"
 * adds Cloudflare's published ranges (CLOUDFLARE_RANGES).
 */
final class ClientIp
{
    public const UNKNOWN = '0.0.0.0';

    /**
     * Cloudflare's published edge ranges, https://www.cloudflare.com/ips/
     * (fetched as ips-v4 / ips-v6). Check this list against that page before each
     * release that relies on it; Cloudflare changes it rarely but does change it.
     */
    public const CLOUDFLARE_RANGES = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
        '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
        '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
        '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
        '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    /** The client address for this request, using TRUSTED_PROXIES from the environment. */
    public static function fromGlobals(): string
    {
        $trusted = $_ENV['TRUSTED_PROXIES'] ?? getenv('TRUSTED_PROXIES');
        return self::resolve($_SERVER, is_string($trusted) ? $trusted : '');
    }

    /**
     * @param array<string, mixed> $server $_SERVER or a copy of it
     * @param string $trustedProxies the TRUSTED_PROXIES value
     */
    public static function resolve(array $server, string $trustedProxies): string
    {
        $remote = self::valid($server['REMOTE_ADDR'] ?? null) ?? self::UNKNOWN;
        $trusted = self::parseTrusted($trustedProxies);
        if ($trusted === [] || $remote === self::UNKNOWN || !self::isTrusted($remote, $trusted)) {
            return $remote;
        }

        $cf = self::valid($server['HTTP_CF_CONNECTING_IP'] ?? null);
        if ($cf !== null) {
            return $cf;
        }

        // Proxies append to X-Forwarded-For, so the right-most address that isn't one
        // of ours is the client. Anything further left was supplied by the client.
        $forwarded = is_string($server['HTTP_X_FORWARDED_FOR'] ?? null) ? $server['HTTP_X_FORWARDED_FOR'] : '';
        foreach (array_reverse(explode(',', $forwarded)) as $entry) {
            $ip = self::valid(trim($entry));
            if ($ip === null) {
                break;
            }
            if (!self::isTrusted($ip, $trusted)) {
                return $ip;
            }
        }
        return $remote;
    }

    /** True when $ip is $range (an IP) or falls inside it (a CIDR range). */
    public static function inRange(string $ip, string $range): bool
    {
        $ipBin = @inet_pton($ip);
        if ($ipBin === false) {
            return false;
        }
        if (!str_contains($range, '/')) {
            $rangeBin = @inet_pton($range);
            return $rangeBin !== false && $rangeBin === $ipBin;
        }

        [$subnet, $bitsText] = explode('/', $range, 2);
        $subnetBin = @inet_pton($subnet);
        if ($subnetBin === false || strlen($subnetBin) !== strlen($ipBin) || !ctype_digit($bitsText)) {
            return false;
        }
        $bits = (int) $bitsText;
        if ($bits > strlen($ipBin) * 8) {
            return false;
        }

        $whole = intdiv($bits, 8);
        if (strncmp($ipBin, $subnetBin, $whole) !== 0) {
            return false;
        }
        $rest = $bits % 8;
        if ($rest === 0) {
            return true;
        }
        $mask = (0xFF << (8 - $rest)) & 0xFF;
        return (ord($ipBin[$whole]) & $mask) === (ord($subnetBin[$whole]) & $mask);
    }

    private static function valid(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }
        $ip = filter_var(trim($value), FILTER_VALIDATE_IP);
        return is_string($ip) ? $ip : null;
    }

    /** @return list<string> */
    private static function parseTrusted(string $list): array
    {
        $ranges = [];
        foreach (explode(',', $list) as $entry) {
            $entry = strtolower(trim($entry));
            if ($entry === '') {
                continue;
            }
            if ($entry === 'cloudflare') {
                array_push($ranges, ...self::CLOUDFLARE_RANGES);
                continue;
            }
            [$address] = explode('/', $entry, 2);
            if (self::valid($address) === null) {
                error_log("ClientIp: ignoring invalid TRUSTED_PROXIES entry '{$entry}'");
                continue;
            }
            $ranges[] = $entry;
        }
        return $ranges;
    }

    /** @param list<string> $ranges */
    private static function isTrusted(string $ip, array $ranges): bool
    {
        foreach ($ranges as $range) {
            if (self::inRange($ip, $range)) {
                return true;
            }
        }
        return false;
    }
}
