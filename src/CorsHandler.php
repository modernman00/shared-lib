<?php

declare(strict_types=1);

namespace Src;

class CorsHandler
{
    /**
     * Apply full CORS and security headers.
     * Dynamically sets response Content-Type and handles preflight OPTIONS requests.
     *
     * @param string $responseType Desired response Content-Type (e.g. 'application/json')
     * @param string $allowedMethods Comma-separated HTTP verbs
     * @param array $allowedHeaders List of permitted custom headers
     * @param int $maxAge Seconds browsers cache preflight results
     */
    public static function setHeaders(
    ?string $responseType = null,
    string $allowedMethods = 'GET, POST, PUT, DELETE, OPTIONS',
    array $allowedHeaders = [
        'Content-Type',
        'Authorization',
        'X-Requested-With',
        'X-CSRF-TOKEN',
        'X-XSRF-TOKEN',
        'Accept',
        'Origin',
    ],
    int $maxAge = 3600
): void {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    $resolvedOrigin = self::resolveOrigin($origin);

    // Dynamically infer response type if not provided
    $type = $responseType ?? self::inferResponseType();

    header("Access-Control-Allow-Origin: {$resolvedOrigin}");
    header("Access-Control-Allow-Credentials: true");
    header("Access-Control-Allow-Methods: {$allowedMethods}");
    header("Access-Control-Allow-Headers: " . implode(', ', $allowedHeaders));
    header("Access-Control-Max-Age: {$maxAge}");
    header("Content-Type: {$type}");

    self::applySecurityHeaders();

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit();
    }
}

private static function inferResponseType(): string
{
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    // Explicit JSON request (e.g. Axios, fetch)
    if (stripos($contentType, 'application/json') !== false) {
        return 'application/json; charset=UTF-8';
    }

    // Accept header prefers JSON and not HTML (e.g. API client)
    if (
        stripos($accept, 'application/json') !== false &&
        stripos($accept, 'text/html') === false
    ) {
        return 'application/json; charset=UTF-8';
    }

    // Default to HTML for form submissions or browser requests
    return 'text/html; charset=UTF-8';
}



    /**
     * Apply security headers to prevent common web vulnerabilities.
     */
    private static function applySecurityHeaders(): void
    {
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
    }

    /**
     * Determine allowed origin based on environment and request.
     */
    private static function resolveOrigin(string $origin): string
    {
        $env = getenv('APP_ENV') ?: 'production';
        $appUrl = getenv('APP_URL') ?: self::inferDomain();

        if (in_array($env, ['development', 'local', 'testing'], true)) {
            return in_array($origin, self::allowedOrigins(), true) ? $origin : $appUrl;
        }

        return $origin === $appUrl ? $appUrl : self::inferDomain();
    }

    /**
     * Infer current domain from server context.
     */
    private static function inferDomain(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $protocol . $host;
    }

    /**
     * Whitelisted origins for development environments.
     */
    private static function allowedOrigins(): array
    {
        return [
            'http://localhost:3000',
            'http://localhost:8080',
            'http://127.0.0.1:3000',
            // Add more as needed
        ];
    }

    /**
     * Validate request origin before proceeding.
     */
    public static function validateOrigin(): bool
    {
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $appUrl = getenv('APP_URL') ?: self::inferDomain();

        return in_array($origin, self::allowedOrigins(), true) || $origin === $appUrl;
    }

    /**
     * Reject invalid origin with structured JSON error.
     */
    public static function rejectInvalidOrigin(): void
    {
        if (!self::validateOrigin()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Origin not allowed']);
            exit();
        }
    }
}

