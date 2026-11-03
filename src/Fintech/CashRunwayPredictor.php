<?php

declare(strict_types=1);

namespace Src\Fintech;

/**
 * CashRunwayPredictor
 *
 * Engineered by the Advanced Technology Group (ATG) - High-Velocity Data & Real-Time Analytics Pod.
 * Governed by the Financial Services Sovereign Board (Abiola / Julian Cole) & TAT 2.0 (Olutobi / David).
 *
 * Provides sovereign, zero-external-dependency working capital forecasting,
 * stress-scenario simulations, and deterministic cash runway calculations
 * across iAccountApp, LoanEasyFinance, ExecMindApp, and TenantScore.
 */
final class CashRunwayPredictor
{
    private const MAX_SIMULATION_MONTHS = 120;

    /**
     * Sanitizes financial amounts against NaN, Infinity, and negative value injection
     */
    private static function sanitizeAmount(float $value, bool $allowNegative = false): float
    {
        if (!is_finite($value)) {
            return 0.0;
        }
        return $allowNegative ? $value : max(0.0, $value);
    }

    /**
     * Compute exact cash runway metrics based on current liquid reserves and monthly cash burn.
     *
     * @param float $availableCash Current available liquid cash / bank balance
     * @param float $monthlyRecurringIncome Stable monthly recurring inflows (e.g. salary, contracted revenue)
     * @param float $monthlyFixedBurn Committed monthly fixed outflows (rent, mortgages, debts, payroll)
     * @param float $monthlyVariableBurn Discretionary / variable monthly spending
     * @return array{
     *     runway_months: float,
     *     runway_days: int,
     *     net_monthly_burn: float,
     *     is_cashflow_positive: bool,
     *     burn_rate_ratio: float,
     *     risk_tier: string,
     *     status: string
     * }
     */
    public static function calculateRunway(
        float $availableCash,
        float $monthlyRecurringIncome,
        float $monthlyFixedBurn,
        float $monthlyVariableBurn = 0.0
    ): array {
        $availableCash = self::sanitizeAmount($availableCash);
        $monthlyRecurringIncome = self::sanitizeAmount($monthlyRecurringIncome);
        $monthlyFixedBurn = self::sanitizeAmount($monthlyFixedBurn);
        $monthlyVariableBurn = self::sanitizeAmount($monthlyVariableBurn);

        $totalMonthlyOutflows = $monthlyFixedBurn + $monthlyVariableBurn;
        $netMonthlyBurn = $totalMonthlyOutflows - $monthlyRecurringIncome;

        if ($netMonthlyBurn <= 0.0) {
            // Cash flow positive or perfectly neutral
            return [
                'runway_months'        => 999.0,
                'runway_days'          => 36500,
                'net_monthly_burn'     => round($netMonthlyBurn, 2),
                'is_cashflow_positive' => true,
                'burn_rate_ratio'      => $monthlyRecurringIncome > 0 ? round($totalMonthlyOutflows / $monthlyRecurringIncome, 2) : 0.0,
                'risk_tier'            => 'sustainable',
                'status'               => 'Cash flow positive. Reserves are self-replenishing.',
            ];
        }

        $availableCash = max(0.0, $availableCash);
        $runwayMonths = round($availableCash / $netMonthlyBurn, 1);
        $runwayDays = (int)round($runwayMonths * 30.4375); // Average Gregorian month length

        $riskTier = 'stable';
        $status = 'Adequate liquidity cushion maintained.';

        if ($runwayMonths < 1.0) {
            $riskTier = 'critical';
            $status = 'Critical liquidity deficit. Immediate working capital intervention required.';
        } elseif ($runwayMonths < 3.0) {
            $riskTier = 'high_risk';
            $status = 'Tight working capital runway. Prioritise non-discretionary commitments.';
        } elseif ($runwayMonths < 6.0) {
            $riskTier = 'moderate_risk';
            $status = 'Moderate buffer available. Optimise discretionary expenditure.';
        }

        return [
            'runway_months'        => $runwayMonths,
            'runway_days'          => $runwayDays,
            'net_monthly_burn'     => round($netMonthlyBurn, 2),
            'is_cashflow_positive' => false,
            'burn_rate_ratio'      => $monthlyRecurringIncome > 0 ? round($totalMonthlyOutflows / $monthlyRecurringIncome, 2) : 99.0,
            'risk_tier'            => $riskTier,
            'status'               => $status,
        ];
    }

    /**
     * Simulate multi-month scenario projections under various macroeconomic stress conditions:
     * - Baseline (current trajectory)
     * - Conservative (-15% income shock, +10% cost inflation)
     * - Severe Stress (-30% income disruption, +20% cost surge)
     *
     * @param float $availableCash
     * @param float $monthlyRecurringIncome
     * @param float $monthlyFixedBurn
     * @param float $monthlyVariableBurn
     * @param int $simulationMonths
     * @return array<string, mixed>
     */
    public static function simulateStressScenarios(
        float $availableCash,
        float $monthlyRecurringIncome,
        float $monthlyFixedBurn,
        float $monthlyVariableBurn = 0.0,
        int $simulationMonths = 12
    ): array {
        $availableCash = self::sanitizeAmount($availableCash);
        $monthlyRecurringIncome = self::sanitizeAmount($monthlyRecurringIncome);
        $monthlyFixedBurn = self::sanitizeAmount($monthlyFixedBurn);
        $monthlyVariableBurn = self::sanitizeAmount($monthlyVariableBurn);
        $simulationMonths = max(1, min($simulationMonths, self::MAX_SIMULATION_MONTHS));

        $scenarios = [
            'baseline' => [
                'income_multiplier' => 1.0,
                'burn_multiplier'   => 1.0,
                'label'             => 'Current Trajectory (Baseline)',
            ],
            'conservative' => [
                'income_multiplier' => 0.85,
                'burn_multiplier'   => 1.10,
                'label'             => 'Conservative (15% Inflow Reduction, 10% Inflation)',
            ],
            'severe_stress' => [
                'income_multiplier' => 0.70,
                'burn_multiplier'   => 1.20,
                'label'             => 'Severe Stress (30% Macro Shock, 20% Cost Surge)',
            ],
        ];

        $results = [];

        foreach ($scenarios as $key => $scenario) {
            $simIncome = $monthlyRecurringIncome * $scenario['income_multiplier'];
            $simBurn = ($monthlyFixedBurn + $monthlyVariableBurn) * $scenario['burn_multiplier'];
            $balance = $availableCash;
            $trajectory = [];
            $depletedMonth = null;

            for ($m = 1; $m <= $simulationMonths; $m++) {
                $balance += ($simIncome - $simBurn);
                $trajectory[] = [
                    'month'   => $m,
                    'balance' => round(max(0.0, $balance), 2),
                ];

                if ($balance <= 0.0 && $depletedMonth === null) {
                    $depletedMonth = $m;
                }
            }

            $runwayAnalysis = self::calculateRunway($availableCash, $simIncome, $monthlyFixedBurn * $scenario['burn_multiplier'], $monthlyVariableBurn * $scenario['burn_multiplier']);

            $results[$key] = [
                'label'             => $scenario['label'],
                'runway_months'     => $runwayAnalysis['runway_months'],
                'runway_days'       => $runwayAnalysis['runway_days'],
                'net_monthly_burn'  => $runwayAnalysis['net_monthly_burn'],
                'depletion_month'   => $depletedMonth,
                'closing_liquidity' => round(max(0.0, $balance), 2),
                'trajectory'        => $trajectory,
            ];
        }

        return $results;
    }

    /**
     * Working capital ratio assessment and liquidity health score (0 - 100).
     *
     * @param float $currentAssets Cash and liquid equivalents
     * @param float $currentLiabilities Short-term liabilities due within 30-60 days
     * @param float $monthlyNetBurn Net monthly outflow
     * @return array{
     *     current_ratio: float,
     *     working_capital_surplus: float,
     *     health_score: int,
     *     solvency_verdict: string
     * }
     */
    public static function assessWorkingCapitalHealth(
        float $currentAssets,
        float $currentLiabilities,
        float $monthlyNetBurn
    ): array {
        $currentAssets = self::sanitizeAmount($currentAssets);
        $currentLiabilities = self::sanitizeAmount($currentLiabilities);
        $monthlyNetBurn = self::sanitizeAmount($monthlyNetBurn, true);

        $currentRatio = $currentLiabilities > 0 ? round($currentAssets / $currentLiabilities, 2) : 99.0;
        $surplus = round($currentAssets - $currentLiabilities, 2);

        // Heuristic scoring based on Basel III & British working capital standards
        $score = 50;

        if ($currentRatio >= 2.0) {
            $score += 30;
        } elseif ($currentRatio >= 1.5) {
            $score += 20;
        } elseif ($currentRatio >= 1.0) {
            $score += 10;
        } else {
            $score -= 25;
        }

        if ($monthlyNetBurn <= 0.0) {
            $score += 20;
        } elseif ($surplus > ($monthlyNetBurn * 6)) {
            $score += 15;
        } elseif ($surplus < ($monthlyNetBurn * 2)) {
            $score -= 20;
        }

        $score = max(0, min(100, $score));

        $verdict = match (true) {
            $score >= 80 => 'Prime Solvency (Excellent Liquidity Buffer)',
            $score >= 60 => 'Stable (Satisfactory Working Capital)',
            $score >= 40 => 'Marginal (Action Recommended to Expand Cushion)',
            default      => 'Stressed (Immediate Intervention Required)',
        };

        return [
            'current_ratio'           => $currentRatio,
            'working_capital_surplus' => $surplus,
            'health_score'            => $score,
            'solvency_verdict'        => $verdict,
        ];
    }
}
