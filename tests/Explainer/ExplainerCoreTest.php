<?php

declare(strict_types=1);

namespace Tests\Explainer;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Src\Explainer\AudioSynthesiserBridge;
use Src\Explainer\ExplainerSecurityValidator;
use Src\Explainer\ExplainerStoryboard;
use Src\Explainer\KineticMotionCompiler;
use Src\Explainer\PortfolioExplainerRegistry;
use Src\Explainer\SovereignVideoRenderer;

/**
 * ExplainerCoreTest
 *
 * Comprehensive test suite verifying the ATG In-House Video Explainer Engine.
 * Tests storyboard validation, 60-second micro-explainer cap, security sanitisation (SSRF/XSS/Command Injection),
 * 60fps kinetic motion compilation, audio ducking, and rendering pipelines.
 */
class ExplainerCoreTest extends TestCase
{
    private function getSampleValidStoryboardData(): array
    {
        return [
            'id' => 'exp_bcp_induction',
            'title' => 'Carer Induction & Shift Confirmation',
            'app_name' => 'bcp',
            'aspect_ratio' => '16:9',
            'theme' => 'healthcare-teal',
            'scenes' => [
                [
                    'id' => 'scene_intro',
                    'duration' => 5.0,
                    'title' => 'CQC Single Assessment Verification',
                    'narration' => 'Welcome to Best Care Professionals. Here is your digital induction passport.',
                    'target_selector' => '#carer-badge',
                    'camera' => ['x' => 0.5, 'y' => 0.5, 'zoom' => 1.0],
                    'cursor' => ['action' => 'move', 'targetX' => 0.5, 'targetY' => 0.5, 'label' => 'View Passport'],
                ],
                [
                    'id' => 'scene_shift_approval',
                    'duration' => 6.0,
                    'title' => 'One-Click Shift Claim',
                    'narration' => 'Review open care home shifts and confirm attendance with a single tap.',
                    'target_selector' => '.shift-claim-btn',
                    'camera' => ['x' => 0.7, 'y' => 0.4, 'zoom' => 1.5],
                    'cursor' => ['action' => 'click', 'targetX' => 0.7, 'targetY' => 0.4, 'label' => 'Confirm Shift'],
                ],
            ],
            'metadata' => [
                'cluster' => 'healthcare',
                'compliance' => 'CQC Reg 19',
            ],
        ];
    }

    public function testValidStoryboardInstantiation(): void
    {
        $data = $this->getSampleValidStoryboardData();
        $storyboard = ExplainerStoryboard::fromArray($data);

        $this->assertSame('exp_bcp_induction', $storyboard->getId());
        $this->assertSame('Carer Induction & Shift Confirmation', $storyboard->getTitle());
        $this->assertSame('bcp', $storyboard->getAppName());
        $this->assertSame('16:9', $storyboard->getAspectRatio());
        $this->assertSame(11.0, $storyboard->getTotalDurationSeconds());
        $this->assertCount(2, $storyboard->getScenes());
    }

    public function testSarahAndChloeSixtySecondMicroExplainerCap(): void
    {
        $data = $this->getSampleValidStoryboardData();
        // Set scene duration to exceed 60s
        $data['scenes'][0]['duration'] = 45.0;
        $data['scenes'][1]['duration'] = 20.0; // Total 65s > 60s

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('violates the 60-second micro-explainer cap');
        ExplainerStoryboard::fromArray($data);
    }

    public function testMinimumDurationThresholdEnforced(): void
    {
        $data = $this->getSampleValidStoryboardData();
        $data['scenes'] = [
            [
                'id' => 'short',
                'duration' => 2.0, // Total 2s < 5s
                'title' => 'Too short',
                'narration' => 'Quick',
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('below the minimum threshold of 5.0s');
        ExplainerStoryboard::fromArray($data);
    }

    public function testUnsupportedAspectRatioThrowsException(): void
    {
        $data = $this->getSampleValidStoryboardData();
        $data['aspect_ratio'] = '4:3'; // Non-standard

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported aspect ratio');
        ExplainerStoryboard::fromArray($data);
    }

    public function testSecOpsBlocksShellCommandInjection(): void
    {
        $maliciousPayload = 'Carer Overview; rm -rf / ; echo hacked';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Security policy violation: Prohibited token ";" detected');
        ExplainerSecurityValidator::validateSafeText($maliciousPayload);
    }

    public function testSecOpsBlocksSsrfTargetingInternalInfrastructure(): void
    {
        $internalUrls = [
            'http://127.0.0.1:8000/api/keys',
            'http://localhost/admin',
            'http://169.254.169.254/latest/meta-data/',
            'file:///etc/passwd',
            'phar://bad.phar',
        ];

        foreach ($internalUrls as $url) {
            try {
                ExplainerSecurityValidator::validateSafeUrl($url);
                $this->fail("SSRF check failed to block dangerous URL: {$url}");
            } catch (InvalidArgumentException $e) {
                $this->assertTrue(true);
            }
        }
    }

    public function testSecOpsSanitizesSvgMarkup(): void
    {
        $maliciousSvg = '<svg><script>alert("XSS")</script><rect width="100" height="100"/></svg>';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Malicious token "<script" detected');
        ExplainerSecurityValidator::sanitizeSvgMarkup($maliciousSvg);
    }

    public function testSecOpsPreventsDirectoryTraversal(): void
    {
        $baseDir = sys_get_temp_dir();
        $traversalPath = '../../../../etc/passwd';

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Directory traversal attempt detected');
        ExplainerSecurityValidator::validateSafePath($traversalPath, $baseDir);
    }

    public function testKineticMotionCompilerGeneratesSixtyFpsKeyframes(): void
    {
        $storyboard = ExplainerStoryboard::fromArray($this->getSampleValidStoryboardData());
        $compiler = new KineticMotionCompiler();

        $compiled = $compiler->compile($storyboard);

        $this->assertSame(60, $compiled['fps']);
        $this->assertSame(660, $compiled['total_frames']); // 11.0s * 60 = 660 frames
        $this->assertSame(11.0, $compiled['total_duration']);
        $this->assertSame(['width' => 1920, 'height' => 1080], $compiled['dimensions']);

        $this->assertArrayHasKey('camera', $compiled['motion_tracks']);
        $this->assertArrayHasKey('cursor', $compiled['motion_tracks']);
        $this->assertArrayHasKey('captions', $compiled['motion_tracks']);

        $this->assertCount(2, $compiled['motion_tracks']['camera']);
        $this->assertCount(2, $compiled['motion_tracks']['cursor']);
    }

    public function testMobileReelDimensionsResolution(): void
    {
        $data = $this->getSampleValidStoryboardData();
        $data['aspect_ratio'] = '9:16';
        $storyboard = ExplainerStoryboard::fromArray($data);

        $compiler = new KineticMotionCompiler();
        $compiled = $compiler->compile($storyboard);

        $this->assertSame(['width' => 1080, 'height' => 1920], $compiled['dimensions']);
    }

    public function testAudioSynthesiserGeneratesWebVttAndDuckingEnvelope(): void
    {
        $storyboard = ExplainerStoryboard::fromArray($this->getSampleValidStoryboardData());
        $audioBridge = new AudioSynthesiserBridge();

        $vtt = $audioBridge->generateWebVTT($storyboard);
        $this->assertStringContainsString('WEBVTT', $vtt);
        $this->assertStringContainsString('Welcome to Best Care Professionals', $vtt);
        $this->assertStringContainsString('00:00:00.000 --> 00:00:04.800', $vtt);

        $srt = $audioBridge->generateSRT($storyboard);
        $this->assertStringContainsString('1', $srt);
        $this->assertStringContainsString('00:00:00,000 --> 00:00:04,800', $srt);

        $ducking = $audioBridge->calculateDuckingEnvelope($storyboard);
        $this->assertNotEmpty($ducking);
        $this->assertSame(0.5, $ducking[0]['volume']); // Initial bed volume
    }

    public function testSovereignVideoRendererGeneratesClientWebCodecsManifest(): void
    {
        $storyboard = ExplainerStoryboard::fromArray($this->getSampleValidStoryboardData());
        $renderer = new SovereignVideoRenderer();

        $manifest = $renderer->generateClientManifest($storyboard);

        $this->assertSame('client_webcodecs', $manifest['mode']);
        $this->assertSame(60, $manifest['render_guardrails']['fps']);
        $this->assertTrue($manifest['render_guardrails']['zero_server_load']);
        $this->assertArrayHasKey('timeline', $manifest);
        $this->assertArrayHasKey('subtitles_vtt', $manifest);
        $this->assertArrayHasKey('ducking_envelope', $manifest);
    }

    public function testSovereignVideoRendererInteractiveWidgetOutput(): void
    {
        $storyboard = ExplainerStoryboard::fromArray($this->getSampleValidStoryboardData());
        $renderer = new SovereignVideoRenderer();

        $html = $renderer->renderInteractiveWidget($storyboard);

        $this->assertStringContainsString('atg-explainer-capsule', $html);
        $this->assertStringContainsString('Carer Induction &amp; Shift Confirmation', $html);
        $this->assertStringContainsString('CQC Single Assessment Verification', $html);
        $this->assertStringContainsString('Zero-Latency Sovereign Player', $html);
    }

    public function testHeadlessSandboxedFallbackWhenFfmpegMissing(): void
    {
        $storyboard = ExplainerStoryboard::fromArray($this->getSampleValidStoryboardData());
        // Pass dummy non-existent binary to force fallback
        $renderer = new SovereignVideoRenderer(ffmpegPath: '/tmp/non_existent_binary_' . uniqid());

        $tempOutput = sys_get_temp_dir() . '/test_explainer_' . uniqid() . '.mp4';
        $result = $renderer->renderHeadlessSandboxed($storyboard, $tempOutput);

        $this->assertSame('fallback', $result['status']);
        $this->assertFileExists($result['path']);
        $this->assertStringEndsWith('.html', $result['path']);
        $this->assertSame(11.0, $result['duration_seconds']);

        @unlink($result['path']);
    }

    public function testPortfolioExplainerRegistryRendersAllEightApplications(): void
    {
        $apps = [
            'partyplatform',
            'familyplatform',
            'idecide',
            'iaccountapp',
            'tenantscore',
            'execmindapp',
            'loaneasyfinance',
            'bcp',
        ];

        foreach ($apps as $appKey) {
            $payload = \Src\Explainer\PortfolioExplainerRegistry::renderAppExplainer($appKey);

            $this->assertTrue($payload['success'], "Failed rendering explainer for {$appKey}");
            $this->assertSame($appKey, $payload['app']);
            $this->assertGreaterThanOrEqual(5.0, $payload['total_duration']);
            $this->assertLessThanOrEqual(60.0, $payload['total_duration']);
            $this->assertStringContainsString('min-h-[44px] min-w-[44px]', $payload['widget_html']);
            $this->assertStringContainsString('whitespace-nowrap flex-shrink-0', $payload['widget_html']);
            $this->assertStringContainsString('atg-btn-spinner', $payload['widget_html']);
            $this->assertTrue($payload['has_complete_narrative_arc'], "App {$appKey} missing complete 3-part narrative arc");
        }
    }

    public function testAll8PortfolioAppsImplementCompleteNarrativeArc(): void
    {
        $catalog = PortfolioExplainerRegistry::getMasterCatalog();
        $this->assertCount(8, $catalog);

        foreach ($catalog as $appKey => $blueprint) {
            $storyboard = ExplainerStoryboard::fromArray($blueprint);
            $this->assertTrue(
                $storyboard->hasCompleteNarrativeArc(),
                "Catalog app {$appKey} must contain Problem, Solution, and Call to Action stages."
            );

            $arc = $storyboard->getNarrativeArc();
            $this->assertArrayHasKey(ExplainerStoryboard::STAGE_PROBLEM, $arc);
            $this->assertArrayHasKey(ExplainerStoryboard::STAGE_SOLUTION, $arc);
            $this->assertArrayHasKey(ExplainerStoryboard::STAGE_CTA, $arc);
        }
    }

    public function testVisualFormatCustomizationAndRendering(): void
    {
        $formats = [
            ExplainerStoryboard::FORMAT_MOTION_GRAPHICS,
            ExplainerStoryboard::FORMAT_WHITEBOARD,
            ExplainerStoryboard::FORMAT_ANIMATION_2D_3D,
            ExplainerStoryboard::FORMAT_MIXED_MEDIA,
        ];

        $renderer = new SovereignVideoRenderer();

        foreach ($formats as $fmt) {
            $storyboard = PortfolioExplainerRegistry::buildStoryboard('partyplatform', [
                'visual_format' => $fmt,
            ]);

            $this->assertSame($fmt, $storyboard->getVisualFormat());
            $html = $renderer->renderInteractiveWidget($storyboard);

            $this->assertStringContainsString('data-format="' . $fmt . '"', $html);
            $this->assertStringContainsString('atg-narrative-arc-tracker', $html);
        }
    }

    public function testAdminStudioSectionRendersWithArcTrackerAndFormatSelector(): void
    {
        // The legacy capsule is still reachable via ?legacy_explainer=1 (World Engine is the default).
        $_GET['legacy_explainer'] = '1';
        try {
            $html = PortfolioExplainerRegistry::renderAdminStudioSection('partyplatform');
        } finally {
            unset($_GET['legacy_explainer']);
        }

        $this->assertStringContainsString('id="atg-admin-explainer-studio"', $html);
        $this->assertStringContainsString('id="atg-admin-app-select"', $html);
        $this->assertStringContainsString('id="atg-admin-format-select"', $html);
        $this->assertStringContainsString('atg-narrative-arc-tracker', $html);
        $this->assertStringContainsString('🚨 The Problem', $html);
        $this->assertStringContainsString('💡 The Solution', $html);
        $this->assertStringContainsString('🚀 Call to Action', $html);
    }

    public function testFallbackBranchNeutralisesPathTraversal(): void
    {
        $renderer = new SovereignVideoRenderer(ffmpegPath: '/non/existent/binary');
        $storyboard = PortfolioExplainerRegistry::buildStoryboard('familyplatform');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Directory traversal attempt detected');

        $renderer->renderHeadlessSandboxed($storyboard, '/tmp/../../etc/passwd.mp4');
    }

    public function testFallbackBranchGeneratesValidHtmlWithSafePath(): void
    {
        $renderer = new SovereignVideoRenderer(ffmpegPath: '/non/existent/binary');
        $storyboard = PortfolioExplainerRegistry::buildStoryboard('familyplatform');
        $tempOutput = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'test_explainer_fallback_' . bin2hex(random_bytes(4)) . '.mp4';

        try {
            $result = $renderer->renderHeadlessSandboxed($storyboard, $tempOutput);
            $this->assertSame('fallback', $result['status']);
            $this->assertFileExists($result['path']);
            $this->assertStringEndsWith('.html', $result['path']);
            $content = (string)file_get_contents($result['path']);
            $this->assertStringContainsString('FamilyPlatform', $content);
            $this->assertStringContainsString('atg-narrative-arc-tracker', $content);
        } finally {
            $htmlPath = preg_replace('/\.mp4$/i', '.html', $tempOutput) ?: $tempOutput . '.html';
            if (file_exists($htmlPath)) {
                @unlink($htmlPath);
            }
        }
    }
}
