<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\Executive\StrategicBriefSynthesizer;

class StrategicBriefSynthesizerTest extends TestCase
{
    private StrategicBriefSynthesizer $synthesizer;

    protected function setUp(): void
    {
        $this->synthesizer = new StrategicBriefSynthesizer();
    }

    public function testSynthesizesTheSovereignThreeFromNoisyStream(): void
    {
        $signals = [
            [
                'title' => 'Routine email about office plants',
                'body' => 'Someone watered the plants yesterday.',
                'urgency' => 'low',
                'financial_impact' => 0.0,
            ],
            [
                'title' => 'FCA Regulatory Audit Submission Deadline',
                'body' => 'Final regulatory capital adequacy report due in 48 hours.',
                'urgency' => 'critical',
                'financial_impact' => 150000.0,
                'decision_required' => 'Approve capital submission annex B.',
                'recommended_action' => 'Sign off through TAT Chair Olutobi.',
            ],
            [
                'title' => 'Pusher Connection Surge Alert',
                'body' => 'PartyPlatform traffic spiked to 12,000 live guests.',
                'urgency' => 'high',
                'financial_impact' => 20000.0,
                'decision_required' => 'Confirm automatic failover to SovereignBroadcaster.',
                'recommended_action' => 'Enable SSE sovereign broadcasting rail.',
            ],
            [
                'title' => 'Junior dev submitted minor CSS pull request',
                'body' => 'Adjusted button margin by 2px.',
                'urgency' => 'low',
                'financial_impact' => 0.0,
            ],
            [
                'title' => 'Series A Convertible Loan Note Extension',
                'body' => 'Lead investor offering £500k extension at 8% coupon.',
                'urgency' => 'high',
                'financial_impact' => 500000.0,
                'decision_required' => 'Accept term sheet or execute organic cash runway plan.',
                'recommended_action' => 'Review with FinTech Chair Abiola.',
            ],
        ];

        $brief = $this->synthesizer->synthesizeDailyBrief($signals, 'Wally');

        $this->assertEquals('Wally', $brief['executive_name']);
        $this->assertEquals(5, $brief['total_signals_analyzed']);
        $this->assertEquals(40.0, $brief['noise_reduction_ratio']); // (5 - 3) / 5 = 40%
        $this->assertCount(3, $brief['the_sovereign_three']);

        // Check top decision is highest impact
        $top = $brief['the_sovereign_three'][0];
        $this->assertEquals(1, $top['rank']);
        $this->assertStringContainsString('Regulatory', $top['title'] . ' ' . $top['pillar']);

        // Check output formats exist
        $this->assertStringContainsString('ExecMind Daily Strategic Brief', $brief['whatsapp_digest']);
        $this->assertStringContainsString('Wally', $brief['whatsapp_digest']);
        $this->assertStringContainsString(':briefcase:', $brief['slack_markdown']);
    }

    public function testHandlesEmptySignalStreamGracefully(): void
    {
        $brief = $this->synthesizer->synthesizeDailyBrief([], 'CEO');

        $this->assertEquals(0, $brief['total_signals_analyzed']);
        $this->assertEmpty($brief['the_sovereign_three']);
        $this->assertStringContainsString('Zero critical blockers', $brief['whatsapp_digest']);
    }
}
