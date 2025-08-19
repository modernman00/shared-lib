<?php

declare(strict_types=1);

namespace Src\functionality;

use Src\Exceptions\NotFoundException;
use Src\{Utility, CorsHandler, Recaptcha, CheckToken, Limiter, JwtHandler};

/**
 * Handles user login functionality within the application.
 *
 * Notes:
 * - Supports both session-based and JWT-based authentication flows.
 * - Applies rate limiting and CAPTCHA validation.
 * - Designed for integration into a reusable authentication library.
 */
class LoginFunctionality
{

    public static function show(string $viewPath): void
    {

        // Optional: trigger view layer response (depends on app structure)
        
        view2($viewPath);
    }

  /**
 * Authenticates a login request and returns either a JWT or session-based response.
 *
 * This function is triggered via JavaScript when the login form is submitted.
 * It performs input sanitization, CAPTCHA verification, rate limiting, and credential authentication.
 *
 * 🔐 Authentication Flow:
 * 1. Sanitize input and extract user identifier (e.g., email or username).
 * 2. Apply CORS headers and verify CAPTCHA using the provided action label.
 * 3. Enforce rate limiting based on the identifier to prevent brute-force attempts.
 * 4. Authenticate credentials using JwtHandler.
 * 5. Respond with a JWT (if $issueJwt is true) or session-based login.
 *
 * ⚙️ Required Environment Variables (set in `.env`):
 * - `COOKIE_TOKEN_LOGIN` — Name of the cookie used to store the token.
 * - `JWT_KEY_PUBLIC` — Public key for validating JWTs.
 * - `JWT_KEY_PRIVATE` — Private key for signing JWTs.
 * - `CAPTCHA_KEY` — Secret key for reCAPTCHA verification.
 * - `DB_TABLE_LOGIN` — Name of the login table in your database.
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
 *
 * 🗂️ Required Database Setup:
 * - Create an `audit_logs` table to track login attempts and authentication events.
 *
 * 🧠 Developer Notes:
 * - Password is removed from the returned user payload for safety.
 * Ensure to set role in your DB table for role-based access control (e.g. `users` table).
 * - Audit logs include IP and user agent for traceability.
 * - Session variables `auth.2FA_token_ts` and `auth.identifyCust` are set for downstream verification.
 *
 * @param array $input           Login payload, must include 'email' or 'username'.
 * @param string $captchaAction  Action label used for CAPTCHA verification.
 * @param bool $issueJwt         Whether to issue a JWT token upon successful login.
 *
 * @throws NotFoundException     If the login payload is missing or malformed.
 */

  public static function login(bool $issueJwt = true): void
  {
    try {
      $input = json_decode(file_get_contents('php://input'), true);
      // Allow flexibility between 'email' and 'username' login styles
      $email = Utility::cleanSession($input['email']) ?? Utility::cleanSession($input['username']) ?? '';

      CorsHandler::setHeaders();
      Recaptcha::verifyCaptcha($input);
      Limiter::limit($email);
      $token = $input['token'] ?? '';
      CheckToken::tokenCheck($token);
      
      // Authenticate user, send code and generate JWT tokens if requested

      $userD = JwtHandler::authenticate($input);

      if (!is_array($userD) || !isset($userD['token'], $userD['userId'])) {
          throw new \UnexpectedValueException('Malformed authentication result');
      }
      $token = $userD['token'];
      $userId = $userD['userId'];

      self::onSuccessfulLogin($userId, $token,  $issueJwt);
      
    } catch (\Throwable $th) {
      // Allow calling code to handle specific failure scenarios
      showError($th);
    }
  }

  /**
   * Finalizes login by resetting limits and returning success response.
   *
   * Internal Logic:
   * - Resets rate limit counters.
   * - Performs post-auth token integrity check.
   * - Regenerates session ID to prevent fixation attacks.
   * - Responds with JWT tokens if applicable, or binds session ID.
   *
   * @param array $user - Authenticated user payload.
   * @param array $token - JWT token set (access, refresh, etc).
   * @param bool $issueJwt - Whether to return JWT or session-based response.
   */
  private static function onSuccessfulLogin(string | int $userId, string $token, bool $issueJwt = true): void
  {
    // Prevent brute-force abuse by clearing rate limits
    Limiter::$argLimiter->reset();
    Limiter::$ipLimiter->reset();

    // After successful login unset the CSRF token to prevent reuse
    unset($_SESSION['token']);

    // Mitigate session fixation vulnerabilities
    session_regenerate_id(true);

    if ($issueJwt) {
      // Return JWT token to client (e.g. SPA or mobile client)
      \msgSuccess(200, 'Verification code sent to your email successfully', $token);
    } else {
      // Store user ID in session for classic web login
      $_SESSION['ID'] = $userId;
      \msgSuccess(200, 'Verification code sent to your email successfully');
    }
  }
}
