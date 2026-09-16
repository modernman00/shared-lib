<?php

declare(strict_types=1);

namespace Src\Auth;

use Src\Limiter;

/**
 * Shared Zero-Trust Admin Guard Middleware for all portfolio applications.
 */
final class AdminGuardMiddleware
{
    public static bool $testingMode = false;

    /**
     * Enforce Zero-Trust security perimeter on admin routes.
     */
    public static function enforce(): void
    {
        $ip = self::getClientIp();
        $userAgent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');

        // 1. IP Whitelisting Gate
        $allowedIpsRaw = (string) ($_ENV['ADMIN_ALLOWED_IPS'] ?? getenv('ADMIN_ALLOWED_IPS') ?: '');
        if ($allowedIpsRaw !== '') {
            $allowedIps = array_filter(array_map('trim', explode(',', $allowedIpsRaw)));
            if (!empty($allowedIps) && !in_array($ip, $allowedIps, true)) {
                error_log("[AdminGuard] Unauthorized IP probe blocked: IP={$ip} UA={$userAgent}");
                self::renderDisguised404();
                return;
            }
        }

        // 2. Route Rate-Limiting Gate
        try {
            Limiter::limit('admin_nav:' . $ip, 'default');
        } catch (\Throwable $e) {
            error_log("[AdminGuard] Route rate-limit exceeded for IP={$ip}: " . $e->getMessage());
            self::renderDisguised404();
            return;
        }

        // 3. Fail-Closed Session Fingerprint Gate
        $isAdmin = (($_SESSION['auth']['type'] ?? '') === 'super_admin') 
                || (($_SESSION['role'] ?? '') === 'admin')
                || (($_SESSION['auth']['type'] ?? '') === 'admin');

        if ($isAdmin && !empty($_SESSION['admin_fingerprint'])) {
            $expectedFingerprint = self::generateFingerprint($ip, $userAgent);
            $currentFingerprint = (string) ($_SESSION['admin_fingerprint'] ?? '');

            if ($currentFingerprint === '' || !hash_equals($currentFingerprint, $expectedFingerprint)) {
                error_log("[AdminGuard] Session hijacking or missing fingerprint detected: IP={$ip} UA={$userAgent}");
                $_SESSION = [];
                if (session_status() === PHP_SESSION_ACTIVE) {
                    session_destroy();
                }
                self::renderDisguised404();
                return;
            }
        }
    }

    public static function bindSession(string $ip, string $userAgent): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['admin_fingerprint'] = self::generateFingerprint($ip, $userAgent);
            $_SESSION['admin_login_at'] = time();
        }
    }

    public static function generateFingerprint(string $ip, string $userAgent): string
    {
        $subnet = self::extractSubnet($ip);
        $secretKey = (string) ($_ENV['APP_KEY'] ?? getenv('APP_KEY') ?: 'zero-trust-admin-salt');
        return hash_hmac('sha256', $subnet . '|' . $userAgent, $secretKey);
    }

    public static function extractSubnet(string $ip): string
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            $octets = explode('.', $ip);
            return implode('.', array_slice($octets, 0, 3)) . '.0';
        }

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $hextets = explode(':', $ip);
            return implode(':', array_slice($hextets, 0, 4)) . '::';
        }

        return $ip;
    }

    public static function getClientIp(): string
    {
        $remoteAddr = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        $trustedProxiesRaw = (string) ($_ENV['TRUSTED_PROXIES'] ?? getenv('TRUSTED_PROXIES') ?: '');

        if ($trustedProxiesRaw !== '') {
            $trustedProxies = array_filter(array_map('trim', explode(',', $trustedProxiesRaw)));
            if (in_array($remoteAddr, $trustedProxies, true)) {
                if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
                    return trim((string) $_SERVER['HTTP_CF_CONNECTING_IP']);
                }
                if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                    $ips = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
                    return trim($ips[0]);
                }
            }
        }

        return $remoteAddr;
    }

    public static function renderDisguised404(): void
    {
        if (!headers_sent()) {
            http_response_code(404);
            header('Content-Type: text/html; charset=utf-8');
        }

        echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>Not Found</h1><p>The requested URL was not found on this server.</p></body></html>';

        if (!self::$testingMode) {
            exit;
        }
    }
}
