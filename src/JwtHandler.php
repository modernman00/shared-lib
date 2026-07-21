<?php

declare(strict_types=1);

namespace Src;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Src\Exceptions\{NotFoundException, UnauthorisedException};
use Src\LoginUtility as CheckSanitise;

/**
 * JwtHandler -.
 *
 * Manages user authentication and JWT token generation.
 *
 * Notes:
 * - Designed for modular use across login flows.
 * - Encodes secure tokens with RS256.
 * - Supports "remember me" cookie-based logic.
 * - Uses .env configuration for expiry, domain, and strictness.
 */
class JwtHandler
{

    /**
     * Initializes expiration time based on .env setting.
     * Uses London timezone for consistency with UK deployments.
     */
    public function __construct()
    {
        date_default_timezone_set('Europe/London');
    }

    /**
     * Authenticates a user via email and password, issues JWT token, and sets secure cookie if requested.
     *
     * 🔐 Authentication Flow:
     * 1. Sanitizes input (`email`, `password`) using defined length constraints.
     * 2. Locates user by email and verifies password hash.
     * 3. Logs successful login attempt for audit trail.
     * 4. Generates JWT token and optionally sets a secure cookie if `rememberMe` is enabled.
     * 5. Sends a 2FA recovery code via email and stores timestamp/session data.
     *
     * ⚙️ Required Environment Variables:
     * - `COOKIE_TOKEN_LOGIN` — Name of the cookie to store the JWT (e.g. `auth_token`, `login_token`)
     * - `COOKIE_EXPIRE` — Expiry time for the cookie in seconds
     * - `APP_ENV` — Used to determine cookie strictness (`local`, `development`, `production`)
     * - `APP_URL` — Used to extract domain for cookie scope
     * - `PATH_TO_SENT_CODE_NOTIFICATION` — Path to the email view template for sending 2FA code
     * - `SUSPICIOUS_ALERT` — Optional flag for triggering alerts on suspicious login attempts
     *
     * 🍪 Cookie Behavior:
     * - Cookie is only set if `rememberMe` is present in the POST payload.
     * - Cookie is `secure` and `httponly` in production environments with HTTPS.
     *
     * 🧠 Developer Notes:
     * - Password is removed from the returned user payload for safety.
     * - Audit logs include IP and user agent for traceability.
     * - Session variables `auth.2FA_token_ts` and `auth.identifyCust` are set for downstream verification.
     * - This method does not handle login throttling or brute-force protection—consider integrating `Limiter`.
     *
     * @param array $input Login credentials containing 'email' and 'password'
     *
     * @return array ['token' => string, 'userId' => int]
     *
     * @throws NotFoundException If user is not found or password is invalid
     */
    public static function authenticate(array $input, ?string $table = null): array
    {
        \Src\LoginUtility::checkIpBan(\Src\Utility::getUserIpAddr());

        $sanitised = CheckSanitise::getSanitisedInputData($input, [
            'data' => ['email', 'password'],
            'min'  => [5, 5],
            'max'  => [50, 100],
        ]);


        $user = CheckSanitise::useEmailToFindData($sanitised, $table);

        CheckSanitise::checkPassword($sanitised, $user, $table);
        // If user is found and password is verified, check if the user exists in the database

        $userId = $user['id'];

        LoginUtility::logAudit($userId, $user['email'], 'success', $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);

        // remove password from user data
        unset($user['password']);

        $generatedToken = self::jwtEncodeData($user);

        // Stash the password-verified user so the persistent login cookie can be
        // issued once Token::verifyToken() confirms the emailed code. Password
        // verification alone must NOT grant access via the auth cookie - doing so
        // here would let anyone with just the password bypass 2FA entirely.
        $_SESSION['auth']['pendingUser'] = $user;

        // Issue and optionally send recovery token via email and sets sessions $_SESSION['auth']['2FA_token_ts'] and $_SESSION['auth']['identifyCust']
        $pathToSentCodeNotification = $_ENV['PATH_TO_SENT_CODE_NOTIFICATION'];

        Token::generateSendTokenEmail($user, $pathToSentCodeNotification);

        // 🛑 CRITICAL SECURITY FIX: Never return the raw JWT token to the frontend payload.
        // Returning the token here before 2FA is completed would allow an attacker to bypass 2FA.
        return [
            'status' => 'pending_2fa',
            'userId' => $userId,
        ];
    }

    /**
     * Issues the persistent JWT login cookie.
     *
     * ⚠️ Must only be called after 2FA verification has succeeded (see
     * Token::verifyToken(), which invokes this automatically once the emailed
     * code matches). Calling this right after a password check, before the code
     * is confirmed, reintroduces the 2FA bypass.
     *
     * @param array $user Sanitised user payload (no password) to embed in the JWT
     *
     * @return string The encoded JWT that was placed in the cookie
     */
    public static function issueLoginCookie(array $user): string
    {
        $generatedToken = self::jwtEncodeData($user);
        $tokenName = $_ENV['COOKIE_TOKEN_LOGIN'] ?? 'auth_token';

        /**
         * Strictness control:
         * - true for production
         * - false for development and testing
         */
        $env = $_ENV['APP_ENV'] ?? 'production';
        $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

        $secure = !in_array($env, ['local', 'development'], true) && $isHttps;
        $httponly = true;
        $domain = parse_url($_ENV['APP_URL'], PHP_URL_HOST);

        // Set secure cookie unconditionally to enforce HttpOnly session security
        if (!empty($tokenName)) {
            setcookie(
                $tokenName,
                $generatedToken,
                time() + (int) $_ENV['COOKIE_EXPIRE'],
                '/',
                $domain,
                $secure,
                $httponly
            );
        }

        // 🛑 CRITICAL SECURITY FIX: Never return the raw JWT token to the frontend payload.
        // The token is now exclusively managed via the HttpOnly cookie.
        return 'SUCCESSFUL';
    }

    /**
     * Encodes JWT token using RS256 algorithm.
     *
     * Payload includes:
     * - iss/aud: issuer and audience match APP_URL
     * - iat/nbf/exp: issued at, not before, expiry times
     * - data: full user record
     * - sub: user ID as string
     * - role: default to 'users' if absent
     *
     * @param array $user - User data must contain 'id', 'role', 'email'
     *
     * @return string - Encoded JWT string
     */
    public static function jwtEncodeData(array $user): string
    {
        $token = [
            'iss' => $_ENV['APP_URL'],
            'aud' => $_ENV['APP_URL'],
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + (int) $_ENV['COOKIE_EXPIRE'],
            'data' => $user,
            'sub' => (string) $user['id'],
            'role' => $user['role'] ?? 'users',
            'token_version' => $user['token_version'] ?? 1,
        ];

        return JWT::encode($token, $_ENV['JWT_KEY'], 'HS256');
    }

    public static function jwtEncodeDataAndSetCookies(array $user, string $cookieName = 'auth_forgot'): string
    {
        $jwt = self::jwtEncodeData($user);
        $cookieName = $_ENV['COOKIE_NAME_GENERAL'] ?? $cookieName;
        $secure = (!in_array($_ENV['APP_ENV'], ['local', 'development']) && isset($_SERVER['HTTPS']));
        $httponly = true;
        $domain = parse_url($_ENV['APP_URL'], PHP_URL_HOST);
        $success = setcookie(
            $cookieName,
            $jwt,
            time() + (int) $_ENV['COOKIE_EXPIRE'],
            '/',
            $domain,
            $secure,
            $httponly
        );

        if (!$success) {
            throw new \Exception("Failed to set auth cookie. Domain: {$domain}, Secure: " . ($secure ? 'true' : 'false'));
        }

        return $jwt;
    }

    // decode JWT token
    public static function jwtDecodeData(string $cookieName = 'auth_forgot'): object
    {
        $cookieName = $_ENV['COOKIE_NAME_GENERAL'] ?? $cookieName;
        if (!isset($_COOKIE[$cookieName])) {
            throw new UnauthorisedException("Missing authentication cookie '{$cookieName}' 🍪");
        }

        $token = $_COOKIE[$cookieName];
        // Basic sanity check before decode
        if (substr_count($token, '.') !== 2) {
            throw new UnauthorisedException("Invalid JWT format in cookie '{$cookieName}'");
        }

        // Decode and verify JWT using RS256 algorithm
        try {
            // Try decoding with the primary active key
            $decoded = JWT::decode($token, new Key($_ENV['JWT_KEY'], 'HS256'));
        } catch (\Throwable $e) {
            // Graceful Key Overlapping: Fallback to the previous key if primary fails
            if (!empty($_ENV['JWT_KEY_PREVIOUS'])) {
                try {
                    $decoded = JWT::decode($token, new Key($_ENV['JWT_KEY_PREVIOUS'], 'HS256'));
                    // If successful, seamlessly re-issue the cookie using the NEW primary key
                    if (isset($decoded->data)) {
                        self::jwtEncodeDataAndSetCookies((array) $decoded->data, $cookieName);
                    }
                } catch (\Throwable $e2) {
                    throw new UnauthorisedException("Invalid JWT signature (rotation failed)");
                }
            } else {
                throw new UnauthorisedException("Invalid JWT signature");
            }
        }
        $userId = $decoded->data->id ?? $decoded->id;
        $userEmail = $decoded->data->email ?? $decoded->email;
        $userParam = $userId ?? $userEmail;

          if (!$userParam) {
            throw new UnauthorisedException("JWT missing user identifier");
        }

        $tokenVersion = $decoded->data->token_version ?? $decoded->token_version ?? 1;
        $result = self::fetchUser($userParam, $tokenVersion);

        if ($result === null) {
            throw new UnauthorisedException("User session invalid or revoked");
        }

        return $decoded;
    }

    public static function fetchUser(int|string $user_id_email, int $tokenVersion = 1): ?string
    {
        try {
            $dbTable = $_ENV['DB_TABLE_LOGIN'] ?? 'users';

            $query = "SELECT email, token_version FROM $dbTable WHERE id = ? OR email = ?";
            $stmt = Db::connect2()->prepare($query);
            $stmt->execute([$user_id_email, $user_id_email]);

            $user = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$user) {
                return null;
            }

            if (isset($user['token_version']) && (int) $user['token_version'] !== (int) $tokenVersion) {
                 return null;
            }

            return 'SUCCESSFUL';
        } catch (\PDOException $e) {
            Utility::showError($e);

            return null;
        }
    }
}
