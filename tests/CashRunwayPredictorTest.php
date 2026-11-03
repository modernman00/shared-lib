<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\Fintech\CashRunwayPredictor;

final class CashRunwayPredictorTest extends TestCase
{
    public function testCalculateRunwayWhenCashFlowPositive(): void
    {
        $res = CashRunwayPredictor::calculateRunway(
            availableCash: 10000.0,
            monthlyRecurringIncome: 4500.0,
            monthlyFixedBurn: 2500.0,
            monthlyVariableBurn: 1000.0
        );

        $this->assertTrue($res['is_cashflow_positive']);
        $this->assertEquals(999.0, $res['runway_months']);
        $this->assertEquals(-1000.0, $res['net_monthly_burn']);
        $this->assertEquals('sustainable', $res['risk_tier']);
    }

    public function testCalculateRunwayWhenBurningCash(): void
    {
        $res = CashRunwayPredictor::calculateRunway(
            availableCash: 6000.0,
            monthlyRecurringIncome: 2000.0,
            monthlyFixedBurn: 3000.0,
            monthlyVariableBurn: 1000.0
        );

        // Outflows = 4000, Income = 2000 => Net burn = 2000
        // Runway = 6000 / 2000 = 3.0 months
        $this->assertFalse($res['is_cashflow_positive']);
        $this->assertEquals(2000.0, $res['net_monthly_burn']);
        $this->assertEquals(3.0, $res['runway_months']);
        $this->assertEquals('moderate_risk', $res['risk_tier']);
    }

    public function testSimulateStressScenarios(): void
    {
        $scenarios = CashRunwayPredictor::simulateStressScenarios(
            availableCash: 12000.0,
            monthlyRecurringIncome: 3000.0,
            monthlyFixedBurn: 3500.0,
            monthlyVariableBurn: 500.0,
            simulationMonths: 6
        );

        $this->assertArrayHasKey('baseline', $scenarios);
        $this->assertArrayHasKey('conservative', $scenarios);
        $this->assertArrayHasKey('severe_stress', $scenarios);
        $this->assertCount(6, $scenarios['baseline']['trajectory']);
        $this->assertCount(6, $scenarios['severe_stress']['trajectory']);
    }

    public function testAssessWorkingCapitalHealth(): void
    {
        $health = CashRunwayPredictor::assessWorkingCapitalHealth(
            currentAssets: 50000.0,
            currentLiabilities: 20000.0,
            monthlyNetBurn: 1500.0
        );

        $this->assertEquals(2.5, $health['current_ratio']);
        $this->assertEquals(30000.0, $health['working_capital_surplus']);
        $this->assertGreaterThanOrEqual(80, $health['health_score']);
        $this->assertStringContainsString('Prime Solvency', $health['solvency_verdict']);
    }

    public function testHardensAgainstNaNInfinityAndSimulationLoopDoS(): void
    {
        // 1. NaN / INF / negative burn manipulation must be sanitized safely
        $runway = CashRunwayPredictor::calculateRunway(NAN, INF, -5000.0, -2000.0);
        $this->assertTrue(is_finite($runway['runway_months']));
        $this->assertNotFalse(json_encode($runway));

        // 2. Unbounded simulationMonths (e.g. 999,999 from an AI bot) must be clamped to 120
        $stress = CashRunwayPredictor::simulateStressScenarios(10000.0, 2000.0, 3000.0, 500.0, 999999);
        $this->assertCount(120, $stress['baseline']['trajectory']);
    }
}
