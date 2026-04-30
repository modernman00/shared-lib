<?php

namespace helper\Middleware;


class CsrfMiddleware
{
    public static function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['token'] ?? null;

            if (!Csrfvalidate($token)) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
        }
    }
}