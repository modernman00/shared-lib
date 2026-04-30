<?php

namespace Helper\Middleware;

use Src\Csrf;

class CsrfMiddleware
{
    public static function handle(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['token'] ?? null;

            if (!Csrf::validate($token)) {
                http_response_code(403);
                die('Invalid CSRF token');
            }
        }
    }
}