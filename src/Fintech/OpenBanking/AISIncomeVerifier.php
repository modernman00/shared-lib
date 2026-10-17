<?php

declare(strict_types=1);

namespace Src\Fintech\OpenBanking;

/**
 * AISIncomeVerifier — Deterministic Open Banking Payroll & Income Verification Engine.
 *
 * Ingests authenticated bank transaction streams to verify applicant affordability:
 * 1. Isolates genuine employer salary and regular payroll credits (BACS / Faster Payments).
 * 2. Filters out noise, internal sweeps, loan disbursements, and volatile transfers.
 * 3. Calculates monthly net earnings, stability metrics, and income variance vs self-declared figures.
 */
class AISIncomeVerifier
{
    private const PAYROLL_PATTERNS = [
        '/\b(SALARY|PAYROLL|WAGES|WAGE|PAYE|EMPLOYER|DIRECT CREDIT|BACS|FP PAYROLL|STIPEND)\b/i',
        '/\b(DWP|UNIVERSAL CREDIT|CHILD BENEFIT|PIPS|HMRC|TAX CREDITS)\b/i',
    ];

    private const EXCLUDED_CREDIT_PATTERNS = [
        '/\b(REFUND|RETURN|CASHBACK|DEPOSIT RETURN|CASINO|BET365|SKYBET|PADDY|WILLIAM HILL|LOTTERY)\b/i',
        '/\b(LOAN|DRAFTY|LENDING STREAM|ZILCH|KLARNA|CLEARPAY|CREDITSPRING|MONEYBOAT)\b/i',
        '/\b(FROM ACC|OWN ACCOUNT|SWEEP|INTERNAL TRANSFER|SAVINGS)\b/i',
    ];

    private const RETURNED_DD_PATTERNS = [
        '/\b(UNPAID|RETURNED DD|UNPAID DD|RETURNED DIRECT DEBIT|INSUFFICIENT FUNDS|RETURNED ITEM)\b/i',
    ];

    private const GAMBLING_PATTERNS = [
        '/\b(BET365|SKYBET|PADDY POWER|LADBROKES|WILLIAM HILL|BETFAIR|BETFRED|CASINO|888|POKER)\b/i',
    ];

    /**
     * Verifies income across an array of Open Banking transactions.
     *
     * @param list<array<string, mixed>> $transactions
     * @param float|null $declaredMonthlyIncome Optional applicant self-reported monthly income (£)
     * @return array{
     *     verified_monthly_net_income: float,
     *     primary_employer_label: string,
     *     salary_credits_count: int,
     *     stability_score: int,
     *     income_variance_pct: float,
     *     confidence_score: int,
     *     flags: list<string>,
     *     credits: list<array{date: string, description: string, amount: float}>
     * }
     */
    public function verifyIncome(array $transactions, ?float $declaredMonthlyIncome = null): array
    {
        $salaryCredits = [];
        $flags = [];
        $returnedDdCount = 0;
        $gamblingOutflow = 0.0;

        foreach ($transactions as $tx) {
            $desc = strtoupper(trim((string)($tx['description'] ?? '')));
            $amount = (float)($tx['amount'] ?? 0.0);
            $type = strtolower((string)($tx['transaction_type'] ?? $tx['type'] ?? ''));
            $date = (string)($tx['timestamp'] ?? $tx['date'] ?? '');

            // 1. Audit negative transactions for adverse credit signals
            if ($amount < 0 || in_array($type, ['debit', 'outflow'], true)) {
                $absAmount = abs($amount);

                if ($this->matchesAny(self::RETURNED_DD_PATTERNS, $desc)) {
                    $returnedDdCount++;
                }

                if ($this->matchesAny(self::GAMBLING_PATTERNS, $desc)) {
                    $gamblingOutflow += $absAmount;
                }
                continue;
            }

            // 2. Audit incoming credits
            $creditAmount = abs($amount);
            if ($creditAmount < 100.0) {
                // Minimum realistic salary or benefits credit threshold
                continue;
            }

            if ($this->matchesAny(self::EXCLUDED_CREDIT_PATTERNS, $desc)) {
                continue;
            }

            if ($this->matchesAny(self::PAYROLL_PATTERNS, $desc) || in_array($type, ['credit', 'salary'], true)) {
                $salaryCredits[] = [
                    'date' => $date,
                    'description' => $desc,
                    'amount' => round($creditAmount, 2),
                ];
            }
        }

        // Add warning flags
        if ($returnedDdCount > 0) {
            $flags[] = "Detected {$returnedDdCount} returned or unpaid direct debits within transaction history.";
        }
        if ($gamblingOutflow > 250.0) {
            $flags[] = "High gambling outflow identified (£" . number_format($gamblingOutflow, 2) . ").";
        }

        // Calculate verified monthly net income
        $creditCount = count($salaryCredits);
        if ($creditCount === 0) {
            return [
                'verified_monthly_net_income' => 0.0,
                'primary_employer_label' => 'Unverified',
                'salary_credits_count' => 0,
                'stability_score' => 0,
                'income_variance_pct' => $declaredMonthlyIncome !== null && $declaredMonthlyIncome > 0 ? 100.0 : 0.0,
                'confidence_score' => 20,
                'flags' => array_merge($flags, ['No recurring payroll or salary credits identified in Open Banking stream.']),
                'credits' => [],
            ];
        }

        // Determine primary employer / payer label from highest aggregate credit source
        $employerTotals = [];
        $totalEarned = 0.0;
        foreach ($salaryCredits as $c) {
            $totalEarned += $c['amount'];
            $key = $this->cleanEmployerName($c['description']);
            $employerTotals[$key] = ($employerTotals[$key] ?? 0.0) + $c['amount'];
        }
        arsort($employerTotals);
        $primaryEmployer = (string)key($employerTotals);

        // Normalize to a monthly figure based on credits count
        // If 3 credits over 90 days, average = total / 3
        $averagePerCredit = $totalEarned / $creditCount;
        $verifiedMonthly = round($averagePerCredit, 2);

        // Calculate Stability Score (0 - 100)
        // High score if multiple regular credits with low variance
        $varianceScore = 100;
        if ($creditCount >= 2) {
            $amounts = array_column($salaryCredits, 'amount');
            $mean = $totalEarned / $creditCount;
            $varianceSum = 0.0;
            foreach ($amounts as $amt) {
                $varianceSum += pow($amt - $mean, 2);
            }
            $stdDev = sqrt($varianceSum / $creditCount);
            $cv = $mean > 0 ? ($stdDev / $mean) * 100 : 0.0;
            $varianceScore = max(40, (int)round(100 - ($cv * 1.5)));
        } else {
            $varianceScore = 70; // Only 1 credit seen
        }

        $stabilityScore = $creditCount >= 3 ? $varianceScore : max(50, $varianceScore - 20);

        // Calculate Income Variance vs Declared
        $incomeVariance = 0.0;
        if ($declaredMonthlyIncome !== null && $declaredMonthlyIncome > 0.0) {
            $diff = abs($verifiedMonthly - $declaredMonthlyIncome);
            $incomeVariance = round(($diff / $declaredMonthlyIncome) * 100, 1);
            if ($incomeVariance > 15.0) {
                $flags[] = "Declared income (£" . number_format($declaredMonthlyIncome, 2) . ") deviates by {$incomeVariance}% from verified banking salary (£" . number_format($verifiedMonthly, 2) . ").";
            }
        }

        // Calculate Final AIS Confidence Score (0 - 100)
        $confidence = 90;
        if ($creditCount >= 3) {
            $confidence += 8; // Verified multi-month cadence
        }
        if ($incomeVariance <= 5.0 && $declaredMonthlyIncome !== null) {
            $confidence += 2; // Exact declared match
        } elseif ($incomeVariance > 20.0) {
            $confidence -= 25; // Significant variance
        }
        if ($returnedDdCount > 0) {
            $confidence -= min(30, $returnedDdCount * 10);
        }

        $finalConfidence = max(25, min(100, $confidence));

        return [
            'verified_monthly_net_income' => $verifiedMonthly,
            'primary_employer_label' => $primaryEmployer !== '' ? $primaryEmployer : 'Verified Employer',
            'salary_credits_count' => $creditCount,
            'stability_score' => $stabilityScore,
            'income_variance_pct' => $incomeVariance,
            'confidence_score' => $finalConfidence,
            'flags' => $flags,
            'credits' => $salaryCredits,
        ];
    }

    /**
     * @param list<string> $patterns
     */
    private function matchesAny(array $patterns, string $subject): bool
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $subject)) {
                return true;
            }
        }
        return false;
    }

    private function cleanEmployerName(string $rawDesc): string
    {
        // Strip common banking noise prefixes/suffixes
        $cleaned = preg_replace('/\b(BACS|FP|DIRECT CREDIT|FASTER PAYMENT|REF|PAYROLL|SALARY|WAGES)\b/i', '', $rawDesc);
        $cleaned = preg_replace('/[^A-Za-z0-9\s&]/', ' ', (string)$cleaned);
        $cleaned = trim((string)preg_replace('/\s+/', ' ', (string)$cleaned));
        return $cleaned !== '' ? ucwords(strtolower(substr($cleaned, 0, 32))) : 'Verified Employer';
    }
}
