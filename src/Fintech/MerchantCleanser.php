<?php

declare(strict_types=1);

namespace Src\Fintech;

/**
 * MerchantCleanser — Strips payment rail noise, card tokens, and reference metadata
 * to isolate pure merchant entities for precise taxonomy categorization and UI presentation.
 */
class MerchantCleanser
{
    /**
     * Leading banking protocol tokens to strip.
     *
     * @var array<int, string>
     */
    private const PAYMENT_RAIL_PREFIXES = [
        'DIRECT DEBIT',
        'APPLE PAY',
        'GOOGLE PAY',
        'CHIP & PIN',
        'CHIP AND PIN',
        'FASTER PAYMENT',
        'ONLINE PAYMENT',
        'CARD SUBSCRIPTION',
        'CONTACTLESS',
        'BILL PAYMENT',
        'AUTOMATED CREDIT',
        'CARD PAYMENT',
        'POS PURCHASE',
        'TRANSFER TO',
        'TRANSFER FROM',
        'TRF TO',
        'TRF FROM',
        'STANDING ORDER',
        'PAYMENT VIA MOBILE',
        'RECURRING TRANSACTION',
        'DEBIT CARD',
        'CREDIT CARD',
        'ATM WITHDRAWAL',
        'CASH WITHDRAWAL'
    ];

    /**
     * Cleans a raw banking line description into a pure merchant name.
     */
    public function clean(string $rawDescription): string
    {
        $desc = trim($rawDescription);
        if ($desc === '') {
            return 'Unknown';
        }

        // 1. Strip leading payment rail tokens
        foreach (self::PAYMENT_RAIL_PREFIXES as $prefix) {
            $len = strlen($prefix);
            if (stripos($desc, $prefix) === 0) {
                $desc = trim(substr($desc, $len));
                break;
            }
        }

        // 2. Strip trailing reference numbers in parentheses e.g. (GC781913), (136800248)
        $desc = preg_replace('/\s*\([A-Za-z0-9_\-\s]{3,30}\)\s*$/u', '', $desc) ?? $desc;

        // 3. Strip trailing card tokens like *1234, CD 1234
        $desc = preg_replace('/\s*(?:\*|CD\s*)\d{4}\b/i', '', $desc) ?? $desc;

        // 4. Clean supermarket / chain suffixes
        // E.g. "LIDL GB SWINDON BARNFI" -> "LIDL"
        if (preg_match('/^LIDL\b/i', $desc)) {
            $desc = 'Lidl';
        } elseif (preg_match('/^ALDI\b/i', $desc)) {
            $desc = 'Aldi';
        } elseif (preg_match('/^TESCO\b/i', $desc)) {
            $desc = preg_replace('/^TESCO\s+(?:STORES|PFS|EXPRESS|METRO|EXTRA)?\s*[\d\s]*/i', 'Tesco ', $desc) ?? 'Tesco';
        } elseif (preg_match('/^SAINSBURYS?\b/i', $desc)) {
            $desc = 'Sainsburys';
        } elseif (preg_match('/^MCDONALDS?\b/i', $desc)) {
            $desc = 'McDonalds';
        } elseif (preg_match('/^AMAZON\b/i', $desc)) {
            $desc = 'Amazon';
        }

        // 5. Clean extra whitespace
        $clean = trim(preg_replace('/\s+/', ' ', $desc) ?? '');

        return $clean !== '' ? $clean : trim($rawDescription);
    }
}
