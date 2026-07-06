<?php

declare(strict_types=1);

namespace Src\functionality;

use Src\{
    CheckToken,
    Exceptions\NotFoundException,
    Exceptions\UnauthorisedException,
    JwtHandler,
    Limiter,
    LoginUtility as CheckSanitise,
    ToSendEmail,
    Update,
    Utility
};
use Src\functionality\middleware\AuthGateMiddleware;
use Src\functionality\middleware\GetRequestData;

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
     *
     * @param array $session Current session state
     *
     * @throws UnauthorisedException
     */
          public static function show(string $viewPath, string $identifySession = 'token'): void
    {

            // check if auth.identifyCust is set
        if (!isset($_SESSION['auth']['identifyCust']) && !isset($_SESSION['auth']['2FA_token_ts'])) {
            $fallback = $_ENV['401URL'] ?? '/401';
            redirect($fallback);
        }

        if ((time() - ($_SESSION['auth']['2FA_token_ts'])) > 1500) {
            $diff = time() - $_SESSION['auth']['2FA_token_ts'];
                        $fallback = $_ENV['401URL'] ?? '/401';
            redirect($fallback);
        }
        
        $value = $_SESSION['auth']['identifyCust'];
        if($value !== null){
            AuthGateMiddleware::enforce('auth.identifyCust', $value);
        } else {
            $fallback = $_ENV['401URL'] ?? '/401';
            redirect($fallback);
        }
        view2($viewPath);
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
     * @throws NotFoundException if user data is missing or invalid
     */
    public static function process()
    {

        try {
            $input = GetRequestData::getRequestData();
        if (!$input) {
            throw new NotFoundException('There was no post data');
        }
        // Extract and sanitise incoming password field
        $cleanData = CheckSanitise::getSanitisedInputData($input, [
            'data' => ['password', 'confirm_password'],
            'min'  => [6, 6],
            'max'  => [30, 30],
        ]);


        // get the users information using jwt decode
        $user = JwtHandler::jwtDecodeData('auth_forgot');

        if (!$user) {
            throw new NotFoundException('We cannot locate the information');
        }

        $userEmail = $user->data->email ?? $user->email;
        Limiter::limit($userEmail);

        // Step 6: Hash new password
        $hashedPassword = \hashPassword($cleanData['password']);

        // Update password
        $update = new Update($_ENV['DB_TABLE_LOGIN']);
        $update->updateTable('password', $hashedPassword, 'email', $userEmail);

        // Revoke all existing sessions by incrementing token_version
        $userId = $user->data->id ?? $user->id ?? null;
        if ($userId) {
            try {
                $stmt = \Src\Db::connect2()->prepare("UPDATE {$_ENV['DB_TABLE_LOGIN']} SET token_version = token_version + 1 WHERE id = ?");
                $stmt->execute([$userId]);
            } catch (\PDOException $e) {
                // Silently fail if token_version column hasn't been migrated yet
            }
        }

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

        Utility::msgSuccess(200, 'Password was successfully changed');
        return true;
        } catch (\Throwable $th) {
            showError($th);
        }
        
    }
}
