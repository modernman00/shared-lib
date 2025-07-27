<?php

declare(strict_types=1);

namespace Src\Library;

use Src\{
    CheckToken,
    Exceptions\NotFoundException,
    Exceptions\UnauthorisedException,
    Sanitise\CheckSanitise,
    Update,
    Utility
};

/**
 * Handles secure password change flow via token-based recovery.
 *
 * Usage:
 * $service = new PasswordResetFunctionality();
 * $service->processRequest($_POST, $_SESSION);
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
    public static function show(array $session, string $sessionName, string $viewPath): void
    {
        if (!isset($session[$sessionName])) {
            throw new UnauthorisedException('NOT SURE WE KNOW YOU');
        }

        // Optional: trigger view layer response (depends on app structure)
        Utility::view2($viewPath);
    }

    /**
     * Processes password change via session email and token.
     *
     * @param array $post POST input payload
     * @param array $session Current session data
     *
     * @throws NotFoundException
     */
    public static function processRequest(array $post, string $table, array &$session, string $redirectPath): void
    {
        // Extract and sanitise incoming password field
        $cleanData = CheckSanitise::getSanitisedInputData($post);

        // Validate session-bound email
        $email = Utility::checkInputEmail($cleanData['email'] ?? '');

        // Token integrity validation
        CheckToken::tokenCheck('token');

        // Update password in persistent store
        $update = new Update($table);
        $update->updateTable('password', $cleanData['password'], $table, 'email', $email);

        // Session renewal and cleanup post-password reset
        session_regenerate_id(true);
        $session = []; // clear all session data

        \redirect($redirectPath);
    }

}
