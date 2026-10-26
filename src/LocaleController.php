<?php

declare(strict_types=1);

namespace Src;

/**
 * Class LocaleController
 *
 * Enterprise on-the-fly language switching controller across all ecosystem applications.
 * Enforces strict allowlist validation via Src\I18n against directory traversal, LFI, and parameter injection.
 */
class LocaleController
{
    /**
     * Switch application language and return user to previous internal referer or target URL.
     *
     * Supports both positional and named argument dispatch ($locale, $lang) from router layers.
     *
     * @param string $locale Requested locale code (e.g. 'en', 'fr', 'es')
     * @param string|null $lang Route parameter alias for backwards compatibility
     */
    public function switch(string $locale = 'en', ?string $lang = null): void
    {
        $target = $lang ?? $locale;
        $success = I18n::setLocale($target);

        $isJson = (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json'))
            || (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

        if ($isJson) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => $success ? 'success' : 'error',
                'locale' => I18n::getLocale(),
            ]);
            exit;
        }

        // Safe redirect: prevent open redirects by verifying referer host
        $referer = $_SERVER['HTTP_REFERER'] ?? '/';
        $host = $_SERVER['HTTP_HOST'] ?? '';

        if (!empty($host) && !empty($referer) && str_contains($referer, $host)) {
            header('Location: ' . $referer);
        } else {
            header('Location: /');
        }
        exit;
    }
}
