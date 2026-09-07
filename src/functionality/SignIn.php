<?php

declare(strict_types=1);

namespace Src\functionality;

use Src\Exceptions\UnauthorisedException;
use Src\functionality\middleware\RoleMiddleware;

/**
 * SignIn functionality for role-based user access.
 *
 * Notes:
 * - Validates authentication and ensures the user has the required role.
 * - Wraps middleware access logic into a reusable verification method.
 * - Intended for lightweight role-gating in protected routes.
 */
final class SignIn
{
    /**
     * Verifies user authentication and role membership using middleware.
     *
     * This method enforces access control by validating the user's JWT token and checking
     * their assigned role. It returns the authenticated user payload if valid, or an empty
     * array if unauthorized.
     *
     * 🔐 Verification Flow:
     * 1. Instantiate `RoleMiddleware` with the required role (e.g. `'users'`, `'admin'`).
     * 2. Middleware checks for a valid JWT and confirms role membership.
     * 3. If valid, returns user data; if invalid, logs error and returns an empty array.
     *
     * 🧠 Developer Notes:
     * - This method is typically used at the start of protected controller actions or API endpoints.
     * - It gracefully handles unauthorized access by catching `UnauthorisedException`.
     * - The returned user payload includes `id`, `email`, and `role`.
     *
     * ⚙️ Required Setup:
     * - JWT must be issued and stored in a cookie or header before this method is called.
     * - `RoleMiddleware` must be configured to parse and validate the token.
     *
     * 📦 Example Usage:
     * ```php
     * $user = SignIn::verify('admin');
     * if (empty($user)) {
     *     // Redirect to login or show access denied
     *     exit('Unauthorized access');
     * }
     * echo "Welcome, {$user['email']}";
     * ```
     *
     * @param string $role The required user role (default: 'users')
     *
     * @return array{id: int, email: string, role: string} Authenticated user data
     */
    public static function verify($role = 'users')
    {
        // Prepare role-based gate
        $roleGate = new RoleMiddleware([$role]);

        // 🔒 Auth + Role enforcement
        return $roleGate->handle();
    }

    /**
     * Silently checks if a user is currently logged in without throwing an exception.
     * This is useful for auto-redirecting authenticated users away from login pages.
     *
     * @param string $role The required user role (default: 'users')
     * @return bool True if logged in with a valid token, false otherwise.
     */
    public static function isLoggedIn($role = 'users'): bool
    {
        try {
            $user = self::verify($role);
            return !empty($user);
        } catch (UnauthorisedException $e) {
            // Token is missing, expired, or tampered with.
            // Safely swallow the exception so the app doesn't crash.
            return false;
        }
    }

    /**
     * Silently and defensively rehydrates the PHP session from a valid auth_token cookie
     * if the session was flushed by PHP's garbage collector or closed browser tabs.
     * This protects all portfolio apps against premature session drops.
     *
     * @param string|null $cookieName Custom cookie name or default from env
     * @return array|null The user payload if successfully rehydrated, or null
     */
    public static function rehydrateSession(?string $cookieName = null): ?array
    {
        if (session_status() === PHP_SESSION_NONE) {
            \Src\SecureSession::start();
        }

        if (!empty($_SESSION['id']) && !empty($_SESSION['auth']['identifyCust'])) {
            return $_SESSION['user'] ?? null; // Session is already active and valid
        }

        $tokenName = $cookieName ?? $_ENV['COOKIE_TOKEN_LOGIN'] ?? 'auth_token';
        $token = $_COOKIE[$tokenName] ?? '';
        if (empty($token) || !is_string($token)) {
            return null; // No auth cookie present
        }

        try {
            $decoded = \Src\JwtHandler::jwtDecodeData($tokenName);
            $userData = (array) ($decoded->data ?? []);
            $userId = (string) ($userData['id'] ?? $decoded->sub ?? '');

            if (!empty($userId)) {
                // Red Team Guard: Protect against cross-account session pollution
                if (!empty($_SESSION['id']) && (string) $_SESSION['id'] !== (string) $userId) {
                    $_SESSION = [];
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_regenerate_id(true);
                    }
                }

                $_SESSION['id'] = $userId;
                $_SESSION['manager_id'] = $userId;
                $_SESSION['auth'] = [
                    'identifyCust' => $userId,
                    'email' => (string) ($userData['email'] ?? ''),
                    'codeVerified' => true,
                    '2FA_token_ts' => time(),
                    'type' => (string) ($userData['type'] ?? 'user'),
                ];
                $_SESSION['user'] = $userData;
                $_SESSION['user_id'] = $userId;

                return $userData;
            }
        } catch (\Throwable $e) {
            // Invalid, expired, or revoked token: gracefully let session expire
            return null;
        }

        return null;
    }
}
