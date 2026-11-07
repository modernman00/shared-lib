<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\Decision\MonteCarloDecisionEngine;

class MonteCarloDecisionEngineTest extends TestCase
{
    private MonteCarloDecisionEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new MonteCarloDecisionEngine();
    }

    public function testMonteCarloSimulationDeterminesSuperiorOption(): void
    {
        $options = [
            [
                'id' => 'option_a',
                'name' => 'Option A: In-House Sovereign Replacement',
                'scores' => [
                    'cost_savings' => 95.0,
                    'execution_speed' => 90.0,
                    'regulatory_control' => 95.0,
                    'scalability' => 88.0,
                ],
                'confidence' => 0.95, // High confidence
            ],
            [
                'id' => 'option_b',
                'name' => 'Option B: Commercial SaaS Subscription',
                'scores' => [
                    'cost_savings' => 25.0,
                    'execution_speed' => 60.0,
                    'regulatory_control' => 40.0,
                    'scalability' => 70.0,
                ],
                'confidence' => 0.70,
            ],
        ];

        $criteriaWeights = [
            'cost_savings' => 0.40,
            'execution_speed' => 0.20,
            'regulatory_control' => 0.25,
            'scalability' => 0.15,
        ];

        $result = $this->engine->simulateDecision($options, $criteriaWeights, 2000);

        $this->assertEquals(2000, $result['total_iterations']);
        $this->assertEquals('Option A: In-House Sovereign Replacement', $result['winning_option']);
        $this->assertCount(2, $result['rankings']);

        $top = $result['rankings'][0];
        $this->assertEquals(1, $top['rank']);
        $this->assertGreaterThanOrEqual(95.0, $top['win_rate_percentage']);
        $this->assertNotEmpty($result['decision_audit_hash']);
        $this->assertEquals(40.0, $result['sensitivity_bottlenecks']['cost_savings']);
    }
}
