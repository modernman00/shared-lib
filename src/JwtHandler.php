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

        LoginUtility::logAudit($userId, $user['email'], 'success', $_SERVER['REMOTE_ADDR'] ?? null, $_SERVER['HTTP_USER_AGENT'] ?? null);

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
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                   (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        $secure = !in_array($env, ['local', 'development'], true) && $isHttps;
        $httponly = true;
        $expire = time() + (int) ($_ENV['COOKIE_EXPIRE'] ?? 2592000);

        // Set secure cookie unconditionally to enforce HttpOnly session security
        if (!empty($tokenName)) {
            $cookieOptions = [
                'expires' => $expire,
                'path' => '/',
                'secure' => $secure,
                'httponly' => $httponly,
                'samesite' => 'Lax',
            ];
            if (!empty($_ENV['COOKIE_DOMAIN'])) {
                $cookieOptions['domain'] = $_ENV['COOKIE_DOMAIN'];
            }
            setcookie($tokenName, $generatedToken, $cookieOptions);
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
    /**
     * Sensitive columns that must NEVER be serialised into client-side JWT cookies.
     *
     * @var list<string>
     */
    private const SENSITIVE_USER_KEYS = [
        'password',
        'password_hash',
        'totp_secret',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'recovery_codes',
        'remember_token',
        'api_token',
        'api_key',
        'secret_key',
        'national_insurance',
        'nino',
        'bank_account_number',
        'sort_code',
    ];

    /**
     * Strip sensitive credential, 2FA, and financial columns before encoding user data into a JWT.
     *
     * @param array<string, mixed> $user
     * @return array<string, mixed>
     */
    public static function sanitiseJwtUserClaims(array $user): array
    {
        foreach (self::SENSITIVE_USER_KEYS as $sensitiveKey) {
            unset($user[$sensitiveKey]);
        }
        return $user;
    }

    public static function jwtEncodeData(array $user, ?int $ttlSeconds = null): string
    {
        $safeUser = self::sanitiseJwtUserClaims($user);
        $ttl = $ttlSeconds ?? (int) ($_ENV['COOKIE_EXPIRE'] ?? 2592000);
        $appUrl = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');
        $token = [
            'iss' => $appUrl,
            'aud' => $appUrl,
            'iat' => time(),
            'nbf' => time(),
            'exp' => time() + $ttl,
            'data' => $safeUser,
            'sub' => (string) ($safeUser['id'] ?? ''),
            'role' => $safeUser['role'] ?? 'users',
            'token_version' => $safeUser['token_version'] ?? 1,
        ];

        $jwtKey = $_ENV['JWT_KEY'] ?? $_ENV['JWT_TOKEN'] ?? '';
        if (empty($jwtKey)) {
            throw new \InvalidArgumentException("JWT key is not defined in environment variables (JWT_KEY or JWT_TOKEN)");
        }

        return JWT::encode($token, $jwtKey, 'HS256');
    }

    public static function jwtEncodeDataAndSetCookies(array $user, ?string $cookieName = null, ?int $ttlSeconds = null): string
    {
        $jwt = self::jwtEncodeData($user, $ttlSeconds);
        $cookieName = ($cookieName !== null && $cookieName !== '')
            ? $cookieName
            : ($_ENV['COOKIE_NAME_GENERAL'] ?? 'auth_forgot');
        $env = $_ENV['APP_ENV'] ?? 'production';
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
                   (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');
        $secure = !in_array($env, ['local', 'development'], true) && $isHttps;
        $httponly = true;
        $ttl = $ttlSeconds ?? (int) ($_ENV['COOKIE_EXPIRE'] ?? 2592000);
        $expire = time() + $ttl;

        $cookieOptions = [
            'expires' => $expire,
            'path' => '/',
            'secure' => $secure,
            'httponly' => $httponly,
            'samesite' => 'Lax',
        ];
        if (!empty($_ENV['COOKIE_DOMAIN'])) {
            $cookieOptions['domain'] = $_ENV['COOKIE_DOMAIN'];
        }

        $success = setcookie($cookieName, $jwt, $cookieOptions);

        if (!$success) {
            throw new \Exception("Failed to set auth cookie '{$cookieName}'. Secure: " . ($secure ? 'true' : 'false'));
        }

        return $jwt;
    }

    // decode JWT token
    public static function jwtDecodeData(?string $cookieName = null): object
    {
        $cookieName = ($cookieName !== null && $cookieName !== '')
            ? $cookieName
            : ($_ENV['COOKIE_NAME_GENERAL'] ?? 'auth_forgot');
        if (!isset($_COOKIE[$cookieName])) {
            throw new UnauthorisedException("Missing authentication cookie '{$cookieName}' 🍪");
        }

        $token = $_COOKIE[$cookieName];
        // Basic sanity check before decode
        if (substr_count($token, '.') !== 2) {
            throw new UnauthorisedException("Invalid JWT format in cookie '{$cookieName}'");
        }

        $jwtKey = $_ENV['JWT_KEY'] ?? $_ENV['JWT_TOKEN'] ?? '';
        if (empty($jwtKey)) {
            throw new UnauthorisedException("JWT key is not configured");
        }

        // Decode and verify JWT using HS256 algorithm
        try {
            // Try decoding with the primary active key
            $decoded = JWT::decode($token, new Key($jwtKey, 'HS256'));
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
        $userId = $decoded->data->id ?? $decoded->id ?? null;
        $userEmail = $decoded->data->email ?? $decoded->email ?? null;
        $userParam = $userId ?? $userEmail;

        // Enforce cryptographic domain separation (iss & aud) when APP_URL is configured
        // so a JWT minted on one portfolio app cannot be replayed against another app sharing a key.
        $expectedAppUrl = rtrim((string) ($_ENV['APP_URL'] ?? ''), '/');
        if ($expectedAppUrl !== '') {
            $tokenIss = isset($decoded->iss) ? rtrim((string) $decoded->iss, '/') : '';
            $tokenAud = isset($decoded->aud) ? rtrim((string) $decoded->aud, '/') : '';
            if (($tokenIss !== '' && $tokenIss !== $expectedAppUrl) || ($tokenAud !== '' && $tokenAud !== $expectedAppUrl)) {
                throw new UnauthorisedException('JWT issuer or audience mismatch for this application domain');
            }
        }

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
            // Log only: the caller turns null into an UnauthorisedException that is reported to
            // the client. Echoing here as well produced two JSON documents in one response.
            try {
                \Src\LoggerFactory::getLogger()->error('JwtHandler::fetchUser database error: ' . $e->getMessage());
            } catch (\Throwable) {
                error_log('JwtHandler::fetchUser database error: ' . $e->getMessage());
            }

            return null;
        }
    }
}
