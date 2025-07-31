<?php

declare(strict_types=1);

namespace Src;

use Firebase\JWT\JWT;
use Src\Sanitise\checkSanitise as CheckSanitise;
use Src\Exceptions\NotFoundException;


/**
 * JwtHandler -
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
    /** @var int $expiredTime - Unix timestamp for token expiry */
    private int $expiredTime;

    /**
     * Initializes expiration time based on .env setting.
     * Uses London timezone for consistency with UK deployments.
     */
    public function __construct()
    {
        date_default_timezone_set('Europe/London');
        $this->expiredTime = time() + (int)$_ENV['COOKIE_EXPIRE'];
    }

    /**
     * Authenticates user via email & password, returns token if valid.
     *
     * Flow:
     * 1. Sanitises input using validation rules.
     * 2. Finds user by email and verifies password.
     * 3. Generates JWT token payload and sets cookie if "rememberMe" is enabled.
     * $COOKIE_TOKEN_NAME must be set in .env. IT COULD BE 'auth_token' or login_token.
     *
     * @param array $input - Login data containing 'email' and 'password'
     * @return array - ['token' => string, 'user' => array]
     * @throws NotFoundException - If credentials are invalid
     */
    public function authenticate(array $input): array
    {
        $sanitised = CheckSanitise::getSanitisedInputData($input, [
            'data' => ['email', 'password'],
            'min'  => [5, 5],
            'max'  => [30, 100]
        ]);

        $user = CheckSanitise::useEmailToFindData($sanitised);

        if(empty($user)) {
            throw new NotFoundException('Oops! No user found with that email.');
        }

        $verified = CheckSanitise::checkPassword($sanitised, $user);

        if (!$verified) {
            throw new NotFoundException('Oops! Wrong email or password.');
        }

        // If user is found and password is verified, check if the user exists in the database
        $confirmedUser = CheckSanitise::findUserByEmailPassword(
            $sanitised['email'], 
            $sanitised['password']
        );

        if (empty($confirmedUser)) {
            throw new NotFoundException("Oops! We can't find you.");
        }

    
        $generatedToken = $this->jwtEncodeData($user);

        $rememberMe = isset($_POST['rememberMe']) ? 'true' : 'false';
        $tokenName = $_ENV['COOKIE_TOKEN_NAME'] ?? 'auth_token';

         /**
         * Strictness control:
         * - true for production
         * - false for development and testing
         */
        $secure = (!in_array($_ENV['APP_ENV'], ['local', 'development']) && isset($_SERVER['HTTPS']));
        $httponly = true;
        $domain = parse_url($_ENV['APP_URL'], PHP_URL_HOST);

        // Set secure cookie only if not already present and rememberMe is checked
        if (!empty($tokenName) && $rememberMe) {
            setcookie(
                $tokenName, 
                $generatedToken, 
                $this->expiredTime,
                '/',
                $domain,
                $secure,
                $httponly
            );
        }
        return [
            'token' => $generatedToken,
            'user' => $user
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
     * @return string - Encoded JWT string
     */
    public function jwtEncodeData(array $user): string
    {
        $token = [
            'iss' => $_ENV['APP_URL'],
            'aud' => $_ENV['APP_URL'],
            'iat' => time(),
            'nbf' => time(),
            'exp' => $this->expiredTime,
            'data' => $user,
            'sub' => (string)$user['id'],
            'role' => $user['role'] ?? 'users',
        ];

        return JWT::encode($token, $_ENV['JWT_KEY'], 'HS256');
    }
}
