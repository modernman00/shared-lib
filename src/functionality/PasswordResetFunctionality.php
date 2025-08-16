<?php

declare(strict_types=1);

namespace Src\functionality;

use Src\{
    CheckToken,
    Exceptions\NotFoundException,
    Exceptions\UnauthorisedException,
    LoginUtility as CheckSanitise,
    ToSendEmail,
    JwtHandler,
    Update,
    Utility,
    Limiter
};

/**
 * Handles secure password change flow via token-based recovery.
 *
 * Usage:
 * PasswordResetFunctionality::processRequest($viewPath);
 */
class PasswordResetFunctionality
{
    /**
     * Validate reset eligibility (e.g. session token present).
     *
     * @param array $session Current session state
     * @return void
     *
     * @throws UnauthorisedException
     */
    public static function show(string $viewPath): void
    {
        if (!isset($_SESSION['auth']['codeVerified'])) {
            throw new UnauthorisedException('NOT SURE WE KNOW YOU');
        }
        // Optional: trigger view layer response (depends on app structure)
        Utility::view2($viewPath);
    }

    /**
     * Processes password change via session email and token.
     *
     * @param array $post POST input payload
     * remember to set the DB_TABLE_LOGIN in the .env file
     * $viewPath is the path to the view file for the PASSWORD CHANGE message
     *
     * @throws NotFoundException
     */
    public static function processRequest($viewPath): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
        // Extract and sanitise incoming password field
        $cleanData = CheckSanitise::getSanitisedInputData($input, [
            'data' => ['password'],
            'min'  => [6],
            'max'  => [30],
        ]);



        // Token integrity validation
        CheckToken::tokenCheck('token');

        // get the users information using jwt decode 
        $user = JwtHandler::jwtDecodeData('auth_forgot');

        $userEmail = $user->data->email ?? $user->email;

        Limiter::limit($userEmail);

        // Update password in persistent store
        $update = new Update($_ENV['DB_TABLE_LOGIN']);

        $update->updateTable('password', $cleanData['password'], $_ENV['DB_TABLE_LOGIN'], 'email', $userEmail);

        $emailData = ToSendEmail::genEmailArray(
            viewPath: $viewPath,
            data: ['email' => $userEmail],
            subject: 'PASSWORD CHANGE'
        );

        ToSendEmail::sendEmailWrapper(var: $emailData, recipientType: 'member');

        // Prevent brute-force abuse by clearing rate limits
        Limiter::$argLimiter->reset();
        Limiter::$ipLimiter->reset();

        unset($_SESSION['token']);

        // Session renewal and cleanup post-password reset
        session_regenerate_id(true);
        // DESTROY SESSION
        session_destroy();
        // DESTROY COOKIES
        setcookie('auth_forgot', '', time() - 3600, '/');
        setcookie('auth_token', '', time() - 3600, '/');

        Utility::msgSuccess(200, "Password was successfully changed");
    }
}
