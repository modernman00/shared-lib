<?php

declare(strict_types=1);

namespace Src\Concierge;

use InvalidArgumentException;

/**
 * ReaLegacyDeconstructor
 *
 * Sovereign Legacy SaaS Deconstruction & Compliant Replacement Blueprint Engine
 * inspired by REA (Reverse Engineer Anything).
 *
 * Analyses overpriced third-party SaaS tools (Semrush, Hootsuite, Mailchimp,
 * legacy Care Rota CRMs, legacy Lettings portals) and generates an actionable,
 * UK-compliant replacement blueprint powered by `modernman00/shared-lib` + GrowthForge.
 */
class ReaLegacyDeconstructor
{
    /**
     * Commercial Tiers for GrowthForge & HermesPulse B2B Clients.
     *
     * @var array<string, array{
     *     tier_id: string,
     *     name: string,
     *     monthly_price_gbp: int,
     *     billing_cadence: string,
     *     ideal_for: string,
     *     included_engines: array<int, string>,
     *     cta_label: string
     * }>
     */
    public const COMMERCIAL_TIERS = [
        'self_serve' => [
            'tier_id' => 'self_serve',
            'name' => 'Self-Serve Growth OS',
            'monthly_price_gbp' => 99,
            'billing_cadence' => 'per month',
            'ideal_for' => 'Founders & lean UK SMEs replacing Semrush, Buffer, and Mailchimp.',
            'included_engines' => [
                'OpenSEO + AEO Visibility Scanner & /llms.txt Generator',
                'Postiz 34-Channel Meeting-to-Social Scheduler',
                'Ghost Authority Newsletter & Client Briefing Composer',
            ],
            'cta_label' => 'Start £99/mo Growth OS',
        ],
        'vertical_pro' => [
            'tier_id' => 'vertical_pro',
            'name' => 'Vertical Pro & Media Studio',
            'monthly_price_gbp' => 499,
            'billing_cadence' => 'per month',
            'ideal_for' => 'Care Homes (BCP), Lettings Agencies (scoreTenant), Accountancy Firms (iAccount) & Event Venues.',
            'included_engines' => [
                'Everything in Self-Serve Growth OS (£99 tier)',
                'AI YouTube Shorts & 9:16 Vertical Clip Studio',
                'VideoLingo Multilingual Voice Dubbing (PL, RO, YO, HI, ES, FR)',
                'Pre-built HermesPulse 24/7 Compliance & Lead Chaser Templates',
            ],
            'cta_label' => 'Deploy £499/mo Vertical Pro',
        ],
        'managed_agency' => [
            'tier_id' => 'managed_agency',
            'name' => 'Managed AI Department & REA Moderniser',
            'monthly_price_gbp' => 1500,
            'billing_cadence' => 'per month retainer',
            'ideal_for' => 'Established UK Enterprises & Multi-Site Operators wanting a Done-For-You AI Concierge & Legacy SaaS Replacement.',
            'included_engines' => [
                'Everything in Vertical Pro (£499 tier)',
                'Dedicated 24/7 HermesPulse AI Concierge Department (WhatsApp, SMS, Email, Slack)',
                'Bespoke REA Legacy Software Deconstruction & Sovereign Migration (CQC / FCA / GDPR compliant)',
                'Dedicated Executive Strategy & AEO Citation Guarantee',
            ],
            'cta_label' => 'Book £1,500/mo AI Concierge Retainer',
        ],
    ];

    /**
     * Generates a complete REA Legacy SaaS Replacement Blueprint and 3-Year TCO Savings Audit.
     *
     * @param string $legacySaaSList Comma-separated list of legacy SaaS tools (e.g. "Semrush, Hootsuite, Mailchimp, Legacy Care CRM")
     * @param string $vertical Target industry vertical (e.g. 'healthcare_cqc', 'proptech_lettings', 'fintech_fca', 'executive_b2b')
     * @param int $seatCount Number of staff seats currently licensed
     * @param float $currentMonthlySpendGbp Current monthly spend on legacy SaaS/agencies in GBP
     * @return array{
     *     legacy_stack: string,
     *     vertical: string,
     *     seat_count: int,
     *     current_monthly_spend_gbp: float,
     *     recommended_tier: string,
     *     sovereign_monthly_cost_gbp: float,
     *     monthly_savings_gbp: float,
     *     annual_savings_gbp: float,
     *     three_year_tco_savings_gbp: float,
     *     compliance_upgrades: array<int, string>,
     *     module_replacement_map: array<int, array{
     *         legacy_capability: string,
     *         sovereign_replacement: string,
     *         open_source_inspiration: string,
     *         migration_days: int
     *     }>
     * }
     */
    public static function generateModernisationBlueprint(
        string $legacySaaSList,
        string $vertical = 'healthcare_cqc',
        int $seatCount = 15,
        float $currentMonthlySpendGbp = 1850.00
    ): array {
        $cleanStack = trim($legacySaaSList);
        if ($cleanStack === '') {
            throw new InvalidArgumentException('Legacy SaaS stack description cannot be empty.');
        }

        $safeSeats = max(1, $seatCount);
        $safeSpend = max(150.0, $currentMonthlySpendGbp);

        $recommendedTierKey = $safeSpend >= 2000.0 || $safeSeats >= 25
            ? 'managed_agency'
            : ($safeSpend >= 600.0 || $safeSeats >= 8 ? 'vertical_pro' : 'self_serve');

        $tier = self::COMMERCIAL_TIERS[$recommendedTierKey];
        $sovereignCost = (float) $tier['monthly_price_gbp'];

        // Ensure positive savings modelling even if user enters a low current spend
        $effectiveCurrentSpend = max($safeSpend, $sovereignCost + 250.0);
        $monthlySavings = round($effectiveCurrentSpend - $sovereignCost, 2);
        $annualSavings = round($monthlySavings * 12, 2);
        $threeYearSavings = round($annualSavings * 3, 2);

        $complianceUpgrades = match (strtolower(trim($vertical))) {
            'healthcare_cqc', 'bcp' => [
                'CQC Regulation 5 (Fit and Proper Persons) & Regulation 19 automated audit trail',
                'Zero third-party patient/carer PII leakage (Sovereign UK GDPR storage)',
                'Automated Polish, Romanian, and Yoruba carer induction verification',
            ],
            'proptech_lettings', 'scoretenant' => [
                'UK Renters Rights framework alignment & portable Tenant Trust Index',
                'Open Banking verified rent streak logs with immutable audit trails',
                'Automated right-to-rent and employer reference chasing',
            ],
            'fintech_fca', 'loaneasyfinance', 'iaccount' => [
                'FCA Consumer Duty & CONC 5.2A affordability audit logging',
                'Double-entry ledger integrity & HMRC Making Tax Digital readiness',
                'Zero external credit-data scraping bloat',
            ],
            default => [
                '100% UK GDPR data sovereignty with zero US sub-processor leakage',
                'Deterministic >= 95% fallback reliability across all AI workflows',
                'Unified audit logging via Src\\AuditLogger',
            ],
        };

        $replacementMap = [
            [
                'legacy_capability' => 'Semrush / Ahrefs (£300–£500/mo)',
                'sovereign_replacement' => 'Src\\Growth\\AeoVisibilityEngine (SEO + ChatGPT/Perplexity AEO)',
                'open_source_inspiration' => 'OpenSEO',
                'migration_days' => 1,
            ],
            [
                'legacy_capability' => 'Hootsuite / Sprout Social (£250–£600/mo)',
                'sovereign_replacement' => 'Src\\Growth\\OmniChannelPostizEngine (34-Platform Scheduler)',
                'open_source_inspiration' => 'Postiz',
                'migration_days' => 2,
            ],
            [
                'legacy_capability' => 'Mailchimp / HubSpot Email (£300–£800/mo)',
                'sovereign_replacement' => 'Src\\Growth\\GhostNewsletterEngine (Authority Briefings)',
                'open_source_inspiration' => 'Ghost',
                'migration_days' => 2,
            ],
            [
                'legacy_capability' => 'Video Editing & Translation Agencies (£1,000+/mo)',
                'sovereign_replacement' => 'Src\\Media\\ShortsAndDubbingEngine (9:16 Clips + 7-Locale Dubbing)',
                'open_source_inspiration' => 'AI YouTube Shorts + VideoLingo',
                'migration_days' => 3,
            ],
            [
                'legacy_capability' => 'Manual Admin Chasing & Legacy Vertical CRM',
                'sovereign_replacement' => 'Src\\Concierge\\HermesPulseOrchestrator (24/7 AI Concierge)',
                'open_source_inspiration' => 'Hermes Agent + REA',
                'migration_days' => 5,
            ],
        ];

        return [
            'legacy_stack' => $cleanStack,
            'vertical' => $vertical,
            'seat_count' => $safeSeats,
            'current_monthly_spend_gbp' => $effectiveCurrentSpend,
            'recommended_tier' => $tier['name'],
            'sovereign_monthly_cost_gbp' => $sovereignCost,
            'monthly_savings_gbp' => $monthlySavings,
            'annual_savings_gbp' => $annualSavings,
            'three_year_tco_savings_gbp' => $threeYearSavings,
            'compliance_upgrades' => $complianceUpgrades,
            'module_replacement_map' => $replacementMap,
        ];
    }
}
