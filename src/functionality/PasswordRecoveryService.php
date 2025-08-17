<?php

declare(strict_types=1);

namespace Src\functionality;

use Src\{
    CorsHandler,
    Recaptcha,
    Limiter,
    JwtHandler,
    Exceptions\NotFoundException,
    Token,
    LoginUtility as CheckSanitise,
    CheckToken,
    Utility,
    Exceptions\UnauthorisedException
};

/**
 * PasswordRecoveryService
 *
 * Handles secure forgot-password flow with sanitisation,
 * CAPTCHA verification, rate limiting, and token issuance.
 *
 * Usage:
 * show function is used to display the forgot-password page
 * the forgot link must have an $_get['verify'] parameter like this  <a href="/appTestForgot?verify=1"> Forgot password? Please click this link</a>
 *  $verify = $_GET['verify'] ?? null;
 * PasswordRecoveryService::show(['verify' => 1]);
 
 * $service->processRecovery();
 */
class PasswordRecoveryService
{

    public static function show($sGet, string $viewPath): void
    {
        if (!isset($sGet)) {
            throw new UnauthorisedException('NOT SURE WE KNOW YOU');
        }

        // Optional: trigger view layer response (depends on app structure)
        Utility::view2($viewPath);
    }

    /**
     * Process forgot-password recovery request.
     * you have to use JS to process the submit button  
     *
     * Flow:
     * 1. Apply CORS and CAPTCHA security controls.
     * 2. Apply rate limiting for the target identifier.
     * 3. Validate and sanitise input.
     * 4. Locate user record.
     * 5. Generate and optionally send recovery token.
     * $viewPath is the path to the view file for the token message
     * MUST HAVE DB_TABLE_CODE_MGT in the .env file
     *
     *
     * @throws NotFoundException If input is invalid or user not found
     */
    public static function process($viewPath, bool $issueJwt = true): void
    {
        try {
            CorsHandler::setHeaders();               // Apply CORS headers for API access
            $input = json_decode(file_get_contents('php://input'), true);

            Recaptcha::verifyCaptcha($input);      // Verify CAPTCHA against brute force
            Limiter::limit($input['email']);         // Rate limit by email address

            if (empty($input)) {
                throw new NotFoundException('Missing recovery input');
            }
            $token = $input['token'] ?? '';
            

            // Apply field-level sanitisation constraints
            $sanitised = CheckSanitise::getSanitisedInputData($input, [
                'data' => ['email'],
                'min'  => [5],
                'max'  => [30],
            ]);

            CheckToken::tokenCheck($token); // Revalidate token integrity

            // Attempt to locate user record
            $user = CheckSanitise::useEmailToFindData($sanitised);

            // create a JWT token
            $token = JwtHandler::jwtEncodeDataAndSetCookies($user, 'auth_forgot');



            if (empty($user)) {
                throw new NotFoundException('User not found');
            }

            // Issue and optionally send recovery token via email and sets sessions $_SESSION['auth']['2FA_token_ts'] and $_SESSION['auth']['identifyCust']
            Token::generateSendTokenEmail($user, $viewPath);

            self::finaliseRecovery($token, $issueJwt);
        } catch (\Throwable $error) {
            showError($error);
        }
    }

    /**
     * Finalise recovery flow after token delivery.
     * Handles session security and user messaging.
     */
    private static function finaliseRecovery(string $token, bool $issueJwt = true): void
    {
        Limiter::$argLimiter->reset();              // Reset argument-based rate limiter
        Limiter::$ipLimiter->reset();               // Reset IP-level rate limiter


         // After successful login unset the CSRF token to prevent reuse
      unset($_SESSION['token']);
        session_regenerate_id(true);                // Prevent session fixation attack

        if ($issueJwt) {
            // Return JWT token to client (e.g. SPA or mobile client)
            \msgSuccess(200, 'Recovery token sent successfully', $token);
          } else {
      
            \msgSuccess(200, 'Recovery token sent successfully');
          }

    }
}
