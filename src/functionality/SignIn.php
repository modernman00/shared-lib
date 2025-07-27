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
     * Verifies user authentication and role membership.
     *
     * Flow:
     * 1. Instantiate RoleMiddleware with desired role.
     * 2. Middleware performs token and role checks.
     * 3. Returns user payload if valid, otherwise shows error.
     *
     * @param string $role - The required user role (e.g. 'users', 'admin').
     * @return array - The authenticated user data (or empty if unauthorized).
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
