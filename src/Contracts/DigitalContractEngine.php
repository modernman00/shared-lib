<?php

declare(strict_types=1);

namespace Src\Contracts;

use InvalidArgumentException;
use PDO;
use RuntimeException;

/**
 * DigitalContractEngine — Enterprise Cryptographic E-Signature & Contract Lifecycle Management (CLM).
 *
 * Compliant with:
 * - UK Electronic Communications Act 2000 (Section 7)
 * - UK Employment Rights Act 1996 (Section 1)
 * - eIDAS (Regulation (EU) No 910/2014) Advanced Electronic Signature (AES) Standards
 * - UK Consumer Credit Act 1974 (Consumer Credit Agreements Regulations & SECCI)
 *
 * Provides:
 * - Deterministic SHA-256 tamper-evident cryptographic sealing
 * - Secure crypto-random tokenized signing URLs with TTL verification
 * - Dual domain enterprise models:
 *     1. Financial Credit Agreements & SECCI (LoanEasyFinance)
 *     2. Event Host, Venue, Vendor & Performer Contracts (MyPartyPlatform)
 * - Portable SQL DDL for contract signatures & lifecycle audit vaults
 * - Full mathematical audit verification against biometric / raster signature data
 */
class DigitalContractEngine
{
    // ==========================================
    // Contract Type Constants
    // ==========================================
    public const TYPE_CREDIT_AGREEMENT = 'credit_agreement';
    public const TYPE_SECCI = 'secci';
    public const TYPE_LOAN_CONTRACT = 'loan_contract';
    public const TYPE_EVENT_VENDOR = 'event_vendor';
    public const TYPE_VENUE_BOOKING = 'venue_booking';
    public const TYPE_PERFORMER_CONTRACT = 'performer_contract';
    public const TYPE_EMPLOYMENT = 'employment';
    public const TYPE_CLIENT_SERVICE = 'client_service';
    public const TYPE_SUPPLIER = 'supplier';
    public const TYPE_TENANCY_AST = 'tenancy_ast';
    public const TYPE_GUARANTOR_DEED = 'guarantor_deed';

    // ==========================================
    // Contract Status Constants
    // ==========================================
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SENT = 'sent';
    public const STATUS_VIEWED = 'viewed';
    public const STATUS_SIGNED = 'signed';
    public const STATUS_EXPIRED = 'expired';
    public const STATUS_REVOKED = 'revoked';

    // ==========================================
    // Signer Role Constants
    // ==========================================
    public const ROLE_APPLICANT = 'applicant';
    public const ROLE_LENDER = 'lender';
    public const ROLE_GUARANTOR = 'guarantor';
    public const ROLE_HOST = 'host';
    public const ROLE_VENDOR = 'vendor';
    public const ROLE_VENUE_MANAGER = 'venue_manager';
    public const ROLE_PERFORMER = 'performer';
    public const ROLE_EMPLOYEE = 'employee';
    public const ROLE_EMPLOYER = 'employer';
    public const ROLE_LANDLORD = 'landlord';
    public const ROLE_TENANT = 'tenant';

    // ==========================================
    // Regulatory Standards
    // ==========================================
    public const STANDARD_EIDAS = 'eIDAS (Regulation (EU) No 910/2014) Advanced Electronic Signature (AES)';
    public const STANDARD_UK_ECA = 'UK Electronic Communications Act 2000 (Section 7)';
    public const STANDARD_UK_ERA = 'UK Employment Rights Act 1996 (Section 1)';
    public const STANDARD_UK_CCA = 'UK Consumer Credit Act 1974 & Consumer Credit Agreements Regulations';
    public const STANDARD_UK_HOUSING = 'UK Housing Act 1988 & Renters\' Rights Compliance';

    /**
     * Default token TTL: 7 days (604,800 seconds).
     */
    public const DEFAULT_TOKEN_TTL = 604800;

    // =========================================================================
    // 1. CRYPTOGRAPHIC TAMPER-EVIDENT SEALING (SHA-256)
    // =========================================================================

    /**
     * Generates a deterministic SHA-256 tamper-evident seal for a contract signature.
     *
     * In accordance with eIDAS Article 26 & UK ECA 2000:
     * 1. Uniquely linked to the signatory (name + email).
     * 2. Incorporates timestamp, IP address, and raw signature payload hash.
     * 3. Incorporates canonical snapshot of terms/metadata to detect post-execution tampering.
     *
     * @param string $contractType e.g., 'credit_agreement', 'event_vendor'
     * @param string $contractRef Unique reference, e.g. 'LEF-CR-2026-A1B2C'
     * @param string $signerName Full legal name of signatory
     * @param string $signerEmail Verified email of signatory
     * @param string $timestampIso ISO-8601 UTC timestamp of execution (e.g. '2026-10-14T15:30:00Z')
     * @param string $ipAddress Origin IP address (IPv4 or IPv6)
     * @param string $signatureData Base64 raster data, SVG vector, or biometric coordinate JSON
     * @param array<string, mixed> $additionalContext Arbitrary context terms (APR, deposit, venue, etc.)
     * @return string 64-character lowercase hexadecimal SHA-256 digest
     */
    public static function generateSeal(
        string $contractType,
        string $contractRef,
        string $signerName,
        string $signerEmail,
        string $timestampIso,
        string $ipAddress,
        string $signatureData,
        array $additionalContext = []
    ): string {
        $sigHash = hash('sha256', $signatureData);
        $canonicalContext = self::canonicalizeArray($additionalContext);
        $contextHash = hash('sha256', json_encode($canonicalContext, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '{}');

        $canonicalString = implode('|', [
            strtolower(trim($contractType)),
            strtoupper(trim($contractRef)),
            trim($signerName),
            strtolower(trim($signerEmail)),
            trim($timestampIso),
            trim($ipAddress),
            $sigHash,
            $contextHash,
        ]);

        return hash('sha256', $canonicalString);
    }

    /**
     * Verifies a digital contract signature against its recorded SHA-256 checksum.
     *
     * @param string $expectedChecksum The recorded checksum from the audit vault
     * @param string $contractType
     * @param string $contractRef
     * @param string $signerName
     * @param string $signerEmail
     * @param string $timestampIso
     * @param string $ipAddress
     * @param string $signatureData
     * @param array<string, mixed> $additionalContext
     * @return array{
     *     verified: bool,
     *     computed_hash: string,
     *     recorded_hash: string,
     *     tamper_detected: bool,
     *     eidas_compliant: bool,
     *     legal_framework: array<int, string>,
     *     audit_trail: array<string, mixed>
     * }
     */
    public static function verifySeal(
        string $expectedChecksum,
        string $contractType,
        string $contractRef,
        string $signerName,
        string $signerEmail,
        string $timestampIso,
        string $ipAddress,
        string $signatureData,
        array $additionalContext = []
    ): array {
        $computed = self::generateSeal(
            $contractType,
            $contractRef,
            $signerName,
            $signerEmail,
            $timestampIso,
            $ipAddress,
            $signatureData,
            $additionalContext
        );

        $verified = hash_equals(strtolower($expectedChecksum), strtolower($computed));

        return [
            'verified' => $verified,
            'computed_hash' => $computed,
            'recorded_hash' => $expectedChecksum,
            'tamper_detected' => !$verified,
            'eidas_compliant' => $verified,
            'legal_framework' => [
                self::STANDARD_UK_ECA,
                self::STANDARD_EIDAS,
                self::STANDARD_UK_ERA,
                self::STANDARD_UK_CCA,
            ],
            'audit_trail' => [
                'contract_type' => $contractType,
                'contract_ref' => $contractRef,
                'signer_name' => $signerName,
                'signer_email' => $signerEmail,
                'timestamp' => $timestampIso,
                'ip_address' => $ipAddress,
                'context' => $additionalContext,
            ],
        ];
    }

    // =========================================================================
    // 2. TOKENIZED SIGNING URL & REFERENCE GENERATION
    // =========================================================================

    /**
     * Generates a high-entropy, cryptographically secure 64-character signing token.
     */
    public static function generateSigningToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Generates a standardized, human-readable contract reference.
     * Example: 'LEF-CR-2026-9E4B2' or 'MPP-EV-2026-F012A'.
     *
     * @param string $prefix Domain prefix (e.g. 'LEF-CR', 'MPP-EV', 'BCP-EMP')
     */
    public static function generateContractRef(string $prefix = 'CTR'): string
    {
        $year = date('Y');
        $randomHex = strtoupper(substr(bin2hex(random_bytes(3)), 0, 5));
        return "{$prefix}-{$year}-{$randomHex}";
    }

    /**
     * Formats a tokenized signing URL for distribution via email, SMS, or dashboard.
     *
     * @param string $baseUrl Base URL or pattern (e.g. 'https://loaneasyfinance.com/contract/sign' or 'https://mypartyplatform.com/contract/sign/{token}')
     * @param string $token 64-character signing token
     * @param array<string, string> $queryParams Additional query parameters (e.g. ['ref' => 'LEF-CR-...'])
     */
    public static function buildSigningUrl(string $baseUrl, string $token, array $queryParams = []): string
    {
        if (str_contains($baseUrl, '{token}')) {
            $url = str_replace('{token}', urlencode($token), $baseUrl);
        } else {
            $url = rtrim($baseUrl, '/') . '/' . urlencode($token);
        }

        if (!empty($queryParams)) {
            $separator = str_contains($url, '?') ? '&' : '?';
            $url .= $separator . http_build_query($queryParams);
        }

        return $url;
    }

    /**
     * Checks whether a signing token has exceeded its TTL.
     *
     * @param string $createdAt Date/time string parsable by strtotime()
     * @param int $ttlSeconds Time to live in seconds (defaults to 7 days)
     */
    public static function isTokenExpired(string $createdAt, int $ttlSeconds = self::DEFAULT_TOKEN_TTL): bool
    {
        $createdTimestamp = strtotime($createdAt);
        if ($createdTimestamp === false) {
            return true;
        }

        return (time() - $createdTimestamp) > $ttlSeconds;
    }

    // =========================================================================
    // 3. DUAL DOMAIN ENGINE: FINANCIAL CREDIT CONTRACTS (LoanEasyFinance)
    // =========================================================================

    /**
     * Prepares and validates a legal Consumer Credit Agreement & SECCI contract package.
     * Tailored for LoanEasyFinance (`loaneasyfinance.com`).
     *
     * Required in $data:
     * - applicant_name (string)
     * - applicant_email (string)
     * - loan_amount (float > 0)
     * - apr (float >= 0)
     * - term_months (int > 0)
     * - monthly_repayment (float > 0)
     * - total_repayable (float >= loan_amount)
     *
     * Optional in $data:
     * - applicant_id (string|int)
     * - agreement_ref (string)
     * - purpose (string)
     * - secci_reference (string)
     * - lender_name (string, defaults to 'LoanEasyFinance Ltd')
     * - lender_address (string)
     *
     * @param array<string, mixed> $data
     * @param string|null $signingBaseUrl Optional base URL to build signing_url
     * @return array<string, mixed>
     */
    public static function prepareFinancialCreditContract(array $data, ?string $signingBaseUrl = null): array
    {
        $applicantName = trim((string)($data['applicant_name'] ?? ''));
        $applicantEmail = strtolower(trim((string)($data['applicant_email'] ?? '')));

        if ($applicantName === '' || $applicantEmail === '' || !filter_var($applicantEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid applicant_name and applicant_email are strictly required for financial credit contracts.');
        }

        $loanAmount = (float)($data['loan_amount'] ?? 0.0);
        $apr = (float)($data['apr'] ?? 0.0);
        $termMonths = (int)($data['term_months'] ?? 0);
        $monthlyRepayment = (float)($data['monthly_repayment'] ?? 0.0);
        $totalRepayable = (float)($data['total_repayable'] ?? 0.0);

        if ($loanAmount <= 0.0 || $termMonths <= 0 || $monthlyRepayment <= 0.0 || $totalRepayable < $loanAmount) {
            throw new InvalidArgumentException('Invalid loan terms: loan_amount, term_months, monthly_repayment, and total_repayable must be strictly positive.');
        }

        $ref = trim((string)($data['agreement_ref'] ?? ''));
        if ($ref === '') {
            $ref = self::generateContractRef('LEF-CR');
        }

        $token = self::generateSigningToken();
        $lenderName = trim((string)($data['lender_name'] ?? 'LoanEasyFinance Ltd'));
        $costOfCredit = round($totalRepayable - $loanAmount, 2);
        $secciRef = trim((string)($data['secci_reference'] ?? 'SECCI-' . $ref));

        $termsPayload = [
            'contract_type' => self::TYPE_CREDIT_AGREEMENT,
            'contract_ref' => $ref,
            'lender' => [
                'name' => $lenderName,
                'address' => (string)($data['lender_address'] ?? 'London, United Kingdom'),
                'regulatory_body' => 'Financial Conduct Authority (FCA)',
            ],
            'applicant' => [
                'name' => $applicantName,
                'email' => $applicantEmail,
                'id' => (string)($data['applicant_id'] ?? ''),
            ],
            'financial_terms' => [
                'loan_amount' => $loanAmount,
                'apr' => $apr,
                'term_months' => $termMonths,
                'monthly_repayment' => $monthlyRepayment,
                'total_repayable' => $totalRepayable,
                'cost_of_credit' => $costOfCredit,
                'purpose' => (string)($data['purpose'] ?? 'Personal Finance'),
            ],
            'regulatory_compliance' => [
                'standard' => self::STANDARD_UK_CCA,
                'secci_reference' => $secciRef,
                'right_to_withdraw_days' => 14,
                'early_settlement_permitted' => true,
            ],
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        // Compute pre-execution canonical integrity hash of terms (SECCI snapshot)
        $termsChecksum = hash('sha256', json_encode(self::canonicalizeArray($termsPayload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');

        $contract = [
            'contract_type' => self::TYPE_CREDIT_AGREEMENT,
            'contract_ref' => $ref,
            'title' => "Consumer Credit Agreement ({$ref}) - {$applicantName}",
            'signer_name' => $applicantName,
            'signer_email' => $applicantEmail,
            'signer_role' => self::ROLE_APPLICANT,
            'signing_token' => $token,
            'status' => self::STATUS_SENT,
            'terms_payload' => $termsPayload,
            'terms_checksum_sha256' => $termsChecksum,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + self::DEFAULT_TOKEN_TTL),
        ];

        if ($signingBaseUrl !== null && $signingBaseUrl !== '') {
            $contract['signing_url'] = self::buildSigningUrl($signingBaseUrl, $token, ['ref' => $ref]);
        }

        return $contract;
    }

    // =========================================================================
    // 4. DUAL DOMAIN ENGINE: EVENT HOST & VENDOR CONTRACTS (MyPartyPlatform)
    // =========================================================================

    /**
     * Prepares and validates an Event Host, Venue Booking, or Vendor/Performer contract package.
     * Tailored for MyPartyPlatform (`mypartyplatform.com`).
     *
     * Required in $data:
     * - event_name (string)
     * - event_date (string, e.g. '2026-11-20' or ISO)
     * - signer_name (string)
     * - signer_email (string)
     * - signer_role (string: 'host', 'vendor', 'venue_manager', 'performer')
     * - total_fee (float >= 0)
     *
     * Optional in $data:
     * - booking_deposit (float, defaults to 0.0)
     * - counterparty_name (string)
     * - counterparty_email (string)
     * - venue_name (string)
     * - venue_address (string)
     * - cancellation_policy (string)
     * - contract_sub_type (string, defaults to 'event_vendor')
     * - agreement_ref (string)
     * - guest_count (int)
     *
     * @param array<string, mixed> $data
     * @param string|null $signingBaseUrl Optional base URL to build signing_url
     * @return array<string, mixed>
     */
    public static function prepareEventContract(array $data, ?string $signingBaseUrl = null): array
    {
        $signerName = trim((string)($data['signer_name'] ?? ''));
        $signerEmail = strtolower(trim((string)($data['signer_email'] ?? '')));
        $signerRole = strtolower(trim((string)($data['signer_role'] ?? self::ROLE_HOST)));
        $eventName = trim((string)($data['event_name'] ?? ''));
        $eventDate = trim((string)($data['event_date'] ?? ''));

        if ($signerName === '' || $signerEmail === '' || !filter_var($signerEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid signer_name and signer_email are strictly required for event contracts.');
        }

        if ($eventName === '' || $eventDate === '') {
            throw new InvalidArgumentException('event_name and event_date are strictly required for event contracts.');
        }

        $totalFee = (float)($data['total_fee'] ?? 0.0);
        $deposit = (float)($data['booking_deposit'] ?? 0.0);

        if ($totalFee < 0.0 || $deposit < 0.0 || $deposit > $totalFee) {
            throw new InvalidArgumentException('Invalid financial terms: booking_deposit cannot exceed total_fee.');
        }

        $subType = trim((string)($data['contract_sub_type'] ?? self::TYPE_EVENT_VENDOR));
        $refPrefix = match ($subType) {
            self::TYPE_VENUE_BOOKING => 'MPP-VB',
            self::TYPE_PERFORMER_CONTRACT => 'MPP-PRF',
            default => 'MPP-EV',
        };

        $ref = trim((string)($data['agreement_ref'] ?? ''));
        if ($ref === '') {
            $ref = self::generateContractRef($refPrefix);
        }

        $token = self::generateSigningToken();
        $cancellationPolicy = trim((string)($data['cancellation_policy'] ?? 'Standard 14-day cancellation policy applies. Deposit is refundable up to 30 days prior to event.'));

        $termsPayload = [
            'contract_type' => $subType,
            'contract_ref' => $ref,
            'event_details' => [
                'event_name' => $eventName,
                'event_date' => $eventDate,
                'venue_name' => (string)($data['venue_name'] ?? 'To Be Confirmed'),
                'venue_address' => (string)($data['venue_address'] ?? ''),
                'guest_count' => (int)($data['guest_count'] ?? 0),
            ],
            'parties' => [
                'signer' => [
                    'name' => $signerName,
                    'email' => $signerEmail,
                    'role' => $signerRole,
                ],
                'counterparty' => [
                    'name' => (string)($data['counterparty_name'] ?? ''),
                    'email' => (string)($data['counterparty_email'] ?? ''),
                ],
            ],
            'commercial_terms' => [
                'total_fee' => $totalFee,
                'booking_deposit' => $deposit,
                'balance_due' => round($totalFee - $deposit, 2),
                'cancellation_policy' => $cancellationPolicy,
            ],
            'legal_clauses' => [
                'force_majeure' => 'Neither party shall be liable for failure to perform due to circumstances beyond reasonable control.',
                'jurisdiction' => 'England and Wales',
                'governing_standard' => self::STANDARD_UK_ECA,
            ],
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        $termsChecksum = hash('sha256', json_encode(self::canonicalizeArray($termsPayload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');

        $contract = [
            'contract_type' => $subType,
            'contract_ref' => $ref,
            'title' => "{$eventName} Agreement ({$ref}) - {$signerName}",
            'signer_name' => $signerName,
            'signer_email' => $signerEmail,
            'signer_role' => $signerRole,
            'signing_token' => $token,
            'status' => self::STATUS_SENT,
            'terms_payload' => $termsPayload,
            'terms_checksum_sha256' => $termsChecksum,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + self::DEFAULT_TOKEN_TTL),
        ];

        if ($signingBaseUrl !== null && $signingBaseUrl !== '') {
            $contract['signing_url'] = self::buildSigningUrl($signingBaseUrl, $token, ['ref' => $ref]);
        }

        return $contract;
    }

    // =========================================================================
    // 5. UNIVERSAL DATABASE SCHEMA DDL HELPERS
    // =========================================================================

    /**
     * Generates production-ready DDL for the `contract_signatures` audit vault table.
     *
     * @param string $driver 'mysql' | 'mariadb' | 'sqlite'
     */
    public static function getContractSignaturesTableSql(string $driver = 'mysql'): string
    {
        $driver = strtolower($driver);

        if ($driver === 'sqlite') {
            return <<<SQL
CREATE TABLE IF NOT EXISTS `contract_signatures` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `contract_type` TEXT NOT NULL,
    `contract_ref` TEXT NOT NULL,
    `signer_name` TEXT NOT NULL,
    `signer_email` TEXT NOT NULL,
    `signer_role` TEXT NOT NULL,
    `signature_base64` TEXT NOT NULL,
    `ip_address` TEXT NOT NULL,
    `user_agent` TEXT DEFAULT NULL,
    `checksum_sha256` TEXT NOT NULL,
    `metadata_json` TEXT DEFAULT NULL,
    `signed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS `idx_contract_signatures_ref` ON `contract_signatures` (`contract_ref`);
CREATE INDEX IF NOT EXISTS `idx_contract_signatures_checksum` ON `contract_signatures` (`checksum_sha256`);
CREATE INDEX IF NOT EXISTS `idx_contract_signatures_email` ON `contract_signatures` (`signer_email`);
SQL;
        }

        return <<<SQL
CREATE TABLE IF NOT EXISTS `contract_signatures` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `contract_type` VARCHAR(64) NOT NULL,
    `contract_ref` VARCHAR(64) NOT NULL,
    `signer_name` VARCHAR(255) NOT NULL,
    `signer_email` VARCHAR(255) NOT NULL,
    `signer_role` VARCHAR(64) NOT NULL,
    `signature_base64` LONGTEXT NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(512) DEFAULT NULL,
    `checksum_sha256` CHAR(64) NOT NULL,
    `metadata_json` JSON DEFAULT NULL,
    `signed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_cs_contract_ref` (`contract_ref`),
    INDEX `idx_cs_contract_type` (`contract_type`),
    INDEX `idx_cs_signer_email` (`signer_email`),
    INDEX `idx_cs_checksum` (`checksum_sha256`),
    INDEX `idx_cs_signed_at` (`signed_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    }

    /**
     * Generates production-ready DDL for the universal `digital_contracts` master table.
     *
     * @param string $driver 'mysql' | 'mariadb' | 'sqlite'
     */
    public static function getContractsTableSql(string $driver = 'mysql'): string
    {
        $driver = strtolower($driver);

        if ($driver === 'sqlite') {
            return <<<SQL
CREATE TABLE IF NOT EXISTS `digital_contracts` (
    `id` INTEGER PRIMARY KEY AUTOINCREMENT,
    `contract_type` TEXT NOT NULL,
    `contract_ref` TEXT NOT NULL UNIQUE,
    `title` TEXT NOT NULL,
    `signer_name` TEXT NOT NULL,
    `signer_email` TEXT NOT NULL,
    `signer_role` TEXT NOT NULL DEFAULT 'signer',
    `signing_token` TEXT NOT NULL UNIQUE,
    `status` TEXT NOT NULL DEFAULT 'sent',
    `document_hash` TEXT DEFAULT NULL,
    `terms_payload` TEXT DEFAULT NULL,
    `expires_at` DATETIME DEFAULT NULL,
    `signed_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX IF NOT EXISTS `idx_digital_contracts_token` ON `digital_contracts` (`signing_token`);
CREATE INDEX IF NOT EXISTS `idx_digital_contracts_status` ON `digital_contracts` (`status`);
SQL;
        }

        return <<<SQL
CREATE TABLE IF NOT EXISTS `digital_contracts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    `contract_type` VARCHAR(64) NOT NULL,
    `contract_ref` VARCHAR(64) NOT NULL UNIQUE,
    `title` VARCHAR(255) NOT NULL,
    `signer_name` VARCHAR(255) NOT NULL,
    `signer_email` VARCHAR(255) NOT NULL,
    `signer_role` VARCHAR(64) NOT NULL DEFAULT 'signer',
    `signing_token` VARCHAR(64) NOT NULL UNIQUE,
    `status` ENUM('draft', 'sent', 'viewed', 'signed', 'expired', 'revoked') NOT NULL DEFAULT 'sent',
    `document_hash` CHAR(64) DEFAULT NULL,
    `terms_payload` JSON DEFAULT NULL,
    `expires_at` DATETIME DEFAULT NULL,
    `signed_at` DATETIME DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_dc_signing_token` (`signing_token`),
    INDEX `idx_dc_status` (`status`),
    INDEX `idx_dc_type_ref` (`contract_type`, `contract_ref`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL;
    }

    // =========================================================================
    // 6. DATABASE OPERATIONS & AUDIT VERIFICATION
    // =========================================================================

    /**
     * Records an electronic signature into the audit vault with cryptographic sealing.
     * Optionally updates the parent contract status if a table name is provided or exists.
     *
     * Required keys in $params:
     * - contract_type (string)
     * - contract_ref (string)
     * - signer_name (string)
     * - signer_email (string)
     * - signature_base64 (string)
     * - ip_address (string)
     *
     * Optional keys in $params:
     * - signer_role (defaults to 'signer')
     * - user_agent (string)
     * - metadata (array)
     * - timestamp_iso (defaults to current UTC time)
     * - update_contract_table (bool|string: table name to update, e.g. 'digital_contracts')
     *
     * @param PDO $pdo
     * @param array<string, mixed> $params
     * @return array{
     *     success: bool,
     *     contract_ref: string,
     *     checksum_sha256: string,
     *     signed_at: string,
     *     message: string
     * }
     */
    public static function recordSignature(PDO $pdo, array $params): array
    {
        $contractType = trim((string)($params['contract_type'] ?? ''));
        $contractRef = trim((string)($params['contract_ref'] ?? ''));
        $signerName = trim((string)($params['signer_name'] ?? ''));
        $signerEmail = strtolower(trim((string)($params['signer_email'] ?? '')));
        $signatureBase64 = (string)($params['signature_base64'] ?? '');
        $ipAddress = trim((string)($params['ip_address'] ?? '127.0.0.1'));
        $userAgent = (string)($params['user_agent'] ?? '');
        $signerRole = trim((string)($params['signer_role'] ?? 'signer'));
        /** @var array<string, mixed> $metadata */
        $metadata = is_array($params['metadata'] ?? null) ? $params['metadata'] : [];

        if ($contractType === '' || $contractRef === '' || $signerName === '' || $signerEmail === '') {
            throw new InvalidArgumentException('contract_type, contract_ref, signer_name, and signer_email are strictly required.');
        }

        if (trim($signatureBase64) === '') {
            throw new InvalidArgumentException('signature_base64 cannot be empty.');
        }

        $timestampIso = (string)($params['timestamp_iso'] ?? gmdate('Y-m-d\TH:i:s\Z'));
        $metadata['_sealed_at_iso'] = $timestampIso;
        $signedAtDb = gmdate('Y-m-d H:i:s', strtotime($timestampIso) ?: time());

        // 1. Generate cryptographic seal
        $checksum = self::generateSeal(
            $contractType,
            $contractRef,
            $signerName,
            $signerEmail,
            $timestampIso,
            $ipAddress,
            $signatureBase64,
            $metadata
        );

        // 2. Insert into contract_signatures
        $metadataJson = json_encode($metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $stmt = $pdo->prepare("INSERT INTO `contract_signatures`
            (`contract_type`, `contract_ref`, `signer_name`, `signer_email`, `signer_role`, `signature_base64`, `ip_address`, `user_agent`, `checksum_sha256`, `metadata_json`, `signed_at`)
            VALUES (:type, :ref, :name, :email, :role, :sig, :ip, :ua, :hash, :meta, :signed_at)");

        $stmt->execute([
            ':type' => $contractType,
            ':ref' => $contractRef,
            ':name' => $signerName,
            ':email' => $signerEmail,
            ':role' => $signerRole,
            ':sig' => $signatureBase64,
            ':ip' => $ipAddress,
            ':ua' => substr($userAgent, 0, 500),
            ':hash' => $checksum,
            ':meta' => $metadataJson,
            ':signed_at' => $signedAtDb,
        ]);

        // 3. Optional update of parent contract table
        $parentTable = $params['update_contract_table'] ?? null;
        if ($parentTable !== null && is_string($parentTable) && preg_match('/^[a-zA-Z0-9_]+$/', $parentTable)) {
            try {
                $upd = $pdo->prepare("UPDATE `{$parentTable}` SET `status` = 'signed', `document_hash` = :hash, `signed_at` = :signed_at WHERE `contract_ref` = :ref");
                $upd->execute([
                    ':hash' => $checksum,
                    ':signed_at' => $signedAtDb,
                    ':ref' => $contractRef,
                ]);
            } catch (\Throwable) {
                // If table doesn't have these exact columns, let caller handle custom updates
            }
        }

        return [
            'success' => true,
            'contract_ref' => $contractRef,
            'checksum_sha256' => $checksum,
            'signed_at' => $signedAtDb,
            'message' => "Contract {$contractRef} successfully sealed and recorded into cryptographic audit vault.",
        ];
    }

    /**
     * Fetches all cryptographic signatures associated with a contract reference.
     *
     * @param PDO $pdo
     * @param string $contractRef
     * @param string|null $contractType
     * @return array<int, array<string, mixed>>
     */
    public static function getSignaturesByContract(PDO $pdo, string $contractRef, ?string $contractType = null): array
    {
        if ($contractType !== null && $contractType !== '') {
            $stmt = $pdo->prepare("SELECT `id`, `contract_type`, `contract_ref`, `signer_name`, `signer_email`, `signer_role`, `ip_address`, `user_agent`, `checksum_sha256`, `metadata_json`, `signed_at`
                FROM `contract_signatures`
                WHERE `contract_ref` = :ref AND `contract_type` = :type
                ORDER BY `signed_at` ASC");
            $stmt->execute([':ref' => $contractRef, ':type' => $contractType]);
        } else {
            $stmt = $pdo->prepare("SELECT `id`, `contract_type`, `contract_ref`, `signer_name`, `signer_email`, `signer_role`, `ip_address`, `user_agent`, `checksum_sha256`, `metadata_json`, `signed_at`
                FROM `contract_signatures`
                WHERE `contract_ref` = :ref
                ORDER BY `signed_at` ASC");
            $stmt->execute([':ref' => $contractRef]);
        }

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Fetches a stored signature and verifies its mathematical integrity against the recorded SHA-256 seal.
     *
     * @param PDO $pdo
     * @param string $contractRef
     * @param string $signerEmail
     * @return array{
     *     verified: bool,
     *     tamper_detected: bool,
     *     computed_hash: string,
     *     recorded_hash: string,
     *     message: string,
     *     audit_record?: array<string, mixed>
     * }
     */
    public static function verifyStoredSignature(PDO $pdo, string $contractRef, string $signerEmail): array
    {
        $stmt = $pdo->prepare("SELECT * FROM `contract_signatures`
            WHERE `contract_ref` = :ref AND `signer_email` = :email
            ORDER BY `id` DESC LIMIT 1");
        $stmt->execute([
            ':ref' => $contractRef,
            ':email' => strtolower(trim($signerEmail)),
        ]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return [
                'verified' => false,
                'tamper_detected' => false,
                'computed_hash' => '',
                'recorded_hash' => '',
                'message' => "No signature found for contract '{$contractRef}' and signer '{$signerEmail}'.",
            ];
        }

        $metadata = [];
        if (!empty($row['metadata_json'])) {
            $decoded = json_decode((string)$row['metadata_json'], true);
            if (is_array($decoded)) {
                $metadata = $decoded;
            }
        }

        $timestampIso = (string)($metadata['_sealed_at_iso'] ?? gmdate('Y-m-d\TH:i:s\Z', strtotime((string)$row['signed_at'] . ' UTC') ?: time()));

        $verification = self::verifySeal(
            (string)$row['checksum_sha256'],
            (string)$row['contract_type'],
            (string)$row['contract_ref'],
            (string)$row['signer_name'],
            (string)$row['signer_email'],
            $timestampIso,
            (string)$row['ip_address'],
            (string)$row['signature_base64'],
            $metadata
        );

        return [
            'verified' => $verification['verified'],
            'tamper_detected' => $verification['tamper_detected'],
            'computed_hash' => $verification['computed_hash'],
            'recorded_hash' => $verification['recorded_hash'],
            'message' => $verification['verified']
                ? "Signature verified mathematically. SHA-256 seal matches audit record with zero tampering."
                : "ALERT: Tamper detected! The computed SHA-256 checksum does not match the stored audit seal.",
            'audit_record' => $row,
        ];
    }

    // =========================================================================
    // 7. TENANCY DOMAIN, MULTI-PARTY ORCHESTRATION & SEALED PDF GENERATION
    // =========================================================================

    /**
     * Prepares and validates a UK Assured Shorthold Tenancy (AST) or Guarantor Deed package.
     * Tailored for TenantScore (`scoretenant`).
     *
     * Required in $data:
     * - tenant_name (string)
     * - tenant_email (string)
     * - landlord_name (string)
     * - landlord_email (string)
     * - property_address (string)
     * - monthly_rent (float > 0)
     * - term_months (int > 0)
     * - tenancy_start_date (string)
     *
     * Optional in $data:
     * - deposit_amount (float >= 0, defaults to 0.0)
     * - deposit_scheme (string, e.g. 'DPS', 'TDS', 'MyDeposits')
     * - guarantor_name (string)
     * - guarantor_email (string)
     * - agreement_ref (string)
     * - tenant_id (string)
     * - landlord_id (string)
     *
     * @param array<string, mixed> $data
     * @param string|null $signingBaseUrl Optional base URL to build per-signer signing_url
     * @return array<string, mixed>
     */
    public static function prepareTenancyContract(array $data, ?string $signingBaseUrl = null): array
    {
        $tenantName = trim((string)($data['tenant_name'] ?? ''));
        $tenantEmail = strtolower(trim((string)($data['tenant_email'] ?? '')));
        $landlordName = trim((string)($data['landlord_name'] ?? ''));
        $landlordEmail = strtolower(trim((string)($data['landlord_email'] ?? '')));
        $propertyAddress = trim((string)($data['property_address'] ?? ''));
        $startDate = trim((string)($data['tenancy_start_date'] ?? ''));

        if ($tenantName === '' || !filter_var($tenantEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid tenant_name and tenant_email are strictly required for tenancy contracts.');
        }

        if ($landlordName === '' || !filter_var($landlordEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('A valid landlord_name and landlord_email are strictly required for tenancy contracts.');
        }

        if ($propertyAddress === '' || $startDate === '') {
            throw new InvalidArgumentException('property_address and tenancy_start_date are strictly required for tenancy contracts.');
        }

        $monthlyRent = (float)($data['monthly_rent'] ?? 0.0);
        $depositAmount = (float)($data['deposit_amount'] ?? 0.0);
        $termMonths = (int)($data['term_months'] ?? 0);

        if ($monthlyRent <= 0.0 || $termMonths <= 0 || $depositAmount < 0.0) {
            throw new InvalidArgumentException('Invalid tenancy terms: monthly_rent and term_months must be positive, and deposit_amount cannot be negative.');
        }

        $guarantorName = trim((string)($data['guarantor_name'] ?? ''));
        $guarantorEmail = strtolower(trim((string)($data['guarantor_email'] ?? '')));
        $hasGuarantor = $guarantorName !== '' && $guarantorEmail !== '';
        if ($hasGuarantor && !filter_var($guarantorEmail, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('guarantor_email must be a valid email address when provided.');
        }

        $ref = trim((string)($data['agreement_ref'] ?? ''));
        if ($ref === '') {
            $ref = self::generateContractRef('TS-AST');
        }

        $requiredSigners = [
            [
                'role' => self::ROLE_LANDLORD,
                'name' => $landlordName,
                'email' => $landlordEmail,
                'signing_token' => self::generateSigningToken(),
            ],
            [
                'role' => self::ROLE_TENANT,
                'name' => $tenantName,
                'email' => $tenantEmail,
                'signing_token' => self::generateSigningToken(),
            ],
        ];

        if ($hasGuarantor) {
            $requiredSigners[] = [
                'role' => self::ROLE_GUARANTOR,
                'name' => $guarantorName,
                'email' => $guarantorEmail,
                'signing_token' => self::generateSigningToken(),
            ];
        }

        if ($signingBaseUrl !== null && $signingBaseUrl !== '') {
            foreach ($requiredSigners as $idx => $signer) {
                $requiredSigners[$idx]['signing_url'] = self::buildSigningUrl(
                    $signingBaseUrl,
                    $signer['signing_token'],
                    ['ref' => $ref, 'role' => $signer['role']]
                );
            }
        }

        $termsPayload = [
            'contract_type' => self::TYPE_TENANCY_AST,
            'contract_ref' => $ref,
            'property_address' => $propertyAddress,
            'landlord' => [
                'name' => $landlordName,
                'email' => $landlordEmail,
                'id' => (string)($data['landlord_id'] ?? ''),
            ],
            'tenant' => [
                'name' => $tenantName,
                'email' => $tenantEmail,
                'id' => (string)($data['tenant_id'] ?? ''),
            ],
            'guarantor' => $hasGuarantor ? [
                'name' => $guarantorName,
                'email' => $guarantorEmail,
            ] : null,
            'tenancy_terms' => [
                'monthly_rent_gbp' => round($monthlyRent, 2),
                'deposit_amount_gbp' => round($depositAmount, 2),
                'deposit_scheme' => (string)($data['deposit_scheme'] ?? 'DPS'),
                'term_months' => $termMonths,
                'tenancy_start_date' => $startDate,
            ],
            'regulatory_compliance' => [
                'standard' => self::STANDARD_UK_HOUSING,
                'eca_standard' => self::STANDARD_UK_ECA,
            ],
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
        ];

        $termsChecksum = hash('sha256', json_encode(self::canonicalizeArray($termsPayload), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '');
        $primaryToken = $requiredSigners[1]['signing_token'];

        $contract = [
            'contract_type' => self::TYPE_TENANCY_AST,
            'contract_ref' => $ref,
            'title' => "Assured Shorthold Tenancy ({$ref}) - {$propertyAddress}",
            'signer_name' => $tenantName,
            'signer_email' => $tenantEmail,
            'signer_role' => self::ROLE_TENANT,
            'signing_token' => $primaryToken,
            'required_roles' => array_column($requiredSigners, 'role'),
            'required_signers' => $requiredSigners,
            'status' => self::STATUS_SENT,
            'terms_payload' => $termsPayload,
            'terms_checksum_sha256' => $termsChecksum,
            'created_at' => gmdate('Y-m-d H:i:s'),
            'expires_at' => gmdate('Y-m-d H:i:s', time() + self::DEFAULT_TOKEN_TTL),
        ];

        if ($signingBaseUrl !== null && $signingBaseUrl !== '') {
            $contract['signing_url'] = self::buildSigningUrl(
                $signingBaseUrl,
                $primaryToken,
                ['ref' => $ref, 'role' => self::ROLE_TENANT]
            );
        }

        return $contract;
    }

    /**
     * Evaluates multi-party signing progress for a contract against required roles.
     *
     * @param PDO $pdo
     * @param string $contractRef
     * @param array<int, string> $requiredRoles
     * @return array{
     *     contract_ref: string,
     *     required_roles: array<int, string>,
     *     signed_roles: array<int, string>,
     *     pending_roles: array<int, string>,
     *     is_fully_signed: bool,
     *     completion_percentage: float,
     *     signatures: array<int, array<string, mixed>>
     * }
     */
    public static function getContractSigningProgress(PDO $pdo, string $contractRef, array $requiredRoles): array
    {
        $normalizedRequired = [];
        foreach ($requiredRoles as $role) {
            $clean = strtolower(trim((string)$role));
            if ($clean !== '' && !in_array($clean, $normalizedRequired, true)) {
                $normalizedRequired[] = $clean;
            }
        }

        if ($normalizedRequired === []) {
            throw new InvalidArgumentException('requiredRoles must contain at least one non-empty role.');
        }

        $signatures = self::getSignaturesByContract($pdo, $contractRef);
        $signedRoles = [];
        foreach ($signatures as $sig) {
            $role = strtolower(trim((string)($sig['signer_role'] ?? '')));
            if ($role !== '' && !in_array($role, $signedRoles, true)) {
                $signedRoles[] = $role;
            }
        }

        $pendingRoles = array_values(array_diff($normalizedRequired, $signedRoles));
        $matchedCount = count($normalizedRequired) - count($pendingRoles);
        $percentage = round(($matchedCount / count($normalizedRequired)) * 100.0, 2);

        return [
            'contract_ref' => $contractRef,
            'required_roles' => $normalizedRequired,
            'signed_roles' => $signedRoles,
            'pending_roles' => $pendingRoles,
            'is_fully_signed' => $pendingRoles === [],
            'completion_percentage' => $percentage,
            'signatures' => $signatures,
        ];
    }

    /**
     * Records a signature in a multi-party workflow and transitions `digital_contracts`
     * to `signed` only once every required role has executed its signature.
     *
     * @param PDO $pdo
     * @param array<string, mixed> $params Must include `required_roles` (array<int, string>) plus `recordSignature` fields.
     *                                     Optional `sync_digital_contracts` (bool, default true) updates `digital_contracts` on completion.
     * @return array{
     *     success: bool,
     *     contract_ref: string,
     *     checksum_sha256: string,
     *     signed_at: string,
     *     is_fully_signed: bool,
     *     pending_roles: array<int, string>,
     *     completion_percentage: float,
     *     composite_checksum_sha256: string|null,
     *     message: string
     * }
     */
    public static function recordMultiPartySignature(PDO $pdo, array $params): array
    {
        $requiredRolesRaw = $params['required_roles'] ?? null;
        if (!is_array($requiredRolesRaw) || $requiredRolesRaw === []) {
            throw new InvalidArgumentException('required_roles must be a non-empty array of signer roles.');
        }

        /** @var array<int, string> $requiredRoles */
        $requiredRoles = array_values(array_map(static fn($r): string => strtolower(trim((string)$r)), $requiredRolesRaw));
        $contractRef = trim((string)($params['contract_ref'] ?? ''));
        $signerRole = strtolower(trim((string)($params['signer_role'] ?? '')));

        if ($signerRole === '' || !in_array($signerRole, $requiredRoles, true)) {
            throw new InvalidArgumentException("signer_role '{$signerRole}' is not in the contract's required_roles list.");
        }

        $allowDuplicate = (bool)($params['allow_duplicate_role'] ?? false);
        if (!$allowDuplicate && $contractRef !== '') {
            $existing = self::getContractSigningProgress($pdo, $contractRef, $requiredRoles);
            if (in_array($signerRole, $existing['signed_roles'], true)) {
                throw new RuntimeException("Role '{$signerRole}' has already signed contract '{$contractRef}'.");
            }
        }

        // Prevent recordSignature() from prematurely marking the parent table as 'signed'
        $recordParams = $params;
        unset($recordParams['update_contract_table'], $recordParams['required_roles'], $recordParams['allow_duplicate_role'], $recordParams['sync_digital_contracts']);
        $recordParams['signer_role'] = $signerRole;

        $sigResult = self::recordSignature($pdo, $recordParams);
        $progress = self::getContractSigningProgress($pdo, $sigResult['contract_ref'], $requiredRoles);

        $compositeHash = null;
        if ($progress['is_fully_signed']) {
            $hashes = array_map(
                static fn(array $s): string => (string)($s['checksum_sha256'] ?? ''),
                $progress['signatures']
            );
            $compositeHash = hash('sha256', implode('|', $hashes));

            $syncMaster = (bool)($params['sync_digital_contracts'] ?? true);
            if ($syncMaster) {
                $upd = $pdo->prepare('UPDATE `digital_contracts` SET `status` = :status, `document_hash` = :hash, `signed_at` = :signed_at WHERE `contract_ref` = :ref');
                $upd->execute([
                    ':status' => self::STATUS_SIGNED,
                    ':hash' => $compositeHash,
                    ':signed_at' => $sigResult['signed_at'],
                    ':ref' => $sigResult['contract_ref'],
                ]);
            }
        }

        return [
            'success' => true,
            'contract_ref' => $sigResult['contract_ref'],
            'checksum_sha256' => $sigResult['checksum_sha256'],
            'signed_at' => $sigResult['signed_at'],
            'is_fully_signed' => $progress['is_fully_signed'],
            'pending_roles' => $progress['pending_roles'],
            'completion_percentage' => $progress['completion_percentage'],
            'composite_checksum_sha256' => $compositeHash,
            'message' => $progress['is_fully_signed']
                ? "All required parties have signed contract {$sigResult['contract_ref']}."
                : "Signature recorded for role '{$signerRole}'. Waiting on: " . implode(', ', $progress['pending_roles']) . '.',
        ];
    }

    /**
     * Appends a cryptographic execution & audit certificate to contract HTML.
     *
     * @param PDO $pdo
     * @param string $contractRef
     * @param string $contractTitle
     * @param string $bodyHtml
     * @param string|null $termsChecksum
     * @return string Complete HTML document ready for PDF rendering
     */
    public static function buildAuditCertificateHtml(
        PDO $pdo,
        string $contractRef,
        string $contractTitle,
        string $bodyHtml,
        ?string $termsChecksum = null
    ): string {
        $stmt = $pdo->prepare('SELECT `signer_name`, `signer_email`, `signer_role`, `signature_base64`, `ip_address`, `checksum_sha256`, `signed_at`
            FROM `contract_signatures`
            WHERE `contract_ref` = :ref
            ORDER BY `signed_at` ASC');
        $stmt->execute([':ref' => $contractRef]);
        $signatures = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $esc = static fn(string $v): string => htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $rowsHtml = '';
        foreach ($signatures as $sig) {
            $sigImg = '';
            $rawSig = trim((string)($sig['signature_base64'] ?? ''));
            if (str_starts_with($rawSig, 'data:image/')) {
                $sigImg = '<img src="' . $esc($rawSig) . '" alt="Signature" style="max-height:48px;max-width:160px;border:1px solid #e2e8f0;padding:2px;background:#fff;" />';
            }

            $rowsHtml .= '<tr>'
                . '<td style="padding:8px;border:1px solid #cbd5e1;">' . $esc((string)($sig['signer_name'] ?? '')) . '<br><small style="color:#475569;">' . $esc((string)($sig['signer_email'] ?? '')) . '</small></td>'
                . '<td style="padding:8px;border:1px solid #cbd5e1;text-transform:uppercase;font-size:11px;">' . $esc((string)($sig['signer_role'] ?? '')) . '</td>'
                . '<td style="padding:8px;border:1px solid #cbd5e1;font-size:11px;">' . $esc((string)($sig['signed_at'] ?? '')) . ' UTC<br>IP: ' . $esc((string)($sig['ip_address'] ?? '')) . '</td>'
                . '<td style="padding:8px;border:1px solid #cbd5e1;font-family:monospace;font-size:10px;word-break:break-all;">' . $esc((string)($sig['checksum_sha256'] ?? '')) . '</td>'
                . '<td style="padding:8px;border:1px solid #cbd5e1;text-align:center;">' . $sigImg . '</td>'
                . '</tr>';
        }

        if ($rowsHtml === '') {
            $rowsHtml = '<tr><td colspan="5" style="padding:10px;border:1px solid #cbd5e1;color:#64748b;">No signatures recorded yet.</td></tr>';
        }

        $termsRow = $termsChecksum !== null && $termsChecksum !== ''
            ? '<p style="font-size:11px;color:#334155;margin:4px 0;"><strong>Terms Snapshot SHA-256:</strong> <span style="font-family:monospace;">' . $esc($termsChecksum) . '</span></p>'
            : '';

        $certificate = '<div style="page-break-before:always;font-family:Arial,Helvetica,sans-serif;color:#0f172a;padding:20px;border:1px solid #cbd5e1;border-radius:8px;background:#f8fafc;">'
            . '<h2 style="margin:0 0 6px 0;font-size:18px;color:#0f172a;">Cryptographic Execution &amp; Audit Certificate</h2>'
            . '<p style="font-size:12px;color:#334155;margin:4px 0;"><strong>Document:</strong> ' . $esc($contractTitle) . ' (' . $esc($contractRef) . ')</p>'
            . $termsRow
            . '<p style="font-size:11px;color:#475569;margin:4px 0 14px 0;">Executed electronically under ' . $esc(self::STANDARD_UK_ECA) . ' &amp; ' . $esc(self::STANDARD_EIDAS) . '.</p>'
            . '<table style="width:100%;border-collapse:collapse;font-size:12px;background:#ffffff;">'
            . '<thead><tr style="background:#e2e8f0;text-align:left;">'
            . '<th style="padding:8px;border:1px solid #cbd5e1;">Signatory</th>'
            . '<th style="padding:8px;border:1px solid #cbd5e1;">Role</th>'
            . '<th style="padding:8px;border:1px solid #cbd5e1;">Timestamp &amp; Origin</th>'
            . '<th style="padding:8px;border:1px solid #cbd5e1;">SHA-256 Seal</th>'
            . '<th style="padding:8px;border:1px solid #cbd5e1;">Mark</th>'
            . '</tr></thead>'
            . '<tbody>' . $rowsHtml . '</tbody>'
            . '</table>'
            . '</div>';

        return $bodyHtml . "\n" . $certificate;
    }

    /**
     * Renders a sealed contract with its Cryptographic Audit Certificate into a PDF binary
     * using SovereignPdfEngine.
     *
     * @param PDO $pdo
     * @param string $contractRef
     * @param string $contractTitle
     * @param string $bodyHtml
     * @param string|null $termsChecksum
     * @param array<string, mixed> $pdfOptions
     * @return string Raw PDF binary
     */
    public static function renderSealedContractPdf(
        PDO $pdo,
        string $contractRef,
        string $contractTitle,
        string $bodyHtml,
        ?string $termsChecksum = null,
        array $pdfOptions = []
    ): string {
        $fullHtml = self::buildAuditCertificateHtml($pdo, $contractRef, $contractTitle, $bodyHtml, $termsChecksum);
        $options = array_merge(['title' => $contractTitle, 'author' => 'DigitalContractEngine'], $pdfOptions);

        return \Src\Media\SovereignPdfEngine::renderHtmlToPdf($fullHtml, $options);
    }

    // =========================================================================
    // HELPER UTILITIES
    // =========================================================================

    /**
     * Recursively sort array keys to guarantee deterministic serialization for hashing.
     *
     * @param array<mixed> $array
     * @return array<mixed>
     */
    private static function canonicalizeArray(array $array): array
    {
        $isAssoc = !array_is_list($array);
        if ($isAssoc) {
            ksort($array);
        }

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $array[$key] = self::canonicalizeArray($value);
            }
        }

        return $array;
    }
}

// Backward-compatibility and interoperability alias
if (!class_exists('SharedLib\Contracts\DigitalContractEngine', false)) {
    class_alias(DigitalContractEngine::class, 'SharedLib\Contracts\DigitalContractEngine');
}
