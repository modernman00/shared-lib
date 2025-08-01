<?php

declare(strict_types=1);

namespace Src\functionality;

use Src\{
    CorsHandler,
    Recaptcha,
    Limiter,
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
 * $service = new PasswordRecoveryService();
 * $service->processRecovery($postInput, 'emailResetView');
 */
class PasswordRecoveryService
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
     * Process forgot-password recovery request.
     *
     * Flow:
     * 1. Apply CORS and CAPTCHA security controls.
     * 2. Apply rate limiting for the target identifier.
     * 3. Validate and sanitise input.
     * 4. Locate user record.
     * 5. Generate and optionally send recovery token.
     *
     * @param array $input Raw POST payload (e.g. ['email' => 'user@example.com'])
     * @param string $viewPath Optional path used in recovery email rendering
     *
     * @throws NotFoundException If input is invalid or user not found
     */
    public static function processRecovery(array $input, string $viewPath): void
    {
        try {
            CorsHandler::setHeaders();               // Apply CORS headers for API access
            Recaptcha::verifyCaptcha('forgot');      // Verify CAPTCHA against brute force
            Limiter::limit($input['email']);         // Rate limit by email address

            if (empty($input)) {
                throw new NotFoundException('Missing recovery input');
            }

            // Apply field-level sanitisation constraints
            $sanitised = CheckSanitise::getSanitisedInputData($input, [
                'data' => ['email'],
                'min'  => [5],
                'max'  => [30],
            ]);

            // Attempt to locate user record
            $user = CheckSanitise::useEmailToFindData($sanitised);

            if (empty($user)) {
                throw new NotFoundException('User not found');
            }

            // Issue and optionally send recovery token via email
            Token::generateSendTokenEmail($user, $viewPath);

            self::finaliseRecovery();
        } catch (\Throwable $error) {
            showError($error);
        }
    }

    /**
     * Finalise recovery flow after token delivery.
     * Handles session security and user messaging.
     */
    private function finaliseRecovery(): void
    {
        Limiter::$argLimiter->reset();              // Reset argument-based rate limiter
        Limiter::$ipLimiter->reset();               // Reset IP-level rate limiter

        CheckToken::tokenCheck();                   // Revalidate token integrity
        session_regenerate_id(true);                // Prevent session fixation attack

        \msgSuccess(200, 'Recovery token sent successfully'); // Response for client use
    }
}
