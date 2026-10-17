<?php

declare(strict_types=1);

namespace Src\Kyc\Contracts;

/**
 * KycVerificationResult — Immutable Data Transfer Object for Identity & IDSP Checks.
 */
class KycVerificationResult
{
    private bool $isVerified;
    private string $status;
    private string $documentType;
    private string $fullName;
    private ?string $dob;
    private ?string $expiryDate;
    private bool $isExpired;
    private ?bool $mrzValid;
    private float $livenessScore;
    private int $confidenceScore;
    /** @var list<string> */
    private array $tamperFlags;
    private string $providerReferenceId;
    /** @var array<string, mixed> */
    private array $rawDetails;

    /**
     * @param list<string> $tamperFlags
     * @param array<string, mixed> $rawDetails
     */
    public function __construct(
        bool $isVerified,
        string $status,
        string $documentType,
        string $fullName,
        ?string $dob = null,
        ?string $expiryDate = null,
        bool $isExpired = false,
        ?bool $mrzValid = null,
        float $livenessScore = 1.0,
        int $confidenceScore = 100,
        array $tamperFlags = [],
        string $providerReferenceId = '',
        array $rawDetails = []
    ) {
        $this->isVerified = $isVerified;
        $this->status = strtoupper($status);
        $this->documentType = strtoupper($documentType);
        $this->fullName = trim($fullName);
        $this->dob = $dob;
        $this->expiryDate = $expiryDate;
        $this->isExpired = $isExpired;
        $this->mrzValid = $mrzValid;
        $this->livenessScore = max(0.0, min(1.0, $livenessScore));
        $this->confidenceScore = max(0, min(100, $confidenceScore));
        $this->tamperFlags = array_values($tamperFlags);
        $this->providerReferenceId = $providerReferenceId;
        $this->rawDetails = $rawDetails;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getDocumentType(): string
    {
        return $this->documentType;
    }

    public function getFullName(): string
    {
        return $this->fullName;
    }

    public function getDob(): ?string
    {
        return $this->dob;
    }

    public function getExpiryDate(): ?string
    {
        return $this->expiryDate;
    }

    public function isExpired(): bool
    {
        return $this->isExpired;
    }

    public function isMrzValid(): ?bool
    {
        return $this->mrzValid;
    }

    public function getLivenessScore(): float
    {
        return $this->livenessScore;
    }

    public function getConfidenceScore(): int
    {
        return $this->confidenceScore;
    }

    /**
     * @return list<string>
     */
    public function getTamperFlags(): array
    {
        return $this->tamperFlags;
    }

    public function getProviderReferenceId(): string
    {
        return $this->providerReferenceId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRawDetails(): array
    {
        return $this->rawDetails;
    }

    /**
     * Serializes outcome to array format for database storage.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'is_verified' => $this->isVerified,
            'status' => $this->status,
            'document_type' => $this->documentType,
            'full_name' => $this->fullName,
            'dob' => $this->dob,
            'expiry_date' => $this->expiryDate,
            'is_expired' => $this->isExpired,
            'mrz_valid' => $this->mrzValid,
            'liveness_score' => $this->livenessScore,
            'confidence_score' => $this->confidenceScore,
            'tamper_flags' => $this->tamperFlags,
            'provider_reference_id' => $this->providerReferenceId,
        ];
    }
}
