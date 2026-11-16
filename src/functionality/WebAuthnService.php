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
     * Decode standard base64 or base64url string into raw binary bytes.
     */
    private function decodeBase64Url(string $input): ?string
    {
        $b64 = strtr($input, '-_', '+/');
        $pad = strlen($b64) % 4;
        if ($pad !== 0) {
            $b64 .= str_repeat('=', 4 - $pad);
        }
        $decoded = base64_decode($b64, true);
        return is_string($decoded) && $decoded !== '' ? $decoded : null;
    }

    /**
     * Build the list of allowed Relying Party IDs for rpIdHash verification.
     *
     * @return list<string>
     */
    private function getAllowedRpIds(?string $clientDataOriginHost = null): array
    {
        $appHost = strtolower((string) (parse_url((string) ($_ENV['APP_URL'] ?? ''), PHP_URL_HOST) ?: ''));
        $reqHost = strtolower(explode(':', $_SERVER['HTTP_HOST'] ?? '')[0]);
        $candidates = array_filter([
            $appHost,
            $reqHost,
            $clientDataOriginHost !== null ? strtolower($clientDataOriginHost) : '',
        ]);
        return array_values(array_unique($candidates));
    }

    /**
     * Verify that the 32-byte rpIdHash in authenticatorData matches an allowed RP ID.
     */
    private function verifyRpIdHash(string $rpIdHash, ?string $clientDataOriginHost = null): bool
    {
        foreach ($this->getAllowedRpIds($clientDataOriginHost) as $rpId) {
            if (hash_equals(hash('sha256', $rpId, true), $rpIdHash)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Extract and validate the COSE public key from a registration attestationObject.
     */
    public function extractPublicKeyFromAttestation(string $attestationObjectB64, ?string $clientDataOriginHost = null): string
    {
        $rawAttestation = $this->decodeBase64Url($attestationObjectB64);
        if ($rawAttestation === null) {
            throw new \Exception('Cryptographic Exception: Malformed attestationObject.');
        }

        $decoder = \CBOR\Decoder::create();
        $stream = \CBOR\StringStream::create($rawAttestation);
        $attestation = $decoder->decode($stream)->normalize();
        if (!is_array($attestation) || !isset($attestation['authData']) || !is_string($attestation['authData'])) {
            throw new \Exception('Cryptographic Exception: Missing authData in attestationObject.');
        }

        $authData = $attestation['authData'];
        if (strlen($authData) < 55) {
            throw new \Exception('Cryptographic Exception: Truncated authenticatorData in attestationObject.');
        }

        $rpIdHash = substr($authData, 0, 32);
        if (!$this->verifyRpIdHash($rpIdHash, $clientDataOriginHost)) {
            throw new \Exception('Cryptographic Exception: rpIdHash mismatch in attestationObject.');
        }

        $flags = ord($authData[32]);
        if (($flags & 0x01) === 0 || ($flags & 0x40) === 0) {
            throw new \Exception('Cryptographic Exception: Attestation missing User Present or Attested Credential Data flag.');
        }

        $credIdLen = unpack('n', substr($authData, 53, 2))[1] ?? 0;
        $coseOffset = 55 + (int) $credIdLen;
        if (strlen($authData) <= $coseOffset) {
            throw new \Exception('Cryptographic Exception: Missing COSE public key in attestationObject.');
        }

        $coseBytes = substr($authData, $coseOffset);
        $coseObj = $decoder->decode(\CBOR\StringStream::create($coseBytes))->normalize();
        if (!is_array($coseObj)) {
            throw new \Exception('Cryptographic Exception: Invalid COSE public key structure.');
        }

        // Validate that Cose\Key\Key can parse this key
        \Cose\Key\Key::create($coseObj);

        return base64_encode($coseBytes);
    }

    /**
     * Verify the WebAuthn signature sent back by the browser.
     */
    public function verifySignature(array $clientData, ?string $storedPublicKey = null): bool
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
        $isAssertion = ($storedPublicKey !== null)
            || isset($clientData['response']['signature'])
            || isset($clientData['response']['authenticatorData']);

        if ($isAssertion && (!is_string($clientDataJSON) || $clientDataJSON === '')) {
            throw new \Exception("Cryptographic Exception: Missing clientDataJSON.");
        }

        $clientDataJsonRaw = null;
        $cdHost = null;
        if ($clientDataJSON !== null) {
            if (!is_string($clientDataJSON) || $clientDataJSON === '' || strlen($clientDataJSON) > 8192) {
                throw new \Exception("Malformed clientDataJSON.");
            }
            $raw = $this->decodeBase64Url($clientDataJSON);
            $parsed = is_string($raw) ? json_decode($raw, true) : null;
            if (!is_array($parsed)) {
                throw new \Exception("Malformed clientDataJSON structure.");
            }
            $clientDataJsonRaw = $raw;

            if (!isset($parsed['type']) || !in_array($parsed['type'], ['webauthn.create', 'webauthn.get'], true)) {
                throw new \Exception("Invalid clientData type.");
            }
            if ($isAssertion && $parsed['type'] !== 'webauthn.get') {
                throw new \Exception("Cryptographic Exception: Expected webauthn.get for login assertion.");
            }
            if ($isAssertion && empty($parsed['challenge'])) {
                throw new \Exception("Cryptographic Exception: Missing challenge in clientDataJSON.");
            }
            if (isset($parsed['challenge'])) {
                if (!is_string($parsed['challenge']) || $parsed['challenge'] === '') {
                    throw new \Exception("Invalid clientData challenge.");
                }
                $expectedDecoded = $this->decodeBase64Url($expectedChallenge) ?? $expectedChallenge;
                $providedDecoded = $this->decodeBase64Url($parsed['challenge']) ?? $parsed['challenge'];
                if (!hash_equals((string) $expectedDecoded, (string) $providedDecoded)) {
                    throw new \Exception("Cryptographic Exception: Challenge mismatch in clientDataJSON.");
                }
            }
            if ($isAssertion && empty($parsed['origin'])) {
                throw new \Exception("Cryptographic Exception: Missing origin in clientDataJSON.");
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

        // 4. Full Assertion Verification (authenticatorData, rpIdHash, UP flag, and COSE ECDSA/RSA signature)
        if ($isAssertion) {
            $authDataB64 = $clientData['response']['authenticatorData'] ?? null;
            $signatureB64 = $clientData['response']['signature'] ?? null;

            if (!is_string($authDataB64) || $authDataB64 === '' || !is_string($signatureB64) || $signatureB64 === '') {
                throw new \Exception("Cryptographic Exception: Missing authenticatorData or signature.");
            }
            if ($storedPublicKey === null || trim($storedPublicKey) === '') {
                throw new \Exception("Cryptographic Exception: Missing stored passkey public key.");
            }

            $authDataRaw = $this->decodeBase64Url($authDataB64);
            $signatureRaw = $this->decodeBase64Url($signatureB64);
            if ($authDataRaw === null || strlen($authDataRaw) < 37 || $signatureRaw === null) {
                throw new \Exception("Cryptographic Exception: Malformed authenticatorData or signature.");
            }

            // Verify rpIdHash (bytes 0..31)
            $rpIdHash = substr($authDataRaw, 0, 32);
            if (!$this->verifyRpIdHash($rpIdHash, $cdHost)) {
                throw new \Exception("Cryptographic Exception: rpIdHash mismatch.");
            }

            // Verify User Present (UP) flag (bit 0 of byte 32)
            $flags = ord($authDataRaw[32]);
            if (($flags & 0x01) === 0) {
                throw new \Exception("Cryptographic Exception: User Presence (UP) flag not set.");
            }

            // Decode stored COSE public key
            $coseKeyRaw = $this->decodeBase64Url($storedPublicKey);
            if ($coseKeyRaw === null) {
                throw new \Exception("Cryptographic Exception: Invalid or legacy passkey public key. Please re-register your passkey.");
            }

            try {
                $coseKeyData = \CBOR\Decoder::create()->decode(\CBOR\StringStream::create($coseKeyRaw))->normalize();
                if (!is_array($coseKeyData)) {
                    throw new \Exception("Invalid COSE key data.");
                }
                $coseKey = \Cose\Key\Key::create($coseKeyData);
            } catch (\Throwable $e) {
                throw new \Exception("Cryptographic Exception: Invalid or legacy passkey public key. Please re-register your passkey.");
            }

            $algManager = \Cose\Algorithm\Manager::create()
                ->add(\Cose\Algorithm\Signature\ECDSA\ES256::create())
                ->add(\Cose\Algorithm\Signature\ECDSA\ES384::create())
                ->add(\Cose\Algorithm\Signature\ECDSA\ES512::create())
                ->add(\Cose\Algorithm\Signature\RSA\RS256::create())
                ->add(\Cose\Algorithm\Signature\RSA\RS384::create())
                ->add(\Cose\Algorithm\Signature\RSA\RS512::create())
                ->add(\Cose\Algorithm\Signature\RSA\PS256::create())
                ->add(\Cose\Algorithm\Signature\EdDSA\Ed25519::create());

            $algId = $coseKey->alg();
            try {
                $algorithm = $algManager->get($algId);
            } catch (\Throwable $e) {
                throw new \Exception("Cryptographic Exception: Unsupported COSE algorithm '{$algId}'.");
            }

            $signedData = $authDataRaw . hash('sha256', (string) $clientDataJsonRaw, true);
            if (!$algorithm->verify($signedData, $coseKey, $signatureRaw)) {
                throw new \Exception("Cryptographic Exception: Invalid WebAuthn signature.");
            }
        }

        return true; 
    }
}
