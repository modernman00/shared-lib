<?php 

namespace Src\functionality;

use Src\functionality\middleware\RoleMiddleware;
use Src\Exceptions\UnauthorisedException;

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
 * @return array{id: int, email: string, role: string} Authenticated user data
 */

    public static function verify($role = 'users'): array
    {
        // Prepare role-based gate
        $roleGate = new RoleMiddleware([$role]);

        try {
            // 🔒 Auth + Role enforcement
            $data = $roleGate->handle(); 
            return $data;

        } catch (UnauthorisedException $e) {
            // Graceful failure: log error & return empty response
            showError($e);
            return [];
        }
    }
}
