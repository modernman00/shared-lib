<?php

declare(strict_types=1);

namespace Src;

/**
 * Class EcosystemPromoService
 *
 * Centralized Cross-App Ecosystem Promotion & Contextual Banner Engine.
 * Shared across all 7 portfolio applications (PartyPlatform, FamilyPlatform,
 * iDecide, iAccount, TenantScore, ExecMind, LoanEasyFinance).
 *
 * Features:
 * 1. Verified Brand Positioning & Authentic Copy for every application.
 * 2. Real Vector Brand Icons / Marks (zero generic icon placeholders).
 * 3. Contextual Lifecycle Targeting (matching immediate user pain with sibling products).
 * 4. Priority Waterfall: Portfolio Apps first -> High-Ticket Affiliates -> Local Sponsors.
 * 5. Secure Cross-App Context Handoff (HMAC signed payloads).
 */
class EcosystemPromoService
{
    /**
     * Master Portfolio App Catalog with authentic brand copy and real vector icons.
     *
     * @var array<string, array<string, mixed>>
     */
    private const PORTFOLIO_CATALOG = [
        'idecide' => [
            'name' => 'iDecide',
            'headline' => 'Stop Overthinking. Decide With Confidence.',
            'tagline' => 'Multi-Model Decision Engine',
            'description' => 'Clear, simple guides to help you make tough life, money, job, and family choices in under 60 seconds.',
            'cta_text' => 'Make a Decision',
            'badge' => 'DECISION HELPER',
            'accent_color' => '#0ea5e9', // Cyan
            'gradient' => 'linear-gradient(135deg, #0ea5e9 0%, #38bdf8 100%)',
            'icon' => 'bi-compass-fill',
            'default_url' => 'https://idecideapp.com',
            'env_key' => 'IDECIDE_APP_URL',
            'trigger_keywords' => ['decision', 'choices', 'decide', 'overthinking', 'options', 'compare', 'matrix', 'dilemma', 'clarity', 'co_host'],
            'svg_icon' => '<svg viewBox="21.0 10.0 204.0 139.0" width="30" height="30" class="pp-brand-logo-svg" aria-label="iDecide Logo" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="cyanGrad_idecide_ico" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#0ea5e9"/><stop offset="100%" stop-color="#38bdf8"/></linearGradient><clipPath id="oct_idecide_ico"><polygon points="100.00,18.00 171.01,141.00 28.99,141.00"/></clipPath><clipPath id="b2t_idecide_ico"><polygon points="171.01,141.00 77.00,-21.84 77.00,10.16 143.30,125.00"/></clipPath><mask id="wvt_idecide_ico" maskUnits="userSpaceOnUse" x="0" y="0" width="200" height="200"><g clip-path="url(#oct_idecide_ico)" fill="#fff" stroke="#000" stroke-width="4" stroke-linejoin="round"><polygon points="28.99,141.00 217.01,141.00 189.30,125.00 56.70,125.00"/><polygon points="100.00,18.00 5.99,180.84 33.70,164.84 100.00,50.00"/><polygon points="171.01,141.00 77.00,-21.84 77.00,10.16 143.30,125.00"/><polygon points="28.99,141.00 217.01,141.00 189.30,125.00 56.70,125.00" clip-path="url(#b2t_idecide_ico)"/></g></mask></defs><g mask="url(#wvt_idecide_ico)"><g clip-path="url(#oct_idecide_ico)" fill="url(#cyanGrad_idecide_ico)"><polygon points="28.99,141.00 217.01,141.00 189.30,125.00 56.70,125.00"/><polygon points="100.00,18.00 5.99,180.84 33.70,164.84 100.00,50.00"/><polygon points="171.01,141.00 77.00,-21.84 77.00,10.16 143.30,125.00"/></g></g></svg>',
        ],
        'iaccount' => [
            'name' => 'iAccountApp',
            'headline' => 'Your Money Made Crystal Clear.',
            'tagline' => 'Financial Forensic Intelligence',
            'description' => 'Turn bank statements into actionable cash-flow telemetry, runway scoring, and future wealth projections in under 60 seconds.',
            'cta_text' => 'Master Cash Flow',
            'badge' => 'FINANCIAL INTELLIGENCE',
            'accent_color' => '#2563eb', // Royal Blue
            'gradient' => 'linear-gradient(135deg, #2563eb 0%, #38bdf8 100%)',
            'icon' => 'bi-wallet2',
            'default_url' => 'https://iaccountapp.com',
            'env_key' => 'IACCOUNT_APP_URL',
            'trigger_keywords' => ['budget', 'expenses', 'invoice', 'receipts', 'cost', 'money', 'split', 'accounting', 'cashflow', 'wealth'],
            'svg_icon' => '<svg viewBox="0 0 32 32" width="28" height="28" class="pp-brand-logo-svg" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="32" height="32" rx="8" fill="url(#iacc_grad)"/><path d="M8 22V16M14 22V10M20 22V13M26 22V18" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round"/><circle cx="20" cy="9" r="2.5" fill="#38bdf8"/><defs><linearGradient id="iacc_grad" x1="0" y1="0" x2="32" y2="32" gradientUnits="userSpaceOnUse"><stop stop-color="#0284c7"/><stop offset="1" stop-color="#2563eb"/></linearGradient></defs></svg>',
        ],
        'familyplatform' => [
            'name' => 'FamilyPlatform',
            'headline' => 'Private Family Social Network & Living Heritage Tree',
            'tagline' => '100% Private Family Haven',
            'description' => 'Connect generations, preserve precious memories, and celebrate life milestones in a secure, private haven designed exclusively for your family.',
            'cta_text' => 'Explore Family Haven',
            'badge' => 'PRIVATE VAULT',
            'accent_color' => '#4f46e5', // Indigo
            'gradient' => 'linear-gradient(135deg, #4f46e5 0%, #10b981 100%)',
            'icon' => 'bi-people-fill',
            'default_url' => 'https://myfamilyplatform.com',
            'env_key' => 'FAMILYPLATFORM_APP_URL',
            'trigger_keywords' => ['family', 'kids', 'birthday', 'anniversary', 'milestone', 'memories', 'vault', 'heritage', 'chores'],
            'svg_icon' => '<svg viewBox="0 0 32 32" width="28" height="28" class="pp-brand-logo-svg" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="32" height="32" rx="8" fill="url(#fam_grad)"/><path d="M16 6C12 6 9 9 9 13C9 17.5 16 26 16 26C16 26 23 17.5 23 13C23 9 20 6 16 6Z" stroke="#ffffff" stroke-width="2" stroke-linejoin="round"/><circle cx="16" cy="12" r="3" fill="#34d399"/><defs><linearGradient id="fam_grad" x1="0" y1="0" x2="32" y2="32" gradientUnits="userSpaceOnUse"><stop stop-color="#4f46e5"/><stop offset="1" stop-color="#10b981"/></linearGradient></defs></svg>',
        ],
        'scoretenant' => [
            'name' => 'scoreTenant',
            'headline' => 'scoreTenant — Verified Rental Trust Index',
            'tagline' => 'Verified Tenant Reputation',
            'description' => 'Track your verified trust metrics, prove your rental reliability, and unlock premium tenancies with total confidence.',
            'cta_text' => 'Check Trust Index',
            'badge' => 'RENTAL REPUTATION',
            'accent_color' => '#10B981', // Emerald
            'gradient' => 'linear-gradient(135deg, #064E3B 0%, #10B981 100%)',
            'icon' => 'bi-shield-check',
            'default_url' => 'https://scoretenant.com',
            'env_key' => 'SCORE_TENANT_APP_URL',
            'trigger_keywords' => ['house_party', 'neighbour', 'flat', 'apartment', 'noise', 'landlord', 'tenant', 'rental', 'lease'],
            'svg_icon' => '<svg viewBox="0 0 512 512" width="28" height="28" class="pp-brand-logo-svg" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="em_grad" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#34D399"/><stop offset="100%" stop-color="#059669"/></linearGradient><linearGradient id="gd_grad" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#FDE047"/><stop offset="100%" stop-color="#D97706"/></linearGradient></defs><rect width="512" height="512" rx="112" fill="#064E3B"/><path d="M256 95 L365 145 C365 270 256 370 256 398 C256 370 147 270 147 145 Z" fill="none" stroke="url(#em_grad)" stroke-width="26" stroke-linejoin="round"/><path d="M190 270 A 80 80 0 1 1 322 270" fill="none" stroke="url(#gd_grad)" stroke-width="30" stroke-linecap="round"/><path d="M210 245 L245 280 L315 195" fill="none" stroke="#FFFFFF" stroke-width="36" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        ],
        'execmind' => [
            'name' => 'ExecMind',
            'headline' => 'ExecMind — Your Private AI Advisor',
            'tagline' => 'Executive Intelligence Platform',
            'description' => 'Automates intelligence gathering, executive decision frameworks, career scaling, and document synthesis on autopilot.',
            'cta_text' => 'Launch AI Advisor',
            'badge' => 'AI ADVISOR',
            'accent_color' => '#8b5cf6', // Violet
            'gradient' => 'linear-gradient(135deg, #4f46e5 0%, #06b6d4 100%)',
            'icon' => 'bi-lightbulb-fill',
            'default_url' => 'https://execmindapp.com',
            'env_key' => 'EXECMIND_APP_URL',
            'trigger_keywords' => ['corporate', 'retreat', 'exec', 'leadership', 'strategy', 'conference', 'gala', 'advisor', 'executive'],
            'svg_icon' => '<svg viewBox="0 0 512 512" width="28" height="28" class="pp-brand-logo-svg" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="cy_exec" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#38BDF8"/><stop offset="100%" stop-color="#0284C7"/></linearGradient><linearGradient id="gd_exec" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#FDE68A"/><stop offset="100%" stop-color="#F59E0B"/></linearGradient></defs><rect width="512" height="512" rx="112" fill="#0F172A"/><polygon points="256,90 385,165 385,315 256,390 127,315 127,165" fill="none" stroke="url(#cy_exec)" stroke-width="24" stroke-linejoin="round"/><polygon points="256,160 326,240 256,320 186,240" fill="url(#gd_exec)"/><circle cx="256" cy="240" r="32" fill="#FFFFFF"/></svg>',
        ],
        'loaneasyfinance' => [
            'name' => 'LoanEasy Finance',
            'headline' => 'Instant Funding Whenever You Need It',
            'tagline' => 'FCA Authorised & Regulated',
            'description' => 'Borrow between £750 and £3,000 for up to 6 months with lightning-fast decisions and crystal-clear terms.',
            'cta_text' => 'Calculate Loan',
            'badge' => 'FCA REGULATED',
            'accent_color' => '#22a06b', // Brand Green
            'gradient' => 'linear-gradient(135deg, #1e3a8a 0%, #22a06b 100%)',
            'icon' => 'bi-cash-coin',
            'default_url' => 'https://loaneasyfinance.com',
            'env_key' => 'LOANEASYFINANCE_APP_URL',
            'trigger_keywords' => ['finance', 'loan', 'luxury', 'wedding', 'venue_hire', 'high_budget', 'financing', 'funding', 'borrow'],
            'svg_icon' => '<svg viewBox="0 0 32 32" width="28" height="28" class="pp-brand-logo-svg" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="32" height="32" rx="8" fill="#1e3a8a"/><path d="M9 16L14 21L23 11" stroke="#22a06b" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><circle cx="16" cy="16" r="10" stroke="#38bdf8" stroke-width="2" stroke-dasharray="3 3"/></svg>',
        ],
        'partyplatform' => [
            'name' => 'PartyPlatform',
            'headline' => 'Throw Unforgettable Parties.',
            'tagline' => 'VIP Celebrations & Ticketing',
            'description' => 'VIP celebrations, instant digital RSVPs, guest list management, and ticketing in one seamless luxury experience.',
            'cta_text' => 'Plan Celebration',
            'badge' => 'CELEBRATION OS',
            'accent_color' => '#9B5CFB', // Electric Purple
            'gradient' => 'linear-gradient(135deg, #FFE259 0%, #FFA751 100%)',
            'icon' => 'bi-balloon-heart-fill',
            'default_url' => 'https://mypartyplatform.com',
            'env_key' => 'PARTYPLATFORM_APP_URL',
            'trigger_keywords' => ['party', 'event', 'celebration', 'rsvp', 'tickets', 'guest_list', 'invitation'],
            'svg_icon' => '<svg viewBox="0 0 512 512" width="28" height="28" class="pp-brand-logo-svg" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="spk_g1" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#F43F5E"/><stop offset="100%" stop-color="#8B5CF6"/></linearGradient><linearGradient id="spk_g2" x1="0%" y1="100%" x2="100%" y2="0%"><stop offset="0%" stop-color="#FBBF24"/><stop offset="100%" stop-color="#EF4444"/></linearGradient></defs><rect width="512" height="512" rx="112" fill="#1E1035"/><path d="M256 120 L278 220 L378 198 L296 256 L378 314 L278 292 L256 392 L234 292 L134 314 L216 256 L134 198 L234 220 Z" fill="url(#spk_g1)"/><path d="M256 80 Q256 220 120 256 Q256 256 256 432 Q256 256 392 256 Q256 220 256 80 Z" fill="url(#spk_g2)"/><circle cx="256" cy="256" r="32" fill="#FFFFFF"/></svg>',
        ],
    ];

    /**
     * Fallback Curated Affiliate Recommendations.
     *
     * @var array<string, array<string, mixed>>
     */
    private const AFFILIATE_CATALOG = [
        'supplies' => [
            'name' => 'Party Essentials Hub',
            'headline' => 'Party Essentials & Curated Favors',
            'tagline' => 'Amazon Party Supplies',
            'description' => 'Eco-friendly tableware, themed balloons, and next-day party supplies delivered fast.',
            'cta_text' => 'Shop Party Supplies',
            'accent_color' => '#f59e0b',
            'gradient' => 'linear-gradient(135deg, #FFE259 0%, #FFA751 100%)',
            'icon' => 'bi-box-seam-fill',
            'target_url' => 'https://www.amazon.co.uk/s?k=party+supplies',
            'disclosure_type' => 'affiliate',
            'badge' => 'CURATED PARTNER',
            'svg_icon' => '<svg viewBox="0 0 24 24" width="28" height="28" class="pp-brand-logo-svg" fill="none" stroke="#FFA751" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line></svg>',
        ],
        'drinks' => [
            'name' => 'Virgin Wines & Majestic',
            'headline' => 'Celebration Cases & Artisan Champagne',
            'tagline' => 'Virgin Wines & Majestic',
            'description' => 'Order premium celebration wine and champagne cases with free event glass hire and direct venue delivery.',
            'cta_text' => 'Order Celebration Cases',
            'accent_color' => '#e11d48',
            'gradient' => 'linear-gradient(135deg, #f43f5e 0%, #be123c 100%)',
            'icon' => 'bi-cup-straw',
            'target_url' => 'https://www.virginwines.co.uk',
            'disclosure_type' => 'affiliate',
            'badge' => 'DRINKS PARTNER',
            'svg_icon' => '<svg viewBox="0 0 24 24" width="28" height="28" class="pp-brand-logo-svg" fill="none" stroke="#f43f5e" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" xmlns="http://www.w3.org/2000/svg"><path d="M8 22h8"></path><path d="M12 11v11"></path><path d="m19 3-4 8H9L5 3z"></path></svg>',
        ],
    ];

    private string $secret;

    public function __construct(?string $secret = null)
    {
        $this->secret = $secret 
            ?? ($_ENV['ECOSYSTEM_SHARED_SECRET'] ?? ($_ENV['APP_KEY'] ?? 'ecosystem_cross_app_master_secret_2026'));
    }

    /**
     * Resolves the highest-priority promo banner for the given context.
     * Prioritizes internal sister apps first, then falls back to affiliates.
     *
     * @param string $currentApp Current application key (e.g. 'partyplatform')
     * @param string $placement Location identifier (e.g. 'dashboard', 'budget', 'event_details')
     * @param array<string, mixed> $context Context attributes (e.g. ['event_type' => 'birthday', 'tags' => ['budget']])
     * @return array<string, mixed>|null Structured banner array or null
     */
    public function getPromoForContext(string $currentApp, string $placement, array $context = []): ?array
    {
        $currentAppKey = strtolower(trim($currentApp));

        // 1. Gather context keywords
        $keywords = $this->extractKeywords($context, $placement);

        // 2. Score internal portfolio candidates (excluding current app)
        $bestMatchApp = null;
        $highestScore = 0;

        foreach (self::PORTFOLIO_CATALOG as $appKey => $meta) {
            if ($appKey === $currentAppKey) {
                continue; // Strictly never promote the current app to itself
            }

            $score = 0;
            $triggerWords = (array) $meta['trigger_keywords'];
            foreach ($keywords as $kw) {
                if (in_array($kw, $triggerWords, true)) {
                    $score += 10;
                }
            }

            if ($score > $highestScore) {
                $highestScore = $score;
                $bestMatchApp = $appKey;
            }
        }

        // 3. If an internal match has positive intent, return Tier 1 Ecosystem banner
        if ($bestMatchApp !== null && $highestScore > 0) {
            return $this->formatEcosystemBanner($bestMatchApp, $currentAppKey, $placement, $context);
        }

        // 4. If no specific keyword match, select default strategic internal partner
        $defaultSibling = $this->getDefaultSiblingApp($currentAppKey, $placement, $context);
        if ($defaultSibling !== null) {
            return $this->formatEcosystemBanner($defaultSibling, $currentAppKey, $placement, $context);
        }

        // 5. Tier 2 Fallback: Curated Affiliate partner
        $affiliateKey = in_array('drinks', $keywords, true) ? 'drinks' : 'supplies';
        $affiliate = self::AFFILIATE_CATALOG[$affiliateKey];

        return [
            'id' => 'affiliate_' . $affiliateKey,
            'type' => 'affiliate',
            'target_app' => 'external',
            'brand_name' => $affiliate['name'],
            'badge' => $affiliate['badge'],
            'title' => $affiliate['headline'],
            'tagline' => $affiliate['tagline'],
            'description' => $affiliate['description'],
            'cta_text' => $affiliate['cta_text'],
            'target_url' => (string) $affiliate['target_url'],
            'accent_color' => (string) $affiliate['accent_color'],
            'gradient' => (string) $affiliate['gradient'],
            'icon' => (string) $affiliate['icon'],
            'svg_icon' => (string) $affiliate['svg_icon'],
            'disclosure_type' => 'affiliate',
            'disclosure_text' => 'Transparency: PartyPlatform earns a commission on qualifying partner purchases.',
            'dismissible' => true,
        ];
    }

    /**
     * Builds a tracked cross-app URL with UTM parameters and optional HMAC handoff context.
     *
     * @param string $targetApp Target application key (e.g. 'idecide')
     * @param string $sourceApp Source application key (e.g. 'partyplatform')
     * @param string $placement Surface identifier (e.g. 'co_hosts')
     * @param array<string, mixed> $handoffContext Optional context to carry across
     * @return string
     */
    public function buildEcosystemUrl(
        string $targetApp,
        string $sourceApp,
        string $placement,
        array $handoffContext = []
    ): string {
        $meta = self::PORTFOLIO_CATALOG[$targetApp] ?? null;
        if ($meta === null) {
            return '/';
        }

        $envKey = (string) $meta['env_key'];
        $baseUrl = '';
        if (isset($_ENV[$envKey]) && (string) $_ENV[$envKey] !== '') {
            $baseUrl = (string) $_ENV[$envKey];
        } elseif ($targetApp === 'familyplatform' && !empty($_ENV['MYFAMILYPLATFORM_APP_URL'])) {
            $baseUrl = (string) $_ENV['MYFAMILYPLATFORM_APP_URL'];
        } elseif ($targetApp === 'partyplatform' && !empty($_ENV['MYPARTYPLATFORM_APP_URL'])) {
            $baseUrl = (string) $_ENV['MYPARTYPLATFORM_APP_URL'];
        } else {
            $baseUrl = (string) $meta['default_url'];
        }

        $params = [
            'utm_source' => $sourceApp,
            'utm_medium' => 'ecosystem_ribbon',
            'utm_campaign' => $placement,
            'utm_content' => $targetApp,
        ];

        if (!empty($handoffContext)) {
            $jsonPayload = json_encode($handoffContext, JSON_THROW_ON_ERROR);
            $b64 = rtrim(strtr(base64_encode($jsonPayload), '+/', '-_'), '=');
            $sig = hash_hmac('sha256', $b64, $this->secret);

            $params['eco_ctx'] = $b64;
            $params['eco_sig'] = $sig;
        }

        $separator = str_contains($baseUrl, '?') ? '&' : '?';
        return $baseUrl . $separator . http_build_query($params);
    }

    /**
     * Verifies and unpacks an inbound cross-app context handoff token.
     *
     * @param string $payload Base64url-encoded context payload
     * @param string $signature Provided HMAC signature
     * @return array<string, mixed>|null Unpacked context or null on verification failure
     */
    public function verifyHandoffToken(string $payload, string $signature): ?array
    {
        if (empty($payload) || empty($signature)) {
            return null;
        }

        $expectedSig = hash_hmac('sha256', $payload, $this->secret);
        if (!hash_equals($expectedSig, $signature)) {
            return null; // Reject signature mismatch fail-closed
        }

        $b64 = strtr($payload, '-_', '+/');
        $padded = str_pad($b64, strlen($b64) + (4 - strlen($b64) % 4) % 4, '=');
        $rawJson = base64_decode($padded, true);
        if ($rawJson === false) {
            return null;
        }

        try {
            $data = json_decode($rawJson, true, 512, JSON_THROW_ON_ERROR);
            return is_array($data) ? $data : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Formats an ecosystem banner record.
     *
     * @param string $targetApp
     * @param string $sourceApp
     * @param string $placement
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function formatEcosystemBanner(
        string $targetApp,
        string $sourceApp,
        string $placement,
        array $context = []
    ): array {
        $meta = self::PORTFOLIO_CATALOG[$targetApp];
        $targetUrl = $this->buildEcosystemUrl($targetApp, $sourceApp, $placement, $context['handoff'] ?? []);

        return [
            'id' => 'eco_' . $targetApp,
            'type' => 'ecosystem',
            'target_app' => $targetApp,
            'brand_name' => (string) $meta['name'],
            'badge' => (string) $meta['badge'],
            'title' => (string) $meta['headline'],
            'tagline' => (string) $meta['tagline'],
            'description' => (string) $meta['description'],
            'cta_text' => (string) $meta['cta_text'],
            'target_url' => $targetUrl,
            'accent_color' => (string) $meta['accent_color'],
            'gradient' => (string) $meta['gradient'],
            'icon' => (string) $meta['icon'],
            'svg_icon' => (string) $meta['svg_icon'],
            'disclosure_type' => 'ecosystem',
            'disclosure_text' => 'Proudly crafted by our sister studio — Seamless zero-friction single login.',
            'dismissible' => true,
        ];
    }

    /**
     * Selects an ecosystem sister app using zero-cron deterministic time-window hashing
     * combined with session impression cycling to eliminate banner blindness.
     *
     * @param string $currentApp Current application key
     * @param string $placement Surface identifier
     * @param array<string, mixed> $context Context attributes
     * @return string|null Sister app key or null
     */
    private function getDefaultSiblingApp(string $currentApp, string $placement, array $context = []): ?string
    {
        // 1. Filter all eligible sister apps (strictly excluding current host app)
        $eligibleApps = array_values(array_filter(
            array_keys(self::PORTFOLIO_CATALOG),
            static fn(string $app): bool => $app !== $currentApp
        ));

        if (empty($eligibleApps)) {
            return null;
        }

        $count = count($eligibleApps);

        // 2. Day-of-year rotation baseline (0-365):
        // Automatically varies every single day at midnight UTC without cron or background workers.
        $dayOfYear = isset($context['mock_day_of_year']) && is_numeric($context['mock_day_of_year'])
            ? (int) $context['mock_day_of_year']
            : (int) date('z');

        // 3. Placement salt ensures distinct pages on the same day feature distinct apps
        $placementSalt = abs(crc32($currentApp . ':' . $placement)) % $count;

        // 4. Session-aware impression index:
        // Sequentially cycles through eligible apps across page loads within an active user session.
        $sessionOffset = 0;
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION)) {
            $sessionKey = '_eco_promo_seq_' . $currentApp;
            $sessionOffset = isset($_SESSION[$sessionKey]) && is_numeric($_SESSION[$sessionKey])
                ? (int) $_SESSION[$sessionKey]
                : 0;

            // Increment sequentially for the next page load
            $_SESSION[$sessionKey] = ($sessionOffset + 1) % $count;
        } elseif (isset($context['mock_session_offset']) && is_numeric($context['mock_session_offset'])) {
            $sessionOffset = (int) $context['mock_session_offset'];
        }

        // 5. Deterministic target index
        $targetIndex = ($dayOfYear + $placementSalt + $sessionOffset) % $count;

        return $eligibleApps[$targetIndex] ?? $eligibleApps[0];
    }

    /**
     * Extracts all relevant lowercase keywords from context.
     *
     * @param array<string, mixed> $context
     * @param string $placement
     * @return array<int, string>
     */
    private function extractKeywords(array $context, string $placement): array
    {
        $words = [];
        $words[] = strtolower(trim($placement));

        if (!empty($context['event_type'])) {
            $words[] = strtolower((string) $context['event_type']);
        }
        if (!empty($context['category'])) {
            $words[] = strtolower((string) $context['category']);
        }
        if (!empty($context['tags']) && is_array($context['tags'])) {
            foreach ($context['tags'] as $tag) {
                if (is_string($tag)) {
                    $words[] = strtolower(trim($tag));
                }
            }
        }

        return array_values(array_unique(array_filter($words)));
    }

    /**
     * Returns full catalog metadata for administrative or testing purposes.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getCatalog(): array
    {
        return self::PORTFOLIO_CATALOG;
    }
}
