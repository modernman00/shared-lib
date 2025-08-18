<?php

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

        $isValid = isset($value) && ($expectedValue === null || $value === $expectedValue);

        if (!$isValid) {
            \redirect($_ENV['401URL']);
          
        }
    }

    /**
     * Retrieve a nested session value using dot notation.
     *
     * @param string $path
     * @return mixed|null
     */
    private static function getSessionValue(string $path): mixed
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
