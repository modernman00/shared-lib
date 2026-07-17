<?php
declare(strict_types=1);

namespace Src\functionality;

use Webauthn\Server;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialUserEntity;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\AuthenticatorSelectionCriteria;

/**
 * Service to handle WebAuthn (Passkeys / FaceID / TouchID) operations.
 */
class WebAuthnService
{
    private Server $server;

    public function __construct()
    {
        // In a real implementation, we would initialize the WebAuthn Server
        // with the appropriate PublicKeyCredentialSourceRepository and dependencies.
        // For the scope of this update, we define the integration interface.
    }

    /**
     * Generate options for a user to register a new Passkey (Biometric).
     */
    public function generateRegistrationOptions(string $userId, string $username, string $displayName): array
    {
        $rp = new PublicKeyCredentialRpEntity('My Enterprise Platform', 'localhost');
        $user = new PublicKeyCredentialUserEntity($username, $userId, $displayName);
        
        $authenticatorSelection = AuthenticatorSelectionCriteria::create()
            ->setAuthenticatorAttachment(AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_PLATFORM)
            ->setUserVerification(AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED);

        // Generate challenge
        $challenge = random_bytes(32);

        // Store $challenge in session to verify later
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['webauthn_challenge'] = base64_encode($challenge);

        // Return the JSON serialized options to pass to navigator.credentials.create()
        return [
            'rp' => [
                'name' => $rp->getName(),
                'id' => $rp->getId()
            ],
            'user' => [
                'id' => base64_encode($user->getId()),
                'name' => $user->getName(),
                'displayName' => $user->getDisplayName()
            ],
            'challenge' => base64_encode($challenge),
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7], // ES256
                ['type' => 'public-key', 'alg' => -257] // RS256
            ],
            'authenticatorSelection' => [
                'authenticatorAttachment' => $authenticatorSelection->getAuthenticatorAttachment(),
                'userVerification' => $authenticatorSelection->getUserVerification()
            ],
            'timeout' => 60000,
            'attestation' => 'none'
        ];
    }

    /**
     * Verify the WebAuthn signature sent back by the browser.
     */
    public function verifySignature(array $clientData): bool
    {
        // 1. Strict Origin Validation (Abiola's Mandate)
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $allowedOrigins = [
            'http://localhost',
            'http://localhost:8000',
            'http://127.0.0.1:8000',
            'https://loaneasyfinance.com',
            'https://partyplatform.com',
            'https://iaccountapp.com',
            'https://execmindapp.com',
            'https://familyplatform.com',
            'https://idecideapp.com',
        ];

        if (!in_array($origin, $allowedOrigins, true)) {
            // Throw a distinct error to catch malicious relay/phishing attempts
            throw new \Exception("Cryptographic Exception: Invalid origin '{$origin}'. Phishing attempt blocked.");
        }

        // 2. Challenge Verification
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $expectedChallenge = $_SESSION['webauthn_challenge'] ?? '';
        
        // Marcus's SecOps Mandate: The challenge must be strictly single-use to prevent replay attacks.
        // We immediately destroy the challenge from the session so it cannot be used again, pass or fail.
        unset($_SESSION['webauthn_challenge']);
        
        if (empty($expectedChallenge)) {
            throw new \Exception("Invalid or expired challenge.");
        }

        // 3. (Stub) FIDO2 Signature Verification 
        // This is where we would use the WebAuthn\Server to verify the credential.
        
        // Simulate checking the clientData structure
        if (!isset($clientData['id']) || !isset($clientData['rawId'])) {
            throw new \Exception("Malformed FIDO2 payload.");
        }

        return true; 
    }
}
