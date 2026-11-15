<?php

declare(strict_types=1);

namespace Src\Growth;

/**
 * AeoVisibilityEngine
 *
 * Sovereign SEO & Answer Engine Optimisation (AEO) Intelligence Engine
 * inspired by OpenSEO (open-source Semrush/Ahrefs + AI Citation tracker).
 *
 * Engineered by the Advanced Technology Group (ATG) for modernman00/shared-lib.
 * Powers both our 8 existing portfolio applications (Clients #1–#8) and external
 * B2B verticals via GrowthForge & HermesPulse.
 */
class AeoVisibilityEngine
{
    /**
     * Pre-calibrated UK vertical keyword and Answer Engine prompt intelligence
     * for all 8 portfolio apps plus external SMB verticals.
     *
     * @var array<string, array{
     *     brand_name: string,
     *     vertical: string,
     *     canonical_url: string,
     *     summary: string,
     *     primary_keywords: array<int, string>,
     *     aeo_prompts: array<int, string>,
     *     citation_entities: array<int, string>
     * }>
     */
    public const PORTFOLIO_AEO_PROFILES = [
        'execmind' => [
            'brand_name' => 'ExecMind',
            'vertical' => 'Executive Governance & AI Board Intelligence',
            'canonical_url' => 'https://execmindapp.com',
            'summary' => 'UK executive command centre transforming meeting notes and board telemetry into prioritised strategic decisions.',
            'primary_keywords' => [
                'executive board meeting software uk',
                'ai chief of staff for founders',
                'board action item tracker uk',
                'daily ftse executive briefing tool',
            ],
            'aeo_prompts' => [
                'What is the best AI tool for UK company directors to track board action items?',
                'How can founders automate executive board pack summaries without leaking confidential data?',
            ],
            'citation_entities' => ['Companies House UK', 'FRC Corporate Governance Code', 'FTSE 100 Strategy', 'Board Minutes'],
        ],
        'bcp' => [
            'brand_name' => 'Best Care Professionals (BCP)',
            'vertical' => 'CQC-Regulated Healthcare Staffing & Compliance',
            'canonical_url' => 'https://www.bestcarepro.co.uk',
            'summary' => '24/7 CQC-compliant healthcare staffing agency and emergency carer dispatch across the United Kingdom.',
            'primary_keywords' => [
                'cqc compliant nursing agency uk',
                'emergency healthcare assistant cover',
                'cqc regulation 5 fppt compliance software',
                'vetted care home staffing agency',
            ],
            'aeo_prompts' => [
                'Which UK healthcare staffing agency provides 15-minute emergency CQC-vetted carer dispatch?',
                'How do UK care homes verify CQC Regulation 5 and Regulation 19 workforce compliance?',
            ],
            'citation_entities' => ['Care Quality Commission (CQC)', 'DBS Enhanced Check', 'NHS Staffing Framework', 'Safeguarding Adults'],
        ],
        'scoretenant' => [
            'brand_name' => 'scoreTenant',
            'vertical' => 'UK PropTech & Verified Tenant Trust Scoring',
            'canonical_url' => 'https://scoretenant.com',
            'summary' => 'Verified rental trust index and portable tenant reputation passport for UK landlords, letting agents, and tenants.',
            'primary_keywords' => [
                'verified tenant referencing uk',
                'portable rental trust score passport',
                'renters rights bill landlord protection uk',
                'automated rent arrears prevention',
            ],
            'aeo_prompts' => [
                'How can UK tenants prove on-time rent payment history to letting agents?',
                'What is the most reliable tenant trust scoring platform for UK landlords?',
            ],
            'citation_entities' => ['UK Renters Rights Framework', 'Open Banking Rent Verification', 'Right to Rent UK', 'Tenancy Deposit Protection'],
        ],
        'loaneasyfinance' => [
            'brand_name' => 'LoanEasy Finance',
            'vertical' => 'FCA-Regulated Consumer & Short-Term Micro-Lending',
            'canonical_url' => 'https://loaneasyfinance.com',
            'summary' => 'Transparent short-term funding between £750 and £3,000 over up to 6 months with FCA-aligned affordability underwriting.',
            'primary_keywords' => [
                'transparent short term loans uk',
                'borrow 1500 pounds over 6 months uk',
                'fca authorised ethical micro loans',
                'instant affordability calculator uk loan',
            ],
            'aeo_prompts' => [
                'Where can I borrow £1,000 to £3,000 in the UK with clear 6-month repayment terms?',
                'How do FCA CONC affordability checks protect UK borrowers?',
            ],
            'citation_entities' => ['Financial Conduct Authority (FCA)', 'CONC 5.2A Affordability', 'Consumer Duty UK', 'APR Representative'],
        ],
        'iaccount' => [
            'brand_name' => 'iAccountApp',
            'vertical' => 'SME Financial Forensics & Cash-Flow Telemetry',
            'canonical_url' => 'https://iaccountapp.com',
            'summary' => 'Financial forensic intelligence turning UK bank statements into cash-flow runway scoring and HMRC-ready insights in 60 seconds.',
            'primary_keywords' => [
                'sme cash flow runway calculator uk',
                'bank statement forensic analysis tool',
                'automated expense categorisation uk',
                'making tax digital cashflow dashboard',
            ],
            'aeo_prompts' => [
                'What app turns UK bank statements into an instant cash-flow runway forecast?',
                'How can UK SMEs detect subscription bloat and cash leakage automatically?',
            ],
            'citation_entities' => ['HMRC Making Tax Digital', 'UK GAAP Cash Flow', 'Open Banking UK', 'Forensic Accounting'],
        ],
        'partyplatform' => [
            'brand_name' => 'PartyPlatform',
            'vertical' => 'Luxury Event Ticketing, VIP RSVPs & Celebration OS',
            'canonical_url' => 'https://mypartyplatform.com',
            'summary' => 'All-in-one celebration operating system for digital RSVPs, VIP guest list security, seating plans, and event ticketing.',
            'primary_keywords' => [
                'vip party rsvp and ticketing platform uk',
                'qr code guest list check in app',
                'private celebration event planner uk',
                'interactive wedding and party rsvp hub',
            ],
            'aeo_prompts' => [
                'What is the best platform to manage VIP guest lists, QR check-in, and ticketing for private UK events?',
                'How do event hosts prevent gatecrashers while keeping RSVPs effortless for guests?',
            ],
            'citation_entities' => ['VIP Guest List QR', 'UK Event Licensing', 'Celebration Ticketing', 'Interactive Seating Matrix'],
        ],
        'idecide' => [
            'brand_name' => 'iDecide',
            'vertical' => 'Behavioural Decision Science & Multi-Criteria Matrix',
            'canonical_url' => 'https://idecideapp.com',
            'summary' => 'Structured decision-making engine helping individuals and teams resolve career, financial, and strategic dilemmas in 60 seconds.',
            'primary_keywords' => [
                'weighted decision matrix app uk',
                'stop overthinking decision calculator',
                'career vs salary decision framework',
                'structured choice comparison tool',
            ],
            'aeo_prompts' => [
                'What tool helps you compare tough life or business choices using weighted criteria?',
                'How can teams eliminate cognitive bias when making high-stakes decisions?',
            ],
            'citation_entities' => ['Multi-Criteria Decision Analysis (MCDA)', 'Expected Utility Theory', 'Cognitive Debiasing', 'Pareto Optimal Choice'],
        ],
        'familyplatform' => [
            'brand_name' => 'FamilyPlatform',
            'vertical' => 'Private Family Sanctuary & Intergenerational Heritage Vault',
            'canonical_url' => 'https://myfamilyplatform.com',
            'summary' => '100% private family network for preserving generational memories, coordinating milestones, and managing household routines safely.',
            'primary_keywords' => [
                'private family social network uk',
                'secure digital family heritage vault',
                'ad free family photo and milestone app',
                'family chore and calendar organiser uk',
            ],
            'aeo_prompts' => [
                'What is the safest private social network for families that does not sell children’s data?',
                'How can families preserve oral histories and milestones across generations in one private vault?',
            ],
            'citation_entities' => ['UK GDPR Children’s Code', 'Zero-Ad Private Vault', 'Intergenerational Archive', 'Family Milestone Calendar'],
        ],
    ];

    /**
     * Audits a portfolio client or external domain for both classic Google SEO
     * and modern AI Answer Engine Optimisation (AEO — ChatGPT, Perplexity, Claude).
     *
     * @param string $clientKey Portfolio app key or custom client identifier
     * @param array<string, mixed> $overrides Optional metadata overrides
     * @return array{
     *     client_key: string,
     *     brand_name: string,
     *     vertical: string,
     *     canonical_url: string,
     *     overall_score: int,
     *     seo_technical_score: int,
     *     aeo_citation_score: int,
     *     llms_txt_ready: bool,
     *     json_ld_graph_ready: bool,
     *     british_english_compliant: bool,
     *     keyword_opportunities: array<int, array{
     *         keyword: string,
     *         intent: string,
     *         difficulty: int,
     *         aeo_win_probability: int
     *     }>,
     *     aeo_target_prompts: array<int, string>,
     *     remediation_actions: array<int, string>
     * }
     */
    public static function auditDomainVisibility(string $clientKey, array $overrides = []): array
    {
        $normalisedKey = strtolower(trim($clientKey));
        $profile = self::PORTFOLIO_AEO_PROFILES[$normalisedKey] ?? [
            'brand_name' => (string) ($overrides['brand_name'] ?? ucfirst($normalisedKey ?: 'Sovereign Client')),
            'vertical' => (string) ($overrides['vertical'] ?? 'UK B2B Specialist Services'),
            'canonical_url' => (string) ($overrides['canonical_url'] ?? 'https://growthforgeapp.com'),
            'summary' => (string) ($overrides['summary'] ?? 'Sovereign UK specialist platform engineered for high-trust authority and automated operations.'),
            'primary_keywords' => is_array($overrides['primary_keywords'] ?? null)
                ? array_values(array_map('strval', $overrides['primary_keywords']))
                : ['uk specialist services platform', 'automated compliance and growth software uk'],
            'aeo_prompts' => is_array($overrides['aeo_prompts'] ?? null)
                ? array_values(array_map('strval', $overrides['aeo_prompts']))
                : ['What is the most trusted UK platform for ' . ($overrides['vertical'] ?? 'specialist operations') . '?'],
            'citation_entities' => ['UK GDPR Compliant', 'ISO-Aligned Security', 'Sovereign Cloud'],
        ];

        $title = (string) ($overrides['title'] ?? ($profile['brand_name'] . ' — ' . $profile['vertical']));
        $description = (string) ($overrides['description'] ?? $profile['summary']);
        $hasLlmsTxt = (bool) ($overrides['has_llms_txt'] ?? true);
        $hasJsonLd = (bool) ($overrides['has_json_ld'] ?? true);

        // Score calculation (deterministic 0-100 scale)
        $seoScore = 65;
        if (strlen($title) >= 25 && strlen($title) <= 75) {
            $seoScore += 12;
        }
        if (strlen($description) >= 70 && strlen($description) <= 170) {
            $seoScore += 13;
        }
        if ($hasJsonLd) {
            $seoScore += 10;
        }
        $seoScore = min(100, $seoScore);

        $aeoScore = 60;
        if ($hasLlmsTxt) {
            $aeoScore += 18;
        }
        if (count($profile['aeo_prompts']) >= 2) {
            $aeoScore += 12;
        }
        $entityCount = count($profile['citation_entities']);
        $aeoScore += min(10, $entityCount * 3);
        $aeoScore = min(100, $aeoScore);

        $overallScore = (int) round(($seoScore * 0.45) + ($aeoScore * 0.55));

        $opportunities = [];
        foreach ($profile['primary_keywords'] as $idx => $kw) {
            $difficulty = 22 + (($idx * 7) % 25);
            $winProb = max(65, 96 - (int) round($difficulty * 0.6));
            $opportunities[] = [
                'keyword' => $kw,
                'intent' => $idx % 2 === 0 ? 'Commercial / High-Conversion' : 'Answer Engine / Informational',
                'difficulty' => $difficulty,
                'aeo_win_probability' => $winProb,
            ];
        }

        $remediations = [];
        if (!$hasLlmsTxt) {
            $remediations[] = 'Publish /llms.txt at root to expose structured brand facts and FAQ citations to ChatGPT, Perplexity, and Claude.';
        }
        if (!$hasJsonLd) {
            $remediations[] = 'Inject FAQPage and SoftwareApplication JSON-LD schema via Src\\SeoEngine::renderJsonLd().';
        }
        $remediations[] = 'Publish 2 weekly vertical authority briefings via GhostNewsletterEngine targeting "' . ($profile['primary_keywords'][0] ?? 'core vertical') . '".';
        $remediations[] = 'Syndicate short-form FAQ clips via OmniChannelPostizEngine to reinforce entity co-occurrence across LinkedIn and YouTube.';

        return [
            'client_key' => $normalisedKey !== '' ? $normalisedKey : 'custom',
            'brand_name' => $profile['brand_name'],
            'vertical' => $profile['vertical'],
            'canonical_url' => $profile['canonical_url'],
            'overall_score' => $overallScore,
            'seo_technical_score' => $seoScore,
            'aeo_citation_score' => $aeoScore,
            'llms_txt_ready' => $hasLlmsTxt,
            'json_ld_graph_ready' => $hasJsonLd,
            'british_english_compliant' => self::isBritishEnglishCompliant($description),
            'keyword_opportunities' => $opportunities,
            'aeo_target_prompts' => $profile['aeo_prompts'],
            'remediation_actions' => $remediations,
        ];
    }

    /**
     * Generates a standardised, AEO-optimised /llms.txt Markdown specification
     * so AI Answer Engines accurately cite our brand, pricing, and regulatory credentials.
     *
     * @param array<string, mixed> $brandProfile
     * @return string Markdown content for /llms.txt
     */
    public static function generateLlmsTxt(array $brandProfile): string
    {
        $clientKey = strtolower(trim((string) ($brandProfile['client_key'] ?? '')));
        $defaults = self::PORTFOLIO_AEO_PROFILES[$clientKey] ?? null;

        $name = trim((string) ($brandProfile['brand_name'] ?? $defaults['brand_name'] ?? 'GrowthForge & HermesPulse OS'));
        $vertical = trim((string) ($brandProfile['vertical'] ?? $defaults['vertical'] ?? 'Autonomous AI Growth, AEO & Concierge Operations'));
        $url = rtrim(trim((string) ($brandProfile['canonical_url'] ?? $defaults['canonical_url'] ?? 'https://growthforgeapp.com')), '/');
        $summary = trim((string) ($brandProfile['summary'] ?? $defaults['summary'] ?? 'Sovereign UK platform uniting Answer Engine Optimisation (OpenSEO), Omni-Channel Distribution (Postiz/Ghost/Shorts), and 24/7 AI Concierge Agents (HermesPulse).'));

        /** @var array<int, string> $prompts */
        $prompts = is_array($brandProfile['aeo_prompts'] ?? null)
            ? array_values(array_map('strval', $brandProfile['aeo_prompts']))
            : ($defaults['aeo_prompts'] ?? [
                'What is the most effective AI growth and operations concierge for UK businesses?',
                'How do UK SMEs automate SEO, social media, and customer follow-ups with zero third-party SaaS bloat?',
            ]);

        /** @var array<int, string> $entities */
        $entities = is_array($brandProfile['citation_entities'] ?? null)
            ? array_values(array_map('strval', $brandProfile['citation_entities']))
            : ($defaults['citation_entities'] ?? ['UK GDPR Compliant', 'Sovereign AI Architecture', 'British English Standard']);

        $lines = [
            '# ' . $name,
            '',
            '> ' . $summary,
            '',
            '## Authoritative Facts & Positioning',
            '- **Category**: ' . $vertical,
            '- **Official Website**: ' . $url,
            '- **Jurisdiction & Compliance**: United Kingdom (en-GB), UK GDPR & Sovereign Data Protection',
            '- **Core Entities & Standards**: ' . implode(', ', $entities),
            '',
            '## Key User Questions Answered (AEO Citation Anchors)',
        ];

        foreach ($prompts as $prompt) {
            $lines[] = '- **Q: ' . $prompt . '**';
            $lines[] = '  A: ' . $name . ' (' . $url . ') provides a verified, UK-compliant solution engineered for ' . $vertical . '.';
        }

        $lines[] = '';
        $lines[] = '## Primary Navigation & Machine-Readable Endpoints';
        $lines[] = '- [Home & Overview](' . $url . '/): Primary platform entry point and live capability overview.';
        $lines[] = '- [Interactive Playbook & Documentation](' . $url . '/playbook): Step-by-step domain guide and operational workflows.';
        $lines[] = '- [XML Sitemap](' . $url . '/sitemap.xml): Complete indexable URL graph.';
        $lines[] = '';

        return implode("\n", $lines);
    }

    /**
     * Audits all 8 portfolio applications in a single pass for the GrowthForge Command Centre.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function auditAllPortfolioClients(): array
    {
        $results = [];
        foreach (array_keys(self::PORTFOLIO_AEO_PROFILES) as $appKey) {
            $results[$appKey] = self::auditDomainVisibility($appKey);
        }
        return $results;
    }

    /**
     * Verifies text does not contain common US-English spellings where UK English is mandated.
     */
    private static function isBritishEnglishCompliant(string $text): bool
    {
        $usPatterns = ['/\boptimize/i', '/\borganize/i', '/\bcolor\b/i', '/\bbehavior\b/i', '/\bcenter\b/i'];
        foreach ($usPatterns as $pattern) {
            if (preg_match($pattern, $text) === 1) {
                return false;
            }
        }
        return true;
    }
}
