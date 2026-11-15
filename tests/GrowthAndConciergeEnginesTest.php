<?php

declare(strict_types=1);

namespace Tests;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Src\Concierge\HermesPulseOrchestrator;
use Src\Concierge\ReaLegacyDeconstructor;
use Src\EcosystemPromoService;
use Src\Growth\AeoVisibilityEngine;
use Src\Growth\GhostNewsletterEngine;
use Src\Growth\OmniChannelPostizEngine;
use Src\Media\ShortsAndDubbingEngine;
use Src\SeoEngine;

/**
 * Comprehensive verification suite for the 7 Open-Source Repo Engines
 * (OpenSEO, Postiz, Ghost, AI Shorts, VideoLingo, Hermes Agent, REA)
 * and the 9th Brand (GrowthForge & HermesPulse) ecosystem integration.
 */
final class GrowthAndConciergeEnginesTest extends TestCase
{
    public function testAeoVisibilityEngineAuditsAllEightPortfolioApps(): void
    {
        $allAudits = AeoVisibilityEngine::auditAllPortfolioClients();
        $this->assertCount(8, $allAudits);

        foreach (['execmind', 'bcp', 'scoretenant', 'loaneasyfinance', 'iaccount', 'partyplatform', 'idecide', 'familyplatform'] as $appKey) {
            $this->assertArrayHasKey($appKey, $allAudits);
            $audit = $allAudits[$appKey];
            $this->assertSame($appKey, $audit['client_key']);
            $this->assertGreaterThanOrEqual(80, $audit['overall_score']);
            $this->assertTrue($audit['llms_txt_ready']);
            $this->assertTrue($audit['british_english_compliant']);
            $this->assertNotEmpty($audit['keyword_opportunities']);
            $this->assertNotEmpty($audit['aeo_target_prompts']);
        }
    }

    public function testSeoEngineRendersStandardisedLlmsTxt(): void
    {
        $llmsTxt = SeoEngine::renderLlmsTxt(['client_key' => 'bcp']);
        $this->assertStringContainsString('# Best Care Professionals (BCP)', $llmsTxt);
        $this->assertStringContainsString('Care Quality Commission (CQC)', $llmsTxt);
        $this->assertStringContainsString('/playbook', $llmsTxt);
    }

    public function testOmniChannelPostizEngineSupports34ChannelsAndTransformsNotes(): void
    {
        $this->assertCount(34, OmniChannelPostizEngine::SUPPORTED_CHANNELS);

        $matrix = OmniChannelPostizEngine::transformBriefToSocialMatrix(
            'execmind',
            'We need to optimize our organization workflow and center board decisions around measurable cash-flow outcomes.',
            ['linkedin', 'x', 'instagram', 'tiktok', 'youtube_shorts']
        );

        $this->assertSame('execmind', $matrix['client_id']);
        $this->assertSame(34, $matrix['total_supported_channels']);
        $this->assertSame(5, $matrix['selected_channel_count']);

        // Verify British English normalisation ('optimize' -> 'optimise', 'organization' -> 'organisation', 'center' -> 'centre')
        $linkedinBody = $matrix['scheduled_posts']['linkedin']['body'];
        $this->assertStringContainsString('optimise', $linkedinBody);
        $this->assertStringContainsString('organisation', $linkedinBody);
        $this->assertStringContainsString('centre', $linkedinBody);

        // Verify X character ceiling
        $this->assertLessThanOrEqual(280, $matrix['scheduled_posts']['x']['character_count']);
    }

    public function testGhostNewsletterEngineComposesAuthorityIssue(): void
    {
        $issue = GhostNewsletterEngine::composeAuthorityIssue(
            'scoretenant',
            'UK Renters Rights & Verified Deposit Protection',
            [
                'Verify 24-month rent payment streaks via Open Banking.',
                'Eliminate manual landlord reference delays.',
            ]
        );

        $this->assertSame('scoretenant', $issue['client_id']);
        $this->assertStringContainsString('scoreTenant', $issue['subject_line_primary']);
        $this->assertStringContainsString('<article', $issue['html_body']);
        $this->assertStringContainsString('Open Banking', $issue['markdown_body']);
        $this->assertGreaterThanOrEqual(2, $issue['estimated_read_minutes']);
    }

    public function testShortsAndDubbingEngineGeneratesVerticalClipsAndMultilingualVtt(): void
    {
        $shorts = ShortsAndDubbingEngine::extractViralShortsManifest(
            'partyplatform',
            'How luxury UK event hosts eliminate gatecrashers with encrypted QR guest lists and instant WhatsApp RSVPs.',
            3
        );

        $this->assertSame('partyplatform', $shorts['client_id']);
        $this->assertSame('9:16', $shorts['aspect_ratio']);
        $this->assertCount(3, $shorts['clips']);
        $this->assertGreaterThanOrEqual(80, $shorts['clips'][0]['virality_score']);

        $dub = ShortsAndDubbingEngine::translateAndDubScript(
            'Please upload your Enhanced DBS certificate before starting your shift.',
            'pl-PL'
        );

        $this->assertSame('pl-PL', $dub['target_locale']);
        $this->assertStringContainsString('WEBVTT', $dub['webvtt_subtitles']);
        $this->assertStringContainsString('Witamy', $dub['dubbed_script']);
    }

    public function testHermesPulseOrchestratorExecutesAutonomousConciergePlaybooks(): void
    {
        $cycle = HermesPulseOrchestrator::executeConciergeCycle(
            'bcp',
            'Chase all healthcare assistants with DBS renewals expiring within 14 days.'
        );

        $this->assertSame('bcp', $cycle['client_id']);
        $this->assertSame('Hermes-CQCGuardian', $cycle['agent_codename']);
        $this->assertGreaterThan(10.0, $cycle['total_hours_saved_monthly']);
        $this->assertSame('dispatched_now', $cycle['active_tasks'][0]['status']);
    }

    public function testReaLegacyDeconstructorCalculatesTcoSavingsAndReplacementMap(): void
    {
        $blueprint = ReaLegacyDeconstructor::generateModernisationBlueprint(
            'Semrush, Hootsuite, Mailchimp, Legacy Care CRM',
            'healthcare_cqc',
            18,
            1950.00
        );

        $this->assertSame('Vertical Pro & Media Studio', $blueprint['recommended_tier']);
        $this->assertSame(499.00, $blueprint['sovereign_monthly_cost_gbp']);
        $this->assertGreaterThan(1000.00, $blueprint['monthly_savings_gbp']);
        $this->assertGreaterThan(40000.00, $blueprint['three_year_tco_savings_gbp']);
        $this->assertCount(5, $blueprint['module_replacement_map']);
    }

    public function testEcosystemPromoCatalogRegistersGrowthForgeAndHermesPulse(): void
    {
        $ref = new \ReflectionClass(EcosystemPromoService::class);
        /** @var array<string, array<string, mixed>> $catalog */
        $catalog = $ref->getConstant('PORTFOLIO_CATALOG');

        $this->assertArrayHasKey('growthforge', $catalog);
        $this->assertArrayHasKey('hermespulse', $catalog);
        $this->assertSame('GrowthForge', $catalog['growthforge']['name']);
        $this->assertSame('HermesPulse', $catalog['hermespulse']['name']);
    }

    public function testEnginesRejectEmptyClientIdsDefensively(): void
    {
        $this->expectException(InvalidArgumentException::class);
        OmniChannelPostizEngine::transformBriefToSocialMatrix('   ', 'Test note');
    }
}
