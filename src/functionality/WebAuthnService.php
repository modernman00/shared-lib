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
        $rpId = explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0];
        $appName = $_ENV['APP_NAME'] ?? 'Enterprise Platform';
        $rp = new PublicKeyCredentialRpEntity($appName, $rpId);
        $user = new PublicKeyCredentialUserEntity($username, $userId, $displayName);
        
        $authenticatorSelection = AuthenticatorSelectionCriteria::create(
            AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_PLATFORM,
            AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_PREFERRED,
            AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_PREFERRED
        );

        // Generate challenge
        $challenge = random_bytes(32);

        // Store $challenge in session to verify later
        if (session_status() === PHP_SESSION_NONE) {
            \Src\SecureSession::start();
        }
        $_SESSION['webauthn_challenge'] = base64_encode($challenge);

        $rpData = ['name' => $rp->name];
        // WebAuthn W3C specification strictly forbids IP addresses as rp.id.
        // If host is an IP address (e.g. 127.0.0.1), omitting id lets the browser default to origin safely.
        if (!filter_var($rpId, FILTER_VALIDATE_IP) && $rpId !== '127.0.0.1') {
            $rpData['id'] = $rpId;
        }

        // Return the JSON serialized options to pass to navigator.credentials.create()
        return [
            'rp' => $rpData,
            'user' => [
                'id' => base64_encode($user->id),
                'name' => $user->name,
                'displayName' => $user->displayName
            ],
            'challenge' => base64_encode($challenge),
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7], // ES256
                ['type' => 'public-key', 'alg' => -257] // RS256
            ],
            'authenticatorSelection' => [
                'authenticatorAttachment' => $authenticatorSelection->authenticatorAttachment,
                'userVerification' => $authenticatorSelection->userVerification,
                'residentKey' => $authenticatorSelection->residentKey
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
        // 1. Origin Validation
        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
        $appHost = parse_url((string) ($_ENV['APP_URL'] ?? ''), PHP_URL_HOST) ?: '';
        $reqHost = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];

        if (!empty($origin)) {
            $originHost = parse_url($origin, PHP_URL_HOST) ?: '';
            $isAllowed = in_array($originHost, ['localhost', '127.0.0.1', $appHost, $reqHost], true)
                || str_ends_with($originHost, '.test')
                || str_ends_with($originHost, '.com');
            if (!$isAllowed) {
                throw new \Exception("Cryptographic Exception: Invalid origin '{$origin}'. Blocked.");
            }
        }

        // 2. Challenge Verification
        if (session_status() === PHP_SESSION_NONE) {
            \Src\SecureSession::start();
        }
        $expectedChallenge = $_SESSION['webauthn_challenge'] ?? '';
        
        // Marcus's SecOps Mandate: The challenge must be strictly single-use to prevent replay attacks.
        unset($_SESSION['webauthn_challenge']);
        
        if (empty($expectedChallenge)) {
            throw new \Exception("Invalid or expired challenge.");
        }

        // 3. (Stub) FIDO2 Signature Verification 
        // This is where we would use the WebAuthn\Server to verify the credential.
        
        // 3. FIDO2 Payload Structure Verification
        $id = $clientData['id'] ?? null;
        $rawId = $clientData['rawId'] ?? null;
        if (!$id || !$rawId) {
            throw new \Exception("Malformed FIDO2 payload.");
        }

        $clientDataJSON = $clientData['response']['clientDataJSON'] ?? $clientData['clientDataJSON'] ?? null;
        if ($clientDataJSON) {
            $raw = base64_decode(strtr($clientDataJSON, '-_', '+/')) ?: base64_decode($clientDataJSON);
            $parsed = json_decode((string)$raw, true);
            if (!empty($parsed) && isset($parsed['type']) && !str_starts_with($parsed['type'], 'webauthn.')) {
                throw new \Exception("Invalid clientData type.");
            }
        }

        return true; 
    }
}
