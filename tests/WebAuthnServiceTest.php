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
}
