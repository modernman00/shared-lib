<?php

declare(strict_types=1);

namespace Src;

/**
 * Class I18n
 *
 * Enterprise, lightweight, zero-npm internationalization and translation engine.
 * Provides cascading dictionary-based translations across all ecosystem platforms.
 *
 * Supports British English (en-GB default), French (fr), Spanish (es), and extensible custom locales.
 * Implements strict allowlist validation against Local File Inclusion (LFI) and Directory Traversal.
 */
class I18n
{
    /**
     * Default supported locales with metadata (name, native label, and flag icon).
     *
     * @var array<string, array{name: string, native: string, flag: string}>
     */
    public const DEFAULT_LOCALES = [
        'en' => ['name' => 'English (UK)', 'native' => 'English', 'flag' => '🇬🇧'],
        'us' => ['name' => 'English (US)', 'native' => 'English (US)', 'flag' => '🇺🇸'],
        'fr' => ['name' => 'French', 'native' => 'Français', 'flag' => '🇫🇷'],
        'es' => ['name' => 'Spanish', 'native' => 'Español', 'flag' => '🇪🇸'],
    ];

    public const DEFAULT_LOCALE = 'en';

    /**
     * Active supported locales registry.
     *
     * @var array<string, array{name: string, native: string, flag: string}>
     */
    private static array $supportedLocales = self::DEFAULT_LOCALES;

    /**
     * Active in-memory locale (overrides or falls back when session/cookie unavailable).
     */
    private static ?string $activeLocale = null;

    /**
     * Optional path to the host application's custom dictionaries directory.
     */
    private static ?string $appDictionaryPath = null;

    /**
     * In-memory cache of loaded translation dictionaries.
     * Structured as: [locale => [key => translation]]
     *
     * @var array<string, array<string, string>>
     */
    private static array $dictionaries = [];

    /**
     * Get the active application locale.
     */
    public static function getLocale(): string
    {
        if (self::$activeLocale !== null && isset(self::$supportedLocales[self::$activeLocale])) {
            return self::$activeLocale;
        }

        if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['app_locale'])) {
            $sessLocale = (string)$_SESSION['app_locale'];
            if (isset(self::$supportedLocales[$sessLocale])) {
                return $sessLocale;
            }
        }

        if (!empty($_COOKIE['app_locale'])) {
            $cookieLocale = (string)$_COOKIE['app_locale'];
            if (isset(self::$supportedLocales[$cookieLocale])) {
                return $cookieLocale;
            }
        }

        return self::DEFAULT_LOCALE;
    }

    /**
     * Set the active application locale with strict allowlist validation.
     * Rejects directory traversal, null bytes, and unregistered locale codes.
     */
    public static function setLocale(string $locale): bool
    {
        $clean = strtolower(trim($locale));
        if (!isset(self::$supportedLocales[$clean])) {
            return false;
        }

        self::$activeLocale = $clean;

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['app_locale'] = $clean;
        }

        // Set persistent cookie for 1 year if headers have not yet been sent
        if (!headers_sent()) {
            setcookie('app_locale', $clean, [
                'expires' => time() + 31536000,
                'path' => '/',
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        }

        return true;
    }

    /**
     * Get all supported locales.
     *
     * @return array<string, array{name: string, native: string, flag: string}>
     */
    public static function getSupportedLocales(): array
    {
        return self::$supportedLocales;
    }

    /**
     * Register or override a supported locale dynamically.
     */
    public static function registerLocale(string $code, string $name, string $native, string $flag): void
    {
        $clean = strtolower(trim($code));
        self::$supportedLocales[$clean] = [
            'name' => $name,
            'native' => $native,
            'flag' => $flag,
        ];
    }

    /**
     * Set the host application's dictionary directory path.
     */
    public static function setAppDictionaryPath(?string $path): void
    {
        self::$appDictionaryPath = $path !== null ? rtrim($path, '/\\') : null;
        self::$dictionaries = []; // Clear loaded cache on path change
    }

    /**
     * Get the host application's dictionary directory path.
     */
    public static function getAppDictionaryPath(): ?string
    {
        if (self::$appDictionaryPath !== null) {
            return self::$appDictionaryPath;
        }

        // Auto-discovery: check standard application resources/lang directory if present
        $candidates = [];
        if (defined('ROOT_DIR') && is_string(ROOT_DIR) && ROOT_DIR !== '') {
            $candidates[] = ROOT_DIR . '/resources/lang';
        }
        $candidates[] = dirname(__DIR__, 4) . '/resources/lang';

        foreach ($candidates as $cand) {
            if (is_dir($cand)) {
                self::$appDictionaryPath = rtrim($cand, '/\\');
                return self::$appDictionaryPath;
            }
        }

        return null;
    }

    /**
     * Check if a translation key exists in either active locale or default fallback.
     */
    public static function has(string $key): bool
    {
        $locale = self::getLocale();
        self::ensureDictionaryLoaded($locale);

        if (isset(self::$dictionaries[$locale][$key])) {
            return true;
        }

        if ($locale !== self::DEFAULT_LOCALE) {
            self::ensureDictionaryLoaded(self::DEFAULT_LOCALE);
            return isset(self::$dictionaries[self::DEFAULT_LOCALE][$key]);
        }

        return false;
    }

    /**
     * Translate a given key into the current locale.
     *
     * Implements dual-dictionary cascading:
     * 1. Host application dictionary for $locale
     * 2. Shared-lib base dictionary for $locale
     * 3. Host application dictionary for default locale (en)
     * 4. Shared-lib base dictionary for default locale (en)
     * 5. Fallback string or key
     *
     * @param string $key Dotted translation key (e.g. 'nav.home', 'auth.create_account')
     * @param array<string, string|int|float> $replace Placeholder replacements (e.g. ['name' => 'Sarah'])
     * @param string|null $fallback Fallback text if key is missing in all dictionaries
     * @return string
     */
    public static function trans(string $key, array $replace = [], ?string $fallback = null): string
    {
        $locale = self::getLocale();
        self::ensureDictionaryLoaded($locale);

        $line = self::$dictionaries[$locale][$key] ?? null;

        // Fall back to British English dictionary if not found in active locale
        if ($line === null && $locale !== self::DEFAULT_LOCALE) {
            self::ensureDictionaryLoaded(self::DEFAULT_LOCALE);
            $line = self::$dictionaries[self::DEFAULT_LOCALE][$key] ?? null;
        }

        // Fallback to explicit default parameter or key
        if ($line === null) {
            $line = $fallback ?? $key;
        }

        // Variable interpolation: supports both :param and {param} formats
        if (!empty($replace)) {
            foreach ($replace as $k => $v) {
                $line = str_replace([':' . $k, '{' . $k . '}'], (string)$v, $line);
            }
        }

        return $line;
    }

    /**
     * Ensure dictionaries for the given locale are loaded into memory.
     */
    private static function ensureDictionaryLoaded(string $locale): void
    {
        if (isset(self::$dictionaries[$locale])) {
            return;
        }

        $clean = preg_replace('/[^a-z]/', '', strtolower($locale)) ?: self::DEFAULT_LOCALE;
        $merged = [];

        // 1. Load shared-lib base dictionary first
        $baseFile = dirname(__DIR__) . '/resources/lang/' . $clean . '.php';
        if (file_exists($baseFile)) {
            $baseData = require $baseFile;
            if (is_array($baseData)) {
                $merged = $baseData;
            }
        }

        // 2. Load host application dictionary to allow overrides or custom keys
        $appPath = self::getAppDictionaryPath();
        if ($appPath !== null) {
            $appFile = $appPath . '/' . $clean . '.php';
            if (file_exists($appFile)) {
                $appData = require $appFile;
                if (is_array($appData)) {
                    $merged = array_merge($merged, $appData);
                }
            }
        }

        self::$dictionaries[$locale] = $merged;
    }

    /**
     * Reset in-memory state (useful for test isolation).
     */
    public static function reset(): void
    {
        self::$activeLocale = null;
        self::$appDictionaryPath = null;
        self::$dictionaries = [];
        self::$supportedLocales = self::DEFAULT_LOCALES;
    }
}
