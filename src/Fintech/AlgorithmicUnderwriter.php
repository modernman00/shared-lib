<?php

declare(strict_types=1);

namespace Src\Fintech;

use InvalidArgumentException;

/**
 * AlgorithmicUnderwriter
 *
 * Sovereign Dynamic Underwriting & Compound Amortisation Engine
 * engineered by the Advanced Technology Group (ATG) under Olutobi (Deloitte)
 * and Victor (CTO/BRATS).
 *
 * Replaces TurnKey Lender and Mambu core banking modules (£1,000s/mo)
 * with an owned, zero-dependency, mathematically verified engine.
 *
 * Features:
 * - Cryptographic verification of iAccount Solvency Passports (HMAC-SHA256).
 * - Exact compound amortisation calculation with month-by-month schedules.
 * - Early repayment savings simulator (interest saved and term reduction).
 * - FCA CONC 5.2A regulatory affordability stress-testing (+300bps rate shock).
 * - Tiered risk pricing and instant fast-track qualification.
 */
class AlgorithmicUnderwriter
{
    public const string TIER_PRIME_A = 'prime_a';
    public const string TIER_PRIME_B = 'prime_b';
    public const string TIER_STANDARD = 'standard';
    public const string TIER_SUBPRIME = 'subprime';

    public const string DECISION_APPROVED = 'APPROVED';
    public const string DECISION_REFERRED = 'REFERRED';
    public const string DECISION_DECLINED = 'DECLINED';

    private const float MIN_BUFFER_RATIO = 1.15; // 15% safety buffer on FDI
    private const float RATE_SHOCK_BPS = 0.03;   // +300bps stress-test buffer

    /**
     * Verifies an external Solvency Passport issued by iAccountApp.
     *
     * @param string $uuid Passport UUID
     * @param string $signature HMAC signature to verify
     * @param array<string, mixed> $passportData Key metrics from passport
     * @param string $signingKey Shared HMAC signing key
     * @return array{
     *     valid: bool,
     *     reason?: string,
     *     tier?: string,
     *     discount_bps?: int,
     *     rate_discount_percent?: float,
     *     metrics?: array<string, mixed>
     * }
     */
    public function verifySolvencyPassport(
        string $uuid,
        string $signature,
        array $passportData,
        string $signingKey = 'iaccount_sovereign_signing_key_2026'
    ): array {
        if ($uuid === '' || $signature === '') {
            return ['valid' => false, 'reason' => 'Passport identifier and cryptographic signature required.'];
        }

        // Validate expiration
        $expiresAt = (string)($passportData['expires_at'] ?? '');
        if ($expiresAt !== '' && strtotime($expiresAt) < time()) {
            return ['valid' => false, 'reason' => 'Solvency Passport has expired (90-day validity window).'];
        }

        // Extract and format numerical metrics exactly as formatted during issuance
        $userId = (int)($passportData['user_id'] ?? 0);
        $type = (string)($passportData['passport_type'] ?? 'comprehensive');
        $netIncome = (float)($passportData['verified_net_monthly_income'] ?? 0.0);
        $ndcr = (float)($passportData['non_discretionary_ratio'] ?? 0.0);
        $dti = (float)($passportData['debt_to_income_ratio'] ?? 0.0);
        $velocity = (float)($passportData['deposit_accumulation_velocity'] ?? 0.0);
        $reliability = (float)($passportData['rent_payment_reliability_score'] ?? 0.0);
        $tier = (string)($passportData['solvency_tier'] ?? self::TIER_STANDARD);

        $netIncStr = number_format($netIncome, 2, '.', '');
        $ndcrStr = number_format($ndcr, 2, '.', '');
        $dtiStr = number_format($dti, 2, '.', '');
        $velStr = number_format($velocity, 2, '.', '');
        $relStr = number_format($reliability, 2, '.', '');

        $rawPayload = "{$uuid}|{$userId}|{$type}|{$netIncStr}|{$ndcrStr}|{$dtiStr}|{$velStr}|{$relStr}|{$tier}|{$expiresAt}";
        $expectedSignature = hash_hmac('sha256', $rawPayload, $signingKey);

        if (!hash_equals($expectedSignature, $signature)) {
            return [
                'valid' => false,
                'reason' => 'Cryptographic signature verification failed: Tamper detected (payload integrity compromised).',
            ];
        }

        // Tier benefits
        $discountBps = match ($tier) {
            self::TIER_PRIME_A => 400, // 4.00% APR discount
            self::TIER_PRIME_B => 200, // 2.00% APR discount
            default => 0,
        };

        $rateDiscountPercent = match ($tier) {
            self::TIER_PRIME_A => 0.50, // 50% reduction in margin
            self::TIER_PRIME_B => 0.25, // 25% reduction in margin
            default => 0.0,
        };

        return [
            'valid' => true,
            'tier' => $tier,
            'discount_bps' => $discountBps,
            'rate_discount_percent' => $rateDiscountPercent,
            'metrics' => [
                'verified_monthly_income' => $netIncome,
                'non_discretionary_ratio' => $ndcr,
                'debt_to_income' => $dti,
                'savings_velocity' => $velocity,
                'payment_reliability' => $reliability,
                'certified_source' => 'iAccount Financial Services Sovereign Engine',
            ],
        ];
    }

    /**
     * Calculates the exact compound amortisation schedule, monthly repayment,
     * total interest, and early repayment impact.
     *
     * @param float $principal Loan amount in GBP
     * @param int $termMonths Loan duration in months
     * @param float $annualRate Nominal annual interest rate (e.g. 0.12 for 12%)
     * @param float $extraMonthlyRepayment Optional monthly overpayment
     * @return array{
     *     principal: float,
     *     term_months: int,
     *     annual_rate: float,
     *     monthly_rate: float,
     *     monthly_repayment: float,
     *     total_repayment: float,
     *     total_interest: float,
     *     effective_term_months: int,
     *     interest_saved: float,
     *     months_saved: int,
     *     schedule: array<int, array{
     *         month: int,
     *         starting_balance: float,
     *         repayment: float,
     *         principal_portion: float,
     *         interest_portion: float,
     *         ending_balance: float
     *     }>
     * }
     */
    public function calculateAmortisationSchedule(
        float $principal,
        int $termMonths,
        float $annualRate,
        float $extraMonthlyRepayment = 0.0
    ): array {
        if ($principal <= 0.0) {
            throw new InvalidArgumentException('Principal must be strictly greater than zero.');
        }
        if ($termMonths <= 0 || $termMonths > 360) {
            throw new InvalidArgumentException('Term months must be between 1 and 360.');
        }
        if ($annualRate < 0.0) {
            throw new InvalidArgumentException('Annual interest rate cannot be negative.');
        }

        $monthlyRate = $annualRate / 12.0;

        // Base monthly repayment formula: M = P * [ r*(1+r)^n / ((1+r)^n - 1) ]
        if ($monthlyRate > 0.0) {
            $factor = pow(1.0 + $monthlyRate, $termMonths);
            $baseMonthlyRepayment = round($principal * ($monthlyRate * $factor) / ($factor - 1.0), 2);
        } else {
            $baseMonthlyRepayment = round($principal / $termMonths, 2);
        }

        // First calculate baseline total interest without extra payments
        $baselineSchedule = $this->generateScheduleRows($principal, $termMonths, $monthlyRate, $baseMonthlyRepayment, 0.0);
        $baselineTotalInterest = array_sum(array_column($baselineSchedule, 'interest_portion'));

        // Next calculate actual schedule with extra payments
        $actualSchedule = $this->generateScheduleRows($principal, $termMonths, $monthlyRate, $baseMonthlyRepayment, $extraMonthlyRepayment);
        $actualTotalInterest = array_sum(array_column($actualSchedule, 'interest_portion'));
        $actualTotalRepayment = array_sum(array_column($actualSchedule, 'repayment'));
        $effectiveTermMonths = count($actualSchedule);

        $interestSaved = max(0.0, round($baselineTotalInterest - $actualTotalInterest, 2));
        $monthsSaved = max(0, $termMonths - $effectiveTermMonths);

        return [
            'principal' => round($principal, 2),
            'term_months' => $termMonths,
            'annual_rate' => $annualRate,
            'monthly_rate' => round($monthlyRate, 6),
            'monthly_repayment' => $baseMonthlyRepayment,
            'total_repayment' => round($actualTotalRepayment, 2),
            'total_interest' => round($actualTotalInterest, 2),
            'effective_term_months' => $effectiveTermMonths,
            'interest_saved' => $interestSaved,
            'months_saved' => $monthsSaved,
            'schedule' => $actualSchedule,
        ];
    }

    /**
     * Executes algorithmic underwriting decisioning incorporating Solvency Passport
     * and FCA CONC 5.2A regulatory affordability stress-testing.
     *
     * @param float $requestedAmount Loan amount in GBP
     * @param int $termMonths Loan duration in months
     * @param array<string, mixed> $applicantProfile Financial metrics & verified passport
     * @param float $baseAnnualRate Default annual interest rate before discounts
     * @return array<string, mixed>
     */
    public function evaluateUnderwritingDecision(
        float $requestedAmount,
        int $termMonths,
        array $applicantProfile,
        float $baseAnnualRate = 0.24
    ): array {
        $monthlyIncome = (float)($applicantProfile['monthly_income'] ?? 0.0);
        $monthlyExpenses = (float)($applicantProfile['monthly_expenses'] ?? 0.0);
        $monthlyLiabilities = (float)($applicantProfile['monthly_liabilities'] ?? 0.0);

        // Check if verified Solvency Passport is attached
        $passport = $applicantProfile['solvency_passport'] ?? null;
        $passportValid = false;
        $solvencyTier = self::TIER_STANDARD;
        $rateDiscountPercent = 0.0;

        if (is_array($passport) && !empty($passport['uuid']) && !empty($passport['signature'])) {
            $verification = $this->verifySolvencyPassport(
                (string)$passport['uuid'],
                (string)$passport['signature'],
                $passport
            );
            if ($verification['valid']) {
                $passportValid = true;
                $solvencyTier = (string)($verification['tier'] ?? self::TIER_STANDARD);
                $rateDiscountPercent = (float)($verification['rate_discount_percent'] ?? 0.0);
                
                // Override self-reported income with cryptographically verified banking income
                if (!empty($verification['metrics']['verified_monthly_income'])) {
                    $monthlyIncome = (float)$verification['metrics']['verified_monthly_income'];
                }
            }
        }

        // Apply rate discount from Solvency Passport
        $effectiveAnnualRate = max(0.05, $baseAnnualRate * (1.0 - $rateDiscountPercent));

        // Compute Base Amortisation
        $amortisation = $this->calculateAmortisationSchedule($requestedAmount, $termMonths, $effectiveAnnualRate);
        $monthlyPayment = $amortisation['monthly_repayment'];

        // Free Disposable Income (FDI)
        $freeDisposableIncome = max(0.0, $monthlyIncome - ($monthlyExpenses + $monthlyLiabilities));

        // Stress-Test Rate (+300bps under FCA CONC 5.2A rules)
        $stressedAnnualRate = $effectiveAnnualRate + self::RATE_SHOCK_BPS;
        $stressedAmortisation = $this->calculateAmortisationSchedule($requestedAmount, $termMonths, $stressedAnnualRate);
        $stressedMonthlyPayment = $stressedAmortisation['monthly_repayment'];

        // Affordability Assessment
        $bufferRatio = $monthlyPayment > 0.0 ? round($freeDisposableIncome / $monthlyPayment, 2) : 99.0;
        $stressedBufferRatio = $stressedMonthlyPayment > 0.0 ? round($freeDisposableIncome / $stressedMonthlyPayment, 2) : 99.0;

        $riskFactors = [];
        if ($freeDisposableIncome <= 0.0) {
            $riskFactors[] = 'Zero or negative verified Free Disposable Income (FDI).';
        }
        if ($bufferRatio < self::MIN_BUFFER_RATIO) {
            $riskFactors[] = "Safety buffer ({$bufferRatio}x) below required regulatory threshold (" . self::MIN_BUFFER_RATIO . 'x).';
        }
        if ($stressedBufferRatio < 1.0) {
            $riskFactors[] = 'Fails FCA +300bps interest rate shock stress-test.';
        }

        // Max Affordable Principal
        $maxSafeMonthlyPayment = $freeDisposableIncome / self::MIN_BUFFER_RATIO;
        $maxAffordablePrincipal = $this->calculateMaxPrincipalFromPayment($maxSafeMonthlyPayment, $termMonths, $effectiveAnnualRate);

        // Decision Logic
        $decision = self::DECISION_DECLINED;
        if (empty($riskFactors)) {
            $decision = self::DECISION_APPROVED;
        } elseif ($bufferRatio >= 1.0 && count($riskFactors) <= 1 && $solvencyTier !== self::TIER_SUBPRIME) {
            $decision = self::DECISION_REFERRED;
        }

        // Fast-track prime certification
        $fastTrackApproved = ($decision === self::DECISION_APPROVED && $solvencyTier === self::TIER_PRIME_A);

        return [
            'decision' => $decision,
            'fast_track_approved' => $fastTrackApproved,
            'solvency_passport_verified' => $passportValid,
            'solvency_tier' => $solvencyTier,
            'requested_principal' => $requestedAmount,
            'term_months' => $termMonths,
            'standard_annual_rate' => $baseAnnualRate,
            'effective_annual_rate' => round($effectiveAnnualRate, 4),
            'rate_discount_bps' => (int)round(($baseAnnualRate - $effectiveAnnualRate) * 10000),
            'monthly_repayment' => $monthlyPayment,
            'stressed_monthly_repayment' => $stressedMonthlyPayment,
            'free_disposable_income' => round($freeDisposableIncome, 2),
            'buffer_ratio' => $bufferRatio,
            'stressed_buffer_ratio' => $stressedBufferRatio,
            'max_affordable_principal' => $maxAffordablePrincipal,
            'risk_factors' => $riskFactors,
            'amortisation_summary' => [
                'total_repayment' => $amortisation['total_repayment'],
                'total_interest' => $amortisation['total_interest'],
            ],
        ];
    }

    /**
     * @return array<int, array{
     *     month: int,
     *     starting_balance: float,
     *     repayment: float,
     *     principal_portion: float,
     *     interest_portion: float,
     *     ending_balance: float
     * }>
     */
    private function generateScheduleRows(
        float $principal,
        int $termMonths,
        float $monthlyRate,
        float $baseMonthlyRepayment,
        float $extraMonthlyRepayment
    ): array {
        $rows = [];
        $balance = $principal;
        $totalMonthlyTarget = $baseMonthlyRepayment + $extraMonthlyRepayment;

        for ($m = 1; $m <= $termMonths; $m++) {
            if ($balance <= 0.001) {
                break;
            }

            $startingBalance = round($balance, 2);
            $interestCharge = round($startingBalance * $monthlyRate, 2);
            $repayment = min($startingBalance + $interestCharge, $totalMonthlyTarget);
            $principalPortion = round($repayment - $interestCharge, 2);

            if ($principalPortion < 0.0) {
                $principalPortion = 0.0;
            }

            $endingBalance = max(0.0, round($startingBalance - $principalPortion, 2));
            $balance = $endingBalance;

            $rows[] = [
                'month' => $m,
                'starting_balance' => $startingBalance,
                'repayment' => round($repayment, 2),
                'principal_portion' => $principalPortion,
                'interest_portion' => $interestCharge,
                'ending_balance' => $endingBalance,
            ];
        }

        return $rows;
    }

    private function calculateMaxPrincipalFromPayment(float $monthlyPayment, int $termMonths, float $annualRate): float
    {
        if ($monthlyPayment <= 0.0 || $termMonths <= 0) {
            return 0.0;
        }

        $monthlyRate = $annualRate / 12.0;
        if ($monthlyRate <= 0.0) {
            return round($monthlyPayment * $termMonths, 2);
        }

        $factor = pow(1.0 + $monthlyRate, $termMonths);
        $principal = $monthlyPayment * ($factor - 1.0) / ($monthlyRate * $factor);

        return max(0.0, round($principal, 2));
    }
}
