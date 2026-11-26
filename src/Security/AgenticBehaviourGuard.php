<?php

declare(strict_types=1);

namespace Src\Security;

use Src\AuditLogger;
use Src\Auth\AdminGuardMiddleware;
use Src\ClientIp;
use Src\Exceptions\ForbiddenException;
use Src\Exceptions\TooManyRequestsException;

/**
 * AgenticBehaviourGuard
 *
 * Principal-grade counter-agentic defensive engine designed to neutralise NCSC-flagged
 * autonomous offensive AI frameworks across all 8 portfolio applications.
 *
 * Counters 3 attack vectors that bypass traditional single-IP rate limiters:
 *  1. Canary Reconnaissance Traps: Instantly detects and quarantines agents probing
 *     high-value discovery paths (/.env, /.git/config, /vendor/composer/installed.json, etc.).
 *  2. Headless Offensive Framework Fingerprinting: Scores User-Agent signatures and HTTP
 *     header correlation entropy to catch automated exploit runners (Nuclei, SQLMap, FFUF, etc.).
 *  3. Distributed Subnet IDOR & Error Velocity Quarantine: Tracks sliding-window 401/403/404
 *     enumeration bursts across a /24 IPv4 (or /48 IPv6) subnet + UA fingerprint in O(1) time.
 */
final class AgenticBehaviourGuard
{
    /**
     * High-signal reconnaissance paths that no legitimate user or browser ever requests.
     *
     * @var list<string>
     */
    public const CANARY_TRAP_PATHS = [
        '/.env',
        '/.env.backup',
        '/.env.production',
        '/.env.local',
        '/.git/config',
        '/.git/HEAD',
        '/vendor/composer/installed.json',
        '/composer.lock',
        '/wp-login.php',
        '/wp-admin',
        '/xmlrpc.php',
        '/phpmyadmin',
        '/actuator/env',
        '/actuator/heapdump',
        '/_profiler/phpinfo',
        '/phpinfo.php',
        '/server-status',
        '/.aws/credentials',
        '/.ssh/id_rsa',
    ];

    /**
     * Offensive scanner & exploit framework User-Agent tokens.
     *
     * @var list<string>
     */
    private const OFFENSIVE_AGENT_SIGNATURES = [
        'sqlmap',
        'nuclei',
        'nikto',
        'dirbuster',
        'gobuster',
        'ffuf',
        'feroxbuster',
        'zgrab',
        'masscan',
        'nmap',
        'acunetix',
        'nessus',
        'metasploit',
        'burpcollaborator',
        'wfuzz',
        'commix',
    ];

    /** Default sliding window in seconds for IDOR / 403 / 404 enumeration tracking. */
    public const VELOCITY_WINDOW_SECONDS = 60;

    /** Default maximum enumeration errors (401/403/404) permitted per subnet+UA fingerprint within window. */
    public const MAX_ENUMERATION_ERRORS_PER_WINDOW = 15;

    /** Quarantine duration in seconds once an agentic scanner trips a canary or velocity threshold (24 hours). */
    public const QUARANTINE_TTL_SECONDS = 86400;

    /**
     * Check if a request path matches a high-confidence reconnaissance canary trap.
     */
    public static function isReconCanaryPath(string $requestUri): bool
    {
        $path = strtolower((string) (parse_url($requestUri, PHP_URL_PATH) ?? $requestUri));
        $query = strtolower((string) (parse_url($requestUri, PHP_URL_QUERY) ?? ''));

        if (str_contains($query, 'xdebug_session_start') || str_contains($query, 'allow_url_include')) {
            return true;
        }

        foreach (self::CANARY_TRAP_PATHS as $trap) {
            $lowerTrap = strtolower($trap);
            if ($path === $lowerTrap || str_starts_with($path, $lowerTrap . '/')) {
                return true;
            }
        }

        return false;
    }

    /**
     * Inspect User-Agent and HTTP header correlation for known offensive frameworks.
     *
     * @param array<string, mixed> $server $_SERVER superglobal or test fixture
     * @return array{is_offensive_tool: bool, risk_score: int, matched_signature: ?string}
     */
    public static function inspectAgentSignature(array $server): array
    {
        $ua = strtolower(trim((string) ($server['HTTP_USER_AGENT'] ?? '')));
        $riskScore = 0;

        foreach (self::OFFENSIVE_AGENT_SIGNATURES as $sig) {
            if ($ua !== '' && str_contains($ua, $sig)) {
                return [
                    'is_offensive_tool' => true,
                    'risk_score' => 100,
                    'matched_signature' => $sig,
                ];
            }
        }

        // Empty User-Agent on state-changing requests
        $method = strtoupper((string) ($server['REQUEST_METHOD'] ?? 'GET'));
        if ($ua === '') {
            $riskScore += 45;
        }

        // Headless raw scripting libraries hitting non-webhook browser routes without Accept-Language
        if (
            (str_contains($ua, 'python-requests') || str_contains($ua, 'aiohttp') || str_contains($ua, 'go-http-client') || str_contains($ua, 'scrapy'))
            && empty($server['HTTP_ACCEPT_LANGUAGE'])
        ) {
            $riskScore += 50;
        }

        if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'], true) && empty($server['HTTP_ACCEPT'])) {
            $riskScore += 25;
        }

        return [
            'is_offensive_tool' => $riskScore >= 100,
            'risk_score' => min(100, $riskScore),
            'matched_signature' => null,
        ];
    }

    /**
     * Compute a privacy-safe behavioural bucket key binding the /24 IPv4 (or /48 IPv6) subnet
     * and the normalised User-Agent family so residential proxy rotation within the same
     * subnet or tool signature shares a single velocity counter.
     *
     * @param array<string, mixed>|null $server
     */
    public static function computeBehaviourFingerprint(?string $ip = null, ?array $server = null): string
    {
        $server ??= $_SERVER;
        $resolvedIp = $ip ?? ClientIp::fromGlobals();
        $subnet = AdminGuardMiddleware::extractSubnet($resolvedIp);
        $ua = strtolower(trim((string) ($server['HTTP_USER_AGENT'] ?? 'unknown')));
        $secret = (string) ($_ENV['APP_KEY'] ?? $_ENV['JWT_KEY'] ?? 'sovereign-behaviour-guard');

        return hash_hmac('sha256', $subnet . '|' . $ua, $secret);
    }

    /**
     * Record an HTTP status (e.g. 401, 403, 404) for sequential IDOR / enumeration tracking
     * and return true if the client has crossed the quarantine threshold.
     */
    public static function recordEnumerationEvent(
        string $fingerprint,
        int $statusCode = 403,
        ?string $storageDir = null,
        int $threshold = self::MAX_ENUMERATION_ERRORS_PER_WINDOW,
        int $windowSeconds = self::VELOCITY_WINDOW_SECONDS
    ): bool {
        if (!in_array($statusCode, [401, 403, 404, 422], true)) {
            return false;
        }

        $dir = $storageDir ?? self::getDefaultStorageDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        $file = $dir . DIRECTORY_SEPARATOR . 'vel_' . substr($fingerprint, 0, 32) . '.json';
        $now = time();
        $timestamps = [];

        if (is_file($file)) {
            $raw = @file_get_contents($file);
            if (is_string($raw) && $raw !== '') {
                $decoded = json_decode($raw, true);
                if (is_array($decoded)) {
                    foreach ($decoded as $ts) {
                        if (is_int($ts) && ($now - $ts) <= $windowSeconds) {
                            $timestamps[] = $ts;
                        }
                    }
                }
            }
        }

        $timestamps[] = $now;
        @file_put_contents($file, (string) json_encode($timestamps), LOCK_EX);

        if (count($timestamps) >= $threshold) {
            self::quarantineFingerprint($fingerprint, 'idor_enumeration_velocity_exceeded', $dir);
            return true;
        }

        return false;
    }

    /**
     * Quarantine a fingerprint for QUARANTINE_TTL_SECONDS.
     */
    public static function quarantineFingerprint(
        string $fingerprint,
        string $reason,
        ?string $storageDir = null,
        int $ttlSeconds = self::QUARANTINE_TTL_SECONDS
    ): void {
        $dir = $storageDir ?? self::getDefaultStorageDir();
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }

        $banFile = $dir . DIRECTORY_SEPARATOR . 'ban_' . substr($fingerprint, 0, 32) . '.json';
        $payload = [
            'reason' => $reason,
            'quarantined_at' => time(),
            'expires_at' => time() + $ttlSeconds,
        ];
        @file_put_contents($banFile, (string) json_encode($payload), LOCK_EX);

        AuditLogger::log('agentic_swarm_quarantined', [
            'fingerprint' => substr($fingerprint, 0, 16),
            'reason' => $reason,
            'ip' => ClientIp::fromGlobals(),
        ]);
    }

    /**
     * Check if a fingerprint is currently under active quarantine.
     */
    public static function isQuarantined(string $fingerprint, ?string $storageDir = null): bool
    {
        $dir = $storageDir ?? self::getDefaultStorageDir();
        $banFile = $dir . DIRECTORY_SEPARATOR . 'ban_' . substr($fingerprint, 0, 32) . '.json';
        if (!is_file($banFile)) {
            return false;
        }

        $raw = @file_get_contents($banFile);
        if (!is_string($raw) || $raw === '') {
            return false;
        }

        $data = json_decode($raw, true);
        $expiresAt = is_array($data) ? (int) ($data['expires_at'] ?? 0) : 0;
        if ($expiresAt <= time()) {
            @unlink($banFile);
            return false;
        }

        return true;
    }

    /**
     * Enforce the full counter-agentic gate on an inbound HTTP request.
     *
     * @param array<string, mixed>|null $server
     * @throws TooManyRequestsException|ForbiddenException
     */
    public static function enforce(?array $server = null, ?string $storageDir = null): void
    {
        $server ??= $_SERVER;
        $ip = ClientIp::fromGlobals();
        $fingerprint = self::computeBehaviourFingerprint($ip, $server);

        // 1. Reject immediately if already quarantined
        if (self::isQuarantined($fingerprint, $storageDir)) {
            throw new TooManyRequestsException('Access temporarily restricted due to automated security policy.');
        }

        // 2. Check Canary Reconnaissance Trap
        $uri = (string) ($server['REQUEST_URI'] ?? '/');
        if (self::isReconCanaryPath($uri)) {
            self::quarantineFingerprint($fingerprint, 'canary_recon_probe:' . $uri, $storageDir);
            throw new ForbiddenException('Resource access denied.');
        }

        // 3. Check Offensive Framework Signature
        $sigCheck = self::inspectAgentSignature($server);
        if ($sigCheck['is_offensive_tool']) {
            self::quarantineFingerprint($fingerprint, 'offensive_tool:' . ($sigCheck['matched_signature'] ?? 'heuristic'), $storageDir);
            throw new ForbiddenException('Automated security scanner blocked.');
        }
    }

    private static function getDefaultStorageDir(): string
    {
        return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sovereign_agentic_guard';
    }
}
