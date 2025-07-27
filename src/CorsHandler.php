<?php

declare(strict_types=1);

namespace Src;

class CorsHandler
{
    // Whitelisted origins for development/testing.
    // You should strictly validate these in production.
    private const ALLOWED_ORIGINS = [
        'http://localhost:8080',
        'http://127.0.0.1:8080',
        'http://idecide.test',
        'http://idecide.test:80',
    ];

    /**
     * Core CORS header setter.
     * Applies essential access controls, security headers, and preflight handling.
     *
     * @param string $contentType Desired content-type response header.
     * @param string $allowedMethods Allowed HTTP verbs.
     * @param int    $maxAge Duration (in seconds) browsers cache preflight results.
     * @param array  $allowedHeaders List of permitted custom headers for cross-origin.
     */
    public static function setHeaders(
        string $contentType = 'application/json; charset=UTF-8',
        string $allowedMethods = 'POST, GET, OPTIONS',
        int $maxAge = 3600,
        array $allowedHeaders = [
            'Content-Type',
            'Access-Control-Allow-Headers',
            'Authorization',
            'X-Requested-With',
            'X-XSRF-TOKEN',
            'X-CSRF-TOKEN',
        ]
    ): void {
        $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // Dynamically resolve allowed origin based on environment
        $allowedOrigin = self::getAllowedOrigin($requestOrigin);

        // CORS headers – crucial for secure API exposure
        header('Access-Control-Allow-Origin: ' . $allowedOrigin);
        header('Access-Control-Allow-Credentials: true'); // Enables cookie/session sharing
        header('Content-Type: ' . $contentType);
        header('Access-Control-Allow-Methods: ' . $allowedMethods);
        header('Access-Control-Max-Age: ' . $maxAge);
        header('Access-Control-Allow-Headers: ' . implode(', ', $allowedHeaders));

        // Extra security headers (prevent attacks via MIME sniffing, framing, XSS, referrer leakage)
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // Handle preflight OPTIONS requests separately
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }

    /**
     * Dynamically determine the allowed origin.
     * Allows flexibility based on dev/test/production environments.
     */
    private static function getAllowedOrigin(string $requestOrigin): string
    {
        if (self::isDevelopment()) {
            // Permit from whitelist or fallback to APP_URL
            if (in_array($requestOrigin, self::ALLOWED_ORIGINS, true)) {
                return $requestOrigin;
            }

            return getenv('APP_URL') ?: 'http://localhost:8080';
        }

        // Production: match only known app URL or fall back to local domain
        $appUrl = getenv('APP_URL');

        if ($appUrl && $requestOrigin === $appUrl) {
            return $appUrl;
        }

        return self::getCurrentDomain(); // Fallback: same-origin enforcement
    }

    /**
     * Environment check for adaptive CORS behavior.
     * Prevents exposing dev-level permissions in production.
     */
    private static function isDevelopment(): bool
    {
        $env = getenv('APP_ENV') ?: 'production';

        return in_array($env, ['development', 'dev', 'local', 'testing'], true);
    }

    /**
     * Helper to infer origin from request context when no APP_URL is available.
     */
    private static function getCurrentDomain(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $protocol . $host;
    }

    /**
     * Set CORS headers tailored for API responses.
     * Grants access to common RESTful verbs and authentication headers.
     */
    public static function setApiHeaders(): void
    {
        self::setHeaders(
            contentType: 'application/json; charset=UTF-8',
            allowedMethods: 'POST, GET, PUT, DELETE, OPTIONS',
            allowedHeaders: [
                'Content-Type',
                'Authorization',
                'X-Requested-With',
                'X-CSRF-TOKEN',
                'Accept',
                'Origin',
            ]
        );
    }

    /**
     * Apply CORS headers suitable for form submissions.
     */
    public static function setFormHeaders(): void
    {
        self::setHeaders(
            contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
            allowedMethods: 'POST, OPTIONS',
            allowedHeaders: [
                'Content-Type',
                'X-Requested-With',
                'X-CSRF-TOKEN',
            ]
        );
    }

    /**
     * Validate current request's origin against expected rules.
     * Useful for pre-checking before responding or allowing session cookies.
     */
    public static function validateOrigin(): bool
    {
        $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';

        if (self::isDevelopment()) {
            return in_array($requestOrigin, self::ALLOWED_ORIGINS, true)
                || $requestOrigin === getenv('APP_URL');
        }

        $appUrl = getenv('APP_URL');

        return $requestOrigin === $appUrl || $requestOrigin === self::getCurrentDomain();
    }

    /**
     * Reject request if origin fails validation.
     * Always returns structured JSON error message.
     */
    public static function enforceOrigin(): void
    {
        if (!self::validateOrigin()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Origin not allowed']);
            exit();
        }
    }
}
