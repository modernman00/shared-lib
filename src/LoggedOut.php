<?php

declare(strict_types=1);

namespace Src;

use Psr\Log\LoggerInterface;
use Src\RedirectInterface;

class LoggedOut implements RedirectInterface
{
    private LoggerInterface $logger;


    // Constructor Injection: Dependencies are provided when the service is instantiated
    public function __construct(
        LoggerInterface $logger,
    ) {
        $this->logger = $logger;
    }

    /**
     * Executes the logout sequence for the current user.
     *
     * @param string $redirectPath The path to redirect to after logout.
     * @param array $options Optional settings, e.g., 'clear_other_sessions' => true.
     * @throws \RuntimeException If session management fails unexpectedly.
     */
    public function logout(string $redirectPath = '/login', array $options = []): void
    {
        // Capture data *before* session destruction
        $currentSessionId = session_id();
        $userId = $this->getAuthenticatedUserId();

        try {
            // Ensure session is started before attempting to manipulate it
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            // Clear all session variables
            $_SESSION = [];

            // Invalidate the session cookie in the client's browser
            if (ini_get("session.use_cookies")) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000, // Expire in the past
                    $params["path"],
                    $params["domain"],
                    $params["secure"],
                    $params["httponly"]
                );
            }

            // Finally, destroy the session data on the server
            session_destroy();

            // Regenerate session ID immediately after logout to prevent session fixation
            // This effectively starts a *new* empty session, but with a fresh ID.
            session_regenerate_id(true);


            $this->logger->info("User ID {$userId} logged out successfully. Session ID: {$currentSessionId}");

        } catch (\Throwable $e) {
            showError($e);
          
            
        }

        // Redirect the user
        $this->redirect($redirectPath);
    }

    /**
     * Placeholder method to get the authenticated user's ID.
     * This method would interact with your actual authentication system (e.g., session, security context).
     */
    private function getAuthenticatedUserId(): ?int
    {
        // Example: Retrieve from session if that's where your user ID is stored
        return $_SESSION['user_id'] ?? $_SESSION['ID'] ?? $_SESSION['auth']['ID'] ?? $_SESSION['auth']['id'] ?? $_SESSION['auth']['user_id'] ?? $_SESSION['id'] ?? null;
    }

    public function redirect(string $uri, int $statusCode = 302): void
    {
        http_response_code($statusCode);
        header("Location: $uri");
        exit(); // Stops further execution
    }
}