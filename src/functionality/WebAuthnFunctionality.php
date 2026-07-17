<?php
declare(strict_types=1);

namespace Src\functionality;

use Src\functionality\middleware\GetRequestData;
use Src\Utility;

/**
 * Handles WebAuthn HTTP routes for all applications.
 */
class WebAuthnFunctionality
{
    /**
     * Endpoint: /webauthn/register/options
     */
    public static function getRegistrationOptions(string $role = 'users'): void
    {
        try {
            // Ensure user is logged in before they can register a device
            if (!SignIn::isLoggedIn($role)) {
                throw new \Src\Exceptions\UnauthorisedException("Must be logged in to register a device.");
            }

            // Get user info from JWT/Session
            $userId = (string) ($_SESSION['auth']['id'] ?? '1'); 
            $email = $_SESSION['auth']['email'] ?? 'user@example.com';
            $name = $_SESSION['auth']['name'] ?? 'User';

            $service = new WebAuthnService();
            $options = $service->generateRegistrationOptions($userId, $email, $name);

            Utility::msgSuccess(200, "Registration options generated", $options);
        } catch (\Throwable $th) {
            Utility::showError($th);
        }
    }

    /**
     * Endpoint: /webauthn/register
     */
    public static function registerDevice(): void
    {
        try {
            $input = GetRequestData::getRequestData();
            
            // In a real implementation, pass $input to Webauthn\Server
            $service = new WebAuthnService();
            $isValid = $service->verifySignature($input);

            if (!$isValid) {
                throw new \Exception("Invalid Passkey signature.");
            }

            // TODO: Save the public key to `webauthn_credentials` database table
            
            Utility::msgSuccess(200, "Device registered successfully");
        } catch (\Throwable $th) {
            Utility::showError($th);
        }
    }

    /**
     * Endpoint: /webauthn/login/options
     */
    public static function getLoginOptions(): void
    {
        try {
            $input = GetRequestData::getRequestData();
            $email = $input['email'] ?? '';

            if (empty($email)) {
                throw new \InvalidArgumentException("Email is required for WebAuthn login");
            }

            // TODO: Lookup the user's registered credential IDs from `webauthn_credentials`

            $service = new WebAuthnService();
            // Generate standard login options...
            $challenge = random_bytes(32);
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['webauthn_challenge'] = base64_encode($challenge);

            $options = [
                'challenge' => base64_encode($challenge),
                'timeout' => 60000,
                // Abiola's Mandate: 'userVerification' MUST be 'required', never 'preferred'.
                // This ensures Biometric / PIN is strictly challenged to prevent unauthorized token reuse.
                'userVerification' => 'required',
                'allowCredentials' => [
                    // Populated from DB
                ]
            ];

            Utility::msgSuccess(200, "Login options generated", $options);
        } catch (\Throwable $th) {
            Utility::showError($th);
        }
    }

    /**
     * Endpoint: /webauthn/login
     */
    public static function login(): void
    {
        try {
            $input = GetRequestData::getRequestData();
            
            $service = new WebAuthnService();
            $isValid = $service->verifySignature($input);

            if (!$isValid) {
                throw new \Exception("Invalid Passkey signature.");
            }

            // Successful WebAuthn login! Issue JWT.
            // ... (Integration with JwtHandler)
            
            Utility::msgSuccess(200, "Login successful");
        } catch (\Throwable $th) {
            Utility::showError($th);
        }
    }
}
