<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\Tenant\RentReliabilityPredictor;

class RentReliabilityPredictorTest extends TestCase
{
    private RentReliabilityPredictor $predictor;
    private string $signingKey = 'test_secret_key_2026';

    protected function setUp(): void
    {
        $this->predictor = new RentReliabilityPredictor();
    }

    public function testPredictRentReliabilitySovereignPrime(): void
    {
        $uuid = bin2hex(random_bytes(16));
        $userId = 77;
        $type = 'tenant_referencing';
        $netIncome = 4500.00;
        $ndcr = 30.00;
        $dti = 12.00;
        $velocity = 950.00;
        $reliability = 100.00;
        $tier = 'prime_a';
        $expiresAt = date('Y-m-d H:i:s', strtotime('+45 days'));

        $netIncStr = number_format($netIncome, 2, '.', '');
        $ndcrStr = number_format($ndcr, 2, '.', '');
        $dtiStr = number_format($dti, 2, '.', '');
        $velStr = number_format($velocity, 2, '.', '');
        $relStr = number_format($reliability, 2, '.', '');

        $signingKey = 'test_secret_key_rent_2026';
        $rawPayload = "{$uuid}|{$userId}|{$type}|{$netIncStr}|{$ndcrStr}|{$dtiStr}|{$velStr}|{$relStr}|{$tier}|{$expiresAt}";
        $signature = hash_hmac('sha256', $rawPayload, $signingKey);

        $applicant = [
            'monthly_income' => 4500.0,
            'monthly_fixed_outgoings' => 1100.0,
            'employment_type' => 'Permanent Full-Time',
            'solvency_passport' => [
                'uuid' => $uuid,
                'signature' => $signature,
                'signing_key' => $signingKey,
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

        $monthlyRent = 1200.0; // RIR = 1200 / 4500 = 26.6%
        $result = $this->predictor->predictRentReliability($applicant, $monthlyRent);

        $this->assertGreaterThanOrEqual(800, $result['score']);
        $this->assertEquals(RentReliabilityPredictor::TIER_SOVEREIGN_PRIME, $result['risk_tier']);
        $this->assertTrue($result['fast_track_guarantee']);
        $this->assertEmpty($result['anti_fraud_flags']);
        $this->assertEquals(26.67, $result['rent_to_income_ratio']);
        $this->assertEquals(2200.0, $result['disposable_living_buffer']);
    }

    public function testAntiFraudDetectionPenalties(): void
    {
        $applicant = [
            'monthly_income' => 3000.0,
            'monthly_fixed_outgoings' => 900.0,
            'has_circular_transfers' => true,
            'metadata_tampering_detected' => true,
            'gambling_outgoings_ratio' => 0.25,
        ];

        $result = $this->predictor->predictRentReliability($applicant, 1100.0);

        $this->assertCount(3, $result['anti_fraud_flags']);
        $this->assertEquals(RentReliabilityPredictor::TIER_HIGH_RISK, $result['risk_tier']);
        $this->assertFalse($result['fast_track_guarantee']);
        $this->assertGreaterThanOrEqual(550, $result['breakdown']['fraud_penalty']);
    }
}
