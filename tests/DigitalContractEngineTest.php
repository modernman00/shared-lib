<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PDO;
use PHPUnit\Framework\TestCase;
use Src\Contracts\DigitalContractEngine;

class DigitalContractEngineTest extends TestCase
{
    private PDO $sqlitePdo;

    protected function setUp(): void
    {
        parent::setUp();

        // Create an in-memory SQLite database for zero-dependency integration testing
        $this->sqlitePdo = new PDO('sqlite::memory:');
        $this->sqlitePdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->sqlitePdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // Execute DDLs
        $this->sqlitePdo->exec(DigitalContractEngine::getContractSignaturesTableSql('sqlite'));
        $this->sqlitePdo->exec(DigitalContractEngine::getContractsTableSql('sqlite'));
    }

    public function testGenerateSealIsDeterministicAndTamperEvident(): void
    {
        $type = DigitalContractEngine::TYPE_CREDIT_AGREEMENT;
        $ref = 'LEF-CR-2026-TEST1';
        $name = 'John Doe';
        $email = 'john.doe@example.com';
        $time = '2026-10-14T12:00:00Z';
        $ip = '192.168.1.50';
        $sigData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';
        $context = ['apr' => 12.5, 'loan_amount' => 5000.00];

        $seal1 = DigitalContractEngine::generateSeal($type, $ref, $name, $email, $time, $ip, $sigData, $context);
        $seal2 = DigitalContractEngine::generateSeal($type, $ref, $name, $email, $time, $ip, $sigData, $context);

        $this->assertSame(64, strlen($seal1));
        $this->assertSame($seal1, $seal2, 'Seals must be identical for identical inputs');

        // Tampering any parameter must yield a completely different checksum
        $tamperedSig = $sigData . 'tampered';
        $sealTamperedSig = DigitalContractEngine::generateSeal($type, $ref, $name, $email, $time, $ip, $tamperedSig, $context);
        $this->assertNotSame($seal1, $sealTamperedSig, 'Altering signature data must change seal');

        $sealTamperedIp = DigitalContractEngine::generateSeal($type, $ref, $name, $email, $time, '10.0.0.1', $sigData, $context);
        $this->assertNotSame($seal1, $sealTamperedIp, 'Altering IP must change seal');

        $sealTamperedContext = DigitalContractEngine::generateSeal($type, $ref, $name, $email, $time, $ip, $sigData, ['apr' => 19.9, 'loan_amount' => 5000.00]);
        $this->assertNotSame($seal1, $sealTamperedContext, 'Altering context APR must change seal');
    }

    public function testVerifySealReturnsTrueOnValidAndFlagsTampering(): void
    {
        $type = DigitalContractEngine::TYPE_EVENT_VENDOR;
        $ref = 'MPP-EV-2026-VND01';
        $name = 'DJ Khaled';
        $email = 'khaled@wethebest.com';
        $time = '2026-10-14T14:30:00Z';
        $ip = '82.45.12.90';
        $sigData = 'data:image/png;base64,sample-signature-stream';
        $context = ['event_name' => 'Tech Summit Afterparty', 'fee' => 1500.00];

        $seal = DigitalContractEngine::generateSeal($type, $ref, $name, $email, $time, $ip, $sigData, $context);

        $resultValid = DigitalContractEngine::verifySeal($seal, $type, $ref, $name, $email, $time, $ip, $sigData, $context);
        $this->assertTrue($resultValid['verified']);
        $this->assertFalse($resultValid['tamper_detected']);
        $this->assertTrue($resultValid['eidas_compliant']);
        $this->assertSame($seal, $resultValid['computed_hash']);

        // Test with forged seal
        $resultForged = DigitalContractEngine::verifySeal('0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef', $type, $ref, $name, $email, $time, $ip, $sigData, $context);
        $this->assertFalse($resultForged['verified']);
        $this->assertTrue($resultForged['tamper_detected']);
        $this->assertFalse($resultForged['eidas_compliant']);
    }

    public function testTokenAndUrlGeneration(): void
    {
        $token = DigitalContractEngine::generateSigningToken();
        $this->assertSame(64, strlen($token));
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);

        $ref = DigitalContractEngine::generateContractRef('LEF-CR');
        $this->assertStringStartsWith('LEF-CR-' . date('Y') . '-', $ref);

        $urlPattern = 'https://loaneasyfinance.com/contract/sign/{token}';
        $builtUrl = DigitalContractEngine::buildSigningUrl($urlPattern, $token, ['ref' => $ref]);
        $this->assertSame("https://loaneasyfinance.com/contract/sign/{$token}?ref=" . urlencode($ref), $builtUrl);

        $urlBase = 'https://mypartyplatform.com/contract/sign';
        $builtUrl2 = DigitalContractEngine::buildSigningUrl($urlBase, $token);
        $this->assertSame("https://mypartyplatform.com/contract/sign/{$token}", $builtUrl2);

        // Expiry check
        $now = gmdate('Y-m-d H:i:s');
        $this->assertFalse(DigitalContractEngine::isTokenExpired($now, 3600));

        $past = gmdate('Y-m-d H:i:s', time() - 4000);
        $this->assertTrue(DigitalContractEngine::isTokenExpired($past, 3600));
    }

    public function testPrepareFinancialCreditContractForLoanEasyFinance(): void
    {
        $contract = DigitalContractEngine::prepareFinancialCreditContract([
            'applicant_name' => 'Alice Johnson',
            'applicant_email' => 'alice@example.co.uk',
            'applicant_id' => 'USR-9981',
            'loan_amount' => 5000.00,
            'apr' => 12.9,
            'term_months' => 36,
            'monthly_repayment' => 166.50,
            'total_repayable' => 5994.00,
            'purpose' => 'Home Improvement',
        ], 'https://loaneasyfinance.com/contract/sign');

        $this->assertSame(DigitalContractEngine::TYPE_CREDIT_AGREEMENT, $contract['contract_type']);
        $this->assertStringStartsWith('LEF-CR-', $contract['contract_ref']);
        $this->assertSame('Alice Johnson', $contract['signer_name']);
        $this->assertSame('alice@example.co.uk', $contract['signer_email']);
        $this->assertSame(DigitalContractEngine::ROLE_APPLICANT, $contract['signer_role']);
        $this->assertSame(DigitalContractEngine::STATUS_SENT, $contract['status']);
        $this->assertSame(64, strlen($contract['signing_token']));
        $this->assertSame(64, strlen($contract['terms_checksum_sha256']));
        $this->assertStringContainsString('https://loaneasyfinance.com/contract/sign/' . $contract['signing_token'], $contract['signing_url']);

        $this->assertEquals(994.00, $contract['terms_payload']['financial_terms']['cost_of_credit']);
        $this->assertEquals(14, $contract['terms_payload']['regulatory_compliance']['right_to_withdraw_days']);
    }

    public function testPrepareFinancialCreditContractValidationThrowsOnInvalidInputs(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DigitalContractEngine::prepareFinancialCreditContract([
            'applicant_name' => 'Alice',
            'applicant_email' => 'invalid-email',
            'loan_amount' => 5000.00,
        ]);
    }

    public function testPrepareEventContractForMyPartyPlatform(): void
    {
        $contract = DigitalContractEngine::prepareEventContract([
            'signer_name' => 'Sarah Host',
            'signer_email' => 'sarah.host@event.com',
            'signer_role' => DigitalContractEngine::ROLE_HOST,
            'event_name' => 'Sarah 30th Birthday Gala',
            'event_date' => '2026-11-28',
            'venue_name' => 'Mayfair Penthouse Ballroom',
            'total_fee' => 3500.00,
            'booking_deposit' => 1000.00,
            'contract_sub_type' => DigitalContractEngine::TYPE_VENUE_BOOKING,
            'counterparty_name' => 'Mayfair Venue Group Ltd',
            'counterparty_email' => 'bookings@mayfairballroom.co.uk',
        ], 'https://mypartyplatform.com/sign/{token}');

        $this->assertSame(DigitalContractEngine::TYPE_VENUE_BOOKING, $contract['contract_type']);
        $this->assertStringStartsWith('MPP-VB-', $contract['contract_ref']);
        $this->assertSame('Sarah Host', $contract['signer_name']);
        $this->assertSame(2500.00, $contract['terms_payload']['commercial_terms']['balance_due']);
        $this->assertSame('Mayfair Penthouse Ballroom', $contract['terms_payload']['event_details']['venue_name']);
        $this->assertStringContainsString('https://mypartyplatform.com/sign/' . $contract['signing_token'], $contract['signing_url']);
    }

    public function testPrepareEventContractThrowsWhenDepositExceedsTotalFee(): void
    {
        $this->expectException(InvalidArgumentException::class);
        DigitalContractEngine::prepareEventContract([
            'signer_name' => 'Host User',
            'signer_email' => 'host@party.com',
            'event_name' => 'Summer Fest',
            'event_date' => '2026-08-15',
            'total_fee' => 500.00,
            'booking_deposit' => 1000.00, // Invalid!
        ]);
    }

    public function testDatabaseRecordSignatureAndVerifyStoredSignatureEndToEnd(): void
    {
        $type = DigitalContractEngine::TYPE_CREDIT_AGREEMENT;
        $ref = 'LEF-CR-2026-E2E01';
        $name = 'Michael Sterling';
        $email = 'michael.sterling@example.co.uk';
        $sigData = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAUAAAAFCAYAAACNbyblAAAAHElEQVQI12P4//8/w38GIAXDIBKE0DHxgljNBAAO9TXL0Y4OHwAAAABJRU5ErkJggg==';
        $ip = '185.120.34.12';
        $ua = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X)';
        $meta = ['loan_amount' => 10000.00, 'apr' => 9.9];

        // 1. Record signature
        $recordResult = DigitalContractEngine::recordSignature($this->sqlitePdo, [
            'contract_type' => $type,
            'contract_ref' => $ref,
            'signer_name' => $name,
            'signer_email' => $email,
            'signer_role' => DigitalContractEngine::ROLE_APPLICANT,
            'signature_base64' => $sigData,
            'ip_address' => $ip,
            'user_agent' => $ua,
            'metadata' => $meta,
        ]);

        $this->assertTrue($recordResult['success']);
        $this->assertSame(64, strlen($recordResult['checksum_sha256']));

        // 2. Fetch list of signatures
        $signatures = DigitalContractEngine::getSignaturesByContract($this->sqlitePdo, $ref);
        $this->assertCount(1, $signatures);
        $this->assertSame($name, $signatures[0]['signer_name']);
        $this->assertSame($recordResult['checksum_sha256'], $signatures[0]['checksum_sha256']);

        // 3. Verify stored signature
        $verifyResult = DigitalContractEngine::verifyStoredSignature($this->sqlitePdo, $ref, $email);
        $this->assertTrue($verifyResult['verified']);
        $this->assertFalse($verifyResult['tamper_detected']);
        $this->assertSame($recordResult['checksum_sha256'], $verifyResult['computed_hash']);

        // 4. Simulate malicious database row modification (tampering with signature data)
        $this->sqlitePdo->exec("UPDATE contract_signatures SET signature_base64 = 'forged-data' WHERE contract_ref = '{$ref}'");

        $tamperedResult = DigitalContractEngine::verifyStoredSignature($this->sqlitePdo, $ref, $email);
        $this->assertFalse($tamperedResult['verified']);
        $this->assertTrue($tamperedResult['tamper_detected']);
    }

    public function testClassAliasSharedLibContractsDigitalContractEngine(): void
    {
        $this->assertTrue(class_exists(\SharedLib\Contracts\DigitalContractEngine::class));
        $ref = \SharedLib\Contracts\DigitalContractEngine::generateContractRef('ALIAS');
        $this->assertStringStartsWith('ALIAS-', $ref);
    }
}
