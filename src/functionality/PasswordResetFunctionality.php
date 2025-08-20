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
use Src\functionality\middleware\AuthGateMiddleware;

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
     
        AuthGateMiddleware::enforce('auth.codeVerified');
        view($viewPath);
    }

    /**
 * Handles a password change request using session-based email and token authentication.
 *
 * This function is triggered via JavaScript when the user submits the password change form.
 * It validates the input, verifies the token, updates the password, and sends a confirmation email.
 *
 * 🔐 Password Change Flow:
 * 1. Decode and sanitize incoming POST data (`password`, `confirm_password`).
 * 2. Verify token integrity using `CheckToken`.
 * 3. Decode user identity from JWT (`auth_forgot`).
 * 4. Enforce rate limiting based on the user's email.
 * 5. Hash the new password securely using bcrypt.
 * 6. Update the password in the login table.
 * 7. Send a confirmation email using the provided view template.
 * 8. Clear rate limits, destroy session and cookies, and respond with success.
 *
 * ⚙️ Required Environment Variables (set in `.env`):
 * - `DB_TABLE_LOGIN` — Name of the database table used for storing login credentials.
 * - `PATH_TO_PASSWORD_CHANGE_NOTIFICATION` — Path to the view file used for rendering the password change confirmation message.
 *
 * 🧠 Developer Notes:
 * - JavaScript must be used to handle the form submission and trigger this function.
 * - JWT must be issued and stored in session under `auth_forgot` before this function is called.
 * - This function clears `$_SESSION['token']` and destroys the session and cookies after execution.
 *
 * @throws NotFoundException                   If user data is missing or invalid.
 */

    public static function process(): void
    {
        $input = json_decode(file_get_contents('php://input'), true);
           if (!$input) {
                throw new NotFoundException('There was no post data');
            }
        // Extract and sanitise incoming password field
        $cleanData = CheckSanitise::getSanitisedInputData($input, [
            'data' => ['password', 'confirm_password'],
            'min'  => [6, 6],
            'max'  => [30, 30],
        ]);
         $token = $input['token'] ?? '';
        // Token integrity validation
        CheckToken::tokenCheck($token);

        // get the users information using jwt decode 
        $user = JwtHandler::jwtDecodeData('auth_forgot');

        if (!$user) {
            throw new NotFoundException('We cannot locate the information');
        }

        $userEmail = $user->data->email ?? $user->email;

        Limiter::limit($userEmail);

        // Step 6: Hash new password
        $hashedPassword = password_hash($cleanData['password'], PASSWORD_DEFAULT, ['cost' => 12]);


        // Update password 
        $update = new Update($_ENV['DB_TABLE_LOGIN']);

        $update->updateTable('password', $hashedPassword, 'email', $userEmail);
        $pathToPwdChangeNotification = $_ENV['PATH_TO_PASSWORD_CHANGE_NOTIFICATION'];


        $emailData = ToSendEmail::genEmailArray(
            viewPath: $pathToPwdChangeNotification,
            data: ['email' => $userEmail],
            subject: 'PASSWORD CHANGE'
        );

        ToSendEmail::sendEmailGeneral($emailData, 'member');

        // Prevent brute-force abuse by clearing rate limits
        Limiter::$argLimiter->reset();
        Limiter::$ipLimiter->reset();

        unset($_SESSION['token']);

        // DESTROY SESSION
        session_destroy();
        // DESTROY COOKIES
        \destroyCookie();

        Utility::msgSuccess(200, "Password was successfully changed");
    }
}
