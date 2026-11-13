<?php

namespace helpers\classes;

class CSP
{
    public static function apply(array $options = []): string
    {
        $nonce = defined('CSP_NONCE') ? (string) CSP_NONCE : bin2hex(random_bytes(16));
        if (!defined('CSP_NONCE')) {
            define('CSP_NONCE', $nonce);
        }

        $directives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-$nonce' https: 'unsafe-eval' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://kit.fontawesome.com https://ka-f.fontawesome.com https://unpkg.com https://d3js.org https://canvasjs.com",
            "script-src-attr 'unsafe-inline'",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://fonts.bunny.net https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://kit.fontawesome.com https://ka-f.fontawesome.com https://unpkg.com",
            "font-src 'self' data: https://fonts.gstatic.com https://fonts.bunny.net https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://ka-f.fontawesome.com",
            "img-src 'self' data: blob: https:",
            "connect-src 'self' https: wss: https://*.pusher.com wss://*.pusher.com https://*.pusherapp.com https://www.google.com https://www.gstatic.com https://region1.google-analytics.com https://www.google-analytics.com https://*.google-analytics.com https://*.analytics.google.com https://*.googletagmanager.com https://ka-f.fontawesome.com https://*.sentry.io https://cdn.jsdelivr.net",
            "frame-src 'self' https://www.google.com https://recaptcha.google.com https://www.youtube-nocookie.com https://www.youtube.com https://player.vimeo.com",
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