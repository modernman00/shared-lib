<?php

declare(strict_types=1);

namespace Src\Executive;

use InvalidArgumentException;

/**
 * StrategicBriefSynthesizer
 *
 * Executive Cognitive Load Balancer & Daily Strategic Brief Engine
 * engineered by the Advanced Technology Group (ATG) under Olutobi (Deloitte)
 * and Victor (CTO/BRATS) for ExecMindApp.
 *
 * Replaces expensive Notion AI / Superhuman executive software ($30-$50/seat/mo)
 * with an in-house, zero-data-leakage, high-density decision synthesizer.
 *
 * Features:
 * - Aggregates and de-noises multi-channel signals (FTSE updates, board alerts, team blockers).
 * - Semantic categorization across 4 strategic pillars (Governance, Capital, Operations, Talent).
 * - Extracts "The Sovereign Three" (top 3 critical executive decisions required today).
 * - Generates high-impact WhatsApp / Slack / Email executive brief formats.
 */
class StrategicBriefSynthesizer
{
    public const string PILLAR_GOVERNANCE = 'Governance & Regulatory Risk';
    public const string PILLAR_CAPITAL = 'Capital Allocation & Revenue';
    public const string PILLAR_OPERATIONS = 'Operational Velocity & Infrastructure';
    public const string PILLAR_TALENT = 'Talent & Key Stakeholders';

    /**
     * Synthesizes raw stream items into an executive strategic brief.
     *
     * @param array<int, array<string, mixed>> $rawSignals Raw incoming signals/items
     * @param string $executiveName Recipient name (e.g. "Wally")
     * @return array{
     *     executive_name: string,
     *     date: string,
     *     total_signals_analyzed: int,
     *     noise_reduction_ratio: float,
     *     the_sovereign_three: array<int, array{
     *         rank: int,
     *         title: string,
     *         pillar: string,
     *         urgency: string,
     *         context: string,
     *         decision_required: string,
     *         recommended_action: string,
     *         impact_score: int
     *     }>,
     *     pillar_breakdown: array<string, int>,
     *     whatsapp_digest: string,
     *     slack_markdown: string
     * }
     */
    public function synthesizeDailyBrief(array $rawSignals, string $executiveName = 'Executive Leader'): array
    {
        $totalInput = count($rawSignals);
        if ($totalInput === 0) {
            return $this->emptyBrief($executiveName);
        }

        // 1. De-noise and score each signal
        $scoredSignals = [];
        $pillarCounts = [
            self::PILLAR_GOVERNANCE => 0,
            self::PILLAR_CAPITAL => 0,
            self::PILLAR_OPERATIONS => 0,
            self::PILLAR_TALENT => 0,
        ];

        foreach ($rawSignals as $signal) {
            $title = trim((string)($signal['title'] ?? 'Strategic Notice'));
            $body = trim((string)($signal['body'] ?? $signal['content'] ?? ''));
            $source = (string)($signal['source'] ?? 'Internal Telemetry');
            $urgency = strtolower((string)($signal['urgency'] ?? 'medium'));
            $financialImpact = (float)($signal['financial_impact'] ?? 0.0);

            // Determine strategic pillar
            $pillar = $this->classifyPillar($title . ' ' . $body, (string)($signal['pillar'] ?? ''));
            $pillarCounts[$pillar]++;

            // Calculate Impact Score (0 - 100)
            $impactScore = 30; // base
            if (in_array($urgency, ['critical', 'high', 'immediate'], true)) {
                $impactScore += 35;
            } elseif ($urgency === 'medium') {
                $impactScore += 15;
            }

            if ($financialImpact >= 50000.0) {
                $impactScore += 25;
            } elseif ($financialImpact >= 10000.0) {
                $impactScore += 15;
            }

            if (str_contains(strtolower($title . ' ' . $body), 'deadline') || str_contains(strtolower($title . ' ' . $body), 'urgent')) {
                $impactScore += 10;
            }

            $scoredSignals[] = [
                'title' => $title,
                'body' => $body,
                'source' => $source,
                'urgency' => strtoupper($urgency),
                'pillar' => $pillar,
                'financial_impact' => $financialImpact,
                'impact_score' => min(100, $impactScore),
                'raw' => $signal,
            ];
        }

        // Sort descending by impact score
        usort($scoredSignals, static fn(array $a, array $b): int => $b['impact_score'] <=> $a['impact_score']);

        // Select "The Sovereign Three"
        $topThree = array_slice($scoredSignals, 0, 3);
        $sovereignThree = [];

        foreach ($topThree as $index => $item) {
            $sovereignThree[] = [
                'rank' => $index + 1,
                'title' => $item['title'],
                'pillar' => $item['pillar'],
                'urgency' => $item['urgency'],
                'context' => $item['body'] ?: 'High-priority executive strategic update requiring executive alignment.',
                'decision_required' => (string)($item['raw']['decision_required'] ?? 'Approve tactical allocation or sign off on delivery milestone.'),
                'recommended_action' => (string)($item['raw']['recommended_action'] ?? 'Execute Option A under Technical Approval Team governance.'),
                'impact_score' => $item['impact_score'],
            ];
        }

        $noiseReductionRatio = $totalInput > 3
            ? round(((($totalInput - 3) / $totalInput) * 100), 1)
            : 0.0;

        $dateStr = date('l, j F Y');
        $whatsappDigest = $this->buildWhatsAppDigest($executiveName, $dateStr, $sovereignThree, $totalInput, $noiseReductionRatio);
        $slackMarkdown = $this->buildSlackMarkdown($executiveName, $dateStr, $sovereignThree, $totalInput, $noiseReductionRatio);

        return [
            'executive_name' => $executiveName,
            'date' => $dateStr,
            'total_signals_analyzed' => $totalInput,
            'noise_reduction_ratio' => $noiseReductionRatio,
            'the_sovereign_three' => $sovereignThree,
            'pillar_breakdown' => $pillarCounts,
            'whatsapp_digest' => $whatsappDigest,
            'slack_markdown' => $slackMarkdown,
        ];
    }

    private function classifyPillar(string $text, string $hint = ''): string
    {
        if ($hint !== '') {
            foreach ([self::PILLAR_GOVERNANCE, self::PILLAR_CAPITAL, self::PILLAR_OPERATIONS, self::PILLAR_TALENT] as $p) {
                if (stripos($hint, $p) !== false || stripos($p, $hint) !== false) {
                    return $p;
                }
            }
        }

        $lower = strtolower($text);
        if (preg_match('/(audit|fca|cqc|gdpr|legal|compliance|risk|board|security)/', $lower)) {
            return self::PILLAR_GOVERNANCE;
        }
        if (preg_match('/(revenue|capital|invest|burn|cash|runway|ebitda|profit|budget|pricing|monetiz|margin)/', $lower)) {
            return self::PILLAR_CAPITAL;
        }
        if (preg_match('/(deploy|database|server|latency|bug|refactor|queue|api|uptime|scale|release|pipeline)/', $lower)) {
            return self::PILLAR_OPERATIONS;
        }
        return self::PILLAR_TALENT;
    }

    /**
     * @param array<int, array<string, mixed>> $sovereignThree
     */
    private function buildWhatsAppDigest(string $name, string $date, array $sovereignThree, int $total, float $reduction): string
    {
        $out = "🏛️ *ExecMind Daily Strategic Brief* — {$date}\n";
        $out .= "Good morning {$name}. Filtered {$total} items into your top 3 decisions ({$reduction}% cognitive noise reduced).\n\n";

        foreach ($sovereignThree as $item) {
            $out .= "*[#{$item['rank']}] {$item['title']}*\n";
            $out .= "• *Pillar:* {$item['pillar']} | *Urgency:* {$item['urgency']}\n";
            $out .= "• *Context:* {$item['context']}\n";
            $out .= "• *Decision:* {$item['decision_required']}\n";
            $out .= "• *Recommendation:* {$item['recommended_action']}\n\n";
        }

        $out .= "⚡ _Governed by Deloitte TAT & ExecMind Sovereign Engine_";
        return $out;
    }

    /**
     * @param array<int, array<string, mixed>> $sovereignThree
     */
    private function buildSlackMarkdown(string $name, string $date, array $sovereignThree, int $total, float $reduction): string
    {
        $out = ":briefcase: *ExecMind Executive Strategic Brief* — `{$date}`\n";
        $out .= "> *Recipient:* {$name} | *Signals Ingested:* {$total} | *Noise Eliminated:* {$reduction}%\n\n";

        foreach ($sovereignThree as $item) {
            $out .= "*{$item['rank']}. {$item['title']}* (`{$item['pillar']}` | *{$item['urgency']}*)\n";
            $out .= "> *Context:* {$item['context']}\n";
            $out .= "> *Decision Required:* {$item['decision_required']}\n";
            $out .= "> *TAT Recommendation:* {$item['recommended_action']}\n\n";
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyBrief(string $name): array
    {
        $dateStr = date('l, j F Y');
        return [
            'executive_name' => $name,
            'date' => $dateStr,
            'total_signals_analyzed' => 0,
            'noise_reduction_ratio' => 0.0,
            'the_sovereign_three' => [],
            'pillar_breakdown' => [
                self::PILLAR_GOVERNANCE => 0,
                self::PILLAR_CAPITAL => 0,
                self::PILLAR_OPERATIONS => 0,
                self::PILLAR_TALENT => 0,
            ],
            'whatsapp_digest' => "🏛️ *ExecMind Daily Strategic Brief* — {$dateStr}\nGood morning {$name}. Zero critical blockers pending executive intervention.",
            'slack_markdown' => ":briefcase: *ExecMind Daily Strategic Brief* — `{$dateStr}`\n> No critical items requiring executive intervention today.",
        ];
    }
}
