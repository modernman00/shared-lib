<?php

declare(strict_types=1);

namespace Src;

use Src\Exceptions\HttpException;
use Src\Exceptions\UnauthorisedException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JWTTokenValidator
{
    protected string $headerToken;

    public function __construct(string $headerToken)
    {
        $this->headerToken = $headerToken;
    }

    /**
     * Verifies the JWT and checks if the user exists
     * JWT_TOKEN_PUBLIC_KEY is the public key for JWT verification
     * JWT_TOKEN is the private key for JWT generation
     * $TABLE is the table to look up the user in
     *
     * @return string|null 'SUCCESSFUL' or null if invalid
     */
    public function isAuth($table = 'users' ?? 'account'): ?string
    {
        try {
            // Try to extract Bearer token from Authorization header
            if (preg_match('/Bearer\s(\S+)/', $this->headerToken, $matches)) {
                $token = $matches[1];
            } else {
                throw new HttpException('Authorization header missing or malformed');
            }

            // Decode token using RS256 and public key
            $decoded = JWT::decode($token, new Key($_ENV['JWT_PUBLIC_KEY'], 'RS256'));

            // Check if token payload contains user ID
            if (!isset($decoded->sub)) {
                throw new UnauthorisedException('Invalid token payload');
            }

            // Fetch user by ID (for extra verification)
            return $this->fetchUser((int) $decoded->sub, $table);
        } catch (\Throwable $th) {
            Utility::showError($th);
            return null;
        }
    }

    /**
     * Lookup user in database by ID
     */
    protected function fetchUser(int $user_id, string $table): ?string
    {
        try {
            $dbTaable = $table;

            $query = "SELECT email FROM $dbTaable WHERE id = ?";
            $stmt = Db::connect2()->prepare($query);
            $stmt->execute([$user_id]);

            return $stmt->rowCount() > 0 ? 'SUCCESSFUL' : null;
        } catch (\PDOException $e) {
            Utility::showError($e);
            return null;
        }
    }




}
