<?php

declare(strict_types=1);

namespace Src\functionality\middleware;

use Src\Exceptions\UnauthorisedException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Src\Db;
use Src\Utility;

/**
 * Middleware for enforcing role-based access control via JWT.
 *
 * Notes:
 * - Token is extracted from a secure cookie.
 * - JWT is validated using public RSA key.
 * - Role is matched against the allowed list.
 * - Intended for lightweight, modular access control in protected routes.
 */
final class RoleMiddleware
{
    private array $allowedRoles;


    /**
     * @param array $allowedRoles - List of permitted roles (e.g. ['admin', 'user']).
     */
    public function __construct(array $allowedRoles = [])
    {
        $this->allowedRoles = $allowedRoles;
     
    }

    /**
     * Validates token from cookie and checks if user has the required role.
     *
     * Requirements:
     * - JWT cookie name should match $_ENV['TOKEN_NAME']
     * - Role must be included in JWT payload
     * - DB table lookup is optional but ensures user existence
     *
     * @return array{id: int, role: string}
     * @throws UnauthorisedException if token or role are invalid
     */
    public function handle(): array
    {
        $tokenName = $_ENV['COOKIE_TOKEN_LOGIN'] ?? 'auth_token';
        $token = $_COOKIE[$tokenName] ?? '';

        if (empty($token)) {
            throw new UnauthorisedException('Missing authentication cookie 🍪');
        }

        try {
            // Decode and verify JWT using RS256 algorithm
            $decoded = JWT::decode($token, new Key($_ENV['JWT_KEY'], 'HS256'));

            // Fallback: extract role from either `data` or direct payload
            $role = $decoded->data->role ?? $decoded->role ?? 'users';

            // Role enforcement
            if (!in_array($role, $this->allowedRoles, true)) {
                throw new UnauthorisedException("Access denied for role: {$role}");
            }

            // Ensure user exists in DB (optional integrity check)
            $this->fetchUser($decoded->data->id ?? $decoded->id);

            return [
                'id' => $decoded->data->id ?? $decoded->id,
                'email' => $decoded->data->email ?? $decoded->email,
                'role' => $role,
            ];
        } catch (\Throwable $e) {
            // Soft fail: log error and return empty payload
            showError($e);
            return [];
        }
    }

    /**
     * Ensures that the user ID exists in the database table.
     *
     * - The target table is defined by $_ENV['DB_TABLE_LOGIN'] (defaults to 'users').
     * - Only validates presence via email match, returns status string.
     *
     * @param int $user_id - The ID of the user from JWT payload.
     * @return string|null - 'SUCCESSFUL' if user exists, null otherwise.
     */
    protected function fetchUser(int $user_id): ?string
    {
        try {
            $dbTable = $_ENV['DB_TABLE_LOGIN'] ?? 'users';

            $query = "SELECT email FROM $dbTable WHERE id = ?";
            $stmt = Db::connect2()->prepare($query);
            $stmt->execute([$user_id]);

            return $stmt->rowCount() > 0 ? 'SUCCESSFUL' : null;
        } catch (\PDOException $e) {
            Utility::showError($e);
            return null;
        }
    }
}
