<?php

declare(strict_types=1);

namespace Src\Kyc\Providers;

use Src\Kyc\Contracts\KycProviderInterface;
use Src\Kyc\Contracts\KycVerificationResult;

/**
 * MockKycProvider — Deterministic In-Memory KYC Provider for CI/CD, Sandboxes & Testing.
 */
class MockKycProvider implements KycProviderInterface
{
    private bool $simulateSuccess;
    private string $mockSecret;

    public function __construct(bool $simulateSuccess = true, string $mockSecret = 'mock_secret_key_12345')
    {
        $this->simulateSuccess = $simulateSuccess;
        $this->mockSecret = $mockSecret;
    }

    public function isConfigured(): bool
    {
        return true;
    }

    public function getProviderName(): string
    {
        return 'MockKycProvider';
    }

    /**
     * @param string $applicantId
     * @param array<string, mixed> $metadata
     * @return array{session_id: string, redirect_url: string, token: string}
     */
    public function createSession(string $applicantId, array $metadata = []): array
    {
        $sessionId = 'mock_sess_' . md5($applicantId . microtime());
        $redirectUrl = (string)($metadata['redirect_url'] ?? 'http://localhost/apply/kyc/callback');
        $token = 'mock_tok_' . bin2hex(random_bytes(16));

        return [
            'session_id' => $sessionId,
            'redirect_url' => $redirectUrl . '?session_id=' . $sessionId . '&token=' . $token,
            'token' => $token,
        ];
    }

    public function verifyWebhookSignature(string $rawPayload, string $signatureHeader): bool
    {
        $expected = hash_hmac('sha256', $rawPayload, $this->mockSecret);
        return hash_equals($expected, trim($signatureHeader));
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function parseResult(array $payload): KycVerificationResult
    {
        if (!$this->simulateSuccess || (isset($payload['status']) && $payload['status'] === 'rejected')) {
            return new KycVerificationResult(
                isVerified: false,
                status: 'REJECTED',
                documentType: 'PASSPORT',
                fullName: 'Failed Applicant',
                dob: '1990-01-01',
                expiryDate: '2020-01-01',
                isExpired: true,
                mrzValid: false,
                livenessScore: 0.2,
                confidenceScore: 10,
                tamperFlags: ['Biometric liveness check failed', 'Document is expired'],
                providerReferenceId: 'mock_ref_failed',
                rawDetails: $payload
            );
        }

        $fullName = (string)($payload['full_name'] ?? 'Sarah Jenkins');
        $docType = (string)($payload['document_type'] ?? 'PASSPORT');

        return new KycVerificationResult(
            isVerified: true,
            status: 'APPROVED',
            documentType: $docType,
            fullName: $fullName,
            dob: '1992-05-14',
            expiryDate: date('Y-m-d', strtotime('+5 years')),
            isExpired: false,
            mrzValid: true,
            livenessScore: 0.99,
            confidenceScore: 99,
            tamperFlags: [],
            providerReferenceId: 'mock_ref_success_99',
            rawDetails: $payload
        );
    }
}
