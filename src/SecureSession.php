<?php

declare(strict_types=1);

namespace Src;

class SecureSession
{
    // CREATE SESSION LIFETIME CONSTANT
    public const SESSION_LIFETIME = 3600; // 60 minutes

    /**
     * Returns true if the app is running in production mode.
     *
     * Determines production mode by checking if the APP_ENV environment variable is set to "production" or if HTTPS is enabled.
     *
     * @return bool
     */
    private static function isProduction()
    {
        $isProduction = ($_ENV['APP_ENV'] === 'production') || (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on');

        return $isProduction;
    }

    /**
     * Start a secure session if none exists.
     *
     * Configures PHP session settings to:
     *  - expire after 30 minutes
     *  - be restricted to the current path
     *  - be restricted to the current HTTP host
     *  - use HTTPS if the site is in production
     *  - be accessible only via the HTTP protocol
     *  - be accessible from the same site (Lax)
     * Enables strict mode and sets the garbage collection max lifetime to 30 minutes.
     * Starts the session.
     * If the session is new, sets the security markers (CREATED, IP and UA) to the current values.
     */
    public static function start()
    {
        if (session_status() === PHP_SESSION_NONE) {
            $isProd = self::isProduction();

            session_set_cookie_params([
                'lifetime' => self::SESSION_LIFETIME,
                'path' => '/',
                'domain' => $_SERVER['HTTP_HOST'],
                'secure' => $isProd,
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            if ($isProd) {
                ini_set('session.cookie_secure', '1');
            }
            ini_set('session.use_strict_mode', '1');
            ini_set('session.gc_maxlifetime', self::SESSION_LIFETIME);
            session_start();

            // Initialize security markers if new session
            if (empty($_SESSION['CREATED'])) {
                self::regenerate();
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
        $_SESSION['IP'] = $_SERVER['REMOTE_ADDR'];
        $_SESSION['UA'] = $_SERVER['HTTP_USER_AGENT'];
    }

    /**
     * Validate the session based on the security markers.
     * If the validation fails, it destroys the session and returns false.
     *
     * @return bool
     */
    public static function validate()
    {
        if ($_SESSION['IP'] !== $_SERVER['REMOTE_ADDR'] ||
            $_SESSION['UA'] !== $_SERVER['HTTP_USER_AGENT']) {
            self::destroy();

            return false;
        }

        // Invalidate idle sessions
        if (time() - $_SESSION['CREATED'] > self::SESSION_LIFETIME) {
            self::destroy();

            return false;
        }

        return true;
    }

    /**
     * Destroys the session, invalidating any further access to it.
     * To be called when the user logs out.
     */
    public static function destroy()
    {
        $_SESSION = [];
        setcookie(session_name(), '', 1, '/');
        session_destroy();
    }
}
