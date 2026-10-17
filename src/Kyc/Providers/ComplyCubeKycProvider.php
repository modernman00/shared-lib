<?php

declare(strict_types=1);

namespace Src\Kyc\Providers;

use Src\Kyc\Contracts\KycProviderInterface;
use Src\Kyc\Contracts\KycVerificationResult;
use Src\Kyc\Exceptions\InvalidWebhookSignatureException;
use Src\Kyc\Exceptions\KycProviderException;

/**
 * ComplyCubeKycProvider — Certified UK AML, Right to Rent & Biometric Identity Verification Provider.
 *
 * Integrates ComplyCube's REST API:
 * 1. Client creation & hosted web/mobile verification flow sessions
 * 2. Biometric liveness check + passport / UK driving licence verification
 * 3. HMAC-SHA256 webhook signature validation & defensive result parsing
 */
class ComplyCubeKycProvider implements KycProviderInterface
{
    private string $apiKey;
    private string $webhookSecret;
    private string $apiBaseUrl;
    private int $connectTimeout;
    private int $timeout;

    /**
     * @param array{
     *     api_key?: string,
     *     webhook_secret?: string,
     *     api_base_url?: string,
     *     connect_timeout?: int,
     *     timeout?: int
     * } $config
     */
    public function __construct(array $config = [])
    {
        $this->apiKey = (string)($config['api_key'] ?? $_ENV['COMPLYCUBE_API_KEY'] ?? getenv('COMPLYCUBE_API_KEY') ?: '');
        $this->webhookSecret = (string)($config['webhook_secret'] ?? $_ENV['COMPLYCUBE_WEBHOOK_SECRET'] ?? getenv('COMPLYCUBE_WEBHOOK_SECRET') ?: '');
        $this->apiBaseUrl = rtrim((string)($config['api_base_url'] ?? 'https://api.complycube.com/v1'), '/');
        $this->connectTimeout = (int)($config['connect_timeout'] ?? 5);
        $this->timeout = (int)($config['timeout'] ?? 10);
    }

    public function isConfigured(): bool
    {
        return $this->apiKey !== '';
    }

    public function getProviderName(): string
    {
        return 'ComplyCube';
    }

    /**
     * Creates an applicant verification session on ComplyCube.
     *
     * @param string $applicantId
     * @param array<string, mixed> $metadata
     * @return array{session_id: string, redirect_url: string, token: string}
     * @throws KycProviderException
     */
    public function createSession(string $applicantId, array $metadata = []): array
    {
        if (!$this->isConfigured()) {
            throw new KycProviderException('ComplyCube API key is not configured.');
        }

        $email = (string)($metadata['email'] ?? "applicant-{$applicantId}@scoretenant.co.uk");
        $firstName = (string)($metadata['first_name'] ?? 'Applicant');
        $lastName = (string)($metadata['last_name'] ?? $applicantId);
        $redirectUrl = (string)($metadata['redirect_url'] ?? '');

        // 1. Create or retrieve client
        $clientResponse = $this->sendHttpRequest('/clients', [
            'type' => 'person',
            'email' => $email,
            'personDetails' => [
                'firstName' => $firstName,
                'lastName' => $lastName,
            ],
            'metadata' => [
                'internal_applicant_id' => $applicantId,
            ],
        ]);

        $clientId = (string)($clientResponse['id'] ?? '');
        if ($clientId === '') {
            throw new KycProviderException('Failed to provision ComplyCube client ID.');
        }

        // 2. Generate Flow Session
        $sessionPayload = [
            'clientId' => $clientId,
            'checkTypes' => ['document_check', 'identity_check'],
            'successUrl' => $redirectUrl !== '' ? $redirectUrl : null,
            'cancelUrl' => $redirectUrl !== '' ? $redirectUrl : null,
        ];

        $sessionResponse = $this->sendHttpRequest('/flow/sessions', $sessionPayload);

        $sessionId = (string)($sessionResponse['id'] ?? $sessionResponse['sessionId'] ?? '');
        $url = (string)($sessionResponse['redirectUrl'] ?? $sessionResponse['url'] ?? '');
        $token = (string)($sessionResponse['token'] ?? '');

        if ($sessionId === '' || $url === '') {
            throw new KycProviderException('Failed to generate ComplyCube verification flow session.');
        }

        return [
            'session_id' => $sessionId,
            'redirect_url' => $url,
            'token' => $token,
        ];
    }

    /**
     * Verifies HMAC-SHA256 signature on inbound webhook payloads.
     */
    public function verifyWebhookSignature(string $rawPayload, string $signatureHeader): bool
    {
        if ($this->webhookSecret === '' || $signatureHeader === '') {
            return false;
        }

        $expected = hash_hmac('sha256', $rawPayload, $this->webhookSecret);
        return hash_equals($expected, trim($signatureHeader));
    }

    /**
     * Parses a webhook or polling payload into a normalized KycVerificationResult.
     *
     * @param array<string, mixed> $payload
     * @return KycVerificationResult
     * @throws KycProviderException
     */
    public function parseResult(array $payload): KycVerificationResult
    {
        $data = is_array($payload['payload'] ?? null) ? $payload['payload'] : $payload;
        $check = is_array($data['check'] ?? null) ? $data['check'] : $data;

        $outcome = strtolower((string)($check['result']['outcome'] ?? $check['outcome'] ?? $data['status'] ?? 'pending'));
        $referenceId = (string)($check['id'] ?? $data['id'] ?? '');

        $document = is_array($check['document'] ?? null) ? $check['document'] : [];
        $rawDocType = strtolower((string)($document['type'] ?? $check['documentType'] ?? 'unknown'));
        
        $docType = match (true) {
            str_contains($rawDocType, 'passport') => 'PASSPORT',
            str_contains($rawDocType, 'driving') => 'DRIVING_LICENCE',
            str_contains($rawDocType, 'national_identity') => 'NATIONAL_IDENTITY_CARD',
            default => 'UNKNOWN'
        };

        $holder = is_array($document['holder'] ?? null) ? $document['holder'] : [];
        $fullName = trim(($holder['firstName'] ?? '') . ' ' . ($holder['lastName'] ?? ''));
        if ($fullName === '') {
            $fullName = (string)($check['applicantName'] ?? 'Verified Applicant');
        }

        $dob = !empty($holder['dob']) ? (string)$holder['dob'] : null;
        $expiry = !empty($document['expiryDate']) ? (string)$document['expiryDate'] : null;

        $isExpired = false;
        if ($expiry !== null && $expiry !== '') {
            $expiryTimestamp = strtotime($expiry);
            if ($expiryTimestamp !== false && $expiryTimestamp < time()) {
                $isExpired = true;
            }
        }

        // Breakdown signals
        $breakdown = is_array($check['breakdown'] ?? null) ? $check['breakdown'] : [];
        $mrzValid = isset($breakdown['mrzCheck']) ? ($breakdown['mrzCheck']['outcome'] ?? '') === 'clear' : null;
        
        $tamperFlags = [];
        if ($isExpired) {
            $tamperFlags[] = 'Document is expired.';
        }
        if ($mrzValid === false) {
            $tamperFlags[] = 'MRZ checksum validation failed.';
        }
        if (isset($breakdown['visualAuthenticity']) && ($breakdown['visualAuthenticity']['outcome'] ?? '') !== 'clear') {
            $tamperFlags[] = 'Visual authenticity check failed or flagged anomalies.';
        }

        // Liveness score
        $liveness = isset($check['identityCheck']['score']) ? (float)$check['identityCheck']['score'] : 1.0;

        $isVerified = in_array($outcome, ['clear', 'approved', 'pass'], true) && !$isExpired && empty($tamperFlags);
        $status = $isVerified ? 'APPROVED' : ($outcome === 'attention' ? 'MANUAL_REVIEW' : 'REJECTED');

        $confidence = $isVerified ? 98 : ($status === 'MANUAL_REVIEW' ? 65 : 15);

        return new KycVerificationResult(
            isVerified: $isVerified,
            status: $status,
            documentType: $docType,
            fullName: $fullName,
            dob: $dob,
            expiryDate: $expiry,
            isExpired: $isExpired,
            mrzValid: $mrzValid,
            livenessScore: $liveness,
            confidenceScore: $confidence,
            tamperFlags: $tamperFlags,
            providerReferenceId: $referenceId,
            rawDetails: $payload
        );
    }

    /**
     * @param array<string, mixed> $body
     * @return array<string, mixed>
     * @throws KycProviderException
     */
    private function sendHttpRequest(string $path, array $body): array
    {
        $url = $this->apiBaseUrl . $path;

        $ch = curl_init($url);
        if ($ch === false) {
            throw new KycProviderException('Failed to initialise cURL for ComplyCube API.');
        }

        $payload = json_encode($body);

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                'Authorization: ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT => $this->timeout,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false || $curlError !== '') {
            throw new KycProviderException('ComplyCube network error: ' . ($curlError ?: 'unknown'));
        }

        $decoded = json_decode((string)$response, true);
        if (!is_array($decoded)) {
            throw new KycProviderException('ComplyCube response malformed (HTTP ' . $httpCode . ').');
        }

        if ($httpCode >= 400) {
            $msg = (string)($decoded['message'] ?? $decoded['error'] ?? 'API error');
            throw new KycProviderException("ComplyCube request failed (HTTP {$httpCode}): {$msg}", $httpCode);
        }

        return $decoded;
    }
}
