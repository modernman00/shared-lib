<?php

declare(strict_types=1);

namespace Src\Admin;

/**
 * Zero-trust secret admin path gate.
 *
 * Apps keep their internal routes under "/admin/...". This gate:
 *  - requires ADMIN_SECRET_PATH to be set (fail closed with a disguised 404 otherwise),
 *  - transparently maps "<secret>/..." onto "/admin/..." before routing,
 *  - answers any direct request to "/admin" or "/admin/..." with a disguised 404.
 *
 * Views must build admin links with admin_url() so they carry the secret prefix.
 */
final class AdminPathGate
{
    public static function secretPath(): string
    {
        $secret = (string) ($_ENV['ADMIN_SECRET_PATH'] ?? getenv('ADMIN_SECRET_PATH') ?: '');
        $secret = '/' . trim($secret, '/');

        return $secret === '/' ? '' : $secret;
    }

    public static function apply(): void
    {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = (string) (parse_url($uri, PHP_URL_PATH) ?? '/');
        $query = (string) (parse_url($uri, PHP_URL_QUERY) ?? '');
        $secret = self::secretPath();

        $isInternal = $path === '/admin' || str_starts_with($path, '/admin/');
        $isSecret = $secret !== '' && ($path === $secret || str_starts_with($path, $secret . '/'));

        if ($isSecret) {
            $rest = substr($path, strlen($secret));
            $rewritten = '/admin' . ($rest === false ? '' : $rest);
            $_SERVER['REQUEST_URI'] = $rewritten . ($query !== '' ? '?' . $query : '');
            $_SERVER['ADMIN_SECRET_ROUTED'] = '1';

            return;
        }

        if ($isInternal) {
            self::disguised404();
        }
    }

    /**
     * For apps whose routes are already registered under the secret prefix:
     * block any direct "/admin" access without rewriting the URI.
     */
    public static function blockDirectAdmin(): void
    {
        if (!isset($_SERVER['REQUEST_URI'])) {
            return;
        }
        $path = (string) (parse_url((string) $_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
        $secret = self::secretPath();
        if ($secret === '') {
            return;
        }
        $isDirect = $path === '/admin' || str_starts_with($path, '/admin/');
        $isSecret = $path === $secret || str_starts_with($path, $secret . '/');
        if ($isDirect && !$isSecret) {
            self::disguised404();
        }
    }

    private static function disguised404(): never
    {
        if (!headers_sent()) {
            http_response_code(404);
            header('Content-Type: text/html; charset=UTF-8');
        }
        echo '<!DOCTYPE html><html><head><title>404 Not Found</title></head><body><h1>Not Found</h1><p>The requested URL was not found on this server.</p></body></html>';
        exit;
    }
}
