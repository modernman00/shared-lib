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
