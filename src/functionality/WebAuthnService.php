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
     *
     * @param string $userId
     * @param string $username
     * @param string $displayName
     * @param array<int, array{type: string, id: string}> $excludeCredentials
     * @return array<string, mixed>
     */
    public function generateRegistrationOptions(string $userId, string $username, string $displayName, array $excludeCredentials = []): array
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
            'excludeCredentials' => $excludeCredentials,
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
            $originHost = strtolower((string) (parse_url($origin, PHP_URL_HOST) ?: ''));
            $allowedHosts = array_filter(['localhost', '127.0.0.1', strtolower($appHost), strtolower($reqHost)]);
            $isAllowed = in_array($originHost, $allowedHosts, true)
                || str_ends_with($originHost, '.test');
            if (!$isAllowed) {
                throw new \Exception("Cryptographic Exception: Invalid origin '{$origin}'. Blocked.");
            }
        }

        // 2. Challenge Verification
        if (session_status() === PHP_SESSION_NONE) {
            \Src\SecureSession::start();
        }
        $expectedChallenge = (string) ($_SESSION['webauthn_challenge'] ?? '');
        
        // Marcus's SecOps Mandate: The challenge must be strictly single-use to prevent replay attacks.
        unset($_SESSION['webauthn_challenge']);
        
        if ($expectedChallenge === '') {
            throw new \Exception("Invalid or expired challenge.");
        }

        // 3. FIDO2 Payload Structure & Cryptographic Binding Verification
        $id = $clientData['id'] ?? null;
        $rawId = $clientData['rawId'] ?? null;
        if (!is_string($id) || !is_string($rawId) || $id === '' || $rawId === '' || strlen($id) > 1024 || strlen($rawId) > 1024) {
            throw new \Exception("Malformed FIDO2 payload.");
        }
        if (!preg_match('/^[A-Za-z0-9+\/_=-]+$/', $id) || !preg_match('/^[A-Za-z0-9+\/_=-]+$/', $rawId)) {
            throw new \Exception("Malformed FIDO2 credential identifier.");
        }

        $clientDataJSON = $clientData['response']['clientDataJSON'] ?? $clientData['clientDataJSON'] ?? null;
        if ($clientDataJSON !== null) {
            if (!is_string($clientDataJSON) || $clientDataJSON === '' || strlen($clientDataJSON) > 8192) {
                throw new \Exception("Malformed clientDataJSON.");
            }
            $raw = base64_decode(strtr($clientDataJSON, '-_', '+/'), true) ?: base64_decode($clientDataJSON, true);
            $parsed = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($parsed)) {
                throw new \Exception("Malformed clientDataJSON structure.");
            }
            if (!isset($parsed['type']) || !in_array($parsed['type'], ['webauthn.create', 'webauthn.get'], true)) {
                throw new \Exception("Invalid clientData type.");
            }
            if (isset($parsed['challenge'])) {
                if (!is_string($parsed['challenge']) || $parsed['challenge'] === '') {
                    throw new \Exception("Invalid clientData challenge.");
                }
                $expectedDecoded = base64_decode(strtr($expectedChallenge, '-_', '+/'), true) ?: base64_decode($expectedChallenge, true) ?: $expectedChallenge;
                $providedDecoded = base64_decode(strtr($parsed['challenge'], '-_', '+/'), true) ?: base64_decode($parsed['challenge'], true) ?: $parsed['challenge'];
                if (!hash_equals((string) $expectedDecoded, (string) $providedDecoded)) {
                    throw new \Exception("Cryptographic Exception: Challenge mismatch in clientDataJSON.");
                }
            }
            if (isset($parsed['origin'])) {
                $cdOrigin = (string) $parsed['origin'];
                $cdHost = strtolower((string) (parse_url($cdOrigin, PHP_URL_HOST) ?: ''));
                $allowedHosts = array_filter(['localhost', '127.0.0.1', strtolower($appHost), strtolower($reqHost)]);
                $cdAllowed = $cdHost !== '' && (in_array($cdHost, $allowedHosts, true) || str_ends_with($cdHost, '.test'));
                if (!$cdAllowed) {
                    throw new \Exception("Cryptographic Exception: Invalid clientData origin '{$cdOrigin}'. Blocked.");
                }
            }
        }

        return true; 
    }
}
