<?php

declare(strict_types=1);

namespace Src\Growth;

use InvalidArgumentException;

/**
 * OmniChannelPostizEngine
 *
 * Sovereign Social Media Automation & Meeting-to-Content Synthesis Engine
 * inspired by Postiz (supporting 34 social & publishing channels).
 *
 * Transforms raw executive voice notes, board summaries, or app milestones
 * into authentic, platform-native content with British English copy,
 * character-limit validation, and deterministic scheduling queues.
 */
class OmniChannelPostizEngine
{
    /**
     * Master registry of the 34 supported social, video, and community platforms
     * inspired by Postiz's multi-channel connector matrix.
     *
     * @var array<string, array{label: string, max_chars: int, format: string, best_uk_time: string}>
     */
    public const SUPPORTED_CHANNELS = [
        'linkedin'       => ['label' => 'LinkedIn', 'max_chars' => 3000, 'format' => 'thought_leadership', 'best_uk_time' => '08:30 GMT'],
        'x'              => ['label' => 'X (Twitter)', 'max_chars' => 280, 'format' => 'punchy_thread', 'best_uk_time' => '12:15 GMT'],
        'instagram'      => ['label' => 'Instagram Reels & Carousel', 'max_chars' => 2200, 'format' => 'visual_caption', 'best_uk_time' => '18:00 GMT'],
        'tiktok'         => ['label' => 'TikTok Vertical Video', 'max_chars' => 2200, 'format' => 'short_video_hook', 'best_uk_time' => '19:30 GMT'],
        'youtube_shorts' => ['label' => 'YouTube Shorts', 'max_chars' => 100, 'format' => 'vertical_title', 'best_uk_time' => '17:00 GMT'],
        'facebook'       => ['label' => 'Facebook Business Page', 'max_chars' => 2000, 'format' => 'community_story', 'best_uk_time' => '13:00 GMT'],
        'threads'        => ['label' => 'Threads', 'max_chars' => 500, 'format' => 'conversational', 'best_uk_time' => '11:00 GMT'],
        'bluesky'        => ['label' => 'Bluesky', 'max_chars' => 300, 'format' => 'micro_insight', 'best_uk_time' => '14:00 GMT'],
        'pinterest'      => ['label' => 'Pinterest Idea Pin', 'max_chars' => 500, 'format' => 'visual_guide', 'best_uk_time' => '20:00 GMT'],
        'reddit'         => ['label' => 'Reddit Vertical Community', 'max_chars' => 4000, 'format' => 'deep_value_post', 'best_uk_time' => '09:00 GMT'],
        'medium'         => ['label' => 'Medium Publication', 'max_chars' => 10000, 'format' => 'article_syndication', 'best_uk_time' => '10:00 GMT'],
        'substack'       => ['label' => 'Substack Notes', 'max_chars' => 1000, 'format' => 'newsletter_teaser', 'best_uk_time' => '08:00 GMT'],
        'ghost'          => ['label' => 'Ghost Authority Hub', 'max_chars' => 15000, 'format' => 'canonical_article', 'best_uk_time' => '07:30 GMT'],
        'whatsapp'       => ['label' => 'WhatsApp Broadcast Channel', 'max_chars' => 1024, 'format' => 'direct_briefing', 'best_uk_time' => '08:45 GMT'],
        'telegram'       => ['label' => 'Telegram Channel', 'max_chars' => 4096, 'format' => 'instant_alert', 'best_uk_time' => '09:30 GMT'],
        'slack'          => ['label' => 'Slack Community Webhook', 'max_chars' => 3000, 'format' => 'stakeholder_digest', 'best_uk_time' => '09:00 GMT'],
        'discord'        => ['label' => 'Discord Announcement', 'max_chars' => 2000, 'format' => 'community_update', 'best_uk_time' => '18:30 GMT'],
        'google_gbp'     => ['label' => 'Google Business Profile', 'max_chars' => 1500, 'format' => 'local_authority', 'best_uk_time' => '10:30 GMT'],
        'mastodon'       => ['label' => 'Mastodon', 'max_chars' => 500, 'format' => 'federated_note', 'best_uk_time' => '15:00 GMT'],
        'devto'          => ['label' => 'DEV.to Engineering Blog', 'max_chars' => 12000, 'format' => 'technical_case_study', 'best_uk_time' => '11:30 GMT'],
        'hashnode'       => ['label' => 'Hashnode', 'max_chars' => 12000, 'format' => 'technical_case_study', 'best_uk_time' => '11:45 GMT'],
        'quora'          => ['label' => 'Quora Space Answer', 'max_chars' => 5000, 'format' => 'aeo_answer', 'best_uk_time' => '16:00 GMT'],
        'linkedin_page'  => ['label' => 'LinkedIn Company Page', 'max_chars' => 3000, 'format' => 'corporate_proof', 'best_uk_time' => '09:15 GMT'],
        'youtube_community' => ['label' => 'YouTube Community Tab', 'max_chars' => 1500, 'format' => 'poll_and_teaser', 'best_uk_time' => '17:30 GMT'],
        'snapchat'       => ['label' => 'Snapchat Spotlight', 'max_chars' => 250, 'format' => 'vertical_hook', 'best_uk_time' => '19:00 GMT'],
        'twitch'         => ['label' => 'Twitch Stream Pulse', 'max_chars' => 500, 'format' => 'live_notification', 'best_uk_time' => '20:30 GMT'],
        'vimeo'          => ['label' => 'Vimeo Enterprise Showcase', 'max_chars' => 2000, 'format' => 'hd_explainer_desc', 'best_uk_time' => '14:30 GMT'],
        'dailymotion'    => ['label' => 'Dailymotion UK', 'max_chars' => 2000, 'format' => 'video_syndication', 'best_uk_time' => '15:30 GMT'],
        'producthunt'    => ['label' => 'Product Hunt Ship / Launch', 'max_chars' => 1000, 'format' => 'maker_update', 'best_uk_time' => '08:01 GMT'],
        'indiehackers'   => ['label' => 'Indie Hackers Milestone', 'max_chars' => 3000, 'format' => 'transparent_metrics', 'best_uk_time' => '13:30 GMT'],
        'hackernews'     => ['label' => 'Hacker News (Show HN)', 'max_chars' => 2000, 'format' => 'engineering_deep_dive', 'best_uk_time' => '14:00 GMT'],
        'trustpilot'     => ['label' => 'Trustpilot Review Invitation', 'max_chars' => 800, 'format' => 'social_proof_prompt', 'best_uk_time' => '11:00 GMT'],
        'nextdoor'       => ['label' => 'Nextdoor UK Local Neighbourhood', 'max_chars' => 1200, 'format' => 'local_community_trust', 'best_uk_time' => '17:45 GMT'],
        'eventbrite'     => ['label' => 'Eventbrite UK Discovery Feed', 'max_chars' => 2500, 'format' => 'event_listing_sync', 'best_uk_time' => '12:00 GMT'],
    ];

    /**
     * Transforms raw meeting notes, voice transcripts, or portfolio app signals
     * into scheduled, platform-specific social posts (Jesse Pujji's "Authentic Meeting-to-Social" workflow).
     *
     * @param string $clientId Strict string client identifier (e.g. 'execmind', 'bcp', 'scoretenant')
     * @param string $rawNotes Raw meeting notes, voice transcript, or operational insight
     * @param array<int, string> $platforms Target platform keys (defaults to core 5)
     * @return array{
     *     client_id: string,
     *     source_excerpt: string,
     *     core_angle: string,
     *     total_supported_channels: int,
     *     selected_channel_count: int,
     *     requires_human_approval: bool,
     *     compliance_notice: string,
     *     scheduled_posts: array<string, array{
     *         platform: string,
     *         label: string,
     *         hook: string,
     *         body: string,
     *         character_count: int,
     *         max_chars: int,
     *         hashtags: array<int, string>,
     *         scheduled_for_uk: string,
     *         status: string
     *     }>
     * }
     */
    public static function transformBriefToSocialMatrix(
        string $clientId,
        string $rawNotes,
        array $platforms = ['linkedin', 'x', 'instagram', 'tiktok', 'youtube_shorts']
    ): array {
        $cleanClientId = strtolower(trim($clientId));
        if ($cleanClientId === '') {
            throw new InvalidArgumentException('Client ID must be a non-empty string.');
        }

        $normalisedNotes = self::normaliseBritishSpelling(trim($rawNotes));
        if ($normalisedNotes === '') {
            $normalisedNotes = 'Today in our leadership review we analysed how UK organisations eliminate manual compliance bottlenecks and optimise customer trust.';
        }

        $brandMeta = AeoVisibilityEngine::PORTFOLIO_AEO_PROFILES[$cleanClientId] ?? [
            'brand_name' => ucfirst($cleanClientId),
            'vertical' => 'UK Business Operations',
            'canonical_url' => 'https://growth.execmindapp.com',
        ];

        $coreAngle = self::extractCoreInsight($normalisedNotes, $brandMeta['brand_name']);
        $hashtags = self::buildVerticalHashtags($cleanClientId);

        if ($platforms === []) {
            $platforms = ['linkedin', 'x', 'instagram', 'tiktok', 'youtube_shorts'];
        }

        $scheduledPosts = [];
        foreach ($platforms as $platformKey) {
            $key = strtolower(trim((string) $platformKey));
            $channel = self::SUPPORTED_CHANNELS[$key] ?? [
                'label' => ucfirst($key),
                'max_chars' => 1000,
                'format' => 'standard',
                'best_uk_time' => '09:00 GMT',
            ];

            $composed = self::composeForChannel(
                $key,
                $brandMeta['brand_name'],
                $brandMeta['canonical_url'],
                $coreAngle,
                $normalisedNotes,
                $hashtags,
                $channel['max_chars']
            );

            $scheduledPosts[$key] = [
                'platform' => $key,
                'label' => $channel['label'],
                'hook' => $composed['hook'],
                'body' => $composed['body'],
                'character_count' => strlen($composed['body']),
                'max_chars' => $channel['max_chars'],
                'hashtags' => $hashtags,
                'scheduled_for_uk' => 'Tomorrow at ' . $channel['best_uk_time'],
                'status' => 'queued_ready',
            ];
        }

        $complianceNotice = match ($cleanClientId) {
            'loaneasyfinance' => 'FCA Consumer Duty Notice: Capital at risk / affordability checks apply. Verify Representative APR before publishing.',
            'bcp' => 'CQC Governance Notice: Ensure no service-user or carer PII is included prior to external publication.',
            default => 'UK GDPR Standard: Review brand tone before scheduling.',
        };
        $requiresHumanApproval = in_array($cleanClientId, ['loaneasyfinance', 'bcp'], true);

        return [
            'client_id' => $cleanClientId,
            'source_excerpt' => mb_substr($normalisedNotes, 0, 140),
            'core_angle' => $coreAngle,
            'total_supported_channels' => count(self::SUPPORTED_CHANNELS),
            'selected_channel_count' => count($scheduledPosts),
            'requires_human_approval' => $requiresHumanApproval,
            'compliance_notice' => $complianceNotice,
            'scheduled_posts' => $scheduledPosts,
        ];
    }

    /**
     * Extracts the punchiest first-principles insight from raw meeting notes.
     */
    private static function extractCoreInsight(string $notes, string $brandName): string
    {
        $sentences = preg_split('/(?<=[.!?])\s+/', $notes) ?: [$notes];
        $firstSentence = trim((string) ($sentences[0] ?? $notes));
        if (mb_strlen($firstSentence) > 120) {
            $firstSentence = mb_substr($firstSentence, 0, 117) . '...';
        }
        return "{$brandName} Field Note: {$firstSentence}";
    }

    /**
     * Composes platform-specific copy respecting strict character limits.
     *
     * @param array<int, string> $hashtags
     * @return array{hook: string, body: string}
     */
    private static function composeForChannel(
        string $channelKey,
        string $brandName,
        string $url,
        string $coreAngle,
        string $notes,
        array $hashtags,
        int $maxChars
    ): array {
        $tagString = implode(' ', array_slice($hashtags, 0, 4));

        if ($channelKey === 'x' || $channelKey === 'bluesky' || $maxChars <= 300) {
            $hook = "Most UK teams overcomplicate this:";
            $snippet = mb_substr($notes, 0, 135);
            $body = "{$hook}\n\n\"{$snippet}\"\n\nHow we solve it at {$brandName}: {$url} {$tagString}";
            if (strlen($body) > $maxChars) {
                $body = substr($body, 0, $maxChars - 3) . '...';
            }
            return ['hook' => $hook, 'body' => $body];
        }

        if ($channelKey === 'youtube_shorts') {
            $hook = mb_substr($coreAngle, 0, 65);
            $body = mb_substr("{$hook} | {$brandName} #Shorts", 0, $maxChars);
            return ['hook' => $hook, 'body' => $body];
        }

        if ($channelKey === 'tiktok' || $channelKey === 'instagram') {
            $hook = "POV: We just unlocked this inside our {$brandName} strategy session:";
            $body = "{$hook}\n\n{$notes}\n\nSave this for your next review & explore live at {$url}\n\n{$tagString}";
            return ['hook' => $hook, 'body' => mb_substr($body, 0, $maxChars)];
        }

        // Default rich thought-leadership format (LinkedIn, Reddit, Ghost, etc.)
        $hook = "From our internal {$brandName} strategy notes this week:";
        $body = "{$hook}\n\n{$notes}\n\nKey takeaway for UK leaders:\n"
            . "1. Standardise your workflow so nothing slips through the cracks.\n"
            . "2. Automate repetitive follow-ups so your team focuses on high-trust relationships.\n"
            . "3. Measure real operational outcomes, not vanity metrics.\n\n"
            . "Explore how we run this live at {$brandName}: {$url}\n\n{$tagString}";

        return ['hook' => $hook, 'body' => mb_substr($body, 0, $maxChars)];
    }

    /**
     * @return array<int, string>
     */
    private static function buildVerticalHashtags(string $clientId): array
    {
        return match ($clientId) {
            'bcp'             => ['#UKHealthcare', '#CQCCompliance', '#CareHomeLeadership', '#BestCarePro'],
            'scoretenant'     => ['#UKPropTech', '#LandlordUK', '#RentersRights', '#scoreTenant'],
            'loaneasyfinance' => ['#UKFinTech', '#EthicalFinance', '#FCACompliance', '#LoanEasy'],
            'execmind'        => ['#UKFounders', '#BoardGovernance', '#ExecutiveLeadership', '#ExecMind'],
            'iaccount'        => ['#UKSME', '#CashFlowIntelligence', '#MakingTaxDigital', '#iAccount'],
            'partyplatform'   => ['#UKEvents', '#VIPCelebrations', '#EventTech', '#PartyPlatform'],
            'idecide'         => ['#DecisionScience', '#StrategicClarity', '#LeadershipTools', '#iDecide'],
            'familyplatform'  => ['#FamilyFirstUK', '#DigitalHeritage', '#PrivateFamilyVault', '#FamilyPlatform'],
            default           => ['#UKBusiness', '#SovereignAI', '#GrowthForge', '#HermesPulse'],
        };
    }

    /**
     * Enforces British English (en-GB) spelling across generated social copy.
     */
    private static function normaliseBritishSpelling(string $text): string
    {
        $replacements = [
            '/\boptimize/i'     => 'optimise',
            '/\boptimizing/i'   => 'optimising',
            '/\boptimization/i' => 'optimisation',
            '/\borganize/i'     => 'organise',
            '/\borganizing/i'   => 'organising',
            '/\borganization/i' => 'organisation',
            '/\bcolor\b/i'      => 'colour',
            '/\bbehavior\b/i'   => 'behaviour',
            '/\bcenter\b/i'     => 'centre',
        ];
        return (string) preg_replace(array_keys($replacements), array_values($replacements), $text);
    }
}
