<?php

declare(strict_types=1);

namespace Src\Explainer;

use InvalidArgumentException;

/**
 * PortfolioExplainerRegistry
 *
 * Central registry and catalog of 60fps sovereign explainer storyboards across the
 * portfolio applications (PartyPlatform, FamilyPlatform, iDecide, iAccountApp,
 * TenantScore, ExecMindApp, LoanEasyFinance, and BestCareProfessionals).
 *
 * Strictly enforces:
 * 1. The Core 3-Part Narrative Arc:
 *    - The Problem: Identify the viewer's specific pain point or frustration.
 *    - The Solution: Introduce the product or service as the clear answer.
 *    - The Call to Action (CTA): Tell the viewer exactly what to do next.
 * 2. The 4 Industry Visual Formats:
 *    - 2D & 3D Animation: Isometric software UI, characters, environments & kinetic motion.
 *    - Whiteboard Animation: Hand-drawn illustrations on a clean white surface with sketch draw-in.
 *    - Motion Graphics: Kinetic typography, abstract shapes, vibrant colours & animated data counters.
 *    - Live-Action & Mixed Media: Device mockups, video-like framing, avatars & synced callouts.
 * 3. British English (UK) Invariant across all narration copy.
 * 4. Zero third-party SaaS dependency.
 */
class PortfolioExplainerRegistry
{
    /**
     * Resolves and normalises application keys.
     *
     * @param string $appKey
     * @return string
     * @throws InvalidArgumentException
     */
    public static function normaliseAppKey(string $appKey): string
    {
        $k = strtolower(trim($appKey));
        return match ($k) {
            'party', 'partyplatform' => 'partyplatform',
            'family', 'familyplatform' => 'familyplatform',
            'idecide', 'idecideapp' => 'idecide',
            'iaccount', 'iaccountapp' => 'iaccountapp',
            'tenantscore', 'scoretenant' => 'tenantscore',
            'execmind', 'execmindapp' => 'execmindapp',
            'loaneasy', 'loaneasyfinance' => 'loaneasyfinance',
            'bcp', 'bestcareprofessionals' => 'bcp',
            default => throw new InvalidArgumentException(sprintf('Unknown portfolio application key: "%s".', $appKey)),
        };
    }

    /**
     * Returns the curated storyboard blueprint definitions for all 8 portfolio apps,
     * strictly adhering to the industry 3-part narrative arc (Problem -> Solution -> CTA).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function getMasterCatalog(): array
    {
        return [
            'partyplatform' => [
                'id' => 'exp_party_viral_reel',
                'title' => 'Viral Event Invitation, Live Celebration Wall & Smart Bar Tab Split',
                'app_name' => 'partyplatform',
                'aspect_ratio' => ExplainerStoryboard::ASPECT_9_16,
                'visual_format' => ExplainerStoryboard::FORMAT_MOTION_GRAPHICS,
                'theme' => 'celebration-vibrant',
                'scenes' => [
                    [
                        'id' => 'party_sc_1',
                        'stage' => ExplainerStoryboard::STAGE_PROBLEM,
                        'duration' => 6.0,
                        'title' => 'The Problem: Chaotic Group Chats & Lost Allergies',
                        'narration' => 'Chasing guests across five WhatsApp chats, losing track of critical nut allergies, and clunky spreadsheets leaves party hosts exhausted.',
                        'target_selector' => '#chat-chaos-card',
                        'camera' => ['x' => 0.5, 'y' => 0.4, 'zoom' => 1.2],
                        'cursor' => ['action' => 'move', 'targetX' => 0.5, 'targetY' => 0.4, 'label' => 'Unread Messages (48)'],
                        'visual_svg' => '<svg viewBox="0 0 400 180" class="w-full h-36"><rect width="400" height="180" rx="12" fill="#1e1b4b"/><rect x="24" y="24" width="352" height="38" rx="8" fill="#450a0a"/><text x="40" y="48" fill="#fca5a5" font-size="13" font-weight="bold">⚠️ WhatsApp: 48 Unread Messages · "Are nuts in the cake?"</text><rect x="24" y="70" width="352" height="38" rx="8" fill="#450a0a"/><text x="40" y="94" fill="#fca5a5" font-size="13" font-weight="bold">⚠️ Spreadsheet: 14 Unconfirmed RSVPs · Lost Plus-Ones</text><text x="40" y="145" fill="#f87171" font-size="14" font-weight="bold">Host Stress: Critical Breakdown</text></svg>',
                    ],
                    [
                        'id' => 'party_sc_2',
                        'stage' => ExplainerStoryboard::STAGE_SOLUTION,
                        'duration' => 6.0,
                        'title' => 'The Solution: 1-Click RSVP & Live Celebration Wall',
                        'narration' => 'PartyPlatform solves it in one click: generate viral animated invitations, automate dietary matrices, and project guest photos onto a real-time live wall.',
                        'target_selector' => '#generate-flyer-btn',
                        'camera' => ['x' => 0.6, 'y' => 0.5, 'zoom' => 1.4],
                        'cursor' => ['action' => 'click', 'targetX' => 0.6, 'targetY' => 0.5, 'label' => 'Launch Celebration Hub'],
                        'visual_svg' => '<svg viewBox="0 0 400 180" class="w-full h-36"><rect width="400" height="180" rx="12" fill="#0f172a"/><circle cx="70" cy="90" r="36" fill="#ec4899"/><text x="130" y="70" fill="#ffffff" font-size="16" font-weight="bold">PartyPlatform Celebration Hub</text><text x="130" y="95" fill="#cbd5e1" font-size="13">148 Confirmed · 100% Dietary Reconciled</text><rect x="130" y="112" width="160" height="30" rx="8" fill="#10b981"/><text x="145" y="132" fill="#022c22" font-size="12" font-weight="bold">✓ Live Wall Active</text></svg>',
                    ],
                    [
                        'id' => 'party_sc_3',
                        'stage' => ExplainerStoryboard::STAGE_CTA,
                        'duration' => 6.0,
                        'title' => 'Call to Action: Launch Your Celebration Free',
                        'narration' => 'Stop the group chat madness. Create your free party celebration right now at partyplatform.test and host like a pro!',
                        'target_selector' => '#launch-event-cta',
                        'camera' => ['x' => 0.5, 'y' => 0.6, 'zoom' => 1.3],
                        'cursor' => ['action' => 'click', 'targetX' => 0.5, 'targetY' => 0.6, 'label' => 'Create Event Free'],
                        'visual_svg' => '<svg viewBox="0 0 400 180" class="w-full h-36"><rect width="400" height="180" rx="12" fill="#064e3b"/><text x="32" y="55" fill="#ecfdf5" font-size="16" font-weight="bold">Host Unforgettable Parties in 60 Seconds</text><text x="32" y="85" fill="#a7f3d0" font-size="13">Free Setup · No App Download Required For Guests</text><rect x="32" y="110" width="220" height="42" rx="10" fill="#10b981"/><text x="50" y="136" fill="#022c22" font-size="14" font-weight="bold">🚀 Create Free Event Now</text></svg>',
                    ],
                ],
                'metadata' => ['cluster' => 'social_lifestyle', 'primary_kpi' => 'viral_k_factor'],
            ],
            'familyplatform' => [
                'id' => 'exp_family_kinship_hub',
                'title' => 'Private Family Kinship Tree, Elder Check-In & Heirloom Vault',
                'app_name' => 'familyplatform',
                'aspect_ratio' => ExplainerStoryboard::ASPECT_16_9,
                'visual_format' => ExplainerStoryboard::FORMAT_MOTION_GRAPHICS,
                'theme' => 'warm-family',
                'scenes' => [
                    [
                        'id' => 'fam_sc_1',
                        'stage' => ExplainerStoryboard::STAGE_PROBLEM,
                        'duration' => 6.0,
                        'title' => 'The Problem: Scattered Relatives & Care Anxiety',
                        'narration' => 'Busy relatives live miles apart, birthdays get missed, and families suffer constant anxiety over vulnerable elderly parents living alone.',
                        'target_selector' => '#isolation-warning',
                        'camera' => ['x' => 0.5, 'y' => 0.5, 'zoom' => 1.2],
                        'cursor' => ['action' => 'move', 'targetX' => 0.5, 'targetY' => 0.5, 'label' => 'Check Isolation Index'],
                        'visual_svg' => '<svg viewBox="0 0 400 180" class="w-full h-36"><rect width="400" height="180" rx="12" fill="#1e293b"/><rect x="28" y="32" width="344" height="42" rx="8" fill="#450a0a"/><text x="44" y="58" fill="#fca5a5" font-size="13" font-weight="bold">⚠️ Grandad: No Response In 18 Hours</text><text x="44" y="115" fill="#e2e8f0" font-size="14">Missed Auntie Nora Birthday · 3 Days Ago</text><text x="44" y="145" fill="#f87171" font-size="13">Family Disconnect Index: High</text></svg>',
                    ],
                    [
                        'id' => 'fam_sc_2',
                        'stage' => ExplainerStoryboard::STAGE_SOLUTION,
                        'duration' => 5.0,
                        'title' => 'The Solution: 1-Tap Elder Check-In & Kinship Sanctuary',
                        'narration' => 'FamilyPlatform creates a private sanctuary: grandparents confirm well-being with a single tap, and relatives coordinate outings effortlessly.',
                        'target_selector' => '#elder-checkin-btn',
                        'camera' => ['x' => 0.5, 'y' => 0.5, 'zoom' => 1.4],
                        'cursor' => ['action' => 'click', 'targetX' => 0.5, 'targetY' => 0.5, 'label' => 'I Am Well Today'],
                        'visual_svg' => '<svg viewBox="0 0 400 180" class="w-full h-36"><rect width="400" height="180" rx="12" fill="#065f46"/><rect x="40" y="40" width="320" height="95" rx="16" fill="#10b981"/><text x="75" y="85" fill="#022c22" font-size="18" font-weight="bold">✓ Grandad Checked In Safe</text><text x="75" y="112" fill="#064e3b" font-size="13">08:45 AM London · Household Reassured</text></svg>',
                    ],
                    [
                        'id' => 'fam_sc_3',
                        'stage' => ExplainerStoryboard::STAGE_CTA,
                        'duration' => 5.0,
                        'title' => 'Call to Action: Unite Your Family Today',
                        'narration' => 'Bring your family together in a private, encrypted sanctuary. Claim your private family hub today at familyplatform.test!',
                        'target_selector' => '#claim-family-hub-cta',
                        'camera' => ['x' => 0.5, 'y' => 0.6, 'zoom' => 1.3],
                        'cursor' => ['action' => 'click', 'targetX' => 0.5, 'targetY' => 0.6, 'label' => 'Claim Family Hub'],
                        'visual_svg' => '<svg viewBox="0 0 400 180" class="w-full h-36"><rect width="400" height="180" rx="12" fill="#312e81"/><text x="32" y="55" fill="#ffffff" font-size="16" font-weight="bold">Protect Your Family Heritage &amp; Peace of Mind</text><text x="32" y="85" fill="#c7d2fe" font-size="13">Private · Ad-Free · End-to-End Encrypted</text><rect x="32" y="110" width="230" height="42" rx="10" fill="#6366f1"/><text x="50" y="136" fill="#ffffff" font-size="14" font-weight="bold">🏡 Claim Your Family Hub</text></svg>',
                    ],
                ],
                'metadata' => ['cluster' => 'social_lifestyle', 'primary_kpi' => 'weekly_active_families'],
            ],
            'idecide' => [
                'id' => 'exp_idecide_monte_carlo',
                'title' => 'Cognitive Decision Matrix, Monte Carlo Simulation & Blind Peer Consensus',
                'app_name' => 'idecide',
                'aspect_ratio' => ExplainerStoryboard::ASPECT_16_9,
                'visual_format' => ExplainerStoryboard::FORMAT_MOTION_GRAPHICS,
                'theme' => 'executive-slate',
                'scenes' => [
                    [
                        'id' => 'idec_sc_1',
                        'stage' => ExplainerStoryboard::STAGE_PROBLEM,
                        'duration' => 6.0,
                        'title' => 'The Problem: Cognitive Bias & Decision Paralysis',
                        'narration' => 'Major decisions—from five-year fixed mortgages to career changes—often lead to sleepless nights, emotional bias, and conflicting advice.',
                        'target_selector' => '#dilemma-bias-warning',
                        'camera' => ['x' => 0.5, 'y' => 0.4, 'zoom' => 1.2],
                        'cursor' => ['action' => 'move', 'targetX' => 0.5, 'targetY' => 0.4, 'label' => 'Inspect Cognitive Bias'],
                        'visual_svg' => '<svg viewBox="0 0 400 180" class="w-full h-36"><rect width="400" height="180" rx="12" fill="#0f172a"/><text x="28" y="48" fill="#f87171" font-size="15" font-weight="bold">⚠️ High-Stakes Decision Paralysis: 50 / 50 Dilemma</text><rect x="28" y="68" width="340" height="32" rx="6" fill="#334155"/><text x="40" y="90" fill="#fca5a5" font-size="13">Option A: Fixed 5-Year Mortgage vs Option B: Career Leap</text><text x="40" y="140" fill="#94a3b8" font-size="13">Anchoring Bias · Groupthink Risk: Extreme</text></svg>',
                    ],
                    [
                        'id' => 'idec_sc_2',
                        'stage' => ExplainerStoryboard::STAGE_SOLUTION,
                        'duration' => 7.0,
                        'title' => 'The Solution: 10,000-Run Monte Carlo Simulation',
                        'narration' => 'iDecide stress-tests your options: run ten thousand stochastic simulations in milliseconds, and invite trusted peers to vote blindly without groupthink.',
                        'target_selector' => '#monte-carlo-run-btn',
                        'camera' => ['x' => 0.6, 'y' => 0.5, 'zoom' => 1.5],
                        'cursor' => ['action' => 'click', 'targetX' => 0.6, 'targetY' => 0.5, 'label' => 'Run 10,000 Simulations'],
                        'visual_svg' => '<svg viewBox="0 0 400 180" class="w-full h-36"><rect width="400" height="180" rx="12" fill="#1e1b4b"/><polyline points="30,135 90,115 150,65 200,35 250,70 310,120 370,140" fill="none" stroke="#a855f7" stroke-width="4"/><text x="70" y="165" fill="#e9d5ff" font-size="13" font-weight="bold">10,000 Runs Verified: Option A Yields 92.4% Utility</text></svg>',
                    ],
                    [
                        'id' => 'idec_sc_3',
                        'stage' => ExplainerStoryboard::STAGE_CTA,
                        'duration' => 6.0,
                        'title' => 'Call to Action: Run Your Free Simulation Today',
                        'narration' => 'Decide with mathematical certainty. Frame your dilemma and run your first free simulation at idecide.test today!',
                        'target_selector' => '#start-decision-cta',
                        'camera' => ['x' => 0.5, 'y' => 0.6, 'zoom' => 1.3],
                        'cursor' => ['action' => 'click', 'targetX' => 0.5, 'targetY' => 0.6, 'label' => 'Run Free Simulation'],
                        'visual_svg' => '<svg viewBox="0 0 400 180" class="w-full h-36"><rect width="400" height="180" rx="12" fill="#042f2e"/><text x="32" y="55" fill="#ccfbf1" font-size="16" font-weight="bold">Make Confident Decisions With Zero Regrets</text><text x="32" y="85" fill="#5eead4" font-size="13">Peer Consensus · Monte Carlo Engine · Completely Free</text><rect x="32" y="110" width="240" height="42" rx="10" fill="#0d9488"/><text x="50" y="136" fill="#ffffff" font-size="14" font-weight="bold">⚖️ Simulate My Dilemma Free</text></svg>',
                    ],
                ],
                'metadata' => ['cluster' => 'fintech_utilities', 'primary_kpi' => 'completed_simulations'],
            ],
            'iaccountapp' => [
                'id' => 'exp_iaccount_hmrc_ledger',
                'title' => 'Double-Entry Cash Runway, TrueLayer Open Banking & HMRC Tax Optimizer',
                'app_name' => 'iaccountapp',
                'aspect_ratio' => ExplainerStoryboard::ASPECT_16_9,
                'visual_format' => ExplainerStoryboard::FORMAT_MOTION_GRAPHICS,
                'theme' => 'fintech-navy',
                'scenes' => [
                    [
                        'id' => 'iacc_sc_1',
                        'stage' => ExplainerStoryboard::STAGE_PROBLEM,
                        'duration' => 6.0,
                        'title' => 'The Problem: Surprise Tax Bills & Vanishing Cash Runway',
                        'narration' => 'Unexpected HMRC Corporation Tax bills, misplaced receipts, and no clear visibility into how many months of cash runway remain can destroy a business.',
                        'target_selector' => '#hmrc-threat-indicator',
                        'camera' => ['x' => 0.5, 'y' => 0.4, 'zoom' => 1.2],
                        'cursor' => ['action' => 'move', 'targetX' => 0.5, 'targetY' => 0.4, 'label' => 'Inspect Cash Threat'],
                        'visual_svg' => '<svg viewBox="0 0 400 180" class="w-full h-36"><rect width="400" height="180" rx="12" fill="#0f172a"/><text x="28" y="48" fill="#f87171" font-size="15" font-weight="bold">⚠️ HMRC Corporation Tax Due: £14,200</text><text x="28" y="85" fill="#94a3b8" font-size="13">True Bank Balance: Unknown · Lost Receipts: 32</text><text x="28" y="135" fill="#ef4444" font-size="14" font-weight="bold">Cash Runway Visibility: Blind</text></svg>',
                    ],
                    [
                        'id' => 'iacc_sc_2',
                        'stage' => ExplainerStoryboard::STAGE_SOLUTION,
                        'duration' => 6.0,
                        'title' => 'The Solution: Double-Entry Precision & 90-Day Runway Forecasting',
                        'narration' => 'iAccount connects UK banks via TrueLayer, automates Corporation Tax and ISA provisions, and models ninety-day cash runway under stress.',
                        'target_selector' => '#truelayer-sync-btn',
                        'camera' => ['x' => 0.6, 'y' => 0.5, 'zoom' => 1.4],
                        'cursor' => ['action' => 'click', 'targetX' => 0.6, 'targetY' => 0.5, 'label' => 'Sync TrueLayer Feeds'],
                        'visual_svg' => '<svg viewBox="0 0 400 180" class="w-full h-36"><rect width="400" height="180" rx="12" fill="#064e3b"/><text x="28" y="55" fill="#ecfdf5" font-size="16" font-weight="bold">Verified Runway: 14.2 Months Solvency</text><text x="28" y="85" fill="#6ee7b7" font-size="13">£48,920.50 Liquid · £3,280 Tax Shield Auto-Provisioned</text><rect x="28" y="110" width="220" height="34" rx="8" fill="#10b981"/><text x="45" y="132" fill="#022c22" font-size="12" font-weight="bold">✓ 100% Reconciled Double-Entry</text></svg>',
                    ],
                    [
                        'id' => 'iacc_sc_3',
                        'stage' => ExplainerStoryboard::STAGE_CTA,
                        'duration' => 6.0,
                        'title' => 'Call to Action: Safeguard Your Runway in 2 Minutes',
                        'narration' => 'Take full control of your company finances. Connect your accounts securely at iaccount.test and forecast your runway today!',
                        'target_selector' => '#connect-bank-cta',
                        'camera' => ['x' => 0.5, 'y' => 0.6, 'zoom' => 1.3],
                        'cursor' => ['action' => 'click', 'targetX' => 0.5, 'targetY' => 0.6, 'label' => 'Connect UK Bank Feeds'],
                        'visual_svg' => '<svg viewBox="0 0 400 180" class="w-full h-36"><rect width="400" height="180" rx="12" fill="#1e293b"/><text x="28" y="55" fill="#f8fafc" font-size="16" font-weight="bold">Never Be Blindsided by HMRC Again</text><text x="28" y="85" fill="#cbd5e1" font-size="13">FCA Regulated Open Banking · Bank-Grade Encryption</text><rect x="28" y="110" width="240" height="42" rx="10" fill="#3b82f6"/><text x="45" y="136" fill="#ffffff" font-size="14" font-weight="bold">💳 Connect Accounts in 2 Mins</text></svg>',
                    ],
                ],
                'metadata' => ['cluster' => 'fintech_utilities', 'primary_kpi' => 'retained_active_ledgers'],
            ],
            'tenantscore' => [
                'id' => 'exp_tenantscore_passport',
                'title' => 'Verified Rent Passport, Awaab Law Damp Radar & Predictive Reliability Score',
                'app_name' => 'tenantscore',
                'aspect_ratio' => ExplainerStoryboard::ASPECT_16_9,
                'visual_format' => ExplainerStoryboard::FORMAT_MOTION_GRAPHICS,
                'theme' => 'property-indigo',
                'scenes' => [
                    [
                        'id' => 'ts_sc_1',
                        'stage' => ExplainerStoryboard::STAGE_PROBLEM,
                        'duration' => 6.0,
                        'title' => 'The Problem: Unfair Deposit Deductions & Awaab Law Damp Hazards',
                        'narration' => 'Good tenants lose deposits unfairly, while landlords face thirty-thousand-pound penalties for unresolved Awaab Law damp hazards and bitter tenancy disputes.',
                        'target_selector' => '#deposit-dispute-warning',
                        'camera' => ['x' => 0.5, 'y' => 0.4, 'zoom' => 1.2],
                        'cursor' => ['action' => 'move', 'targetX' => 0.5, 'targetY' => 0.4, 'label' => 'Review Dispute Flag'],
                        'visual_svg' => '<svg viewBox="0 0 400 180" class="w-full h-36"><rect width="400" height="180" rx="12" fill="#1e1b4b"/><text x="28" y="48" fill="#f87171" font-size="15" font-weight="bold">⚠️ £1,200 Deposit Withheld Without Evidence</text><text x="28" y="85" fill="#fca5a5" font-size="13">Unresolved Damp Notice · Awaab Law Non-Compliance</text><text x="28" y="135" fill="#f87171" font-size="14" font-weight="bold">End of Tenancy Friction: Critical</text></svg>',
                    ],
                    [
                        'id' => 'ts_sc_2',
                        'stage' => ExplainerStoryboard::STAGE_SOLUTION,
                        'duration' => 6.0,
                        'title' => 'The Solution: Verified Rent Passport & Timestamped Maintenance Radar',
                        'narration' => 'TenantScore verifies thirty-six months of on-time rent streaks into a portable passport, logging timestamped property safety checks to resolve disputes bilaterally.',
                        'target_selector' => '#tenant-score-gauge',
                        'camera' => ['x' => 0.6, 'y' => 0.5, 'zoom' => 1.4],
                        'cursor' => ['action' => 'click', 'targetX' => 0.6, 'targetY' => 0.5, 'label' => 'Verify 942/1000 Score'],
                        'visual_svg' => '<svg viewBox="0 0 400 180" class="w-full h-36"><rect width="400" height="180" rx="12" fill="#0f172a"/><circle cx="75" cy="90" r="40" fill="none" stroke="#10b981" stroke-width="8"/><text x="56" y="96" fill="#ffffff" font-size="18" font-weight="bold">942</text><text x="135" y="70" fill="#ffffff" font-size="15" font-weight="bold">Tier-1 Verified Rent Passport</text><text x="135" y="98" fill="#38bdf8" font-size="13">Zero Damp Hazards · EICR &amp; Gas Safe Certified</text></svg>',
                    ],
                    [
                        'id' => 'ts_sc_3',
                        'stage' => ExplainerStoryboard::STAGE_CTA,
                        'duration' => 5.0,
                        'title' => 'Call to Action: Claim Your Verified Rent Passport',
                        'narration' => 'Prove your reliability and protect your home. Generate your verified Rent Passport in two minutes at tenantscore.test!',
                        'target_selector' => '#get-passport-cta',
                        'camera' => ['x' => 0.5, 'y' => 0.6, 'zoom' => 1.3],
                        'cursor' => ['action' => 'click', 'targetX' => 0.5, 'targetY' => 0.6, 'label' => 'Generate Passport Free'],
                        'visual_svg' => '<svg viewBox="0 0 400 180" class="w-full h-36"><rect width="400" height="180" rx="12" fill="#064e3b"/><text x="28" y="55" fill="#ecfdf5" font-size="16" font-weight="bold">Rent With Complete Trust &amp; Transparency</text><text x="28" y="85" fill="#a7f3d0" font-size="13">Instant Verification · TDS &amp; MyDeposits Compliant</text><rect x="28" y="110" width="240" height="42" rx="10" fill="#10b981"/><text x="45" y="136" fill="#022c22" font-size="14" font-weight="bold">🏢 Claim Rent Passport Free</text></svg>',
                    ],
                ],
                'metadata' => ['cluster' => 'fintech_utilities', 'primary_kpi' => 'verified_tenancies'],
            ],
            'execmindapp' => [
                'id' => 'exp_execmind_board_brief',
                'title' => 'Executive Morning Radar, FTSE Board Briefings & Prospecting Intelligence',
                'app_name' => 'execmindapp',
                'aspect_ratio' => ExplainerStoryboard::ASPECT_16_9,
                'visual_format' => ExplainerStoryboard::FORMAT_MOTION_GRAPHICS,
                'theme' => 'board-gold',
                'scenes' => [
                    [
                        'id' => 'exec_sc_1',
                        'stage' => ExplainerStoryboard::STAGE_PROBLEM,
                        'duration' => 6.0,
                        'title' => 'The Problem: 80-Page Board Pack Information Overload',
                        'narration' => 'Executive directors drown in dense eighty-page board packs, missing critical governance red flags, security blind spots, and cash burn accelerations.',
                        'target_selector' => '#board-pack-overload',
                        'camera' => ['x' => 0.5, 'y' => 0.4, 'zoom' => 1.2],
                        'cursor' => ['action' => 'move', 'targetX' => 0.5, 'targetY' => 0.4, 'label' => 'Inspect 80-Page Pack'],
                        'visual_svg' => '<svg viewBox="0 0 400 180" class="w-full h-36"><rect width="400" height="180" rx="12" fill="#0f172a"/><text x="28" y="48" fill="#f87171" font-size="15" font-weight="bold">⚠️ Board Pack: 84 Pages of Unfiltered Fluff</text><text x="28" y="85" fill="#fca5a5" font-size="13">SecOps Risk Buried on Page 62 · Hidden Cash Burn</text><text x="28" y="135" fill="#f87171" font-size="14" font-weight="bold">Executive Blind Spot Risk: Severe</text></svg>',
                    ],
                    [
                        'id' => 'exec_sc_2',
                        'stage' => ExplainerStoryboard::STAGE_SOLUTION,
                        'duration' => 6.0,
                        'title' => 'The Solution: 5 Sovereign Executive Advisory Lenses',
                        'narration' => 'ExecMind distils raw operational data through five specialized executive lenses: CPO, CMO, SecOps, Governance, and Deloitte Audit for instant clarity.',
                        'target_selector' => '#morning-radar-card',
                        'camera' => ['x' => 0.6, 'y' => 0.5, 'zoom' => 1.4],
                        'cursor' => ['action' => 'click', 'targetX' => 0.6, 'targetY' => 0.5, 'label' => 'View 60-Second Brief'],
                        'visual_svg' => '<svg viewBox="0 0 400 180" class="w-full h-36"><rect width="400" height="180" rx="12" fill="#1e293b"/><text x="28" y="50" fill="#fbbf24" font-size="16" font-weight="bold">ExecMind Sovereign Briefing (60s Read)</text><text x="28" y="85" fill="#f8fafc" font-size="13">CPO: 92% · SecOps: 100% Sealed · Deloitte Audit: Passed</text><rect x="28" y="108" width="220" height="34" rx="8" fill="#d97706"/><text x="45" y="130" fill="#ffffff" font-size="12" font-weight="bold">✓ 3 High-Conviction Interventions</text></svg>',
                    ],
                    [
                        'id' => 'exec_sc_3',
                        'stage' => ExplainerStoryboard::STAGE_CTA,
                        'duration' => 6.0,
                        'title' => 'Call to Action: Request an Executive Briefing Demo',
                        'narration' => 'Arm your board with sovereign clarity. Book your executive briefing session at execmind.test today!',
                        'target_selector' => '#exec-demo-cta',
                        'camera' => ['x' => 0.5, 'y' => 0.6, 'zoom' => 1.3],
                        'cursor' => ['action' => 'click', 'targetX' => 0.5, 'targetY' => 0.6, 'label' => 'Schedule Briefing'],
                        'visual_svg' => '<svg viewBox="0 0 400 180" class="w-full h-36"><rect width="400" height="180" rx="12" fill="#172554"/><text x="28" y="55" fill="#93c5fd" font-size="16" font-weight="bold">Lead With Complete Strategic Conviction</text><text x="28" y="85" fill="#dbeafe" font-size="13">FTSE Governance Aligned · Zero Data Hallucination</text><rect x="28" y="110" width="240" height="42" rx="10" fill="#2563eb"/><text x="45" y="136" fill="#ffffff" font-size="14" font-weight="bold">🧠 Book Executive Briefing</text></svg>',
                    ],
                ],
                'metadata' => ['cluster' => 'social_lifestyle', 'primary_kpi' => 'executive_retention'],
            ],
            'loaneasyfinance' => [
                'id' => 'exp_loaneasy_underwriting',
                'title' => 'FCA CONC 5.2A Instant Underwriting, APR Truth Radar & Digital Loan Contract',
                'app_name' => 'loaneasyfinance',
                'aspect_ratio' => ExplainerStoryboard::ASPECT_16_9,
                'visual_format' => ExplainerStoryboard::FORMAT_MOTION_GRAPHICS,
                'theme' => 'fintech-navy',
                'scenes' => [
                    [
                        'id' => 'loan_sc_1',
                        'stage' => ExplainerStoryboard::STAGE_PROBLEM,
                        'duration' => 6.0,
                        'title' => 'The Problem: 3-Week Bank Delays & Opaque Rates',
                        'narration' => 'SMEs wait three stressful weeks for High Street bank loan decisions, buried in paperwork only to be hit with punitive hidden borrowing costs.',
                        'target_selector' => '#bank-delay-indicator',
                        'camera' => ['x' => 0.5, 'y' => 0.4, 'zoom' => 1.2],
                        'cursor' => ['action' => 'move', 'targetX' => 0.5, 'targetY' => 0.4, 'label' => 'Pending: Day 21'],
                        'visual_svg' => '<svg viewBox="0 0 400 180" class="w-full h-36"><rect width="400" height="180" rx="12" fill="#0f172a"/><text x="28" y="48" fill="#f87171" font-size="15" font-weight="bold">⚠️ High Street Bank: 21 Days and Counting...</text><text x="28" y="85" fill="#fca5a5" font-size="13">42 Paper Forms Required · 28.4% Effective APR Shock</text><text x="28" y="135" fill="#f87171" font-size="14" font-weight="bold">Business Capital Stalled</text></svg>',
                    ],
                    [
                        'id' => 'loan_sc_2',
                        'stage' => ExplainerStoryboard::STAGE_SOLUTION,
                        'duration' => 6.0,
                        'title' => 'The Solution: 4-Minute Algorithmic Underwriting (FCA CONC 5.2A)',
                        'narration' => 'LoanEasy automates affordability and credit risk in under four minutes, issuing compliant digital agreements with instant Faster Payments funding.',
                        'target_selector' => '#run-underwriting-btn',
                        'camera' => ['x' => 0.6, 'y' => 0.5, 'zoom' => 1.4],
                        'cursor' => ['action' => 'click', 'targetX' => 0.6, 'targetY' => 0.5, 'label' => 'Approve £50,000 Facility'],
                        'visual_svg' => '<svg viewBox="0 0 400 180" class="w-full h-36"><rect width="400" height="180" rx="12" fill="#064e3b"/><text x="28" y="55" fill="#ecfdf5" font-size="16" font-weight="bold">Approved: £50,000 Capital Facility in 3m 48s</text><text x="28" y="85" fill="#6ee7b7" font-size="13">Transparent 11.9% APR · £0 Early Repayment Penalty</text><rect x="28" y="110" width="220" height="34" rx="8" fill="#10b981"/><text x="45" y="132" fill="#022c22" font-size="12" font-weight="bold">✓ FCA CONC 5.2A Certified</text></svg>',
                    ],
                    [
                        'id' => 'loan_sc_3',
                        'stage' => ExplainerStoryboard::STAGE_CTA,
                        'duration' => 6.0,
                        'title' => 'Call to Action: Check Eligibility With Zero Credit Impact',
                        'narration' => 'Fund your business growth today. Check your borrowing eligibility in under three minutes with zero credit impact at loaneasy.test!',
                        'target_selector' => '#check-eligibility-cta',
                        'camera' => ['x' => 0.5, 'y' => 0.6, 'zoom' => 1.3],
                        'cursor' => ['action' => 'click', 'targetX' => 0.5, 'targetY' => 0.6, 'label' => 'Check Eligibility Free'],
                        'visual_svg' => '<svg viewBox="0 0 400 180" class="w-full h-36"><rect width="400" height="180" rx="12" fill="#1e1b4b"/><text x="28" y="55" fill="#ffffff" font-size="16" font-weight="bold">Unlock Working Capital in Under 4 Minutes</text><text x="28" y="85" fill="#a5b4fc" font-size="13">Soft Search Only · No Credit Footprint Recorded</text><rect x="28" y="110" width="240" height="42" rx="10" fill="#6366f1"/><text x="45" y="136" fill="#ffffff" font-size="14" font-weight="bold">🏦 Check Eligibility Free</text></svg>',
                    ],
                ],
                'metadata' => ['cluster' => 'fintech_utilities', 'primary_kpi' => 'underwriting_conversion'],
            ],
            'bcp' => [
                'id' => 'exp_bcp_cqc_reg19',
                'title' => 'CQC Regulation 19 SCR Hard-Stop, 1-Click Shift Fill & Carer Passport',
                'app_name' => 'bcp',
                'aspect_ratio' => ExplainerStoryboard::ASPECT_16_9,
                'visual_format' => ExplainerStoryboard::FORMAT_MOTION_GRAPHICS,
                'theme' => 'healthcare-teal',
                'scenes' => [
                    [
                        'id' => 'bcp_sc_1',
                        'stage' => ExplainerStoryboard::STAGE_PROBLEM,
                        'duration' => 6.0,
                        'title' => 'The Problem: CQC Regulation 19 Compliance Breaches',
                        'narration' => 'Care homes risk fifty-thousand-pound CQC fines and emergency closure notices when agency carers arrive without verified training or Enhanced DBS checks.',
                        'target_selector' => '#cqc-warning-card',
                        'camera' => ['x' => 0.5, 'y' => 0.4, 'zoom' => 1.2],
                        'cursor' => ['action' => 'move', 'targetX' => 0.5, 'targetY' => 0.4, 'label' => 'Inspect CQC Violation'],
                        'visual_svg' => '<svg viewBox="0 0 400 180" class="w-full h-36"><rect width="400" height="180" rx="12" fill="#042f2e"/><text x="28" y="48" fill="#f87171" font-size="15" font-weight="bold">⚠️ CQC Warning: Carer Training Certificate Expired</text><text x="28" y="85" fill="#fca5a5" font-size="13">Regulation 19 Breach · 07:00 Morning Shift Unfilled</text><text x="28" y="135" fill="#f87171" font-size="14" font-weight="bold">CQC Single Assessment Rating: At Risk</text></svg>',
                    ],
                    [
                        'id' => 'bcp_sc_2',
                        'stage' => ExplainerStoryboard::STAGE_SOLUTION,
                        'duration' => 6.0,
                        'title' => 'The Solution: SCR Hard-Stop & 1-Click Shift Fill',
                        'narration' => 'BCP locks every carer to verified DBS and CSTF training records, filling 07:00 shifts in one click with tamper-proof HMAC audit trails for CQC inspectors.',
                        'target_selector' => '#shift-claim-btn',
                        'camera' => ['x' => 0.6, 'y' => 0.5, 'zoom' => 1.4],
                        'cursor' => ['action' => 'click', 'targetX' => 0.6, 'targetY' => 0.5, 'label' => 'Confirm Preferred Carer'],
                        'visual_svg' => '<svg viewBox="0 0 400 180" class="w-full h-36"><rect width="400" height="180" rx="12" fill="#065f46"/><text x="28" y="55" fill="#ecfdf5" font-size="16" font-weight="bold">Aria Care Home: 07:00 Shift Confirmed</text><text x="28" y="85" fill="#a7f3d0" font-size="13">CQC Reg 19 &amp; 18 100% Certified · HMAC Sealed Audit</text><rect x="28" y="110" width="220" height="34" rx="8" fill="#10b981"/><text x="45" y="132" fill="#022c22" font-size="12" font-weight="bold">✓ 99.2% Continuity Match</text></svg>',
                    ],
                    [
                        'id' => 'bcp_sc_3',
                        'stage' => ExplainerStoryboard::STAGE_CTA,
                        'duration' => 6.0,
                        'title' => 'Call to Action: Secure Your Preferred Carer Pool',
                        'narration' => 'Demolish agency friction and guarantee 100% CQC compliance. Book your verified carer pool today at bestcareprofessionals.test!',
                        'target_selector' => '#book-carer-pool-cta',
                        'camera' => ['x' => 0.5, 'y' => 0.6, 'zoom' => 1.3],
                        'cursor' => ['action' => 'click', 'targetX' => 0.5, 'targetY' => 0.6, 'label' => 'Book Carer Pool'],
                        'visual_svg' => '<svg viewBox="0 0 400 180" class="w-full h-36"><rect width="400" height="180" rx="12" fill="#0f172a"/><text x="28" y="55" fill="#5eead4" font-size="16" font-weight="bold">Zero Agency Hassle · 100% Inspection-Ready</text><text x="28" y="85" fill="#ccfbf1" font-size="13">Caldicott Principle 5 Verified · 1-Click Rota Fill</text><rect x="28" y="110" width="240" height="42" rx="10" fill="#0d9488"/><text x="45" y="136" fill="#ffffff" font-size="14" font-weight="bold">🏥 Book Carer Pool Free</text></svg>',
                    ],
                ],
                'metadata' => ['cluster' => 'healthcare', 'primary_kpi' => 'shift_fill_rate'],
            ],
        ];
    }

    /**
     * Builds a validated ExplainerStoryboard for a given portfolio application,
     * optionally applying sanitised scene, title, or visual format overrides.
     *
     * @param string $appKey
     * @param array<string, mixed> $overrides
     * @return ExplainerStoryboard
     */
    public static function buildStoryboard(string $appKey, array $overrides = []): ExplainerStoryboard
    {
        $normalised = self::normaliseAppKey($appKey);
        $catalog = self::getMasterCatalog();
        $blueprint = $catalog[$normalised];

        if (!empty($overrides['title']) && is_string($overrides['title'])) {
            $blueprint['title'] = ExplainerSecurityValidator::validateSafeText($overrides['title'], 160);
        }

        if (!empty($overrides['aspect_ratio']) && is_string($overrides['aspect_ratio'])) {
            $blueprint['aspect_ratio'] = $overrides['aspect_ratio'];
        }

        if (!empty($overrides['visual_format']) && is_string($overrides['visual_format'])) {
            $blueprint['visual_format'] = $overrides['visual_format'];
        }

        if (!empty($overrides['scenes']) && is_array($overrides['scenes'])) {
            $blueprint['scenes'] = $overrides['scenes'];
        }

        return ExplainerStoryboard::fromArray($blueprint);
    }

    /**
     * Generates the complete sovereign explainer payload (Storyboard + 60fps WebCodecs Manifest +
     * WebVTT/SRT Subtitles + 4-State Interactive HTML5 Widget) for any portfolio app.
     *
     * @param string $appKey
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    public static function renderAppExplainer(string $appKey, array $overrides = []): array
    {
        $normalised = self::normaliseAppKey($appKey);
        $storyboard = self::buildStoryboard($normalised, $overrides);
        $renderer = new SovereignVideoRenderer();

        $manifest = $renderer->generateClientManifest($storyboard);
        $widgetHtml = $renderer->renderInteractiveWidget($storyboard);

        return [
            'success' => true,
            'app' => $normalised,
            'engine' => 'ExplainerCore (SovereignVideoRenderer + KineticMotionCompiler 60fps)',
            'capsule_id' => $storyboard->getId(),
            'title' => $storyboard->getTitle(),
            'aspect_ratio' => $storyboard->getAspectRatio(),
            'visual_format' => $storyboard->getVisualFormat(),
            'narrative_arc' => $storyboard->getNarrativeArc(),
            'has_complete_narrative_arc' => $storyboard->hasCompleteNarrativeArc(),
            'total_duration' => $storyboard->getTotalDurationSeconds(),
            'total_frames' => $manifest['timeline']['total_frames'],
            'client_manifest' => $manifest,
            'widget_html' => $widgetHtml,
        ];
    }

    /**
     * Renders a self-contained, zero-dependency Admin Video Explainer Studio section
     * for embedding inside the UMAC / SuperAdmin Executive Command Centre across all 8 apps.
     * Includes live Application switching, Visual Format switching (Motion Graphics, Whiteboard,
     * 2D/3D Animation, Mixed Media), Narrative Arc tracking, and Client GPU WebM export.
     *
     * @param string $defaultAppKey
     * @return string HTML + Vanilla JS block
     */
    public static function renderAdminStudioSection(string $defaultAppKey = 'partyplatform'): string
    {
        // World Engine studio (explainers + posters) supersedes the legacy capsule.
        // Append ?legacy_explainer=1 to the dashboard URL to see the previous studio.
        if (($_GET['legacy_explainer'] ?? '') !== '1' && class_exists(\Src\WorldEngine\WorldStudio::class)) {
            return \Src\WorldEngine\WorldStudio::renderSection($defaultAppKey);
        }

        try {
            $activeKey = self::normaliseAppKey($defaultAppKey);
        } catch (\Throwable) {
            $activeKey = 'partyplatform';
        }

        $appLabels = [
            'partyplatform' => '🎉 PartyPlatform (9:16 Viral Reel)',
            'familyplatform' => '🏡 FamilyPlatform (16:9 Kinship Hub)',
            'idecide' => '⚖️ iDecide (16:9 Monte Carlo Arena)',
            'iaccountapp' => '💳 iAccountApp (16:9 HMRC & Ledger)',
            'tenantscore' => '🏢 TenantScore (16:9 Rent Passport)',
            'execmindapp' => '🧠 ExecMindApp (16:9 Board Radar)',
            'loaneasyfinance' => '🏦 LoanEasyFinance (16:9 FCA CONC 5.2A)',
            'bcp' => '🏥 Best Care Pro / BCP (16:9 CQC Reg 19)',
        ];

        $formats = [
            ExplainerStoryboard::FORMAT_MOTION_GRAPHICS => '🎨 Motion Graphics (Kinetic Typography & Shapes)',
            ExplainerStoryboard::FORMAT_WHITEBOARD => '✏️ Whiteboard Animation (Hand-Drawn Sketch Canvas)',
            ExplainerStoryboard::FORMAT_ANIMATION_2D_3D => '✨ 2D & 3D Animation (Isometric Software UI & Motion)',
            ExplainerStoryboard::FORMAT_MIXED_MEDIA => '🎥 Live-Action & Mixed Media (Device Mockups & Video Frame)',
        ];

        $allBundles = [];
        $appOptionsHtml = '';
        foreach ($appLabels as $key => $label) {
            $selectedAttr = $key === $activeKey ? ' selected' : '';
            $appOptionsHtml .= sprintf(
                '<option value="%s"%s>%s</option>',
                htmlspecialchars($key, ENT_QUOTES, 'UTF-8'),
                $selectedAttr,
                htmlspecialchars($label, ENT_QUOTES, 'UTF-8')
            );

            // Generate bundle for the active format
            $allBundles[$key] = self::renderAppExplainer($key);
        }

        $formatOptionsHtml = '';
        foreach ($formats as $fKey => $fLabel) {
            $formatOptionsHtml .= sprintf(
                '<option value="%s">%s</option>',
                htmlspecialchars($fKey, ENT_QUOTES, 'UTF-8'),
                htmlspecialchars($fLabel, ENT_QUOTES, 'UTF-8')
            );
        }

        $activeBundle = $allBundles[$activeKey];
        $bundlesJson = json_encode($allBundles, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?: '{}';

        return sprintf(
            '<section id="atg-admin-explainer-studio" class="mb-10 glass-panel rounded-2xl p-6 border border-indigo-500/20" style="background:rgba(15,23,42,0.85); border:1px solid rgba(99,102,241,0.25); border-radius:1rem; padding:1.5rem; margin-bottom:2rem;">
                <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 pb-5 mb-6 border-b border-slate-800">
                    <div>
                        <div class="flex flex-wrap items-center gap-2 mb-1.5">
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold uppercase tracking-wide bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">ATG Sovereign ExplainerCore</span>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">Admin-Only Studio · £0 SaaS Cost</span>
                            <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold bg-purple-500/20 text-purple-300 border border-purple-500/30">3-Part Narrative Arc Enforced</span>
                        </div>
                        <h2 class="text-xl font-bold text-white tracking-tight">In-House 60-90s Video Explainer Studio</h2>
                        <p class="text-sm text-slate-400">Simplify complex features with the classic narrative arc (The Problem ➔ The Solution ➔ Call to Action) across 4 selectable visual formats.</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <div class="flex items-center gap-1.5">
                            <label for="atg-admin-app-select" class="text-xs font-semibold text-slate-300 uppercase tracking-wide">App:</label>
                            <select id="atg-admin-app-select" class="min-h-[44px] px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-slate-100 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                %s
                            </select>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <label for="atg-admin-format-select" class="text-xs font-semibold text-slate-300 uppercase tracking-wide">Style:</label>
                            <select id="atg-admin-format-select" class="min-h-[44px] px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-slate-100 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                %s
                            </select>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <label for="atg-admin-voice-select" class="text-xs font-semibold text-slate-300 uppercase tracking-wide">Voice:</label>
                            <select id="atg-admin-voice-select" class="min-h-[44px] px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-slate-100 text-xs font-semibold focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                <option value="uk_male">👨 UK Male (Oliver / Daniel)</option>
                                <option value="uk_female">👩 UK Female (Serena / Stephanie)</option>
                                <option value="upbeat">⚡ Upbeat Social Reel</option>
                                <option value="deep">🎧 Deep Documentary</option>
                            </select>
                        </div>
                        <button type="button" id="atg-admin-vtt-btn" class="min-h-[44px] min-w-[44px] px-3.5 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-200 text-xs font-semibold whitespace-nowrap flex-shrink-0 transition-all flex items-center gap-1.5">
                            <span>📄</span>
                            <span>Subtitles (.vtt)</span>
                        </button>
                    </div>
                </div>
                <div id="atg-admin-explainer-mount">%s</div>
                <script>
                (function() {
                    const bundles = %s;
                    let currentApp = %s;
                    let currentFormat = "motion_graphics";
                    const selectEl = document.getElementById("atg-admin-app-select");
                    const formatSelectEl = document.getElementById("atg-admin-format-select");
                    const mountEl = document.getElementById("atg-admin-explainer-mount");
                    const vttBtn = document.getElementById("atg-admin-vtt-btn");

                    function bindCapsule() {
                        if (!mountEl) return;
                        const container = mountEl.querySelector(".atg-explainer-capsule");
                        if (!container) return;
                        const tabs = container.querySelectorAll(".atg-scene-tab");
                        const cards = container.querySelectorAll(".atg-scene-card");
                        const arcNodes = container.querySelectorAll(".atg-arc-node");
                        const stepNumEl = container.querySelector(".atg-active-step-num");

                        function speakText(text, callback) {
                            if (!("speechSynthesis" in window)) {
                                if (callback) setTimeout(callback, 3500);
                                return;
                            }
                            window.speechSynthesis.cancel();
                            const u = new SpeechSynthesisUtterance(text);
                            u.lang = "en-GB";
                            u.rate = 1.0;
                            const voices = window.speechSynthesis.getVoices();
                            const gbVoice = voices.find(v => v.lang === "en-GB" || v.lang.startsWith("en_GB"))
                                         || voices.find(v => v.lang.startsWith("en"));
                            if (gbVoice) u.voice = gbVoice;
                            u.onend = () => { if (callback) callback(); };
                            u.onerror = () => { if (callback) callback(); };
                            window.speechSynthesis.speak(u);
                        }

                        function switchScene(idx, playAudio = false) {
                            tabs.forEach((t, i) => {
                                t.classList.toggle("bg-indigo-600", i === idx);
                                t.classList.toggle("text-white", i === idx);
                                t.classList.toggle("font-semibold", i === idx);
                                t.classList.toggle("shadow-md", i === idx);
                                t.classList.toggle("bg-slate-800", i !== idx);
                                t.classList.toggle("text-slate-200", i !== idx);
                            });
                            cards.forEach((c, i) => {
                                c.classList.toggle("hidden", i !== idx);
                                c.classList.toggle("block", i === idx);
                            });
                            arcNodes.forEach((node, i) => {
                                if (i === idx) {
                                    node.className = "atg-arc-node p-2 rounded-lg font-semibold transition-all " +
                                        (i === 0 ? "bg-rose-500/20 border border-rose-500/50 text-rose-200 shadow-sm" :
                                         i === 1 ? "bg-indigo-500/20 border border-indigo-500/50 text-indigo-200 shadow-sm" :
                                                   "bg-emerald-500/20 border border-emerald-500/50 text-emerald-200 shadow-sm");
                                } else {
                                    node.className = "atg-arc-node p-2 rounded-lg bg-slate-800/40 border border-slate-700/40 text-slate-400 font-medium transition-all opacity-70";
                                }
                            });
                            if (stepNumEl) stepNumEl.textContent = (idx + 1).toString();

                            if (playAudio && cards[idx]) {
                                const p = cards[idx].querySelector("p");
                                if (p) speakText(p.textContent, null);
                            }
                        }

                        tabs.forEach((tab) => {
                            tab.addEventListener("click", () => switchScene(parseInt(tab.getAttribute("data-scene-index") || "0", 10), true));
                        });

                        const playBtn = container.querySelector(".atg-play-btn");
                        if (playBtn) {
                            let isPlayingTour = false;

                            playBtn.addEventListener("click", () => {
                                const label = playBtn.querySelector(".atg-btn-label");
                                const spin = playBtn.querySelector(".atg-btn-spinner");

                                if (isPlayingTour) {
                                    isPlayingTour = false;
                                    if ("speechSynthesis" in window) window.speechSynthesis.cancel();
                                    if (label) label.textContent = "▶ Play 60fps Tour with Voice";
                                    if (spin) spin.classList.add("hidden");
                                    return;
                                }

                                isPlayingTour = true;
                                if (label) label.textContent = "Playing Tour with Voice...";
                                if (spin) spin.classList.remove("hidden");

                                let step = 0;
                                function stepTour() {
                                    if (!isPlayingTour) return;
                                    if (step >= tabs.length) {
                                        isPlayingTour = false;
                                        switchScene(0, false);
                                        if (label) label.textContent = "▶ Play 60fps Tour with Voice";
                                        if (spin) spin.classList.add("hidden");
                                        return;
                                    }

                                    switchScene(step, false);
                                    const cardP = cards[step] ? cards[step].querySelector("p") : null;
                                    const text = cardP ? cardP.textContent : "";

                                    speakText(text, () => {
                                        if (!isPlayingTour) return;
                                        step++;
                                        setTimeout(stepTour, 600); // 600ms breath pause between scenes
                                    });
                                }

                                stepTour();
                            });
                        }

                        const exportBtn = container.querySelector(".atg-export-btn");
                        if (exportBtn) {
                            exportBtn.addEventListener("click", () => {
                                exportBtn.disabled = true;
                                const label = exportBtn.querySelector(".atg-btn-label");
                                const spin = exportBtn.querySelector(".atg-btn-spinner");
                                if (label) label.textContent = "Encoding 60fps WebM...";
                                if (spin) spin.classList.remove("hidden");

                                const bundle = bundles[currentApp] || {};
                                const dims = (bundle.client_manifest && bundle.client_manifest.timeline && bundle.client_manifest.timeline.dimensions) || {width: 1920, height: 1080};
                                const canvas = document.createElement("canvas");
                                canvas.width = dims.width;
                                canvas.height = dims.height;
                                const ctx = canvas.getContext("2d");
                                const stream = canvas.captureStream(60);
                                const recorder = new MediaRecorder(stream, { mimeType: "video/webm" });
                                const chunks = [];
                                recorder.ondataavailable = (e) => { if (e.data && e.data.size > 0) chunks.push(e.data); };
                                recorder.onstop = () => {
                                    const blob = new Blob(chunks, { type: "video/webm" });
                                    const a = document.createElement("a");
                                    a.href = URL.createObjectURL(blob);
                                    a.download = (bundle.capsule_id || "sovereign-explainer") + "-" + currentFormat + ".webm";
                                    a.click();
                                    exportBtn.disabled = false;
                                    if (label) label.textContent = "⬇ Export WebM (Client GPU)";
                                    if (spin) spin.classList.add("hidden");
                                };
                                recorder.start();
                                const start = performance.now();
                                const isWhiteboard = currentFormat === "whiteboard";
                                const draw = (now) => {
                                    const elapsed = (now - start) / 1000;
                                    if (elapsed >= 2.5) { recorder.stop(); return; }
                                    
                                    // Background styling by format
                                    ctx.fillStyle = isWhiteboard ? "#ffffff" : "#0b0f19";
                                    ctx.fillRect(0, 0, canvas.width, canvas.height);

                                    if (isWhiteboard) {
                                        ctx.strokeStyle = "#cbd5e1";
                                        ctx.lineWidth = 12;
                                        ctx.strokeRect(30, 30, canvas.width - 60, canvas.height - 60);
                                        ctx.fillStyle = "#0f172a";
                                        ctx.font = "bold 44px sans-serif";
                                        ctx.fillText("✏️ " + (bundle.title || "ATG Explainer Video"), 80, 160);
                                        ctx.fillStyle = "#e11d48";
                                        ctx.font = "bold 32px sans-serif";
                                        ctx.fillText("1. The Problem ➔ 2. The Solution ➔ 3. Call to Action", 80, 240);
                                        ctx.fillStyle = "#475569";
                                        ctx.font = "28px monospace";
                                        ctx.fillText("Whiteboard Animation Mode · 60fps GPU Frame " + Math.floor(elapsed * 60), 80, 320);
                                    } else {
                                        ctx.fillStyle = "#6366f1";
                                        ctx.font = "bold 44px sans-serif";
                                        ctx.fillText("🎨 " + (bundle.title || "ATG Explainer Video"), 80, 160);
                                        ctx.fillStyle = "#10b981";
                                        ctx.font = "bold 32px sans-serif";
                                        ctx.fillText("1. The Problem ➔ 2. The Solution ➔ 3. Call to Action", 80, 240);
                                        ctx.fillStyle = "#94a3b8";
                                        ctx.font = "28px monospace";
                                        ctx.fillText("Motion Graphics Mode · 60fps GPU Frame " + Math.floor(elapsed * 60), 80, 320);
                                    }

                                    requestAnimationFrame(draw);
                                };
                                requestAnimationFrame(draw);
                            });
                        }
                    }

                    if (selectEl) {
                        selectEl.addEventListener("change", () => {
                            currentApp = selectEl.value;
                            if (bundles[currentApp] && mountEl) {
                                mountEl.innerHTML = bundles[currentApp].widget_html;
                                bindCapsule();
                            }
                        });
                    }

                    if (formatSelectEl) {
                        formatSelectEl.addEventListener("change", () => {
                            currentFormat = formatSelectEl.value;
                            if (bundles[currentApp] && mountEl) {
                                // Apply format attribute to container
                                const capsule = mountEl.querySelector(".atg-explainer-capsule");
                                if (capsule) {
                                    capsule.setAttribute("data-format", currentFormat);
                                    const formatSpan = capsule.querySelector(".atg-format-label");
                                    if (formatSpan) formatSpan.textContent = formatSelectEl.options[formatSelectEl.selectedIndex].text;
                                }
                            }
                        });
                    }

                    if (vttBtn) {
                        vttBtn.addEventListener("click", () => {
                            const bundle = bundles[currentApp] || {};
                            const vtt = (bundle.client_manifest && bundle.client_manifest.subtitles_vtt) || "WEBVTT\n";
                            const blob = new Blob([vtt], { type: "text/vtt;charset=utf-8" });
                            const a = document.createElement("a");
                            a.href = URL.createObjectURL(blob);
                            a.download = (bundle.capsule_id || "subtitles") + ".vtt";
                            a.click();
                        });
                    }

                    bindCapsule();
                })();
                </script>
            </section>',
            $appOptionsHtml,
            $formatOptionsHtml,
            $activeBundle['widget_html'],
            $bundlesJson,
            json_encode($activeKey)
        );
    }
}