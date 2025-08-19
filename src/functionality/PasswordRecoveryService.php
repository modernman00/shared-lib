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
            redirect('/error/401');
        }

        // Optional: trigger view layer response (depends on app structure)
        Utility::view2($viewPath);
    }

    /**
 * Handles a forgot-password recovery request and initiates token-based authentication.
 *
 * This function is triggered via JavaScript when the recovery form is submitted.
 * It applies security controls, validates input, locates the user, and issues a recovery token.
 *
 * 🔄 Recovery Flow:
 * 1. Apply CORS headers for cross-origin access.
 * 2. Verify CAPTCHA to prevent automated abuse.
 * 3. Enforce rate limiting based on the user's email.
 * 4. Validate and sanitize input fields.
 * 5. Locate the user record in the database.
 * 6. Generate a JWT token and optionally send a recovery email.
 * 7. Finalize recovery by setting session variables and issuing token.
 *
 * ⚙️ Required Environment Variables (set in `.env`):
 * - `DB_TABLE_CODE_MGT` — Name of the database table used for managing recovery codes.
 *
 * 📁 Required View Configuration:
 * - `$pathToSentCodeNotification` — Path to the view file used for rendering the recovery token message.
 *
 * 🧠 Developer Notes:
 * - JavaScript must be used to handle the form submission and trigger this function.
 * - Recovery email sets `$_SESSION['auth']['2FA_token_ts']` and `$_SESSION['auth']['identifyCust']`.
 *
 * @param string $pathToSentCodeNotification  Path to the view file for token notification.
 * @param bool   $issueJwt                    Whether to issue a JWT token during recovery.
 *
 * @throws NotFoundException                  If input is missing or user cannot be found.
 */

    public static function process(bool $issueJwt = true): void
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
              $pathToSentCodeNotification = $_ENV['PATH_TO_SENT_CODE_NOTIFICATION']; 
            Token::generateSendTokenEmail($user, $pathToSentCodeNotification);

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
            \msgSuccess(200, 'Recovery token sent to your email successfully', $token);
        } else {

            \msgSuccess(200, 'Recovery token sent to your email successfully');
        }
    }
}
