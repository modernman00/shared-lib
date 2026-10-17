<?php

declare(strict_types=1);

namespace Tests\Fintech\OpenBanking;

use PHPUnit\Framework\TestCase;
use Src\Fintech\OpenBanking\AISIncomeVerifier;
use Src\Fintech\OpenBanking\TrueLayerClient;

class OpenBankingTest extends TestCase
{
    private AISIncomeVerifier $incomeVerifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->incomeVerifier = new AISIncomeVerifier();
    }

    public function testTrueLayerClientAuthUrlGeneration(): void
    {
        $client = new TrueLayerClient([
            'client_id' => 'test-client-id-123',
            'client_secret' => 'test-secret-456',
            'redirect_uri' => 'https://scoretenant.com/apply/truelayer/callback',
            'sandbox' => true,
        ]);

        $this->assertTrue($client->isConfigured());
        $this->assertTrue($client->isSandbox());

        $authUrl = $client->getAuthUrl('state_nonce_abcdef123456');

        $this->assertStringContainsString('https://auth.truelayer-sandbox.com', $authUrl);
        $this->assertStringContainsString('client_id=test-client-id-123', $authUrl);
        $this->assertStringContainsString('state=state_nonce_abcdef123456', $authUrl);
        $this->assertStringContainsString('response_type=code', $authUrl);
    }

    public function testAISIncomeVerifierWithRegularPayroll(): void
    {
        $transactions = [
            ['timestamp' => '2026-07-28T09:00:00Z', 'description' => 'BACS ACME CORP SALARY', 'amount' => 2850.00, 'type' => 'credit'],
            ['timestamp' => '2026-08-28T09:00:00Z', 'description' => 'BACS ACME CORP SALARY', 'amount' => 2850.00, 'type' => 'credit'],
            ['timestamp' => '2026-09-28T09:00:00Z', 'description' => 'BACS ACME CORP SALARY', 'amount' => 2850.00, 'type' => 'credit'],
            ['timestamp' => '2026-08-15T12:00:00Z', 'description' => 'TESCO STORES 1234', 'amount' => -45.50, 'type' => 'debit'],
            ['timestamp' => '2026-09-01T08:00:00Z', 'description' => 'INTERNAL TRANSFER FROM SAVINGS', 'amount' => 500.00, 'type' => 'credit'],
        ];

        $result = $this->incomeVerifier->verifyIncome($transactions, 2850.00);

        $this->assertEquals(2850.00, $result['verified_monthly_net_income']);
        $this->assertEquals(3, $result['salary_credits_count']);
        $this->assertStringContainsString('Acme Corp', $result['primary_employer_label']);
        $this->assertEquals(0.0, $result['income_variance_pct']);
        $this->assertGreaterThanOrEqual(95, $result['confidence_score']);
        $this->assertEmpty($result['flags']);
    }

    public function testAISIncomeVerifierDetectsFlagsAndVariances(): void
    {
        $transactions = [
            ['timestamp' => '2026-08-28T09:00:00Z', 'description' => 'PAYROLL TECH SERVICES WAGES', 'amount' => 1900.00, 'type' => 'credit'],
            ['timestamp' => '2026-09-28T09:00:00Z', 'description' => 'PAYROLL TECH SERVICES WAGES', 'amount' => 1900.00, 'type' => 'credit'],
            ['timestamp' => '2026-09-10T14:00:00Z', 'description' => 'BET365 CASINO ONLINE', 'amount' => -300.00, 'type' => 'debit'],
            ['timestamp' => '2026-09-15T09:00:00Z', 'description' => 'UNPAID DIRECT DEBIT COUNCIL TAX', 'amount' => -15.00, 'type' => 'debit'],
        ];

        // Applicant declared £3000, but verified is £1900
        $result = $this->incomeVerifier->verifyIncome($transactions, 3000.00);

        $this->assertEquals(1900.00, $result['verified_monthly_net_income']);
        $this->assertGreaterThan(30.0, $result['income_variance_pct']);
        $this->assertNotEmpty($result['flags']);
        $this->assertLessThan(80, $result['confidence_score']);
    }

    public function testAISIncomeVerifierZeroCreditsFallback(): void
    {
        $transactions = [
            ['timestamp' => '2026-08-15T12:00:00Z', 'description' => 'AMAZON PURCHASE', 'amount' => -25.00, 'type' => 'debit'],
        ];

        $result = $this->incomeVerifier->verifyIncome($transactions, 2000.00);

        $this->assertEquals(0.0, $result['verified_monthly_net_income']);
        $this->assertEquals(0, $result['salary_credits_count']);
        $this->assertEquals(20, $result['confidence_score']);
        $this->assertNotEmpty($result['flags']);
    }
}
