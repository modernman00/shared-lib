<?php

declare(strict_types=1);

namespace Src;

class SecureSession
{
    // CREATE SESSION LIFETIME CONSTANT
    // DEFAULT SESSION LIFETIME (30 days in seconds)
    public const SESSION_LIFETIME = 2592000;

    /**
     * Retrieves the active session lifetime in seconds from environment or default (30 days).
     */
    public static function getLifetime(): int
    {
        if (!empty($_ENV['SESSION_LIFETIME'])) {
            return (int) $_ENV['SESSION_LIFETIME'];
        }
        if (!empty($_ENV['COOKIE_EXPIRE'])) {
            return (int) $_ENV['COOKIE_EXPIRE'];
        }

        return self::SESSION_LIFETIME;
    }

    /**
     * Returns true if the app is running in production mode.
     *
     * Determines production mode by checking if the APP_ENV environment variable is set to "production" or if HTTPS is enabled.
     *
     * @return bool
     */
    private static function isProduction()
    {
        $isProduction = (isset($_ENV['APP_ENV']) && $_ENV['APP_ENV'] === 'production') ||
                        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                        (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        return $isProduction;
    }

    /**
     * Start a secure session if none exists.
     *
     * Configures PHP session settings to:
     *  - expire after configured lifetime (default 30 days)
     *  - be restricted to the current path
     *  - use host-only domain (or optional COOKIE_DOMAIN)
     *  - use HTTPS if the site is in production
     *  - be accessible only via HttpOnly protocol
     *  - use SameSite=Lax
     * Enables strict mode and sets the garbage collection max lifetime.
     * Starts the session.
     * If the session is new, sets the security markers (CREATED, LAST_ACTIVITY, IP and UA).
     */
    public static function start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            $isProd = self::isProduction();
            $lifetime = self::getLifetime();

            $cookieParams = [
                'lifetime' => $lifetime,
                'path' => '/',
                'secure' => $isProd,
                'httponly' => true,
                'samesite' => 'Lax',
            ];
            if (!empty($_ENV['COOKIE_DOMAIN'])) {
                $cookieParams['domain'] = $_ENV['COOKIE_DOMAIN'];
            }

            session_set_cookie_params($cookieParams);
            if ($isProd) {
                ini_set('session.cookie_secure', '1');
            }
            ini_set('session.use_strict_mode', '1');
            ini_set('session.gc_maxlifetime', (string) $lifetime);
            session_start();

            // Initialize security markers if new session
            if (empty($_SESSION['CREATED'])) {
                self::regenerate();
            } else {
                $_SESSION['LAST_ACTIVITY'] = time();
            }
        }
    }

    /**
     * Regenerates the session ID and resets the security markers.
     * Intended to be called after a privilege level change.
     */
    public static function regenerate()
    {
        session_regenerate_id(true);
        $_SESSION['CREATED'] = time();
        $_SESSION['LAST_ACTIVITY'] = time();
        $_SESSION['IP'] = $_SERVER['REMOTE_ADDR'] ?? '';
        $_SESSION['UA'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    }

    /**
     * Validate the session based on the security markers.
     * If the validation fails, it destroys the session and returns false.
     *
     * @return bool
     */
    public static function validate()
    {
        // Protect against session hijacking via User Agent mismatch
        if (isset($_SESSION['UA'], $_SERVER['HTTP_USER_AGENT']) && $_SESSION['UA'] !== $_SERVER['HTTP_USER_AGENT']) {
            self::destroy();

            return false;
        }

        // Strict IP verification can be optionally enabled. By default, disabled to prevent
        // dropping valid mobile users across cell-tower hops and Apple iCloud Private Relay.
        if (!empty($_ENV['SESSION_STRICT_IP']) && isset($_SESSION['IP'], $_SERVER['REMOTE_ADDR']) && $_SESSION['IP'] !== $_SERVER['REMOTE_ADDR']) {
            self::destroy();

            return false;
        }

        // Invalidate idle sessions based on sliding activity window
        $lifetime = self::getLifetime();
        $lastActivity = $_SESSION['LAST_ACTIVITY'] ?? $_SESSION['CREATED'] ?? time();
        if ((time() - $lastActivity) > $lifetime) {
            self::destroy();

            return false;
        }

        // Update sliding window
        $_SESSION['LAST_ACTIVITY'] = time();

        return true;
    }

    /**
     * Destroys the session, invalidating any further access to it.
     * To be called when the user logs out.
     */
    public static function destroy()
    {
        $_SESSION = [];
        $isProd = self::isProduction();
        $cookieOptions = [
            'expires' => time() - 42000,
            'path' => '/',
            'secure' => $isProd,
            'httponly' => true,
            'samesite' => 'Lax',
        ];
        if (!empty($_ENV['COOKIE_DOMAIN'])) {
            $cookieOptions['domain'] = $_ENV['COOKIE_DOMAIN'];
        }

        setcookie(session_name(), '', $cookieOptions);
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_destroy();
        }
    }
}
