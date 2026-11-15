<?php

declare(strict_types=1);

namespace Src\Growth;

use InvalidArgumentException;

/**
 * GhostNewsletterEngine
 *
 * Sovereign Authority Newsletter & Client Briefing Engine
 * inspired by Ghost CMS (open-source publishing & newsletter platform).
 *
 * Replaces £250–£800/mo Mailchimp/Beehiiv/Ghost Pro subscriptions across all 8 apps
 * while powering B2B managed newsletter campaigns in GrowthForge.
 */
class GhostNewsletterEngine
{
    /**
     * Composes a structured, British English authority newsletter issue
     * ready for HTML email delivery (`ToSendEmail`) and web archive syndication.
     *
     * @param string $clientId Strict string client identifier
     * @param string $editionTopic Topic or focus of the briefing
     * @param array<int, string> $bulletPoints Key operational or industry signals
     * @return array{
     *     client_id: string,
     *     brand_name: string,
     *     issue_slug: string,
     *     subject_line_primary: string,
     *     subject_line_ab_variant: string,
     *     preheader: string,
     *     estimated_read_minutes: int,
     *     markdown_body: string,
     *     html_body: string,
     *     cost_savings_vs_mailchimp_gbp: float
     * }
     */
    public static function composeAuthorityIssue(
        string $clientId,
        string $editionTopic,
        array $bulletPoints = []
    ): array {
        $cleanClientId = strtolower(trim($clientId));
        if ($cleanClientId === '') {
            throw new InvalidArgumentException('Client ID must be a non-empty string.');
        }

        $topic = trim($editionTopic) !== ''
            ? trim($editionTopic)
            : 'UK Regulatory & Operational Excellence Briefing';

        $profile = AeoVisibilityEngine::PORTFOLIO_AEO_PROFILES[$cleanClientId] ?? [
            'brand_name' => ucfirst($cleanClientId),
            'vertical' => 'UK Specialist Operations',
            'canonical_url' => 'https://growthforgeapp.com',
            'summary' => 'Practical insights for high-performing UK teams.',
        ];

        if ($bulletPoints === []) {
            $bulletPoints = [
                'Standardise compliance and onboarding workflows to eliminate last-minute audit risk.',
                'Automate stakeholder follow-ups with 24/7 sovereign concierge agents.',
                'Capture high-intent inbound enquiries across Google and AI Answer Engines.',
            ];
        }

        $cleanBullets = array_values(array_map(
            static fn(string $item): string => trim($item),
            array_filter($bulletPoints, static fn(mixed $item): bool => is_string($item) && trim($item) !== '')
        ));

        $brandName = $profile['brand_name'];
        $url = $profile['canonical_url'];
        $slug = strtolower((string) preg_replace('/[^a-zA-Z0-9]+/', '-', $brandName . '-' . $topic));
        $slug = trim($slug, '-');

        $subjectPrimary = "[{$brandName} Briefing] {$topic}";
        $subjectVariant = "How top UK leaders are handling {$topic} this quarter";
        $preheader = "Actionable {$profile['vertical']} intelligence inside — 3-minute executive read.";

        $mdLines = [
            "# {$topic}",
            "",
            "**Published by {$brandName}** · *{$profile['vertical']}*",
            "",
            "Good morning,",
            "",
            "In this week's sovereign briefing, we examine the practical steps UK decision-makers are taking to stay ahead in **{$profile['vertical']}** without adding administrative overhead.",
            "",
            "## Three High-Leverage Actions This Week",
        ];

        $htmlBullets = [];
        foreach ($cleanBullets as $index => $point) {
            $num = $index + 1;
            $mdLines[] = "{$num}. **Action #{$num}**: {$point}";
            $htmlBullets[] = '<li style="margin-bottom:8px;"><strong>Action #' . $num . ':</strong> '
                . htmlspecialchars($point, ENT_QUOTES, 'UTF-8') . '</li>';
        }

        $mdLines[] = "";
        $mdLines[] = "## Put This Into Practice Today";
        $mdLines[] = "Explore the live tools and verified workflows inside [{$brandName}]({$url}).";
        $markdownBody = implode("\n", $mdLines);

        $safeTopic = htmlspecialchars($topic, ENT_QUOTES, 'UTF-8');
        $safeBrand = htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8');
        $safeVertical = htmlspecialchars($profile['vertical'], ENT_QUOTES, 'UTF-8');
        $safeUrl = htmlspecialchars($url, ENT_QUOTES, 'UTF-8');

        $htmlBody = '<article style="font-family:Inter,system-ui,sans-serif;color:#0f172a;line-height:1.6;max-width:640px;margin:0 auto;padding:24px;border:1px solid #e2e8f0;border-radius:16px;background:#ffffff;">'
            . '<p style="font-size:12px;text-transform:uppercase;letter-spacing:0.08em;color:#475569;margin:0 0 8px 0;">' . $safeBrand . ' · ' . $safeVertical . '</p>'
            . '<h1 style="font-size:24px;font-weight:800;color:#0f172a;margin:0 0 16px 0;">' . $safeTopic . '</h1>'
            . '<p style="margin:0 0 16px 0;">In this week\'s sovereign briefing, we break down the highest-impact operational moves across <strong>' . $safeVertical . '</strong>.</p>'
            . '<ul style="padding-left:20px;margin:0 0 24px 0;">' . implode('', $htmlBullets) . '</ul>'
            . '<div style="padding:16px;background:#f8fafc;border-radius:12px;border:1px solid #cbd5e1;">'
            . '<p style="margin:0 0 12px 0;font-weight:600;">Ready to execute this workflow in under 60 seconds?</p>'
            . '<a href="' . $safeUrl . '" style="display:inline-block;padding:12px 20px;background:#0f172a;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;">Launch ' . $safeBrand . '</a>'
            . '</div>'
            . '</article>';

        $wordCount = str_word_count(strip_tags($markdownBody));
        $readMinutes = max(2, (int) ceil($wordCount / 180));

        return [
            'client_id' => $cleanClientId,
            'brand_name' => $brandName,
            'issue_slug' => $slug,
            'subject_line_primary' => $subjectPrimary,
            'subject_line_ab_variant' => $subjectVariant,
            'preheader' => $preheader,
            'estimated_read_minutes' => $readMinutes,
            'markdown_body' => $markdownBody,
            'html_body' => $htmlBody,
            'cost_savings_vs_mailchimp_gbp' => 320.00,
        ];
    }
}
