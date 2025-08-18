<?php 

namespace Src;

final class ErrorController
{
    public static function unauthorized(): void
    {
        self::renderError(401, 'Unauthorized access');
    }

    public static function forbidden(): void
    {
        self::renderError(403, 'Forbidden');
    }

    public static function notFound(): void
    {
        self::renderError(404, 'Page not found');
    }

    public static function tooManyRequests(): void
    {
        self::renderError(429, 'Too many requests');
    }

    public static function serverError(): void
    {
        self::renderError(500, 'Internal server error');
    }

    private static function renderError(int $code, ?string $message = null): void
    {
        // Render view
        Utility::view2("errors/$code", ['error' => $message]); // e.g. views/errors/404.php
    }

}
