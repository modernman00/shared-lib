<?php

declare(strict_types=1);

namespace Src\functionality\middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Src\Db;
use Src\Exceptions\UnauthorisedException;
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
     *
     * @throws UnauthorisedException if token or role are invalid
     */
    public function handle(): mixed
    {
        $tokenName = $_ENV['COOKIE_TOKEN_LOGIN'] ?? 'auth_token';
        $token = $_COOKIE[$tokenName] ?? '';

        if (empty($token)) {
            throw new UnauthorisedException('Missing authentication cookie 🍪');
        }

            // Decode and verify JWT using HS256 algorithm
            $decoded = JWT::decode($token, new Key($_ENV['JWT_KEY'], 'HS256'));

            // Fallback: extract role from either `data` or direct payload
            $role = $decoded->data->role ?? $decoded->role;

            // Role enforcement
            if (!in_array($role, $this->allowedRoles, true)) {
                throw new UnauthorisedException("Access denied for role: {$role}");
            }

            $tokenVersion = $decoded->data->token_version ?? $decoded->token_version ?? 1;

            // Ensure user exists in DB (optional integrity check)
            $result = $this->fetchUser($decoded->data->id ?? $decoded->id, $tokenVersion);

            if ($result === null) {
                throw new UnauthorisedException("User not found or session revoked");
            }

            // GET THE FAMCODE 
            $id = $decoded->data->id ?? $decoded->id;
            $email = $decoded->data->email ?? $decoded->email;
  if($email){
                $_SESSION['auth']['email'] = $email;
            }


            return [
                'id' => $id,
                'email' => $email,
                'role' => $role
             
            ];

          

    }

    /**
     * Ensures that the user ID exists in the database table.
     *
     * - The target table is defined by $_ENV['DB_TABLE_LOGIN'] (defaults to 'users').
     * - Only validates presence via email match, returns status string.
     *
     * @param int $user_id - The ID of the user from JWT payload
     *
     * @return string|null - 'SUCCESSFUL' if user exists, null otherwise
     */
    protected function fetchUser(int|string $user_id, int $tokenVersion = 1): ?string
    {
 
            $dbTable = $_ENV['DB_TABLE_LOGIN'] ?? 'users';
            $id = checkInput($user_id);
            $query = "SELECT email, token_version FROM $dbTable WHERE id = ?";
            $stmt = Db::connect2()->prepare($query);
            $stmt->execute([$id]);
            
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$user) {
                return null;
            }

            if (isset($user['token_version']) && (int) $user['token_version'] !== (int) $tokenVersion) {
                 return null;
            }

            return 'SUCCESSFUL';

    }
}
