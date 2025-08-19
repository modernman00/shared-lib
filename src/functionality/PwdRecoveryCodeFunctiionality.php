<?php

declare(strict_types=1);

namespace Src\functionality;

use Src\functionality\middleware\AuthGateMiddleware;
use Src\{Utility,Token, Limiter, CheckToken, LoginUtility as CheckSanitise,};

class PwdRecoveryCodeFunctiionality
{
  public static function show(string $viewPath): void
  {

    AuthGateMiddleware::enforce('auth.identifyCust');
    Utility::view2($viewPath);
  }

/**
 * Verifies a 2FA code and CSRF token to authorize password reset.
 *
 * This method is part of the password recovery flow. It validates a time-bound 2FA code
 * and a CSRF token, then sets a session flag to allow access to the password reset page.
 *
 * 🔐 Verification Flow:
 * 1. Decode and sanitize incoming POST data (`code`, `token`).
 * 2. Validate the age of the 2FA token stored in session (`2FA_token_ts`).
 * 3. Enforce rate limiting based on the code.
 * 4. Validate CSRF token integrity.
 * 5. Verify the 2FA code using `Token::verifyToken`.
 * 6. If valid, clear session tokens, reset rate limits, and regenerate session ID.
 * 7. Set `$_SESSION['auth']['codeVerified'] = true` to authorize password reset.
 *
 * ⚙️ Session Requirements:
 * - `$_SESSION['auth']['2FA_token_ts']` must be set when the 2FA code is issued.
 * - `$_SESSION['token']` should contain the CSRF token.
 *
 * 🧠 Developer Notes:
 * - This method does not handle password updates directly—it only verifies access.
 * - The frontend should redirect to the password reset page upon success.
 * - Rate limits are cleared to prevent lockout after successful verification.
 *
 * @throws \Exception If token is expired or invalid.
 */

  
  public static function process(): void
  {
    $input = json_decode(file_get_contents('php://input'), true);

    // Validate session-bound email
    $code = $input['code'] ?? '';
    $csrfToken = $input['token'] ?? '';

    $sanitised = CheckSanitise::getSanitisedInputData($input, [
      'data' => ['code'],
      'min'  => [6],
      'max'  => [15],
    ]);

    $code = $sanitised['code'] ?? '';

    if ((time() - ($_SESSION['auth']['2FA_token_ts'])) > 1000) {
      $diff = time() - $_SESSION['auth']['2FA_token_ts'];
      Utility::msgException(401, "Invalid or expired Token $diff");
    }

    Limiter::limit($code);

    // Token integrity validation
    CheckToken::tokenCheck($csrfToken);

    // now check if the code is valid
    $data = Token::verifyToken($code);

    // if code is valid, update password take me to the password reset page
    if ($data) {
      unset($_SESSION['token']);
      // unset the time session 
      unset($_SESSION['auth']['2FA_token_ts']);

      // Prevent brute-force abuse by clearing rate limits
      Limiter::$argLimiter->reset();
      Limiter::$ipLimiter->reset();

      // create the codeVerifiedSession 
      $_SESSION['auth']['codeVerified'] = true;
      // Session renewal and cleanup post-password reset
      session_regenerate_id(true);
      Utility::msgSuccess(200, 'Code verified successfully');
    }
  }
}
