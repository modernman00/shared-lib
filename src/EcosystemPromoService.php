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
            'tagline' => '60-Second Multi-Model Decision Engine',
            'description' => 'Clear, simple guides to help you make tough life, money, job, and family choices in under 60 seconds. Backed by structured multi-model scoring so you never second-guess big decisions.',
            'cta_text' => 'Make a Decision',
            'badge' => 'DECISION HELPER',
            'accent_color' => '#0ea5e9', // Cyan
            'gradient' => 'linear-gradient(135deg, #0ea5e9 0%, #38bdf8 100%)',
            'icon' => 'bi-compass-fill',
            'default_url' => 'https://idecideapp.com',
            'env_key' => 'IDECIDE_APP_URL',
            'logo_url' => '/public/images/ecosystem/idecide.png',
            'value_pills' => ['🧭 60-Second Clarity Matrix', '⚖️ Bias-Free Option Scoring', '👨‍👩‍👧‍👦 Family & Career Choices', '✨ Free Instant Verdict'],
            'trigger_keywords' => ['decision', 'choices', 'decide', 'overthinking', 'options', 'compare', 'matrix', 'dilemma', 'clarity', 'co_host'],
            'svg_icon' => '<svg viewBox="0 0 512 512" width="32" height="32" class="pp-brand-logo-svg" aria-label="iDecide Logo" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="cyanGrad_idecide_ico" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#0ea5e9"/><stop offset="100%" stop-color="#38bdf8"/></linearGradient></defs><rect width="512" height="512" rx="112" fill="#092B68"/><circle cx="256" cy="105" r="43" fill="#FFFFFF"/><path d="M230 174 C183 178 144 201 111 236 L75 199 L75 320 L196 320 L158 282 C183 255 211 241 242 239" fill="none" stroke="#E6F7FA" stroke-width="48" stroke-linecap="round" stroke-linejoin="round"/><path d="M282 239 C313 241 341 255 366 282 L328 320 L449 320 L449 199 L413 236 C380 201 341 178 294 174" fill="none" stroke="url(#cyanGrad_idecide_ico)" stroke-width="48" stroke-linecap="round" stroke-linejoin="round"/><path d="M183 300 L235 351 L344 235" fill="none" stroke="#FFFFFF" stroke-width="36" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        ],
        'iaccount' => [
            'name' => 'iAccountApp',
            'headline' => 'Your Money Made Crystal Clear.',
            'tagline' => 'Instant Cash-Flow & Wealth Intelligence',
            'description' => 'Drop in any bank statement to uncover hidden subscription leaks, calculate your exact cash runway, and forecast future family or business wealth in under 60 seconds.',
            'cta_text' => 'Master Cash Flow',
            'badge' => 'FINANCIAL INTELLIGENCE',
            'accent_color' => '#2563eb', // Royal Blue
            'gradient' => 'linear-gradient(135deg, #2563eb 0%, #38bdf8 100%)',
            'icon' => 'bi-wallet2',
            'default_url' => 'https://iaccountapp.com',
            'env_key' => 'IACCOUNT_APP_URL',
            'logo_url' => '/public/images/ecosystem/iaccount.png',
            'value_pills' => ['📊 60-Second Bank Audit', '💸 Spot Hidden Cash Leaks', '📈 Live Runway & Wealth Score', '🔒 Bank-Grade Privacy'],
            'trigger_keywords' => ['budget', 'expenses', 'invoice', 'receipts', 'cost', 'money', 'split', 'accounting', 'cashflow', 'wealth'],
            'svg_icon' => '<svg viewBox="0 0 512 512" width="32" height="32" class="pp-brand-logo-svg" fill="none" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="iacc_grad" x1="0" y1="512" x2="512" y2="0" gradientUnits="userSpaceOnUse"><stop stop-color="#002984"/><stop offset="0.55" stop-color="#0267DF"/><stop offset="1" stop-color="#00B4FF"/></linearGradient><linearGradient id="iacc_teal" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#4DF8C8"/><stop offset="1" stop-color="#00D2B8"/></linearGradient></defs><rect width="512" height="512" rx="112" fill="url(#iacc_grad)"/><circle cx="148" cy="126" r="52" fill="url(#iacc_teal)"/><rect x="96" y="196" width="104" height="232" rx="48" fill="url(#iacc_teal)"/><path d="M214 220 L246 154 C258 130 292 128 308 154 L412 362 C424 388 404 426 372 428 L318 428 C298 428 282 414 274 396 L206 254 C200 242 204 230 214 220 Z" fill="#FFFFFF"/></svg>',
        ],
        'familyplatform' => [
            'name' => 'FamilyPlatform',
            'headline' => 'Private Family Social Network & Living Heritage Sanctuary',
            'tagline' => '100% Ad-Free Private Family Haven',
            'description' => 'Unite generations in a secure, invitation-only family sanctuary — complete with interactive family trees, sealed time capsules, living voice heirlooms, and milestone journals.',
            'cta_text' => 'Explore Family Haven',
            'badge' => 'PRIVATE VAULT',
            'accent_color' => '#a31621', // Crimson
            'gradient' => 'linear-gradient(135deg, #b8202c 0%, #8a1019 100%)',
            'icon' => 'bi-people-fill',
            'default_url' => 'https://myfamilyplatform.com',
            'env_key' => 'FAMILYPLATFORM_APP_URL',
            'logo_url' => '/public/images/ecosystem/familyplatform.png',
            'value_pills' => ['🌳 Interactive Family Tree', '⏳ Sealed Time Capsules', '🎙️ Voice & Video Heirlooms', '🛡️ 100% Private & Ad-Free'],
            'trigger_keywords' => ['family', 'kids', 'birthday', 'anniversary', 'milestone', 'memories', 'vault', 'heritage', 'chores'],
            'svg_icon' => '<svg viewBox="0 0 512 512" width="32" height="32" class="pp-brand-logo-svg" fill="none" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="fam_crimson_grad" x1="0" y1="0" x2="512" y2="512" gradientUnits="userSpaceOnUse"><stop stop-color="#b8202c"/><stop offset="1" stop-color="#8a1019"/></linearGradient></defs><rect width="512" height="512" rx="112" fill="url(#fam_crimson_grad)"/><text x="256" y="305" text-anchor="middle" fill="#ffffff" font-family="Pacifico, Georgia, serif" font-style="italic" font-weight="700" font-size="168">family</text></svg>',
        ],
        'scoretenant' => [
            'name' => 'scoreTenant',
            'headline' => 'Turn On-Time Rent Into Your Ultimate Property Passport.',
            'tagline' => 'Verified UK Tenant Trust Index & Instant References',
            'description' => 'Stand out above 20+ applicants for your next home. Build a portable, verified rental track record and instant landlord reference pack that top UK letting agents trust.',
            'cta_text' => 'Build Your Trust Score',
            'badge' => 'RENTAL REPUTATION',
            'accent_color' => '#10B981', // Emerald
            'gradient' => 'linear-gradient(135deg, #064E3B 0%, #10B981 100%)',
            'icon' => 'bi-shield-check',
            'default_url' => 'https://scoretenant.com',
            'env_key' => 'SCORE_TENANT_APP_URL',
            'logo_url' => '',
            'value_pills' => ['🏆 Beat Rental Competition', '⚡ Instant Landlord Pack', '🛡️ Verified Rent Track Record', '🇬🇧 UK Tenancy Ready'],
            'trigger_keywords' => ['house_party', 'neighbour', 'flat', 'apartment', 'noise', 'landlord', 'tenant', 'rental', 'lease'],
            'svg_icon' => '<svg viewBox="0 0 512 512" width="32" height="32" class="pp-brand-logo-svg" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="em_grad" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#34D399"/><stop offset="100%" stop-color="#059669"/></linearGradient><linearGradient id="gd_grad" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#FDE047"/><stop offset="100%" stop-color="#D97706"/></linearGradient></defs><rect width="512" height="512" rx="112" fill="#064E3B"/><path d="M256 95 L365 145 C365 270 256 370 256 398 C256 370 147 270 147 145 Z" fill="none" stroke="url(#em_grad)" stroke-width="26" stroke-linejoin="round"/><path d="M190 270 A 80 80 0 1 1 322 270" fill="none" stroke="url(#gd_grad)" stroke-width="30" stroke-linecap="round"/><path d="M210 245 L245 280 L315 195" fill="none" stroke="#FFFFFF" stroke-width="36" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        ],
        'execmind' => [
            'name' => 'ExecMind',
            'headline' => 'Walk Into Every Board & Leadership Meeting Ready to Win.',
            'tagline' => 'AI Chief of Staff for Founders, Directors & Boards',
            'description' => 'Transform meeting transcripts into tracked board resolutions, owner accountability alerts, and a razor-sharp 2-minute daily executive market briefing.',
            'cta_text' => 'Try ExecMind Free',
            'badge' => 'EXECUTIVE OS',
            'accent_color' => '#8b5cf6', // Violet
            'gradient' => 'linear-gradient(135deg, #4f46e5 0%, #06b6d4 100%)',
            'icon' => 'bi-lightbulb-fill',
            'default_url' => 'https://execmindapp.com',
            'env_key' => 'EXECMIND_APP_URL',
            'logo_url' => '/public/images/ecosystem/execmind.png',
            'value_pills' => ['📋 Auto-Tracked Board Actions', '📈 2-Min Daily Market Brief', '🎯 Owner Accountability Radar', '⚡ 1-Click Board Pack'],
            'trigger_keywords' => ['corporate', 'retreat', 'exec', 'leadership', 'strategy', 'conference', 'gala', 'advisor', 'executive'],
            'svg_icon' => '<svg viewBox="0 0 512 512" width="32" height="32" class="pp-brand-logo-svg" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="cy_exec" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#38BDF8"/><stop offset="100%" stop-color="#0284C7"/></linearGradient><linearGradient id="gd_exec" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#FDE68A"/><stop offset="100%" stop-color="#F59E0B"/></linearGradient></defs><rect width="512" height="512" rx="112" fill="#0F172A"/><polygon points="256,90 385,165 385,315 256,390 127,315 127,165" fill="none" stroke="url(#cy_exec)" stroke-width="24" stroke-linejoin="round"/><polygon points="256,160 326,240 256,320 186,240" fill="url(#gd_exec)"/><circle cx="256" cy="240" r="32" fill="#FFFFFF"/></svg>',
        ],
        'loaneasyfinance' => [
            'name' => 'LoanEasy Finance',
            'headline' => 'Fast, Fair UK Short-Term Funding Without Hidden Surprises.',
            'tagline' => 'FCA Authorised Direct Lender • £750 to £3,000',
            'description' => 'Spread unexpected household, car, or celebration expenses over up to 6 months with same-day affordability decisions, fixed transparent terms, and £0 early-exit fees.',
            'cta_text' => 'Check Your Rate Now',
            'badge' => 'FCA REGULATED',
            'accent_color' => '#22a06b', // Brand Green
            'gradient' => 'linear-gradient(135deg, #1e3a8a 0%, #22a06b 100%)',
            'icon' => 'bi-cash-coin',
            'default_url' => 'https://loaneasyfinance.com',
            'env_key' => 'LOANEASYFINANCE_APP_URL',
            'logo_url' => '/public/images/ecosystem/loaneasyfinance.png',
            'value_pills' => ['🇬🇧 FCA Authorised Lender', '⚡ Same-Day UK Decision', '💷 £750–£3,000 Flexible Terms', '✅ £0 Early Settlement Fee'],
            'trigger_keywords' => ['finance', 'loan', 'luxury', 'wedding', 'venue_hire', 'high_budget', 'financing', 'funding', 'borrow'],
            'svg_icon' => '<svg viewBox="0 0 32 32" width="32" height="32" class="pp-brand-logo-svg" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="32" height="32" rx="8" fill="#1e3a8a"/><path d="M9 16L14 21L23 11" stroke="#22a06b" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/><circle cx="16" cy="16" r="10" stroke="#38bdf8" stroke-width="2" stroke-dasharray="3 3"/></svg>',
        ],
        'partyplatform' => [
            'name' => 'PartyPlatform',
            'headline' => 'Host Show-Stopping Parties Guests Talk About All Year.',
            'tagline' => '3D Invites • 1-Tap WhatsApp RSVPs • Live Photo Wall',
            'description' => 'Launch a bespoke celebration microsite in 60 seconds — complete with animated 3D invitations, instant WhatsApp RSVP tracking, table seating, gift wishlists, and a live event photo wall.',
            'cta_text' => 'Host Your Party Free',
            'badge' => 'CELEBRATION STUDIO',
            'accent_color' => '#9B5CFB', // Electric Purple
            'gradient' => 'linear-gradient(135deg, #7b03fc 0%, #d946ef 100%)',
            'icon' => 'bi-balloon-heart-fill',
            'default_url' => 'https://mypartyplatform.com',
            'env_key' => 'PARTYPLATFORM_APP_URL',
            'logo_url' => '/public/images/ecosystem/partyplatform.png',
            'value_pills' => ['⚡ 60-Second Event Microsite', '💌 1-Tap WhatsApp RSVPs', '🎁 Gift & Drinks Registry', '📸 Live Guest Photo Wall'],
            'trigger_keywords' => ['party', 'event', 'celebration', 'rsvp', 'tickets', 'guest_list', 'invitation'],
            'svg_icon' => '<svg viewBox="0 0 512 512" width="32" height="32" class="pp-brand-logo-svg" aria-label="PartyPlatform Logo" xmlns="http://www.w3.org/2000/svg"><defs><linearGradient id="pp_bg_grad" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#051342"/><stop offset="50%" stop-color="#0A0628"/><stop offset="100%" stop-color="#1E0538"/></linearGradient><linearGradient id="pp_stem_cyan" x1="0%" y1="100%" x2="100%" y2="0%"><stop offset="0%" stop-color="#00F0FF"/><stop offset="55%" stop-color="#2563EB"/><stop offset="100%" stop-color="#7C3AED"/></linearGradient><linearGradient id="pp_top_magenta" x1="0%" y1="100%" x2="100%" y2="0%"><stop offset="0%" stop-color="#9333EA"/><stop offset="50%" stop-color="#FF1E8E"/><stop offset="100%" stop-color="#FF6B4A"/></linearGradient><linearGradient id="pp_bowl_gold" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#FFE600"/><stop offset="50%" stop-color="#FF8A00"/><stop offset="100%" stop-color="#FF1E8E"/></linearGradient><linearGradient id="pp_bottom_loop" x1="100%" y1="0%" x2="0%" y2="100%"><stop offset="0%" stop-color="#E11D8F"/><stop offset="50%" stop-color="#7C3AED"/><stop offset="100%" stop-color="#00D4FF"/></linearGradient><linearGradient id="pp_star_gold" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="#FFF066"/><stop offset="100%" stop-color="#F59E0B"/></linearGradient></defs><rect width="512" height="512" rx="118" fill="url(#pp_bg_grad)"/><path d="M100 205 L186 155 L186 355 C186 408 148 446 100 456 Z" fill="url(#pp_stem_cyan)"/><path d="M100 265 C100 135 152 82 266 82 L212 162 C192 162 186 178 186 205 L100 280 Z" fill="url(#pp_top_magenta)"/><path d="M266 82 C372 82 448 148 448 238 C448 282 426 322 390 346 L354 274 C366 260 370 246 370 228 C370 184 334 162 280 162 L212 162 Z" fill="url(#pp_bowl_gold)"/><path d="M440 206 C458 286 398 370 296 370 L204 370 C216 320 250 292 296 292 C344 292 372 264 372 224 Z" fill="url(#pp_bottom_loop)"/><polygon points="266,178 281,214 320,218 290,244 299,282 266,262 233,282 242,244 212,218 251,214" fill="url(#pp_star_gold)"/></svg>',
        ],
        'bcp' => [
            'name' => 'Best Care Professionals',
            'headline' => 'CQC-Vetted Nurses & Carers Dispatched Across the UK in 15 Mins.',
            'tagline' => '24/7 CQC Regulated Healthcare Staffing & Home Care',
            'description' => 'Whether covering an urgent ward shift or arranging compassionate private care for an elderly relative at home, book 100% DBS-checked, CQC-regulated healthcare professionals.',
            'cta_text' => 'Book Vetted Care Staff',
            'badge' => 'CQC REGULATED',
            'accent_color' => '#006699', // Ocean Blue
            'gradient' => 'linear-gradient(135deg, #006699 0%, #70a936 100%)',
            'icon' => 'bi-heart-pulse-fill',
            'default_url' => 'https://www.bestcarepro.co.uk',
            'env_key' => 'BCP_APP_URL',
            'logo_url' => '/public/images/ecosystem/bcp.png',
            'value_pills' => ['🏥 CQC Regulated Agency', '⏱️ 15-Min Emergency Dispatch', '🩺 100% DBS & NMC Vetted', '🏡 Hospital & Home Care'],
            'trigger_keywords' => ['care', 'nurse', 'health', 'carer', 'cqc', 'elderly', 'hospital', 'staffing', 'shift', 'medical', 'clinic', 'first_aid', 'safeguarding'],
            'svg_icon' => '<svg viewBox="0 0 32 32" width="32" height="32" class="pp-brand-logo-svg" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="32" height="32" rx="8" fill="url(#bcp_grad)"/><path d="M16 8V24M8 16H24" stroke="#ffffff" stroke-width="3" stroke-linecap="round"/><circle cx="16" cy="16" r="11" stroke="#70a936" stroke-width="2"/><defs><linearGradient id="bcp_grad" x1="0" y1="0" x2="32" y2="32" gradientUnits="userSpaceOnUse"><stop stop-color="#006699"/><stop offset="1" stop-color="#004d73"/></linearGradient></defs></svg>',
        ],
        'growthforge' => [
            'name' => 'GrowthForge',
            'headline' => 'Turn One Idea Into 34 Social Campaigns & #1 AI Search Citations.',
            'tagline' => 'Autonomous Content, Viral Shorts & ChatGPT/Google AEO OS',
            'description' => 'Replace £3,000/mo agency retainers. Automatically turn voice notes into 34-channel social posts, faceless YouTube Shorts, multilingual video dubs, and #1 ChatGPT & Google AEO rankings.',
            'cta_text' => 'Launch GrowthForge',
            'badge' => 'AI GROWTH OS',
            'accent_color' => '#f97316', // Sovereign Amber-Orange
            'gradient' => 'linear-gradient(135deg, #ea580c 0%, #f59e0b 100%)',
            'icon' => 'bi-rocket-takeoff-fill',
            'default_url' => 'https://growth.execmindapp.com',
            'env_key' => 'GROWTHFORGE_APP_URL',
            'logo_url' => '',
            'value_pills' => ['🚀 34-Channel Auto-Publishing', '🎬 AI Shorts & Video Dubbing', '🔍 #1 ChatGPT & Google AEO', '💷 Replaces £3k/mo Agencies'],
            'trigger_keywords' => ['seo', 'aeo', 'marketing', 'social_media', 'newsletter', 'shorts', 'growth', 'leads', 'traffic', 'content'],
            'svg_icon' => '<svg viewBox="0 0 32 32" width="32" height="32" class="pp-brand-logo-svg" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="32" height="32" rx="8" fill="#0f172a"/><path d="M8 22L14 15L18 19L24 10" stroke="#f97316" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/><path d="M19 10H24V15" stroke="#fbbf24" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"/></svg>',
        ],
        'hermespulse' => [
            'name' => 'HermesPulse',
            'headline' => 'Your 24/7 AI Concierge That Chases Leads, Docs & RSVPs For You.',
            'tagline' => 'Persistent-Memory WhatsApp, SMS & Email Operations Agent',
            'description' => 'Deploy a tireless 24/7 AI concierge that follows up on client compliance, references, invoices, and guest enquiries across WhatsApp, SMS, and Email while you sleep.',
            'cta_text' => 'Hire AI Concierge',
            'badge' => '24/7 AI CONCIERGE',
            'accent_color' => '#14b8a6', // Sovereign Teal
            'gradient' => 'linear-gradient(135deg, #0f766e 0%, #14b8a6 100%)',
            'icon' => 'bi-robot',
            'default_url' => 'https://growth.execmindapp.com/?suite=hermespulse',
            'env_key' => 'HERMESPULSE_APP_URL',
            'logo_url' => '',
            'value_pills' => ['💬 Native WhatsApp & SMS', '🤖 Persistent Client Memory', '⏱️ Saves 15+ Hours Weekly', '🔄 Zero-Touch Follow-Ups'],
            'trigger_keywords' => ['concierge', 'automation', 'agent', 'outreach', 'follow_up', 'chaser', 'legacy_saas', 'operations', 'whatsapp_bot'],
            'svg_icon' => '<svg viewBox="0 0 32 32" width="32" height="32" class="pp-brand-logo-svg" fill="none" xmlns="http://www.w3.org/2000/svg"><rect width="32" height="32" rx="8" fill="#042f2e"/><circle cx="16" cy="16" r="8" stroke="#14b8a6" stroke-width="2.5"/><circle cx="16" cy="16" r="3" fill="#5eead4"/><path d="M16 4V8M16 24V28M4 16H8M24 16H28" stroke="#14b8a6" stroke-width="2" stroke-linecap="round"/></svg>',
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

        // 0. Check Tier 0 Active Dynamic Campaign from Central EcosystemAdManager
        if (class_exists(EcosystemAdManager::class)) {
            $customAd = EcosystemAdManager::getAdvertForApp($currentAppKey, $placement, $context);
            if ($customAd !== null) {
                EcosystemAdManager::recordImpression((string) ($customAd['id'] ?? ''), $currentAppKey);
                return $customAd;
            }
        }

        // 1. Gather context keywords
        $keywords = $this->extractKeywords($context, $placement);

        // 2. Score internal portfolio candidates (excluding current app)
        $candidates = [];

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

            if ($score > 0) {
                $candidates[$appKey] = $score;
            }
        }

        // 3. If an internal match has positive intent, rotate among top candidates
        if (!empty($candidates)) {
            arsort($candidates);
            $topScore = reset($candidates);
            $topMatches = array_keys(array_filter($candidates, static fn(int $s): bool => $s >= $topScore));

            $matchIndex = self::resolveSequenceIndex($currentAppKey . '_match', count($topMatches), $context);
            $chosenApp = $topMatches[$matchIndex] ?? $topMatches[0];

            $banner = $this->formatEcosystemBanner($chosenApp, $currentAppKey, $placement, $context);
            if (class_exists(EcosystemAdManager::class)) {
                EcosystemAdManager::recordImpression((string) ($banner['id'] ?? ''), $currentAppKey);
            }
            return $banner;
        }

        // 4. If no specific keyword match, select default strategic internal partner
        $defaultSibling = $this->getDefaultSiblingApp($currentAppKey, $placement, $context);
        if ($defaultSibling !== null) {
            $banner = $this->formatEcosystemBanner($defaultSibling, $currentAppKey, $placement, $context);
            if (class_exists(EcosystemAdManager::class)) {
                EcosystemAdManager::recordImpression((string) ($banner['id'] ?? ''), $currentAppKey);
            }
            return $banner;
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
        } elseif ($targetApp === 'bcp' && !empty($_ENV['BESTCARE_APP_URL'])) {
            $baseUrl = (string) $_ENV['BESTCARE_APP_URL'];
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
            'logo_url' => (string) ($meta['logo_url'] ?? ''),
            'value_pills' => array_values((array) ($meta['value_pills'] ?? [])),
            'disclosure_type' => 'ecosystem',
            'disclosure_text' => 'Sister Studio Spotlight • 1-Click Single Login',
            'dismissible' => true,
        ];
    }

    /**
     * Builds a complete rotation deck of eligible ecosystem and custom campaign banners
     * for client-side and multi-impression rotation, starting with the primary selected
     * banner for the current context.
     *
     * @param string $currentApp Current application key (e.g. 'familyplatform')
     * @param string $placement Surface identifier (e.g. 'family_feed')
     * @param array<string, mixed> $context Context attributes
     * @return array<int, array<string, mixed>>
     */
    public function getPromoDeckForContext(string $currentApp, string $placement, array $context = []): array
    {
        $currentAppKey = strtolower(trim($currentApp));
        $deck = [];
        $seenAppKeys = [];

        // 1. Primary resolved banner for this impression
        $primary = $this->getPromoForContext($currentAppKey, $placement, $context);
        if ($primary !== null) {
            $deck[] = $primary;
            $primaryTarget = (string) ($primary['target_app'] ?? '');
            if ($primaryTarget !== '') {
                $seenAppKeys[$primaryTarget] = true;
            }
        }

        // 2. Gather all eligible sister apps (excluding current host app)
        $eligibleApps = array_values(array_filter(
            array_keys(self::PORTFOLIO_CATALOG),
            static fn(string $app): bool => $app !== $currentAppKey
        ));

        $count = count($eligibleApps);
        if ($count > 0) {
            $dayOfYear = isset($context['mock_day_of_year']) && is_numeric($context['mock_day_of_year'])
                ? (int) $context['mock_day_of_year']
                : (int) date('z');
            $placementSalt = abs(crc32($currentAppKey . ':' . $placement)) % $count;
            $startOffset = ($dayOfYear + $placementSalt) % $count;

            for ($i = 0; $i < $count; $i++) {
                $appKey = $eligibleApps[($startOffset + $i) % $count];
                if (isset($seenAppKeys[$appKey])) {
                    continue;
                }
                $seenAppKeys[$appKey] = true;
                $deck[] = $this->formatEcosystemBanner($appKey, $currentAppKey, $placement, $context);
            }
        }

        return $deck;
    }

    /**
     * Resolves a rolling rotation sequence index across multiple tiers:
     * 1. Mock context (for unit tests and deterministic simulation)
     * 2. PHP $_SESSION (if session is active)
     * 3. Browser HTTP cookie ($_COOKIE) (for Laravel and stateless frameworks where $_SESSION is absent)
     * 4. Micro-time interval entropy (rotates every 60 seconds so subsequent visits cycle naturally)
     *
     * @param string $appKey
     * @param int $count
     * @param array<string, mixed> $context
     * @return int
     */
    public static function resolveSequenceIndex(string $appKey, int $count, array $context = []): int
    {
        if ($count <= 1) {
            return 0;
        }

        // 1. Mock override for testing
        if (isset($context['mock_session_offset']) && is_numeric($context['mock_session_offset'])) {
            return abs((int) $context['mock_session_offset']) % $count;
        }

        // 2. Native PHP Session
        $sessionKey = '_eco_promo_seq_' . $appKey;
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION)) {
            $offset = isset($_SESSION[$sessionKey]) && is_numeric($_SESSION[$sessionKey])
                ? (int) $_SESSION[$sessionKey]
                : 0;
            $_SESSION[$sessionKey] = ($offset + 1) % $count;
            return $offset % $count;
        }

        // 3. Client Cookie fallback (handles Laravel/stateless apps where native $_SESSION is inactive)
        $cookieKey = '_eco_seq_' . substr(md5($appKey), 0, 8);
        if (isset($_COOKIE[$cookieKey]) && is_numeric($_COOKIE[$cookieKey])) {
            $offset = (int) $_COOKIE[$cookieKey];
            $next = ($offset + 1) % $count;
            if (!headers_sent()) {
                @setcookie($cookieKey, (string) $next, [
                    'expires' => time() + 86400 * 7,
                    'path' => '/',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            }
            $_COOKIE[$cookieKey] = (string) $next;
            return $offset % $count;
        }

        // 4. Micro-time interval entropy (rotates every 60 seconds)
        $timeWindow = (int) floor(time() / 60);
        $next = ($timeWindow + 1) % $count;
        if (!headers_sent()) {
            @setcookie($cookieKey, (string) $next, [
                'expires' => time() + 86400 * 7,
                'path' => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
            $_COOKIE[$cookieKey] = (string) $next;
        }

        return $timeWindow % $count;
    }

    /**
     * Selects an ecosystem sister app using multi-tier anti-fatigue rotation:
     * Day-of-year baseline + placement salt + (Session/Cookie/Interval sequence index).
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

        // 2. Day-of-year rotation baseline (0-365)
        $dayOfYear = isset($context['mock_day_of_year']) && is_numeric($context['mock_day_of_year'])
            ? (int) $context['mock_day_of_year']
            : (int) date('z');

        // 3. Placement salt ensures distinct pages on the same day feature distinct apps
        $placementSalt = abs(crc32($currentApp . ':' . $placement)) % $count;

        // 4. Multi-tier anti-fatigue sequence offset (Session -> Cookie -> Time Window)
        $sessionOffset = self::resolveSequenceIndex($currentApp, $count, $context);

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
