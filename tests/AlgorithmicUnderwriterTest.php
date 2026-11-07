<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\Fintech\AlgorithmicUnderwriter;

class AlgorithmicUnderwriterTest extends TestCase
{
    private AlgorithmicUnderwriter $underwriter;
    private string $signingKey = 'test_secret_key_2026';

    protected function setUp(): void
    {
        $this->underwriter = new AlgorithmicUnderwriter();
    }

    public function testAmortisationCalculationAndScheduleIntegrity(): void
    {
        $principal = 1000.0;
        $termMonths = 12;
        $annualRate = 0.12; // 12% per year = 1% per month

        $result = $this->underwriter->calculateAmortisationSchedule($principal, $termMonths, $annualRate);

        $this->assertEquals(1000.0, $result['principal']);
        $this->assertEquals(12, $result['term_months']);
        $this->assertGreaterThan(0.0, $result['monthly_repayment']);
        $this->assertCount(12, $result['schedule']);

        // Check schedule continuity
        $firstMonth = $result['schedule'][0];
        $this->assertEquals(1000.0, $firstMonth['starting_balance']);
        $this->assertEquals(10.0, $firstMonth['interest_portion']); // 1% of 1000

        $lastMonth = end($result['schedule']);
        $this->assertEquals(12, $lastMonth['month']);
        $this->assertEquals(0.0, $lastMonth['ending_balance']);
        $this->assertEquals(0.0, $result['interest_saved']);
    }

    public function testEarlyRepaymentSavingsCalculation(): void
    {
        $principal = 5000.0;
        $termMonths = 24;
        $annualRate = 0.18;
        $extraMonthly = 50.0; // £50 extra every month

        $result = $this->underwriter->calculateAmortisationSchedule($principal, $termMonths, $annualRate, $extraMonthly);

        $this->assertGreaterThan(0.0, $result['interest_saved']);
        $this->assertGreaterThan(0, $result['months_saved']);
        $this->assertLessThan(24, $result['effective_term_months']);
    }

    public function testSolvencyPassportVerificationValid(): void
    {
        $uuid = bin2hex(random_bytes(16));
        $userId = 42;
        $type = 'mortgage_readiness';
        $netIncome = 3500.00;
        $ndcr = 38.50;
        $dti = 22.00;
        $velocity = 650.00;
        $reliability = 98.00;
        $tier = AlgorithmicUnderwriter::TIER_PRIME_A;
        $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

        $netIncStr = number_format($netIncome, 2, '.', '');
        $ndcrStr = number_format($ndcr, 2, '.', '');
        $dtiStr = number_format($dti, 2, '.', '');
        $velStr = number_format($velocity, 2, '.', '');
        $relStr = number_format($reliability, 2, '.', '');

        $rawPayload = "{$uuid}|{$userId}|{$type}|{$netIncStr}|{$ndcrStr}|{$dtiStr}|{$velStr}|{$relStr}|{$tier}|{$expiresAt}";
        $signature = hash_hmac('sha256', $rawPayload, $this->signingKey);

        $passportData = [
            'user_id' => $userId,
            'passport_type' => $type,
            'verified_net_monthly_income' => $netIncome,
            'non_discretionary_ratio' => $ndcr,
            'debt_to_income_ratio' => $dti,
            'deposit_accumulation_velocity' => $velocity,
            'rent_payment_reliability_score' => $reliability,
            'solvency_tier' => $tier,
            'expires_at' => $expiresAt,
        ];

        $verification = $this->underwriter->verifySolvencyPassport($uuid, $signature, $passportData, $this->signingKey);

        $this->assertTrue($verification['valid']);
        $this->assertEquals(AlgorithmicUnderwriter::TIER_PRIME_A, $verification['tier']);
        $this->assertEquals(400, $verification['discount_bps']);
        $this->assertEquals(0.50, $verification['rate_discount_percent']);
    }

    public function testSolvencyPassportVerificationTampered(): void
    {
        $uuid = bin2hex(random_bytes(16));
        $passportData = [
            'user_id' => 99,
            'passport_type' => 'comprehensive',
            'verified_net_monthly_income' => 9000.0, // Tampered high income
            'expires_at' => date('Y-m-d H:i:s', strtotime('+30 days')),
        ];

        $invalidSignature = 'bad_signature_hash_12345';
        $verification = $this->underwriter->verifySolvencyPassport($uuid, $invalidSignature, $passportData, $this->signingKey);

        $this->assertFalse($verification['valid']);
        $this->assertStringContainsString('Tamper detected', $verification['reason'] ?? 'Payload integrity compromised');
    }

    public function testEvaluateUnderwritingDecisionApprovedWithFastTrack(): void
    {
        $uuid = bin2hex(random_bytes(16));
        $userId = 10;
        $type = 'comprehensive';
        $netIncome = 4200.00;
        $ndcr = 30.00;
        $dti = 15.00;
        $velocity = 1200.00;
        $reliability = 100.00;
        $tier = AlgorithmicUnderwriter::TIER_PRIME_A;
        $expiresAt = date('Y-m-d H:i:s', strtotime('+60 days'));

        $netIncStr = number_format($netIncome, 2, '.', '');
        $ndcrStr = number_format($ndcr, 2, '.', '');
        $dtiStr = number_format($dti, 2, '.', '');
        $velStr = number_format($velocity, 2, '.', '');
        $relStr = number_format($reliability, 2, '.', '');

        $rawPayload = "{$uuid}|{$userId}|{$type}|{$netIncStr}|{$ndcrStr}|{$dtiStr}|{$velStr}|{$relStr}|{$tier}|{$expiresAt}";
        $signature = hash_hmac('sha256', $rawPayload, 'iaccount_sovereign_signing_key_2026');

        $profile = [
            'monthly_income' => 4200.0,
            'monthly_expenses' => 1200.0,
            'monthly_liabilities' => 300.0,
            'solvency_passport' => [
                'uuid' => $uuid,
                'signature' => $signature,
                'user_id' => $userId,
                'passport_type' => $type,
                'verified_net_monthly_income' => $netIncome,
                'non_discretionary_ratio' => $ndcr,
                'debt_to_income_ratio' => $dti,
                'deposit_accumulation_velocity' => $velocity,
                'rent_payment_reliability_score' => $reliability,
                'solvency_tier' => $tier,
                'expires_at' => $expiresAt,
            ],
        ];

        $decision = $this->underwriter->evaluateUnderwritingDecision(1500.0, 6, $profile, 0.20);

        $this->assertEquals(AlgorithmicUnderwriter::DECISION_APPROVED, $decision['decision']);
        $this->assertTrue($decision['fast_track_approved']);
        $this->assertTrue($decision['solvency_passport_verified']);
        $this->assertEquals(0.10, $decision['effective_annual_rate']); // 50% discount on 20%
    }
}
