<?php

namespace Src;

class LaravelHelper
{
  

    /**
     * Get the base URL of the application.
     */
    public static function baseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . $_SERVER['HTTP_HOST'];
    }

    /**
     * Generate a full URL from a relative path.
     */
    public static function url(string $path = ''): string
    {
        return rtrim(self::baseUrl(), '/') . '/' . ltrim($path, '/');
    }

    /**
     * Generate the full URL to an asset in the /public directory.
     */
    public static function asset(string $path = ''): string
    {
        return self::url('public/' . ltrim($path, '/'));
    }

    /**
     * Generate a URL from a named route with optional parameters.
     */
    public static function route(string $name, array $params = []): string
    {
        $uri = $name;
        foreach ($params as $key => $val) {
            $uri = str_replace("{{$key}}", urlencode($val), $uri);
        }
        return self::url($uri);
    }

    /**
     * Retrieve a value from the old POST data or return default.
     */
    public static function old(string $key, $default = ''): mixed
    {
        return $_POST[$key] ?? $default;
    }

    /**
     * Generate or retrieve a CSRF token from the session.
     */
    public static function csrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    /**
     * Return a hidden input field for CSRF protection.
     */
    public static function csrfField(): string
    {
        return '<input type="hidden" name="_token" value="' . self::csrfToken() . '">';
    }

    /**
     * Retrieve a value from the session.
     */
    public static function sessionGet(string $key, $default = null): mixed
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Flash a value to the session (store temporarily).
     */
    public static function sessionFlash(string $key, $value): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Check if a session key exists.
     */
    public static function sessionHas(string $key): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a session key.
     */
    public static function sessionForget(string $key): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        unset($_SESSION[$key]);
    }

    /**
     * Redirect the user to a given path and stop execution.
     */
    public static function redirect(string $path): void
    {
        header('Location: ' . self::url($path));
        exit;
    }
}
