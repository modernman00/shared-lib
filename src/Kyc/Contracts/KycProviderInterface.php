<?php

declare(strict_types=1);

namespace Src\Kyc\Contracts;

use Src\Kyc\Exceptions\KycProviderException;

/**
 * KycProviderInterface — Standardized Identity, Right to Rent & Biometric KYC Provider Contract.
 */
interface KycProviderInterface
{
    /**
     * Checks if the provider is fully configured with required API keys and secrets.
     */
    public function isConfigured(): bool;

    /**
     * Returns the human-readable identifier of the provider (e.g. 'ComplyCube', 'Onfido', 'Mock').
     */
    public function getProviderName(): string;

    /**
     * Creates an applicant verification session / flow.
     *
     * @param string $applicantId Unique internal applicant/tenant ID
     * @param array<string, mixed> $metadata Additional context (name, email, redirect_url)
     * @return array{
     *     session_id: string,
     *     redirect_url: string,
     *     token: string
     * }
     * @throws KycProviderException
     */
    public function createSession(string $applicantId, array $metadata = []): array;

    /**
     * Cryptographically verifies an incoming webhook signature.
     *
     * @param string $rawPayload Raw HTTP request body string
     * @param string $signatureHeader Signature header provided by webhook request
     * @return bool
     */
    public function verifyWebhookSignature(string $rawPayload, string $signatureHeader): bool;

    /**
     * Parses an incoming webhook payload or polling check into a normalized KycVerificationResult.
     *
     * @param array<string, mixed> $payload
     * @return KycVerificationResult
     * @throws KycProviderException
     */
    public function parseResult(array $payload): KycVerificationResult;
}
