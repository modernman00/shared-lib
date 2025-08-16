<?php

declare(strict_types=1);

namespace Src\functionality;

use Src\ErrorCollector;
use Src\Exceptions\NotFoundException;
use Src\Exceptions\UnauthorisedException;
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

      CorsHandler::setHeaders();
      Recaptcha::verifyCaptcha($input);
      Limiter::limit($email);
      $token = $input['token'] ?? '';
      CheckToken::tokenCheck($token);
      
      // Authenticate user and generate JWT tokens if requested

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
  private static function onSuccessfulLogin(string $userId, string $token, bool $issueJwt = true): void
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
      \msgSuccess(200, 'Login Successful', $token);
    } else {
      // Store user ID in session for classic web login
      $_SESSION['ID'] = $userId;
      \msgSuccess(200, 'Login Successful');
    }
  }
}
