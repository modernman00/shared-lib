<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\ClientIp;

/**
 * Which address a request comes from, for rate limits and IP bans.
 *
 * Forwarding headers (X-Forwarded-For, Client-IP, CF-Connecting-IP) are set by
 * whoever sends the request, so they are believed only when the connection comes
 * from a proxy we trust (TRUSTED_PROXIES). Red Team finding, PartyPlatform
 * invite-flow review 2026-09-26.
 */
class ClientIpTest extends TestCase
{
    /** @return array<string, array{array<string, string>, string, string}> */
    public static function cases(): array
    {
        $cfEdge = '173.245.48.10';
        return [
            'no proxies trusted: the connection address' => [['REMOTE_ADDR' => '203.0.113.7'], '', '203.0.113.7'],
            'no proxies trusted: X-Forwarded-For ignored' => [['REMOTE_ADDR' => '203.0.113.7', 'HTTP_X_FORWARDED_FOR' => '198.51.100.1'], '', '203.0.113.7'],
            'no proxies trusted: Client-IP ignored' => [['REMOTE_ADDR' => '203.0.113.7', 'HTTP_CLIENT_IP' => '198.51.100.1'], '', '203.0.113.7'],
            'no proxies trusted: CF-Connecting-IP ignored' => [['REMOTE_ADDR' => '203.0.113.7', 'HTTP_CF_CONNECTING_IP' => '198.51.100.1'], '', '203.0.113.7'],
            'Cloudflare trusted, but request not from Cloudflare: header ignored' => [['REMOTE_ADDR' => '203.0.113.7', 'HTTP_CF_CONNECTING_IP' => '198.51.100.1'], 'cloudflare', '203.0.113.7'],
            'request from Cloudflare: its header is used' => [['REMOTE_ADDR' => $cfEdge, 'HTTP_CF_CONNECTING_IP' => '198.51.100.1'], 'cloudflare', '198.51.100.1'],
            'request from Cloudflare over IPv6' => [['REMOTE_ADDR' => '2606:4700::1', 'HTTP_CF_CONNECTING_IP' => '2001:db8::5'], 'cloudflare', '2001:db8::5'],
            'trusted proxy: right-most untrusted X-Forwarded-For entry' => [['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '1.1.1.1, 198.51.100.9, 10.0.0.4'], '10.0.0.0/8', '198.51.100.9'],
            'trusted proxy: a spoofed left-most entry is not used' => [['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => '6.6.6.6, 198.51.100.9'], '10.0.0.5', '198.51.100.9'],
            'trusted proxy, no forwarding header: the proxy address' => [['REMOTE_ADDR' => '10.0.0.5'], '10.0.0.5', '10.0.0.5'],
            'trusted proxy, garbage header: the proxy address' => [['REMOTE_ADDR' => '10.0.0.5', 'HTTP_X_FORWARDED_FOR' => "not-an-ip, <script>"], '10.0.0.5', '10.0.0.5'],
            'garbage Cloudflare header falls back' => [['REMOTE_ADDR' => $cfEdge, 'HTTP_CF_CONNECTING_IP' => '999.1.1.1'], 'cloudflare', $cfEdge],
            'no connection address (CLI)' => [[], '', '0.0.0.0'],
            'invalid connection address' => [['REMOTE_ADDR' => 'unknown'], '', '0.0.0.0'],
            'bad entries in the trusted list are ignored' => [['REMOTE_ADDR' => '203.0.113.7', 'HTTP_X_FORWARDED_FOR' => '198.51.100.1'], 'nonsense, 300.1.1.1/8, 10.0.0.0/99', '203.0.113.7'],
        ];
    }

    /** @param array<string, string> $server */
    #[DataProvider('cases')]
    public function testResolve(array $server, string $trusted, string $expected): void
    {
        $this->assertSame($expected, ClientIp::resolve($server, $trusted));
    }

    public function testGetUserIpAddrNoLongerBelievesForwardingHeadersByDefault(): void
    {
        $saved = [$_SERVER, $_ENV];
        try {
            unset($_ENV['TRUSTED_PROXIES']);
            putenv('TRUSTED_PROXIES');
            $_SERVER['REMOTE_ADDR'] = '203.0.113.7';
            $_SERVER['HTTP_X_FORWARDED_FOR'] = '198.51.100.1';
            $_SERVER['HTTP_CLIENT_IP'] = '198.51.100.2';
            $_SERVER['HTTP_CF_CONNECTING_IP'] = '198.51.100.3';
            $this->assertSame('203.0.113.7', \Src\Utility::getUserIpAddr());
        } finally {
            [$_SERVER, $_ENV] = $saved;
        }
    }

    public function testCidrMatching(): void
    {
        $this->assertTrue(ClientIp::inRange('10.1.2.3', '10.0.0.0/8'));
        $this->assertFalse(ClientIp::inRange('11.1.2.3', '10.0.0.0/8'));
        $this->assertTrue(ClientIp::inRange('10.0.0.5', '10.0.0.5'));
        $this->assertTrue(ClientIp::inRange('2606:4700:1::1', '2606:4700::/32'));
        $this->assertFalse(ClientIp::inRange('2606:4701::1', '2606:4700::/32'));
        $this->assertFalse(ClientIp::inRange('10.0.0.5', '2606:4700::/32'), 'IPv4 never matches an IPv6 range');
        $this->assertFalse(ClientIp::inRange('10.0.0.5', '10.0.0.0/33'));
    }
}
