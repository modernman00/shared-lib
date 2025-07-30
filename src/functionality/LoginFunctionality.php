<?php

declare(strict_types=1);

namespace Src\functionality;

use Src\{Utility, CorsHandler, Recaptcha, CheckToken, Limiter, JwtHandler};
use Src\Exceptions\NotFoundException;
use Src\Exceptions\UnauthorisedException;

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

    public static function show(array $session, string $sessionName, string $viewPath): void
    {
        if (!isset($session[$sessionName])) {
            throw new UnauthorisedException('NOT SURE WE KNOW YOU');
        }

        // Optional: trigger view layer response (depends on app structure)
        Utility::view2($viewPath);
    }

  /**
   * Processes the login request and returns authentication outcome.
   *
   * Flow:
   * 1. Sanitize input and extract identifier.
   * 2. Apply CORS headers and CAPTCHA verification.
   * 3. Enforce rate limiting based on identifier.
   * 4. Authenticate credentials using JwtHandler.
   * 5. Respond with success (JWT or session-based) or throw exception.
   * $_ENV must have COOKIE_TOKEN_NAME for cookie handling, JWT_KEY_PUBLIC for JWT validation, and CAPTCHA_KEY for reCAPTCHA, JWT_KEY_PRIVATE for signing DB_TABLE_LOGIN for login table name
   *
   * @param array $input - Login payload, expected to include 'email' or 'username'.
   * @param string $captchaAction - Contextual action label for CAPTCHA verification.
   * @param bool $issueJwt - Flag to determine if JWT token should be issued.
   *
   * @throws NotFoundException - If post data is missing.
   */
  public static function login(array $input,  bool $issueJwt = true): void
  {
    try {
      // Allow flexibility between 'email' and 'username' login styles
      $email = Utility::cleanSession($input['email']) ?? Utility::cleanSession($input['username']) ?? '';

      // Set required CORS headers
      CorsHandler::setHeaders();

      // CAPTCHA to prevent bot submissions
      Recaptcha::verifyCaptcha();

      // Rate limiting by identifier (email or username)
      Limiter::limit($email);

      // Token integrity validation
      CheckToken::tokenCheck('token');

      // Authenticate user and generate JWT tokens if requested
      $jwtService = new JwtHandler();
      [$token, $user] = $jwtService->authenticate($input);

      self::onSuccessfulLogin($user, $token, $issueJwt);
      
    } catch (\Throwable $th) {
      // Allow calling code to handle specific failure scenarios
      throw $th;
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
  private function onSuccessfulLogin(array $user, array $token, bool $issueJwt = true): void
  {
    // Prevent brute-force abuse by clearing rate limits
    Limiter::$argLimiter->reset();
    Limiter::$ipLimiter->reset();


    // Mitigate session fixation vulnerabilities
    session_regenerate_id(true);

    if ($issueJwt) {
      // Return JWT token to client (e.g. SPA or mobile client)
      \msgSuccess(200, 'Login Successful', $token);
    } else {
      // Store user ID in session for classic web login
      $_SESSION['ID'] = $user['id'];
      \msgSuccess(200, 'Login Successful');
    }
  }
}
