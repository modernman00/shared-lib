<?php
declare(strict_types=1);

namespace Tests\Functionality;

use PHPUnit\Framework\TestCase;
use Src\functionality\WebAuthnFunctionality;
use Src\functionality\WebAuthnService;

class WebAuthnPasskeyPromptTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SESSION = [];
        $_SERVER['REQUEST_URI'] = '/dashboard';
        $_SERVER['HTTP_HOST'] = 'localhost';
        $_ENV['APP_NAME'] = 'PartyPlatform';
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        parent::tearDown();
    }

    public function testResolveAuthenticatedUserReturnsNullForGuest(): void
    {
        $this->assertNull(WebAuthnFunctionality::resolveAuthenticatedUser());
        $state = WebAuthnFunctionality::getClientPromptState();
        $this->assertFalse($state['authenticated']);
        $this->assertSame('', WebAuthnFunctionality::renderActivationPrompt());
    }

    public function testResolveAuthenticatedUserSupportsStandardAndBcpSessions(): void
    {
        $_SESSION['user_id'] = 'USR_101';
        $_SESSION['user_name'] = 'Wale Olaogun';
        $_SESSION['email'] = 'wale@example.co.uk';

        $resolved = WebAuthnFunctionality::resolveAuthenticatedUser();
        $this->assertNotNull($resolved);
        $this->assertSame('USR_101', $resolved['user_id']);
        $this->assertSame('wale@example.co.uk', $resolved['email']);
        $this->assertSame('Wale Olaogun', $resolved['name']);
        $this->assertSame('users', $resolved['role']);

        // Test BCP staff session convention
        $_SESSION = [
            'staff_email' => 'carer@bcp.co.uk',
            'staff_name'  => 'Amina Khan',
        ];
        $bcpResolved = WebAuthnFunctionality::resolveAuthenticatedUser();
        $this->assertNotNull($bcpResolved);
        $this->assertSame('carer@bcp.co.uk', $bcpResolved['user_id']);
        $this->assertSame('staff', $bcpResolved['role']);
    }

    public function testRenderActivationPromptEmitsModalScriptWhenNotEnrolledAndSuppressesOnLoginRoute(): void
    {
        $_SESSION['user_id'] = 'USR_202';
        $_SESSION['email'] = 'member@example.co.uk';
        $_SESSION['webauthn_enrolled_USR_202'] = false;

        $html = WebAuthnFunctionality::renderActivationPrompt('test-nonce-123');
        $this->assertStringContainsString('nonce="test-nonce-123"', $html);
        $this->assertStringContainsString('Activate Biometric Sign-In', $html);
        $this->assertStringContainsString('/webauthn/register/options', $html);
        $this->assertStringContainsString('window.BiometricPasskeyPrompt', $html);

        // Must be suppressed on /login or /signin routes
        $_SERVER['REQUEST_URI'] = '/login';
        $this->assertSame('', WebAuthnFunctionality::renderActivationPrompt('test-nonce-123'));
    }

    public function testRenderActivationPromptSyncsLocalStorageWhenAlreadyEnrolled(): void
    {
        $_SESSION['user_id'] = 'USR_303';
        $_SESSION['webauthn_enrolled_USR_303'] = true;

        $html = WebAuthnFunctionality::renderActivationPrompt('nonce-xyz');
        $this->assertStringContainsString("localStorage.setItem('biometric_enrolled_'", $html);
        $this->assertStringNotContainsString('universal-passkey-prompt-backdrop', $html);
    }

    public function testGenerateRegistrationOptionsIncludesExcludeCredentials(): void
    {
        $service = new WebAuthnService();
        $exclude = [
            ['type' => 'public-key', 'id' => 'cred_abc_123']
        ];
        $options = $service->generateRegistrationOptions('USR_101', 'wale@example.co.uk', 'Wale', $exclude);

        $this->assertArrayHasKey('excludeCredentials', $options);
        $this->assertSame($exclude, $options['excludeCredentials']);
        $this->assertNotEmpty($options['challenge']);
    }

    public function testAdversarialPoCExploitNeutralization(): void
    {
        $service = new WebAuthnService();

        // 1. Cross-Origin Spoofing PoC (evil-phishing.ru)
        $_SESSION['webauthn_challenge'] = base64_encode(random_bytes(32));
        $_SERVER['HTTP_ORIGIN'] = 'https://evil-phishing.ru';
        $_SERVER['HTTP_HOST'] = 'partyplatform.test';

        try {
            $service->verifySignature(['id' => 'cred_1', 'rawId' => 'cred_1']);
            $this->fail('Expected cross-origin spoofing to be blocked.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString("Cryptographic Exception: Invalid origin 'https://evil-phishing.ru'. Blocked.", $e->getMessage());
        }

        // 2. Single-Use Challenge Replay Attack PoC
        $_SERVER['HTTP_ORIGIN'] = 'https://partyplatform.test';
        $_SESSION['webauthn_challenge'] = base64_encode(random_bytes(32));
        $firstPass = $service->verifySignature(['id' => 'cred_1', 'rawId' => 'cred_1']);
        $this->assertTrue($firstPass);

        // Second attempt with the same session challenge must fail (challenge was consumed & unset)
        try {
            $service->verifySignature(['id' => 'cred_1', 'rawId' => 'cred_1']);
            $this->fail('Expected replay attack with consumed challenge to be blocked.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Invalid or expired challenge.', $e->getMessage());
        }

        // 3. Wildcard .com Domain Bypass Neutralization (evil-phishing.com must be rejected)
        $_SESSION['webauthn_challenge'] = base64_encode(random_bytes(32));
        $_SERVER['HTTP_ORIGIN'] = 'https://evil-phishing.com';
        $_SERVER['HTTP_HOST'] = 'partyplatform.test';

        try {
            $service->verifySignature(['id' => 'cred_1', 'rawId' => 'cred_1']);
            $this->fail('Expected .com cross-origin spoofing to be blocked.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString("Cryptographic Exception: Invalid origin 'https://evil-phishing.com'. Blocked.", $e->getMessage());
        }

        // 4. Forged clientDataJSON Challenge Mismatch Neutralization
        $_SERVER['HTTP_ORIGIN'] = 'https://partyplatform.test';
        $_SESSION['webauthn_challenge'] = base64_encode('legitimate-32-byte-challenge-xyz');
        $forgedClientData = base64_encode((string) json_encode([
            'type'      => 'webauthn.create',
            'challenge' => base64_encode('forged-attacker-challenge-000000'),
            'origin'    => 'https://partyplatform.test',
        ]));

        try {
            $service->verifySignature([
                'id'       => 'cred_1',
                'rawId'    => 'cred_1',
                'response' => ['clientDataJSON' => $forgedClientData],
            ]);
            $this->fail('Expected forged clientDataJSON challenge mismatch to be blocked.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Challenge mismatch in clientDataJSON', $e->getMessage());
        }

        // 5. Forged clientDataJSON Origin Spoofing Neutralization (even when HTTP_ORIGIN header is omitted by AI agent)
        unset($_SERVER['HTTP_ORIGIN']);
        $_SESSION['webauthn_challenge'] = base64_encode('legitimate-32-byte-challenge-xyz');
        $forgedOriginClientData = base64_encode((string) json_encode([
            'type'      => 'webauthn.get',
            'challenge' => base64_encode('legitimate-32-byte-challenge-xyz'),
            'origin'    => 'https://attacker-agent.com',
        ]));

        try {
            $service->verifySignature([
                'id'       => 'cred_1',
                'rawId'    => 'cred_1',
                'response' => ['clientDataJSON' => $forgedOriginClientData],
            ]);
            $this->fail('Expected forged clientDataJSON origin to be blocked.');
        } catch (\Throwable $e) {
            $this->assertStringContainsString('Invalid clientData origin', $e->getMessage());
        }
    }
}
