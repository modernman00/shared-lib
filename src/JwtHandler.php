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
    public static function authenticate(array $input): array
    {
        $sanitised = CheckSanitise::getSanitisedInputData($input, [
            'data' => ['email', 'password'],
            'min'  => [5, 5],
            'max'  => [30, 100],
        ]);

        

        $user = CheckSanitise::useEmailToFindData($sanitised);

        CheckSanitise::checkPassword($sanitised, $user);
        // If user is found and password is verified, check if the user exists in the database

        $userId = $user['id'];

        LoginUtility::logAudit($userId, $user['email'], 'success', $_SERVER['REMOTE_ADDR'], $_SERVER['HTTP_USER_AGENT']);

        // remove password from user data
        unset($user['password']);

        $generatedToken = self::jwtEncodeData($user);
        $rememberMe = isset($input['rememberMe']) ? 'true' : 'false';
        $tokenName = $_ENV['COOKIE_TOKEN_LOGIN'] ?? 'auth_token';

        // Issue and optionally send recovery token via email and sets sessions $_SESSION['auth']['2FA_token_ts'] and $_SESSION['auth']['identifyCust']
        $pathToSentCodeNotification = $_ENV['PATH_TO_SENT_CODE_NOTIFICATION'];

        Token::generateSendTokenEmail($user, $pathToSentCodeNotification);

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

        // Set secure cookie only if not already present and rememberMe is checked
        if (!empty($tokenName) && $rememberMe) {
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

        return [
            'token' => $generatedToken,
            'userId' => $userId,
        ];
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
        ];

        return JWT::encode($token, $_ENV['JWT_KEY'], 'HS256');
    }

    public static function jwtEncodeDataAndSetCookies(array $user, string $cookieName = 'auth_forgot'): string
    {
        $data = self::jwtEncodeData($user);
        $cookieName = $_ENV['COOKIE_NAME_GENERAL'] ?? 'auth_forgot';
        $secure = (!in_array($_ENV['APP_ENV'], ['local', 'development']) && isset($_SERVER['HTTPS']));
        $httponly = true;
        $domain = parse_url($_ENV['APP_URL'], PHP_URL_HOST);
        setcookie(
            $cookieName,
            $data,
            time() + (int) $_ENV['COOKIE_EXPIRE'],
            '/',
            $domain,
            $secure,
            $httponly
        );

        return $data;
    }

    // decode JWT token
    public static function jwtDecodeData(string $cookieName = 'auth_forgot'): object
    {
        $token = $_COOKIE[$_ENV['COOKIE_NAME_GENERAL']] ?? $cookieName;
        if (empty($token)) {
            throw new UnauthorisedException('Missing authentication cookie 🍪');
        }
        // Decode and verify JWT using RS256 algorithm
        $decoded = JWT::decode($token, new Key($_ENV['JWT_KEY'], 'HS256'));

        $userId = $decoded->data->id ?? $decoded->id;
        $userEmail = $decoded->data->email ?? $decoded->email;
        $userParam = $userId ?? $userEmail;

        self::fetchUser($userParam);

        return $decoded;
    }

    public static function fetchUser(int|string $user_id_email): ?string
    {
        try {
            $dbTable = $_ENV['DB_TABLE_LOGIN'] ?? 'users';

            $query = "SELECT email FROM $dbTable WHERE id = ? OR email = ?";
            $stmt = Db::connect2()->prepare($query);
            $stmt->execute([$user_id_email, $user_id_email]);

            return $stmt->rowCount() > 0 ? 'SUCCESSFUL' : null;
        } catch (\PDOException $e) {
            Utility::showError($e);

            return null;
        }
    }
}
