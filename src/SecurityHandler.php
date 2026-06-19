<?php

declare(strict_types=1);

namespace Src;

class SecurityHandler
{
    /**
     * Define the CSP Nonce constant if not already defined.
     */
    public static function initNonce(): string
    {
        if (!defined('CSP_NONCE')) {
            define('CSP_NONCE', bin2hex(random_bytes(16)));
        }
        return CSP_NONCE;
    }

    /**
     * Apply global security headers to protect against Clickjacking, MIME-sniffing, etc.
     */
    public static function applyGlobalHeaders(): void
    {
        if (php_sapi_name() === 'cli') {
            return;
        }

        // Initialize CSP Nonce
        self::initNonce();

        // Anti-Clickjacking
        header('X-Frame-Options: DENY');

        // Prevent MIME Sniffing
        header('X-Content-Type-Options: nosniff');

        // Referrer Policy
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // XSS Protection (older browsers fallback)
        header('X-XSS-Protection: 1; mode=block');

        // Strict Transport Security (HSTS)
        $isHttps = (isset($_SERVER['HTTPS']) && ($_SERVER['SERVER_PORT'] == 443 || $_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === 1)) ||
            (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        if ($isHttps) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains; preload');
        }
    }

    /**
     * Validate the CSRF token on all state-changing HTTP methods.
     */
    public static function verifyCsrf(): void
    {
        if (php_sapi_name() === 'cli' && !getenv('PHPUNIT_RUNNING')) {
            return;
        }

        // Only enforce on state-changing methods
        if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'DELETE', 'PATCH'], true)) {
            return;
        }

        $sessionToken = $_SESSION['token'] ?? null;
        if (!$sessionToken) {
            self::abortCsrf();
        }

        $requestToken = null;

        // 1. Check HTTP request headers
        if (isset($_SERVER['HTTP_X_XSRF_TOKEN'])) {
            $requestToken = $_SERVER['HTTP_X_XSRF_TOKEN'];
        } elseif (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            $requestToken = $_SERVER['HTTP_X_CSRF_TOKEN'];
        }
        // 2. Check standard POST variables
        elseif (isset($_POST['token'])) {
            $requestToken = $_POST['token'];
        } elseif (isset($_POST['_token'])) {
            $requestToken = $_POST['_token'];
        }
        // 3. Check raw JSON request bodies
        else {
            $json = file_get_contents('php://input');
            if ($json) {
                $data = json_decode($json, true);
                if (is_array($data)) {
                    $requestToken = $data['token'] ?? $data['_token'] ?? null;
                }
            }
        }

        if (!$requestToken) {
            self::abortCsrf();
        }

        $decodedRequestToken = urldecode($requestToken);

        if (!hash_equals($sessionToken, $requestToken) && !hash_equals($sessionToken, $decodedRequestToken)) {
            self::abortCsrf();
        }
    }

    /**
     * Send a 400 Bad Request JSON response and terminate execution.
     */
    private static function abortCsrf(): void
    {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode([
            'status' => 'error',
            'message' => 'CSRF verification failed or token expired. Please refresh the page.',
            'code' => 400
        ]);
        if (getenv('PHPUNIT_RUNNING')) {
            throw new \Src\Exceptions\UnauthorisedException('CSRF verification failed or token expired. Please refresh the page.');
        }
        exit;
    }
}
