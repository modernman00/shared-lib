<?php

declare(strict_types=1);

namespace Src\Fintech;

/**
 * BankProfileMatcher — Deterministic Financial Institution Fingerprinting.
 *
 * Identifies banking institutions and statement profiles from raw text streams
 * to activate tailored ledger geometry and column decoders.
 */
class BankProfileMatcher
{
    /**
     * @var array<string, array{name: string, patterns: array<int, string>, date_format: string, has_eod_balance: bool}>
     */
    private const BANK_PROFILES = [
        'starling' => [
            'name' => 'Starling Bank',
            'patterns' => [
                'starling bank',
                'starlingbank.com',
                'gb10srlg',
                'srlggb2l'
            ],
            'date_format' => 'd/m/Y',
            'has_eod_balance' => true,
        ],
        'natwest' => [
            'name' => 'NatWest Bank',
            'patterns' => [
                'national westminster bank',
                'natwest',
                'ways to bank with natwest',
                'nwbk'
            ],
            'date_format' => 'd M Y',
            'has_eod_balance' => false,
        ],
        'rbs' => [
            'name' => 'Royal Bank of Scotland',
            'patterns' => [
                'royal bank of scotland',
                'rbs.co.uk',
                'rbs bank'
            ],
            'date_format' => 'd M Y',
            'has_eod_balance' => false,
        ],
        'barclays' => [
            'name' => 'Barclays Bank',
            'patterns' => [
                'barclays bank',
                'barclays.co.uk',
                'barcgb22'
            ],
            'date_format' => 'd/m/Y',
            'has_eod_balance' => false,
        ],
        'hsbc' => [
            'name' => 'HSBC UK',
            'patterns' => [
                'hsbc uk bank',
                'hsbc.co.uk',
                'hsbchd'
            ],
            'date_format' => 'd M Y',
            'has_eod_balance' => false,
        ],
        'first_direct' => [
            'name' => 'First Direct',
            'patterns' => [
                'first direct',
                'firstdirect.com'
            ],
            'date_format' => 'd M Y',
            'has_eod_balance' => false,
        ],
        'lloyds' => [
            'name' => 'Lloyds Bank',
            'patterns' => [
                'lloyds bank',
                'lloydsbank.com',
                'loykgb'
            ],
            'date_format' => 'd M Y',
            'has_eod_balance' => false,
        ],
        'halifax' => [
            'name' => 'Halifax',
            'patterns' => [
                'halifax plc',
                'halifax.co.uk',
                'hlfx'
            ],
            'date_format' => 'd M Y',
            'has_eod_balance' => false,
        ],
        'santander' => [
            'name' => 'Santander UK',
            'patterns' => [
                'santander uk',
                'santander.co.uk',
                'abbygb'
            ],
            'date_format' => 'd/m/Y',
            'has_eod_balance' => false,
        ],
        'monzo' => [
            'name' => 'Monzo Bank',
            'patterns' => [
                'monzo bank',
                'monzo.com',
                'monzgb'
            ],
            'date_format' => 'd/m/Y',
            'has_eod_balance' => false,
        ],
        'revolut' => [
            'name' => 'Revolut',
            'patterns' => [
                'revolut ltd',
                'revolut.com',
                'revogb'
            ],
            'date_format' => 'd/m/Y',
            'has_eod_balance' => false,
        ],
        'chase' => [
            'name' => 'Chase UK',
            'patterns' => [
                'chase bank',
                'chase.co.uk',
                'j.p. morgan europe limited'
            ],
            'date_format' => 'd M Y',
            'has_eod_balance' => false,
        ],
        'nationwide' => [
            'name' => 'Nationwide Building Society',
            'patterns' => [
                'nationwide building society',
                'nationwide.co.uk',
                'nbaigb'
            ],
            'date_format' => 'd/m/Y',
            'has_eod_balance' => false,
        ],
        'amex' => [
            'name' => 'American Express',
            'patterns' => [
                'american express',
                'amex.co.uk',
                'americanexpress'
            ],
            'date_format' => 'd M Y',
            'has_eod_balance' => false,
        ],
        'monese' => [
            'name' => 'Monese',
            'patterns' => [
                'monese',
                'monese ltd',
                'monese.com',
                'poolgb'
            ],
            'date_format' => 'd/m/Y',
            'has_eod_balance' => false,
        ],
    ];

    /**
     * Identifies bank profile from document text.
     *
     * @return array{id: string, name: string, has_eod_balance: bool, confidence: float}
     */
    public function detectProfile(string $documentText): array
    {
        $lower = strtolower($documentText);

        foreach (self::BANK_PROFILES as $id => $profile) {
            $matches = 0;
            foreach ($profile['patterns'] as $pattern) {
                if (str_contains($lower, $pattern)) {
                    $matches++;
                }
            }

            if ($matches > 0) {
                $confidence = min(1.0, 0.5 + ($matches * 0.25));
                return [
                    'id' => $id,
                    'name' => $profile['name'],
                    'has_eod_balance' => $profile['has_eod_balance'],
                    'confidence' => $confidence,
                ];
            }
        }

        return [
            'id' => 'universal',
            'name' => 'Universal Spatial Ledger',
            'has_eod_balance' => false,
            'confidence' => 0.4,
        ];
    }
}
