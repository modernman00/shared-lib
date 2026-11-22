<?php

declare(strict_types=1);

namespace Src\Admin;

use PDO;
use Src\AuditLogger;
use Src\Concierge\HermesPulseOrchestrator;
use Src\Concierge\ReaLegacyDeconstructor;
use Src\Growth\AeoVisibilityEngine;
use Src\Growth\GhostNewsletterEngine;
use Src\Growth\OmniChannelPostizEngine;
use Src\Media\ShortsAndDubbingEngine;
use Src\SeoEngine;

/**
 * GrowthAndConciergeAdminService
 *
 * Centralised Admin Portal integration for GrowthForge (OpenSEO/AEO, Postiz,
 * Ghost, AI Shorts, VideoLingo) and HermesPulse (24/7 AI Concierge & REA).
 *
 * Designed to be exposed via `AdminService::getGrowthAndConciergeService()`
 * across all 8 portfolio applications' Admin Portals with zero duplicated logic.
 */
class GrowthAndConciergeAdminService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? \Src\Db::connect2();
    }

    public function getDb(): PDO
    {
        return $this->db;
    }

    /**
     * Returns the live GrowthForge & HermesPulse Admin summary for a portfolio app.
     *
     * @return array{
     *     app_key: string,
     *     aeo_audit: array<string, mixed>,
     *     concierge: array<string, mixed>,
     *     llms_txt: string,
     *     growthforge_admin_url: string
     * }
     */
    public function getAppGrowthSnapshot(string $appKey): array
    {
        $cleanKey = strtolower(trim($appKey));
        if ($cleanKey === '' || $cleanKey === 'all') {
            $cleanKey = 'execmind';
        }

        $aeoAudit = AeoVisibilityEngine::auditDomainVisibility($cleanKey);
        $concierge = HermesPulseOrchestrator::executeConciergeCycle($cleanKey);
        $llmsTxt = SeoEngine::renderLlmsTxt(['client_key' => $cleanKey]);

        $baseUrl = rtrim((string) ($_ENV['GROWTHFORGE_APP_URL'] ?? getenv('GROWTHFORGE_APP_URL') ?: 'https://growth.execmindapp.com'), '/');

        return [
            'app_key' => $cleanKey,
            'aeo_audit' => $aeoAudit,
            'concierge' => $concierge,
            'llms_txt' => $llmsTxt,
            'growthforge_admin_url' => $baseUrl . '/?client=' . rawurlencode($cleanKey),
        ];
    }

    /**
     * Renders a self-contained, WCAG AA compliant Admin Panel card for any of the 8 portfolio apps.
     */
    public static function renderAdminWidget(string $appKey): string
    {
        $service = new self();
        $snapshot = $service->getAppGrowthSnapshot($appKey);
        /** @var array<string, mixed> $audit */
        $audit = $snapshot['aeo_audit'];
        /** @var array<string, mixed> $concierge */
        $concierge = $snapshot['concierge'];
        $url = htmlspecialchars($snapshot['growthforge_admin_url'], ENT_QUOTES, 'UTF-8');
        $score = (int) ($audit['overall_score'] ?? 90);
        $agent = htmlspecialchars((string) ($concierge['agent_codename'] ?? 'HermesPulse'), ENT_QUOTES, 'UTF-8');
        $hours = htmlspecialchars((string) ($concierge['total_hours_saved_monthly'] ?? '25'), ENT_QUOTES, 'UTF-8');

        return '<div class="rounded-2xl p-5 border border-slate-800 bg-slate-950 text-white my-4 shadow-sm">'
            . '<div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">'
            . '<div>'
            . '<h3 class="text-base font-bold text-white">GrowthForge &amp; HermesPulse (AEO + 24/7 AI Concierge)</h3>'
            . '<p class="text-xs text-slate-300 mt-1">'
            . 'AEO Visibility: <strong class="text-emerald-400">' . $score . '/100</strong>'
            . ' · Agent: <strong class="text-teal-300">' . $agent . '</strong>'
            . ' · Est. Saved: <strong class="text-white">' . $hours . ' hrs/mo</strong>'
            . '</p>'
            . '</div>'
            . '<div class="flex flex-wrap items-center gap-2">'
            . '<a href="/llms.txt" target="_blank" rel="noopener" style="min-height:44px;display:inline-flex;align-items:center;padding:8px 14px;border-radius:12px;background:#1e293b;color:#f8fafc;font-size:12px;font-weight:600;text-decoration:none;white-space:nowrap;">View /llms.txt</a>'
            . '<a href="' . $url . '" target="_blank" rel="noopener" style="min-height:44px;display:inline-flex;align-items:center;padding:8px 14px;border-radius:12px;background:#f59e0b;color:#0f172a;font-size:12px;font-weight:700;text-decoration:none;white-space:nowrap;">Open GrowthForge &amp; HermesPulse</a>'
            . '</div>'
            . '</div>'
            . '</div>';
    }

    /**
     * Reports live API/Webhook credential readiness for outbound channels (Email, SMS/WhatsApp, LinkedIn, X, Slack).
     *
     * @return array<string, array{label: string, configured: bool, env_var: string, mode: string}>
     */
    public static function getLiveConnectorStatus(): array
    {
        $hasEnv = static fn(string $key): bool => trim((string) ($_ENV[$key] ?? getenv($key) ?: '')) !== '';

        return [
            'email_smtp' => [
                'label' => 'Email Newsletter & Briefing (SMTP / ToSendEmail)',
                'configured' => $hasEnv('MAIL_HOST') || $hasEnv('SMTP_HOST') || class_exists(\Src\ToSendEmail::class),
                'env_var' => 'MAIL_HOST / SMTP_HOST',
                'mode' => 'live_ready',
            ],
            'sms_whatsapp' => [
                'label' => 'WhatsApp & SMS Concierge (Twilio / Vonage / TextMagic)',
                'configured' => $hasEnv('TWILIO_SID') || $hasEnv('VONAGE_KEY') || $hasEnv('TEXTMAGIC_API_KEY'),
                'env_var' => 'TWILIO_SID / VONAGE_KEY',
                'mode' => ($hasEnv('TWILIO_SID') || $hasEnv('VONAGE_KEY')) ? 'live_api' : 'sqlite_queue_ready',
            ],
            'linkedin_api' => [
                'label' => 'LinkedIn Company & Executive Publishing (Postiz Connector)',
                'configured' => $hasEnv('LINKEDIN_ACCESS_TOKEN') || $hasEnv('POSTIZ_WEBHOOK_URL'),
                'env_var' => 'LINKEDIN_ACCESS_TOKEN',
                'mode' => ($hasEnv('LINKEDIN_ACCESS_TOKEN') || $hasEnv('POSTIZ_WEBHOOK_URL')) ? 'live_api' : 'one_click_copy_ready',
            ],
            'x_api' => [
                'label' => 'X (Twitter) Thread Publisher',
                'configured' => $hasEnv('X_BEARER_TOKEN') || $hasEnv('POSTIZ_WEBHOOK_URL'),
                'env_var' => 'X_BEARER_TOKEN',
                'mode' => ($hasEnv('X_BEARER_TOKEN') || $hasEnv('POSTIZ_WEBHOOK_URL')) ? 'live_api' : 'one_click_copy_ready',
            ],
            'slack_webhook' => [
                'label' => 'Slack & Webhook Broadcasts',
                'configured' => $hasEnv('SLACK_WEBHOOK_URL') || $hasEnv('POSTIZ_WEBHOOK_URL'),
                'env_var' => 'SLACK_WEBHOOK_URL',
                'mode' => ($hasEnv('SLACK_WEBHOOK_URL') || $hasEnv('POSTIZ_WEBHOOK_URL')) ? 'live_webhook' : 'sqlite_queue_ready',
            ],
        ];
    }

    /**
     * Runs an Admin-approved omni-channel media campaign (Postiz + Ghost + Shorts + VideoLingo).
     *
     * @return array<string, mixed>
     */
    public function runAdminMediaCampaign(string $appKey, string $notes, string $locale = 'pl-PL', int $adminUserId = 0): array
    {
        $cleanKey = strtolower(trim($appKey));
        $social = OmniChannelPostizEngine::transformBriefToSocialMatrix($cleanKey, $notes);
        $newsletter = GhostNewsletterEngine::composeAuthorityIssue($cleanKey, 'Admin Authority Briefing');
        $shorts = ShortsAndDubbingEngine::extractViralShortsManifest($cleanKey, $notes, 3);
        $dub = ShortsAndDubbingEngine::translateAndDubScript($notes !== '' ? $notes : 'UK compliance update.', $locale);

        AuditLogger::log('admin_growth_campaign_synthesised', [
            'admin_user_id' => $adminUserId,
            'app_key' => $cleanKey,
            'requires_human_approval' => $social['requires_human_approval'],
        ]);

        return [
            'social_matrix' => $social,
            'newsletter' => $newsletter,
            'shorts_manifest' => $shorts,
            'dubbed_preview' => $dub,
        ];
    }

    /**
     * Dispatches a HermesPulse concierge directive from an app's Admin Portal.
     *
     * @return array<string, mixed>
     */
    public function dispatchAdminConciergeDirective(string $appKey, string $directive, int $adminUserId = 0): array
    {
        $cleanKey = strtolower(trim($appKey));
        $result = HermesPulseOrchestrator::executeConciergeCycle($cleanKey, $directive);

        AuditLogger::log('admin_hermes_directive_dispatched', [
            'admin_user_id' => $adminUserId,
            'app_key' => $cleanKey,
            'agent_codename' => $result['agent_codename'],
        ]);

        return $result;
    }

    /**
     * Returns the REA commercial tiers for upselling external B2B partners from Admin.
     *
     * @return array<string, array<string, mixed>>
     */
    public function getCommercialTiers(): array
    {
        return ReaLegacyDeconstructor::COMMERCIAL_TIERS;
    }

    /**
     * Renders a self-contained, WCAG AA compliant GrowthForge & HermesPulse Admin Panel card
     * for one-line embedding in any portfolio app's Admin Dashboard (zero duplicated HTML).
     */
    public static function renderAdminCardHtml(string $appKey, string $theme = 'dark'): string
    {
        $cleanKey = strtolower(trim($appKey));
        if ($cleanKey === '' || $cleanKey === 'all') {
            $cleanKey = 'execmind';
        }

        $aeoAudit = AeoVisibilityEngine::auditDomainVisibility($cleanKey);
        $concierge = HermesPulseOrchestrator::executeConciergeCycle($cleanKey);
        $baseUrl = rtrim((string) ($_ENV['GROWTHFORGE_APP_URL'] ?? getenv('GROWTHFORGE_APP_URL') ?: 'https://growth.execmindapp.com'), '/');
        $launchUrl = htmlspecialchars($baseUrl . '/?client=' . rawurlencode($cleanKey), ENT_QUOTES, 'UTF-8');

        $score = (int) $aeoAudit['overall_score'];
        $codename = htmlspecialchars((string) $concierge['agent_codename'], ENT_QUOTES, 'UTF-8');
        $hoursSaved = htmlspecialchars((string) $concierge['total_hours_saved_monthly'], ENT_QUOTES, 'UTF-8');
        $brandName = htmlspecialchars((string) $aeoAudit['brand_name'], ENT_QUOTES, 'UTF-8');

        $isLight = strtolower(trim($theme)) === 'light';
        $cardClasses = $isLight
            ? 'rounded-2xl p-5 border border-slate-200 bg-white text-slate-900 shadow-sm mt-6'
            : 'rounded-2xl p-5 border border-slate-700 bg-slate-900 text-white shadow-sm mt-6';
        $subTextClass = $isLight ? 'text-slate-600' : 'text-slate-300';
        $secondaryBtnClass = $isLight
            ? 'bg-slate-100 hover:bg-slate-200 text-slate-900 border border-slate-300'
            : 'bg-slate-800 hover:bg-slate-700 text-white border border-slate-700';

        return '<div class="' . $cardClasses . '">'
            . '<div style="display:flex;flex-wrap:wrap;align-items:center;justify-content:space-between;gap:16px;">'
            . '<div>'
            . '<h3 style="font-size:16px;font-weight:700;margin:0;">GrowthForge &amp; HermesPulse — ' . $brandName . '</h3>'
            . '<p class="' . $subTextClass . '" style="font-size:13px;margin:6px 0 0 0;">'
            . 'AEO Visibility: <strong>' . $score . '/100</strong>'
            . ' &middot; Agent: <strong>' . $codename . '</strong>'
            . ' &middot; Est. Saved: <strong>' . $hoursSaved . ' hrs/mo</strong>'
            . '</p>'
            . '</div>'
            . '<div style="display:flex;flex-wrap:wrap;align-items:center;gap:8px;">'
            . '<a href="/llms.txt" target="_blank" rel="noopener" class="inline-flex items-center px-4 py-2.5 min-h-[44px] rounded-xl text-xs font-semibold whitespace-nowrap shrink-0 ' . $secondaryBtnClass . '" style="text-decoration:none;">View /llms.txt</a>'
            . '<a href="' . $launchUrl . '" target="_blank" rel="noopener" class="inline-flex items-center px-4 py-2.5 min-h-[44px] rounded-xl bg-amber-500 hover:bg-amber-400 text-slate-950 text-xs font-bold whitespace-nowrap shrink-0" style="text-decoration:none;">Open GrowthForge &amp; HermesPulse</a>'
            . '</div>'
            . '</div>'
            . '</div>';
    }
}
