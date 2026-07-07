<?php

declare(strict_types=1);

namespace Src;

use Psr\Log\LoggerInterface;

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
     * @param string $redirectPath the path to redirect to after logout
     * @param array $options Optional settings, e.g., 'clear_other_sessions' => true.
     *
     * @throws \RuntimeException if session management fails unexpectedly
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
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(
                    session_name(),
                    '',
                    time() - 42000, // Expire in the past
                    $params['path'],
                    $params['domain'],
                    $params['secure'],
                    $params['httponly']
                );
            }

            // Invalidate the JWT Auth Cookie
            $tokenName = $_ENV['COOKIE_TOKEN_LOGIN'] ?? 'auth_token';
            if (isset($_COOKIE[$tokenName])) {
                $domain = parse_url($_ENV['APP_URL'], PHP_URL_HOST);

                // Must match the secure/httponly flags used when the cookie was set
                // (see JwtHandler::issueLoginCookie), otherwise browsers will refuse
                // to overwrite/expire a Secure, HttpOnly cookie and it survives logout.
                $env = $_ENV['APP_ENV'] ?? 'production';
                $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
                $secure = !in_array($env, ['local', 'development'], true) && $isHttps;

                setcookie($tokenName, '', time() - 3600, '/', $domain, $secure, true);
            }

            // Revoke all existing sessions by incrementing token_version globally
            if ($userId) {
                try {
                    $dbTable = $_ENV['DB_TABLE_LOGIN'] ?? 'users';
                    $stmt = \Src\Db::connect2()->prepare("UPDATE {$dbTable} SET token_version = token_version + 1 WHERE id = ?");
                    $stmt->execute([$userId]);
                } catch (\PDOException $e) {
                    // Silently fail if token_version column hasn't been migrated yet
                }
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
    private function getAuthenticatedUserId()
    {
        // Try to get ID from JWT cookie first
        $tokenName = $_ENV['COOKIE_TOKEN_LOGIN'] ?? 'auth_token';
        if (isset($_COOKIE[$tokenName])) {
            try {
                $token = $_COOKIE[$tokenName];
                $decoded = \Firebase\JWT\JWT::decode($token, new \Firebase\JWT\Key($_ENV['JWT_KEY'], 'HS256'));
                return $decoded->data->id ?? $decoded->id ?? null;
            } catch (\Throwable $e) {
                // Ignore decoding errors
            }
        }

        // Example: Retrieve from session if that's where your user ID is stored
        return $_SESSION['user_id'] ?? $_SESSION['ID'] ?? $_SESSION['auth']['ID'] ?? $_SESSION['auth']['id'] ?? $_SESSION['auth']['user_id'] ?? $_SESSION['id'] ?? null;
    }

    public function redirect(string $uri, int $statusCode = 302): void
    {
        $uri = url($uri);
        http_response_code($statusCode);
        header("Location: $uri");
        exit(); // Stops further execution
    }
}
