<?php

declare(strict_types=1);

namespace Src\functionality\middleware;

final class AuthGateMiddleware
{
    /**
     * Enforce session-based access control.
     *
     * @param string $sessionPath Dot-notated path to session key (e.g. 'auth.identifyCust')
     * @param mixed $expectedValue Optional value to match (e.g. true, 'admin')
     * @param string|null $fallbackView Optional fallback view path
     */
    public static function enforce(string $sessionPath, mixed $expectedValue = null): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    $value = self::getSessionValue($sessionPath);

    // Valid if:
    // 1. The session key exists (not null)
    // 2. AND either no expected value was provided OR it matches exactly
    $isValid = $value !== null && ($expectedValue === null || $value === $expectedValue);

    if (!$isValid) {
        $fallback = $_ENV['401URL'] ?? '/401';
        redirect($fallback);
    }
}


    /**
     * Retrieve a nested session value using dot notation.
     *
     * @param string $path
     *
     * @return mixed|null
     */
    public static function getSessionValue(string $path): mixed
    {
        $segments = explode('.', $path);
        $value = $_SESSION;

        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return null;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}
