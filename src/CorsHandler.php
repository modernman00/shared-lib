<?php

declare(strict_types=1);

namespace Src;

class CorsHandler
{
    private const ALLOWED_ORIGINS = [
        'http://localhost:8080',
        'http://127.0.0.1:8080',
        'http://idecide.test',
        'http://idecide.test:80'
    ];

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
            'X-CSRF-TOKEN'
        ]
    ): void {
        // Get the request origin
        $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        // Determine allowed origin
        $allowedOrigin = self::getAllowedOrigin($requestOrigin);
        
        // Set CORS headers
        header('Access-Control-Allow-Origin: ' . $allowedOrigin);
        header('Access-Control-Allow-Credentials: true'); // Allow cookies/sessions
        header('Content-Type: ' . $contentType);
        header('Access-Control-Allow-Methods: ' . $allowedMethods);
        header('Access-Control-Max-Age: ' . $maxAge);
        header('Access-Control-Allow-Headers: ' . implode(', ', $allowedHeaders));
        
        // Security headers
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('X-XSS-Protection: 1; mode=block');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        
        // Handle preflight OPTIONS requests
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }
    }

    /**
     * Determine the allowed origin based on environment and request
     */
    private static function getAllowedOrigin(string $requestOrigin): string
    {
        // For development/testing, allow configured origins
        if (self::isDevelopment()) {
            if (in_array($requestOrigin, self::ALLOWED_ORIGINS, true)) {
                return $requestOrigin;
            }
            
            // Fallback to APP_URL for development
            $appUrl = getenv('APP_URL');
            if ($appUrl) {
                return $appUrl;
            }
            
            return 'http://localhost:8080'; // Default development origin
        }
        
        // For production, be more strict
        $appUrl = getenv('APP_URL');
        if ($appUrl && $requestOrigin === $appUrl) {
            return $appUrl;
        }
        
        // Default to same origin for production
        return self::getCurrentDomain();
    }

    /**
     * Check if we're in development environment
     */
    private static function isDevelopment(): bool
    {
        $env = getenv('APP_ENV') ?: 'production';
        return in_array($env, ['development', 'dev', 'local', 'testing'], true);
    }

    /**
     * Get current domain
     */
    private static function getCurrentDomain(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . $host;
    }

    /**
     * Set headers specifically for API endpoints
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
                'Origin'
            ]
        );
    }

    /**
     * Set headers for form submissions
     */
    public static function setFormHeaders(): void
    {
        self::setHeaders(
            contentType: 'application/x-www-form-urlencoded; charset=UTF-8',
            allowedMethods: 'POST, OPTIONS',
            allowedHeaders: [
                'Content-Type',
                'X-Requested-With',
                'X-CSRF-TOKEN'
            ]
        );
    }

    /**
     * Validate origin against whitelist
     */
    public static function validateOrigin(): bool
    {
        $requestOrigin = $_SERVER['HTTP_ORIGIN'] ?? '';
        
        if (self::isDevelopment()) {
            return in_array($requestOrigin, self::ALLOWED_ORIGINS, true) || 
                   $requestOrigin === getenv('APP_URL');
        }
        
        // Production validation
        $appUrl = getenv('APP_URL');
        return $requestOrigin === $appUrl || $requestOrigin === self::getCurrentDomain();
    }

    /**
     * Block request if origin is not allowed
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