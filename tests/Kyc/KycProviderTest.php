<?php

declare(strict_types=1);

namespace Tests\Kyc;

use PHPUnit\Framework\TestCase;
use Src\Kyc\Providers\ComplyCubeKycProvider;
use Src\Kyc\Providers\MockKycProvider;

class KycProviderTest extends TestCase
{
    public function testMockKycProviderSessionAndOutcome(): void
    {
        $provider = new MockKycProvider(true, 'secret_webhook_key_abc');

        $this->assertTrue($provider->isConfigured());
        $this->assertEquals('MockKycProvider', $provider->getProviderName());

        $session = $provider->createSession('user_123', ['redirect_url' => 'https://scoretenant.com/kyc/callback']);
        $this->assertNotEmpty($session['session_id']);
        $this->assertStringContainsString('https://scoretenant.com/kyc/callback', $session['redirect_url']);

        $payload = ['full_name' => 'Johnathan Smith', 'document_type' => 'PASSPORT'];
        $result = $provider->parseResult($payload);

        $this->assertTrue($result->isVerified());
        $this->assertEquals('APPROVED', $result->getStatus());
        $this->assertEquals('PASSPORT', $result->getDocumentType());
        $this->assertEquals('Johnathan Smith', $result->getFullName());
        $this->assertFalse($result->isExpired());
        $this->assertTrue($result->isMrzValid());
        $this->assertGreaterThanOrEqual(95, $result->getConfidenceScore());
    }

    public function testMockKycProviderRejection(): void
    {
        $provider = new MockKycProvider(false);
        $result = $provider->parseResult(['status' => 'rejected']);

        $this->assertFalse($result->isVerified());
        $this->assertEquals('REJECTED', $result->getStatus());
        $this->assertTrue($result->isExpired());
        $this->assertNotEmpty($result->getTamperFlags());
        $this->assertLessThan(20, $result->getConfidenceScore());
    }

    public function testComplyCubeWebhookHmacValidation(): void
    {
        $secret = 'webhook_signing_secret_xyz789';
        $provider = new ComplyCubeKycProvider([
            'api_key' => 'live_api_key_complycube_123',
            'webhook_secret' => $secret,
        ]);

        $rawBody = json_encode(['event' => 'check.completed', 'id' => 'chk_998877']);
        $validSignature = hash_hmac('sha256', (string)$rawBody, $secret);
        $invalidSignature = 'forged_fake_signature_hash';

        $this->assertTrue($provider->verifyWebhookSignature((string)$rawBody, $validSignature));
        $this->assertFalse($provider->verifyWebhookSignature((string)$rawBody, $invalidSignature));
    }

    public function testComplyCubeParseResultApproved(): void
    {
        $provider = new ComplyCubeKycProvider([
            'api_key' => 'mock_api_key',
            'webhook_secret' => 'mock_secret',
        ]);

        $payload = [
            'payload' => [
                'check' => [
                    'id' => 'chk_123456',
                    'outcome' => 'clear',
                    'document' => [
                        'type' => 'passport',
                        'holder' => [
                            'firstName' => 'Elizabeth',
                            'lastName' => 'Taylor',
                            'dob' => '1991-03-22',
                        ],
                        'expiryDate' => '2030-05-18',
                    ],
                    'breakdown' => [
                        'mrzCheck' => ['outcome' => 'clear'],
                        'visualAuthenticity' => ['outcome' => 'clear'],
                    ],
                    'identityCheck' => [
                        'score' => 0.98,
                    ],
                ],
            ],
        ];

        $result = $provider->parseResult($payload);

        $this->assertTrue($result->isVerified());
        $this->assertEquals('APPROVED', $result->getStatus());
        $this->assertEquals('PASSPORT', $result->getDocumentType());
        $this->assertEquals('Elizabeth Taylor', $result->getFullName());
        $this->assertEquals('1991-03-22', $result->getDob());
        $this->assertEquals('2030-05-18', $result->getExpiryDate());
        $this->assertFalse($result->isExpired());
        $this->assertTrue($result->isMrzValid());
        $this->assertEquals(0.98, $result->getLivenessScore());
        $this->assertEquals(98, $result->getConfidenceScore());
        $this->assertEmpty($result->getTamperFlags());
    }

    public function testComplyCubeParseResultExpiredAndFlagged(): void
    {
        $provider = new ComplyCubeKycProvider([
            'api_key' => 'mock_api_key',
            'webhook_secret' => 'mock_secret',
        ]);

        $payload = [
            'payload' => [
                'check' => [
                    'id' => 'chk_expired_99',
                    'outcome' => 'rejected',
                    'document' => [
                        'type' => 'driving_licence',
                        'holder' => [
                            'firstName' => 'David',
                            'lastName' => 'Renton',
                        ],
                        'expiryDate' => '2021-01-01',
                    ],
                    'breakdown' => [
                        'visualAuthenticity' => ['outcome' => 'attention'],
                    ],
                ],
            ],
        ];

        $result = $provider->parseResult($payload);

        $this->assertFalse($result->isVerified());
        $this->assertEquals('REJECTED', $result->getStatus());
        $this->assertEquals('DRIVING_LICENCE', $result->getDocumentType());
        $this->assertTrue($result->isExpired());
        $this->assertNotEmpty($result->getTamperFlags());
        $this->assertLessThan(20, $result->getConfidenceScore());
    }
}
