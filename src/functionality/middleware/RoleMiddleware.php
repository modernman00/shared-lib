<?php

declare(strict_types=1);

namespace Src\functionality\middleware;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Src\Db;
use Src\Exceptions\UnauthorisedException;
use Src\Utility;

/**xxxxxx
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
            try {
                $decoded = JWT::decode($token, new Key($_ENV['JWT_KEY'], 'HS256'));
            } catch (\Throwable $e) {
                if (!empty($_ENV['JWT_KEY_PREVIOUS'])) {
                    try {
                        $decoded = JWT::decode($token, new Key($_ENV['JWT_KEY_PREVIOUS'], 'HS256'));
                        if (isset($decoded->data)) {
                            \Src\JwtHandler::jwtEncodeDataAndSetCookies((array) $decoded->data, $tokenName);
                        }
                    } catch (\Throwable $e2) {
                        throw new UnauthorisedException('Invalid JWT signature (rotation failed)');
                    }
                } else {
                    throw new UnauthorisedException('Invalid JWT signature');
                }
            }
            // Fallback: extract role from either `data` or direct payload
            $role = $decoded->data->role ?? $decoded->role;

            // Role enforcement
            if (!in_array($role, $this->allowedRoles, true)) {
                throw new UnauthorisedException("Access denied for role: {$role}");
            }

            $tokenVersion = (int) ($decoded->data->token_version ?? $decoded->token_version ?? 1);

            // Ensure user exists in DB (optional integrity check)
            $result = $this->fetchUser($decoded->data->id ?? $decoded->id, $tokenVersion);

            if ($result === null) {
                throw new UnauthorisedException("User not found or session revoked");
            }

            // GET THE FAMCODE 
          
                        $id = $decoded->data->id ?? $decoded->id ?? null;
            
            $email = null;
            if (isset($decoded->data->email)) {
                $email = $decoded->data->email;
            } elseif (isset($decoded->email)) {
                $email = $decoded->email;
            }

            if ($email) {
                $_SESSION['auth']['email'] = $email;
            }

            if ($id) {
                // Red Team Guard: Prevent cross-account session pollution/desync
                if (!empty($_SESSION['id']) && (string) $_SESSION['id'] !== (string) $id) {
                    $_SESSION = [];
                    if (session_status() === PHP_SESSION_ACTIVE) {
                        session_regenerate_id(true);
                    }
                    if ($email) {
                        $_SESSION['auth']['email'] = $email;
                    }
                }

                $_SESSION['id'] = $id;
                $_SESSION['auth']['identifyCust'] = $id;
                $_SESSION['auth']['codeVerified'] = true;
                $_SESSION['auth']['2FA_token_ts'] = $_SESSION['auth']['2FA_token_ts'] ?? time();
            }

            return [
                'id' => $id,
                'email' => $email,
                'role' => $role,
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
        try {
            $dbTable = $_ENV['DB_TABLE_LOGIN'] ?? 'users';
            $id = checkInput($user_id);
            
            try {
                $query = "SELECT email, token_version FROM $dbTable WHERE id = ?";
                $stmt = Db::connect2()->prepare($query);
                $stmt->execute([$id]);
                $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            } catch (\PDOException $pe) {
                // Fallback if table does not have token_version column
                $query = "SELECT email FROM $dbTable WHERE id = ?";
                $stmt = Db::connect2()->prepare($query);
                $stmt->execute([$id]);
                $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            }
            
            if (!$user && strlen((string) $id) > 10) {
                $truncatedId = substr((string) $id, 0, 10);
                try {
                    $query = "SELECT email, token_version FROM $dbTable WHERE id = ?";
                    $stmt = Db::connect2()->prepare($query);
                    $stmt->execute([$truncatedId]);
                    $user = $stmt->fetch(\PDO::FETCH_ASSOC);
                } catch (\PDOException $pe2) {
                    $query = "SELECT email FROM $dbTable WHERE id = ?";
                    $stmt = Db::connect2()->prepare($query);
                    $stmt->execute([$truncatedId]);
                    $user = $stmt->fetch(\PDO::FETCH_ASSOC);
                }
            }

            if (!$user) {
                return null;
            }

            if (isset($user['token_version']) && (int) $user['token_version'] !== (int) $tokenVersion) {
                 return null;
            }

            return 'SUCCESSFUL';
        } catch (\Throwable $e) {
            error_log('RoleMiddleware fetchUser error: ' . $e->getMessage());
            return null;
        }
    }
}
