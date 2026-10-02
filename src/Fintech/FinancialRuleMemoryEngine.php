<?php

declare(strict_types=1);

namespace Src\Fintech;

/**
 * FinancialRuleMemoryEngine — In-House Deterministic Grammar Lexer.
 *
 * Scans user statements and conversational inputs to extract persistent financial
 * declarations (savings goals, debt balances, salary/income, and spending caps)
 * without external LLM roundtrips.
 */
class FinancialRuleMemoryEngine
{
    /**
     * Extracts structured financial profile memories from user input.
     *
     * @return array<int, array{key: string, value: string}>
     */
    public function extractMemories(string $text): array
    {
        $clean = trim($text);
        if ($clean === '') {
            return [];
        }

        $memories = [];

        // 1. Savings Goal Detector: (e.g. "want to save £10,000 for a house by December 2026")
        if (preg_match('/(?:want\s+to\s+save|saving\s+for|save|target\s+is|target\s+to\s+save)\s+([£$€]?\s*[\d,]+(?:\.\d{2})?)\s*(?:for|towards|by|before|in)?\s*(.+)/iu', $clean, $m)) {
            $amount = trim($m[1]);
            $purpose = trim($m[2]);
            $memories[] = [
                'key' => 'savings_goal',
                'value' => "Target to save {$amount} ({$purpose})",
            ];
        }

        // 2. Outstanding Debt / Liability Detector: (e.g. "I owe £4,500 on my Barclaycard credit card")
        if (preg_match('/(?:owe|debt\s+of|loan\s+of|credit\s+card\s+balance\s+is|mortgage\s+balance\s+is)\s*([£$€]?\s*[\d,]+(?:\.\d{2})?)\s*(?:on|to|for)?\s*([^,\.\n]*)/iu', $clean, $m)) {
            $amount = trim($m[1]);
            $creditor = trim($m[2]);
            $creditorStr = $creditor !== '' ? " on {$creditor}" : '';
            $memories[] = [
                'key' => 'outstanding_debt',
                'value' => "Outstanding liability of {$amount}{$creditorStr}",
            ];
        }

        // 3. Salary / Income Detector: (e.g. "My monthly take-home salary is £3,800")
        if (preg_match('/(?:salary\s+is|take-home\s+is|earn|income\s+is|paid)\s*([£$€]?\s*[\d,]+(?:\.\d{2})?)\s*(per\s+month|monthly|a\s+year|annually|per\s+year)?/iu', $clean, $m)) {
            $amount = trim($m[1]);
            $period = trim($m[2] ?? 'monthly');
            $memories[] = [
                'key' => 'income_declaration',
                'value' => "Declared income of {$amount} ({$period})",
            ];
        }

        // 4. Budget Constraint / Spending Cap Detector: (e.g. "Limit dining out to £250 per month")
        if (preg_match('/(?:limit|cap|budget|restrict)\s+([a-z\s]+)\s+to\s+([£$€]?\s*[\d,]+(?:\.\d{2})?)\s*(per\s+month|monthly|a\s+week)?/iu', $clean, $m)) {
            $category = trim($m[1]);
            $amount = trim($m[2]);
            $period = trim($m[3] ?? 'per month');
            $memories[] = [
                'key' => 'budget_constraint',
                'value' => "Budget limit on {$category}: {$amount} {$period}",
            ];
        }

        return $memories;
    }
}
