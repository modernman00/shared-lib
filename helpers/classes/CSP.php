<?php

namespace helpers\classes;

class CSP
{
    public static function apply(array $options = []): string
    {
        $nonce = bin2hex(random_bytes(16));

        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-$nonce' https: 'unsafe-eval'",
            "script-src-attr 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
            "font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net",
            "img-src 'self' data: https:",
            "connect-src 'self' https://www.google.com https://www.gstatic.com",
            "frame-src 'self' https://www.google.com https://recaptcha.google.com",
        ];

        if (!empty($options['extra'])) {
            $directives = array_merge($directives, $options['extra']);
        }

        header(
            'Content-Security-Policy: ' . implode('; ', $directives)
        );

        return $nonce;
    }
}