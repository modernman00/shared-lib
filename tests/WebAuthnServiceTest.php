<?php
declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\functionality\WebAuthnService;

class WebAuthnServiceTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        $_SERVER['HTTP_HOST'] = 'iaccountapp.test';
        $_SERVER['HTTP_ORIGIN'] = 'https://iaccountapp.test';
        $_ENV['APP_NAME'] = 'iAccountApp';
    }

    public function testGenerateRegistrationOptionsOmitsIpRpId(): void
    {
        $_SERVER['HTTP_HOST'] = '127.0.0.1:8000';
        $service = new WebAuthnService();
        $options = $service->generateRegistrationOptions('123', 'test@example.com', 'Test User');

        $this->assertArrayHasKey('rp', $options);
        $this->assertSame('iAccountApp', $options['rp']['name']);
        // Crucial WebAuthn W3C RFC 5280 check: IP addresses must NOT be set as rp.id
        $this->assertArrayNotHasKey('id', $options['rp']);
        $this->assertNotEmpty($options['challenge']);
        $this->assertArrayHasKey('webauthn_challenge', $_SESSION);
    }

    public function testGenerateRegistrationOptionsIncludesDomainRpId(): void
    {
        $_SERVER['HTTP_HOST'] = 'iaccountapp.test:443';
        $service = new WebAuthnService();
        $options = $service->generateRegistrationOptions('123', 'test@example.com', 'Test User');

        $this->assertArrayHasKey('rp', $options);
        $this->assertSame('iaccountapp.test', $options['rp']['id']);
    }

    public function testReplayAttackBlockedBySingleUseChallenge(): void
    {
        $service = new WebAuthnService();
        $options = $service->generateRegistrationOptions('123', 'test@example.com', 'Test User');

        $validPayload = [
            'id' => 'cred_abc123',
            'rawId' => 'raw_abc123',
            'clientDataJSON' => base64_encode(json_encode(['type' => 'webauthn.create', 'challenge' => $options['challenge']]))
        ];

        // First verification should pass
        $this->assertTrue($service->verifySignature($validPayload));

        // Challenge was destroyed from session
        $this->assertArrayNotHasKey('webauthn_challenge', $_SESSION);

        // Immediate replay attempt must throw exception
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Invalid or expired challenge.');
        $service->verifySignature($validPayload);
    }

    public function testInvalidOriginThrowsCryptographicException(): void
    {
        $_SERVER['HTTP_ORIGIN'] = 'https://malicious-phishing-site.xyz';
        $service = new WebAuthnService();
        $_SESSION['webauthn_challenge'] = base64_encode('random_challenge');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Cryptographic Exception: Invalid origin');

        $service->verifySignature([
            'id' => 'cred_1',
            'rawId' => 'raw_1'
        ]);
    }

    public function testLegacyOrInvalidPublicKeyThrowsHttpExceptionWithActionableMessage(): void
    {
        $service = new WebAuthnService();
        $challengeBytes = random_bytes(32);
        $challengeB64 = base64_encode($challengeBytes);
        $_SESSION['webauthn_challenge'] = $challengeB64;

        $b64url = static function (string $raw): string {
            return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
        };

        $clientDataJson = json_encode([
            'type' => 'webauthn.get',
            'challenge' => $b64url($challengeBytes),
            'origin' => 'https://iaccountapp.test',
        ]);
        $rpIdHash = hash('sha256', 'iaccountapp.test', true);
        $authData = $rpIdHash . "\x01" . "\x00\x00\x00\x01";

        $this->expectException(\Src\Exceptions\UnauthorisedException::class);
        $this->expectExceptionMessage('Invalid or legacy passkey. Please sign in with your password and re-register Face ID / Touch ID in Account Settings.');

        $service->verifySignature([
            'id' => 'cred_legacy',
            'rawId' => 'cred_legacy',
            'response' => [
                'clientDataJSON' => $b64url((string) $clientDataJson),
                'authenticatorData' => $b64url($authData),
                'signature' => $b64url('dummy_sig'),
            ],
        ], 'legacy_non_cose_placeholder');
    }
}
