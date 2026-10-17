# RFC 0002: Universal Open Banking (AIS) & Third-Party KYC Identity Referencing Engine

- **Status:** Proposed
- **Authors:** James (Principal Architect & Head of TFT), Dr. Aris Thorne (Senior Staff Systems Engineer), Victor (CTO)
- **Reviewers:** Sarah (CPO), Chloe (CMO), Marcus (SecOps), Helena (Board Rep), David (Gatewatcher), Olutobi (Deloitte Audit), Dr. Silas Thorne (Contrarian Lead)
- **Target Release:** `modernman00/shared-lib` v2.6.0
- **Consumer Applications:** `ScoreTenant`, `LoanEasyFinance`, `iAccountApp`

---

## 1. Executive Summary & Problem Statement

Across UK PropTech and FinTech applications (`ScoreTenant`, `LoanEasyFinance`, `iAccountApp`), applicant self-declaration of income and upload of static paper documents (payslips, utility bills, PDF scans) introduces severe commercial and operational failure modes:
1. **Pervasive Document Fraud:** Manipulated PDF payslips, falsified net income, and forged bank statements are trivially generated using browser inspection tools or template packs.
2. **Brittle Heuristics & OCR Failure:** Optical character recognition (OCR) on skewed, low-resolution phone photos of payslips exhibits high error rates, leading to false rejections or manual review backlogs.
3. **Statutory Right to Rent Liability:** Under UK immigration regulations, private landlords and letting agents face statutory civil penalties if Right to Rent identity verification is not executed via an approved Identity Service Provider (IDSP) or direct physical inspection.

This RFC standardises two foundational, tamper-proof pillars directly inside `modernman00/shared-lib`:
- **Pillar 1: Regulated Open Banking Account Information Services (AIS)** via TrueLayer Data API to deterministically verify 90-day salary inflows, regular payroll cadence, and cashflow commitments with mathematical zero-trust.
- **Pillar 2: Certified Third-Party KYC & Biometric Identity Verification** via an extensible provider contract (`KycProviderInterface`) supporting approved IDSPs (ComplyCube / Onfido / Yoti) with automated liveness and MRZ validation.

---

## 2. Technical Architecture & Component Design

### 2.1 Namespace & Directory Layout
```
src/
├── Fintech/
│   └── OpenBanking/
│       ├── TrueLayerClient.php             # Low-level authenticated HTTP client (Data API AIS)
│       ├── OpenBankingSession.php          # OAuth2 state, PKCE & token life-cycle model
│       ├── AISIncomeVerifier.php           # High-precision payroll extraction & income stability scoring
│       └── Exceptions/
│           ├── OpenBankingException.php
│           └── TokenExpiredException.php
└── Kyc/
    ├── Contracts/
    │   ├── KycProviderInterface.php        # Immutable contract for identity verification
    │   └── KycVerificationResult.php       # Strongly typed immutable DTO
    ├── Providers/
    │   ├── ComplyCubeKycProvider.php       # Primary UK AML/KYC & IDSP provider
    │   └── MockKycProvider.php             # Deterministic sandbox for test suites & CI/CD
    └── Exceptions/
        ├── KycProviderException.php
        └── InvalidWebhookSignatureException.php
```

### 2.2 Pillar 1: TrueLayerClient & AISIncomeVerifier

#### `TrueLayerClient`
- Manages secure bank selection redirects (`uk-ob-all`, `uk-oauth-all`).
- Encapsulates OAuth 2.0 authorization code exchange and automated refresh token rotation.
- Ingests accounts, balances, and historical transaction arrays with strict defensive typing.

#### `AISIncomeVerifier`
- Processes 90-day bank transaction streams.
- Filters out non-recurring credits, internal transfers, and loan disbursements.
- Extracts recurring payroll credits using regular expression matching against UK payroll clearing indicators (`SALARY`, `PAYROLL`, `WAGES`, `BACS`, `FASTER PAYMENT EMPLOYER`).
- Computes:
  - `verified_monthly_net_income`: Mathematically normalized monthly net pay.
  - `income_stability_index`: Consistency score based on payment variance and gap analysis.
  - `discretionary_cashflow_buffer`: Free disposable income after fixed liabilities.

### 2.3 Pillar 2: KycProviderInterface & KycVerificationResult

```php
namespace Src\Kyc\Contracts;

interface KycProviderInterface
{
    /**
     * Generate an applicant biometric verification session.
     *
     * @param string $applicantId Unique internal tenant/user identifier
     * @param array<string, mixed> $metadata Client-specific telemetry (callback URLs, document constraints)
     * @return array{session_id: string, redirect_url: string, token: string}
     */
    public function createSession(string $applicantId, array $metadata = []): array;

    /**
     * Cryptographically verify inbound webhook signature (HMAC-SHA256).
     */
    public function verifyWebhookSignature(string $rawPayload, string $signatureHeader): bool;

    /**
     * Parse and normalize webhook or polling result into immutable DTO.
     *
     * @param array<string, mixed> $payload
     */
    public function parseResult(array $payload): KycVerificationResult;
}
```

#### `KycVerificationResult` DTO
- `status`: `APPROVED`, `REJECTED`, `FLAGGED_FOR_MANUAL_REVIEW`.
- `document_type`: `PASSPORT`, `DRIVING_LICENCE`, `NATIONAL_IDENTITY_CARD`.
- `full_name`: Standardised full name extracted from document.
- `dob`: Date of birth (`Y-m-d`).
- `expiry_date`: Document expiration date.
- `is_expired`: Boolean check.
- `mrz_valid`: Boolean (for passports).
- `liveness_score`: Biometric face matching score ($0.0 - 1.0$).
- `tamper_flags`: Array of optical/cryptographic tamper flags.

---

## 3. Security, Privacy & Adversarial Threat Modeling (Marcus & Ghost Audit)

1. **Token Protection at Rest:** Open Banking access and refresh tokens MUST be encrypted with AES-256-GCM using application keystore keys prior to persistence.
2. **Webhook Replay & Spoofing Defense:** Webhook endpoints MUST validate HMAC-SHA256 signatures with timestamp drift guards ($\le 300\text{ seconds}$).
3. **GDPR / Caldicott Principle Minimisation:** Raw biometric images and unmasked passport numbers are NOT retained in application databases. Only certified verification tokens, document expiry dates, and verification hash proofs are stored.
4. **State Manipulation (OAuth / CSRF):** TrueLayer authorization redirects enforce cryptographically random 32-byte `state` tokens bound to the authenticated user's session.

---

## 4. Gatewatcher Verification (David & Kieran Clearance)

- **PHPStan Level 8:** Strict typing across all new classes, zero un-typed iterables, strict null safety on external API payloads.
- **Circuit Breakers & Timeouts:** cURL HTTP requests enforce 5.0s connect timeouts and 10.0s total execution timeouts to prevent upstream provider stalls from deadlocking application worker threads.
- **Deterministic Mocking:** `MockKycProvider` and sandbox mode in `TrueLayerClient` enable 100% test coverage without live external network dependencies in automated CI/CD pipelines.

---

## 5. Verification & Test Plan

1. **Unit Tests:**
   - `tests/Fintech/OpenBanking/TrueLayerClientTest.php`: OAuth URL creation, token exchange mocking, error handling.
   - `tests/Fintech/OpenBanking/AISIncomeVerifierTest.php`: Mathematical salary calculation, erratic income detection, transfer filtering.
   - `tests/Kyc/ComplyCubeKycProviderTest.php`: Webhook HMAC validation, parsing of valid vs forged payloads.
2. **Chaos Tests:**
   - Network failure simulation (HTTP 504, malformed JSON response, expired token).
3. **Integration Test Suite:**
   - Synthetic end-to-end applicant verification loop.
