<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Src\Exceptions\ValidationException;
use Src\Media\SovereignVideoJobHandler;

class SovereignVideoJobHandlerTest extends TestCase
{
    public function testModule1StoryboardManifestGeneratesDeterministicScenes(): void
    {
        $manifest = SovereignVideoJobHandler::buildStoryboardManifest(
            'Grandad Arthur arrived in London in 1962. The family gathered at Kensington Manor. Celebrating sixty years of heritage.',
            'heritage_gold',
            12.0
        );

        $this->assertSame('heritage_gold', $manifest['template']);
        $this->assertCount(3, $manifest['scenes']);
        $this->assertEqualsWithDelta(12.0, $manifest['totalDurationSec'], 0.05);
        $this->assertSame('Grandad Arthur arrived in London in', $manifest['scenes'][0]['headline']);
    }

    public function testModule1RejectsEmptyOrOversizedPrompt(): void
    {
        $this->expectException(ValidationException::class);
        SovereignVideoJobHandler::buildStoryboardManifest('   ');
    }

    public function testModule2ParallaxFilterGraphEnforcesEvenDimensionsAndBounds(): void
    {
        $vf = SovereignVideoJobHandler::buildParallaxFilterGraph(1921, 1081, 4.0, 'zoom_in');
        $this->assertStringContainsString('s=1920x1080', $vf);
        $this->assertStringContainsString('zoompan=', $vf);

        $this->expectException(ValidationException::class);
        SovereignVideoJobHandler::buildParallaxFilterGraph(7680, 4320, 4.0);
    }

    public function testModule3AvatarVisemeTimelineMapsAmplitudesAndBlinks(): void
    {
        $samples = array_fill(0, 75, 0.45);
        $samples[0] = 0.01;
        $timeline = SovereignVideoJobHandler::buildAvatarVisemeTimeline($samples, 0.05);

        $this->assertCount(75, $timeline);
        $this->assertSame('sil', $timeline[0]['viseme']);
        $this->assertNotSame('sil', $timeline[1]['viseme']);
        $this->assertTrue($timeline[70]['blink']);
    }

    public function testModule4KaraokeSrtFormatsWordTimestamps(): void
    {
        $srt = SovereignVideoJobHandler::buildKaraokeSrt([
            ['word' => 'Welcome', 'startSec' => 0.0, 'endSec' => 0.4],
            ['word' => 'home', 'startSec' => 0.4, 'endSec' => 0.9],
            ['word' => 'family', 'startSec' => 0.9, 'endSec' => 1.5],
        ], 2);

        $this->assertStringContainsString('00:00:00,000 --> 00:00:00,900', $srt);
        $this->assertStringContainsString('Welcome home', $srt);
        $this->assertStringContainsString('family', $srt);
    }

    public function testModule5BritishTtsDescriptorSupportsProfiles(): void
    {
        $tts = SovereignVideoJobHandler::buildBritishTtsDescriptor('Cheerio everyone!', 'elders', 1.1, 2.0);
        $this->assertSame('elders', $tts['profile']);
        $this->assertSame('en-GB', $tts['accent']);
        $this->assertGreaterThan(104.0, $tts['pitchHz']);
        $this->assertSame('Cheerio everyone!', $tts['text']);
    }

    public function testModule6VoiceCloningBlocksMissingGdprArticle9Consent(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('GDPR Article 9');
        SovereignVideoJobHandler::buildVoiceDubbingFilterGraph(false, 'user_123', 1.05, 50.0);
    }

    public function testModule6VoiceCloningSucceedsWithExplicitGdprArticle9Consent(): void
    {
        $af = SovereignVideoJobHandler::buildVoiceDubbingFilterGraph(true, 'user_123', 1.05, 50.0);
        $this->assertStringContainsString('asetrate=44100*1.0500', $af);
        $this->assertStringContainsString('equalizer=f=1150', $af);
    }

    public function testModule7SilenceDetectionFindsDeadAir(): void
    {
        // 1.0s @ 8000Hz: 0.5s silence + 0.5s speech
        $samples = array_merge(array_fill(0, 4000, 0.001), array_fill(0, 4000, 0.4));
        $intervals = SovereignVideoJobHandler::detectSilencePcm($samples, 8000, 0.02, 0.3);

        $this->assertCount(1, $intervals);
        $this->assertEqualsWithDelta(0.0, $intervals[0]['startSec'], 0.01);
        $this->assertEqualsWithDelta(0.5, $intervals[0]['endSec'], 0.06);
    }

    public function testModule8ChromaKeyFilterGraphValidatesHexColour(): void
    {
        $vf = SovereignVideoJobHandler::buildChromaKeyFilterGraph('#00b140', 0.18, 0.05);
        $this->assertSame('chromakey=0x00B140:0.180:0.050', $vf);

        $this->expectException(ValidationException::class);
        SovereignVideoJobHandler::buildChromaKeyFilterGraph('not-hex');
    }

    public function testModule9SubjectTrackingClampsToFrameBounds(): void
    {
        $res = SovereignVideoJobHandler::validateTrackingRegion(1800, 1000, 400, 300, 1920, 1080, 'privacy_blur');
        $this->assertLessThanOrEqual(1920, $res['x'] + $res['width']);
        $this->assertLessThanOrEqual(1080, $res['y'] + $res['height']);
        $this->assertStringStartsWith('delogo=', $res['filterGraph']);
    }

    public function testModule10LanczosUpscaleFilterGraph(): void
    {
        $vf = SovereignVideoJobHandler::buildLanczosUpscaleFilterGraph(3840, 2160, 0.9);
        $this->assertStringContainsString('scale=3840:2160:flags=lanczos', $vf);
        $this->assertStringContainsString('unsharp=5:5:0.90', $vf);
    }

    public function testModule11ColourMatchFilterGraph(): void
    {
        $vf = SovereignVideoJobHandler::buildColourMatchFilterGraph('heritage_gold', 0.9);
        $this->assertStringContainsString('colorbalance=', $vf);
        $this->assertStringContainsString('eq=gamma=', $vf);
    }

    public function testModule12NoiseReductionFilterGraph(): void
    {
        $af = SovereignVideoJobHandler::buildNoiseReductionFilterGraph(50, -30.0);
        $this->assertStringContainsString('bandreject=f=50', $af);
        $this->assertStringContainsString('afftdn=nf=-30.0', $af);
    }

    public function testZeroSsrfAndPathTraversalBlocksExternalUrlsInFfmpegCommand(): void
    {
        $this->expectException(ValidationException::class);
        SovereignVideoJobHandler::buildDeterministicFfmpegCommand('https://evil.example/ssrf.mp4', '/tmp/out.mp4');
    }

    public function testDeterministicFfmpegCommandEscapesArguments(): void
    {
        $cmd = SovereignVideoJobHandler::buildDeterministicFfmpegCommand(
            'uploads/clip1.mp4',
            'uploads/out1.mp4',
            'scale=1920:1080:flags=lanczos',
            'afftdn=nf=-28.0'
        );

        $this->assertStringContainsString("ffmpeg -y -i 'uploads/clip1.mp4'", $cmd);
        $this->assertStringContainsString("-vf 'scale=1920:1080:flags=lanczos'", $cmd);
        $this->assertStringContainsString("-af 'afftdn=nf=-28.0'", $cmd);
        $this->assertStringContainsString("'uploads/out1.mp4'", $cmd);
    }

    public function testModule4KaraokeSrtHandlesMalformedWordEntriesGracefully(): void
    {
        $srt = SovereignVideoJobHandler::buildKaraokeSrt([
            'not_an_array',
            ['startSec' => 0.0, 'endSec' => 0.4],
            ['word' => 'Safe', 'startSec' => 0.4, 'endSec' => 0.9],
        ], 3);

        $this->assertStringContainsString('Safe', $srt);
    }

    public function testNleProjectSanitisationAndFilterGraphs(): void
    {
        $project = SovereignVideoJobHandler::sanitiseNleProjectPayload([
            'aspectRatio' => '9:16',
            'clips' => [
                [
                    'id' => 'clip!@#_1',
                    'track' => 'V2',
                    'title' => '<script>xss</script>Hero Clip',
                    'startSec' => 1.5,
                    'durationSec' => 5.0,
                    'reversed' => true,
                    'mirrored' => true,
                    'transform' => ['scaleX' => 5.0, 'blendMode' => 'multiply', 'opacity' => 0.85],
                    'mask' => ['type' => 'circle', 'feather' => 12.0, 'invert' => true, 'size' => 0.7],
                    'speed' => ['rate' => 4.0, 'pitchPreserve' => true],
                ],
            ],
        ]);

        $this->assertSame('9:16', $project['aspectRatio']);
        $this->assertSame('clip_1', $project['clips'][0]['id']);
        $this->assertSame('xssHero Clip', $project['clips'][0]['title']);
        $this->assertSame(3.0, $project['clips'][0]['transform']['scaleX']);
        $this->assertSame('multiply', $project['clips'][0]['transform']['blendMode']);
        $this->assertSame('circle', $project['clips'][0]['mask']['type']);

        $maskFg = SovereignVideoJobHandler::buildMaskFilterGraph('circle', 8.0, true, 1280, 720);
        $this->assertStringContainsString('yuva420p', $maskFg);
        $this->assertStringContainsString('boxblur=2.0:1', $maskFg);

        $speedFg = SovereignVideoJobHandler::buildSpeedCurveFilterGraph(4.0, true);
        $this->assertStringContainsString('setpts=0.2500*PTS', $speedFg['video']);
        $this->assertSame('atempo=2.0,atempo=2.0000', $speedFg['audio']);

        $adjFg = SovereignVideoJobHandler::buildAdjustmentFilterGraph(['brightness' => 10, 'contrast' => 20, 'saturation' => 15, 'sharpness' => 25, 'exposure' => 10, 'tint' => 12, 'highlights' => 20]);
        $this->assertStringContainsString('eq=brightness=0.160:contrast=1.200:saturation=1.150', $adjFg);
        $this->assertStringContainsString('hue=h=12.00', $adjFg);
        $this->assertStringContainsString('colorlevels=', $adjFg);
        $this->assertStringContainsString('unsharp=5:5:0.50', $adjFg);
    }

    public function testCapabilities13To18MultiTrackCompositingAndOverlays(): void
    {
        // Cap 13 & 15 & 16 & 18: dynamic tracks (V3, ADJ1), add blend mode, linear_gradient mask, lighting
        $project = SovereignVideoJobHandler::sanitiseNleProjectPayload([
            'aspectRatio' => '16:9',
            'clips' => [
                [
                    'id' => 'ov_1',
                    'track' => 'V3',
                    'type' => 'overlay',
                    'title' => 'Bonfire Flames',
                    'transform' => ['blendMode' => 'add', 'opacity' => 0.9],
                    'mask' => ['type' => 'linear_gradient', 'feather' => 16.0, 'invert' => false],
                    'lighting' => ['strobeFrequency' => 6.0, 'flickerIntensity' => 0.5, 'glowPreset' => 'warm_glowing_tan', 'glowIntensity' => 0.7],
                ],
                [
                    'id' => 'adj_1',
                    'track' => 'ADJ1',
                    'type' => 'adjustment',
                    'title' => 'Global Grade',
                ],
            ],
        ]);
        $this->assertSame('V3', $project['clips'][0]['track']);
        $this->assertSame('overlay', $project['clips'][0]['type']);
        $this->assertSame('add', $project['clips'][0]['transform']['blendMode']);
        $this->assertSame('linear_gradient', $project['clips'][0]['mask']['type']);
        $this->assertSame('warm_glowing_tan', $project['clips'][0]['lighting']['glowPreset']);
        $this->assertSame('ADJ1', $project['clips'][1]['track']);

        // Cap 14: Atmospheric overlay library & filtergraphs
        $lib = SovereignVideoJobHandler::getAtmosphericOverlayLibrary();
        $this->assertCount(5, $lib);
        $this->assertStringContainsString('noise=', SovereignVideoJobHandler::buildAtmosphericOverlayFilterGraph('bonfire_flames'));

        // Cap 15: Blend mode filtergraph
        $this->assertSame('blend=all_mode=addition:all_opacity=0.800', SovereignVideoJobHandler::buildBlendModeFilterGraph('add', 0.8));

        // Cap 16: Linear gradient feathered mask
        $gradMask = SovereignVideoJobHandler::buildMaskFilterGraph('linear_gradient', 12.0, false, 1280, 720);
        $this->assertStringContainsString('255*(1-(Y/720))', $gradMask);
        $this->assertStringContainsString('boxblur=3.0:1', $gradMask);

        // Cap 18: Procedural lighting & mood shaders
        $lightFg = SovereignVideoJobHandler::buildLightingMoodFilterGraph(6.0, 0.5, 'warm_glowing_tan', 0.7);
        $this->assertStringContainsString('sin(2*PI*6.00*t)', $lightFg);
        $this->assertStringContainsString('colorbalance=', $lightFg);
    }

    public function test_adjustment_temp_vignette_and_stabilise_filtergraphs(): void
    {
        $adjFg = SovereignVideoJobHandler::buildAdjustmentFilterGraph([
            'temp' => 25,
            'vignette' => 0.5,
            'sharpness' => 25,
            'grain' => 0.5,
        ]);
        $this->assertStringContainsString('colorbalance=rs=', $adjFg);
        $this->assertStringContainsString('vignette=angle=', $adjFg);
        $this->assertStringContainsString('noise=alls=', $adjFg);

        $deshake = SovereignVideoJobHandler::buildStabiliseFilterGraph(6.0, 12.0, false);
        $this->assertSame('deshake=rx=24:ry=24:edge=mirror', $deshake);

        $twoPass = SovereignVideoJobHandler::buildStabiliseFilterGraph(50.0, 99.0, true);
        $this->assertSame('vidstabdetect=shakiness=10:accuracy=10,vidstabtransform=smoothing=30:zoom=2:optzoom=1', $twoPass);

        $grid = SovereignVideoJobHandler::buildMultiGridFilterGraph(4, 'grid_2x2', 1280, 720);
        $this->assertSame('xstack=inputs=4:layout=0_0|640_0|0_360|640_360', $grid);

        $xfade = SovereignVideoJobHandler::buildXfadeTransitionGraph('dissolve', 0.8, 4.0);
        $this->assertSame('xfade=transition=dissolve:duration=0.80:offset=4.00', $xfade);

        $i2v = SovereignVideoJobHandler::buildImageToVideoFilterGraph('social_content', 720, 1280, 5.0);
        $this->assertStringContainsString('zoompan=', $i2v);
        $this->assertStringContainsString('vignette=angle=', $i2v);

        $adapter = SovereignVideoJobHandler::resolveGenerativeVideoAdapter('Family celebration in London', ['style' => 'storytelling']);
        $this->assertTrue($adapter['fallbackUsed']);
        $this->assertSame('deterministic_zero_saas', $adapter['mode']);
    }

    public function test_generative_adapter_blocks_non_https_endpoint(): void
    {
        $this->expectException(ValidationException::class);
        SovereignVideoJobHandler::resolveGenerativeVideoAdapter('Test', [
            'externalGpuEndpoint' => 'http://127.0.0.1:8188/generate',
        ]);
    }

    public function test_commercial_license_issuance_verification_sdk_manifest_and_api_job(): void
    {
        $secret = 'sovereign_master_license_secret_2026_uk';
        $token = SovereignVideoJobHandler::issueLicenseToken(
            'Britannia Media Group',
            'enterprise_whitelabel',
            ['studio.britannia.co.uk'],
            90,
            $secret
        );

        $this->assertStringStartsWith('svk_v1.', $token);

        $verified = SovereignVideoJobHandler::verifyLicenseToken($token, $secret, 'studio.britannia.co.uk');
        $this->assertSame('Britannia Media Group', $verified['org']);
        $this->assertSame('enterprise_whitelabel', $verified['tier']);
        $this->assertTrue($verified['capabilities']['allowWhitelabelDownload']);

        $sdk = SovereignVideoJobHandler::buildDownloadableSdkManifest('Britannia Media Group', 'enterprise_whitelabel');
        $this->assertSame('@modernman00/sovereign-video-studio-sdk', $sdk['package']);
        $this->assertSame('3.1.0', $sdk['openApi']['openapi']);

        $job = SovereignVideoJobHandler::handleApiJobRequest([
            'license_token' => $token,
            'origin_domain' => 'studio.britannia.co.uk',
            'action'        => 'multi_grid',
            'params'        => ['count' => 4, 'layout' => 'grid_2x2', 'width' => 1280, 'height' => 720],
        ], $secret);

        $this->assertSame(200, $job['status']);
        $this->assertSame('Britannia Media Group', $job['licensee']);
        $this->assertStringContainsString('xstack=inputs=4', $job['result']['filterGraph']);
    }

    public function test_license_token_rejects_unauthorised_domain_or_tampered_signature(): void
    {
        $secret = 'sovereign_master_license_secret_2026_uk';
        $token = SovereignVideoJobHandler::issueLicenseToken(
            'Britannia Media Group',
            'growth',
            ['allowed.co.uk'],
            30,
            $secret
        );

        $this->expectException(ValidationException::class);
        SovereignVideoJobHandler::verifyLicenseToken($token, $secret, 'evil.example.com');
    }

    public function test_container_codec_compliance_and_5_gate_agent_certification_checklist(): void
    {
        $mp4 = SovereignVideoJobHandler::buildCompliantContainerExportCommand('uploads/raw.mp4', 'uploads/master.mp4', 'mp4', 'h264', '16:9', 60, 1080);
        $this->assertStringContainsString('scale=1920:1080:flags=lanczos,fps=60', $mp4);
        $this->assertStringContainsString('-movflags +faststart', $mp4);
        $this->assertStringContainsString('loudnorm=I=-14.0:TP=-1.0', $mp4);

        $mov = SovereignVideoJobHandler::buildCompliantContainerExportCommand('uploads/raw.mp4', 'uploads/master.mov', 'mov', 'prores', '9:16', 24, 1920);
        $this->assertStringContainsString('prores_ks', $mov);
        $this->assertStringContainsString('scale=1080:1920:flags=lanczos,fps=24', $mov);

        $cert = SovereignVideoJobHandler::runFullCertificationSuite();
        $this->assertTrue($cert['CERTIFIED']);
        $this->assertTrue($cert['gates']['schema_valid']);
        $this->assertTrue($cert['gates']['media_integrity']);
        $this->assertTrue($cert['gates']['ai_quality_threshold']);
        $this->assertTrue($cert['gates']['sla_compliance']);
        $this->assertTrue($cert['gates']['resource_cleanup']);
    }

    public function test_generative_queue_retry_fallback_and_50_job_stability_benchmark(): void
    {
        $calls = 0;
        $retryOk = SovereignVideoJobHandler::executeGenerativeQueueJob(
            static function () use (&$calls): array {
                $calls++;
                if ($calls < 2) {
                    throw new \RuntimeException('Transient GPU contention');
                }
                return ['rendered' => true];
            },
            ['maxRetries' => 3, 'backoffMicros' => 0]
        );
        $this->assertTrue($retryOk['completed']);
        $this->assertFalse($retryOk['fallbackUsed']);
        $this->assertSame(2, $retryOk['attempts']);

        $bench = SovereignVideoJobHandler::runStabilityAndPerformanceBenchmark(50);
        $this->assertSame(50, $bench['jobsRun']);
        $this->assertSame(100.0, $bench['completionRatePct']);
        $this->assertTrue($bench['zeroFatalCrashes']);
        $this->assertTrue($bench['memoryWithinFivePct']);
    }

    public function test_broadcast_colour_match_and_continuity_lock_payload_validation(): void
    {
        $fg = SovereignVideoJobHandler::buildBroadcastColourMatchFilterGraph([
            'meanR' => 160,
            'meanB' => 100,
            'meanLuma' => 144,
            'stdLuma' => 52,
        ]);
        $this->assertStringContainsString('eq=brightness=', $fg);
        $this->assertStringContainsString('colorbalance=rs=', $fg);

        // Extreme clamping check
        $clampedFg = SovereignVideoJobHandler::buildBroadcastColourMatchFilterGraph([
            'meanR' => 9999,
            'meanB' => -500,
            'meanLuma' => 999,
            'stdLuma' => 0,
        ]);
        $this->assertSame('eq=brightness=0.179:contrast=0.854,colorbalance=rs=0.150:bs=-0.150', $clampedFg);

        $sanitisedLock = SovereignVideoJobHandler::validateContinuityLockPayload([
            'enabled'     => true,
            'lutPreset'   => 'invalid_evil_lut;rm -rf /',
            'aspectRatio' => '21:9',
            'vignette'    => 2.5,
            'styleSeed'   => -10,
        ]);
        $this->assertTrue($sanitisedLock['enabled']);
        $this->assertSame('heritage_gold', $sanitisedLock['lutPreset']);
        $this->assertSame('16:9', $sanitisedLock['aspectRatio']);
        $this->assertSame(1.0, $sanitisedLock['vignette']);
        $this->assertSame(1, $sanitisedLock['styleSeed']);
    }

    public function test_license_domain_lock_and_tier_entitlements_fail_closed(): void
    {
        $secret = 'test_signing_secret_2026';
        $starterToken = SovereignVideoJobHandler::issueLicenseToken('Starter Studio', 'starter', ['partner.example.com'], 3600, $secret);

        // Missing origin on domain-locked token must fail closed
        try {
            SovereignVideoJobHandler::verifyLicenseToken($starterToken, $secret, null);
            $this->fail('Expected ValidationException when origin is missing on domain-locked token.');
        } catch (ValidationException $e) {
            $this->assertStringContainsString('Origin domain', $e->getMessage());
        }

        // Starter tier requesting 4K width must fail with ENTITLEMENT VIOLATION
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('ENTITLEMENT VIOLATION');
        SovereignVideoJobHandler::handleApiJobRequest([
            'license_token' => $starterToken,
            'origin_domain' => 'partner.example.com',
            'action'        => 'multi_grid',
            'params'        => ['width' => 3840, 'height' => 2160],
        ], $secret);
    }

    public function test_diagram_payload_motion_sfx_and_loudness_normalisation(): void
    {
        $payload = SovereignVideoJobHandler::buildDiagramPayload(
            'cashflow_pareto',
            'Monthly Outflow <script>alert(1)</script>',
            ['Housing', 'Food', 'Travel'],
            [1400, 450, 220],
            '#10b981'
        );
        $this->assertSame('cashflow_pareto', $payload['chartType']);
        $this->assertStringNotContainsString('<script>', $payload['title']);
        $this->assertSame('#10b981', $payload['accentHex']);

        $sfx = SovereignVideoJobHandler::extractMotionSfxEvents([
            ['id' => 'diag_1', 'type' => 'chart', 'startSec' => 0.0, 'durationSec' => 4.0, 'chartData' => ['chartType' => '168h_ring']],
        ], 2.05, 1.95);
        $this->assertCount(1, $sfx);
        $this->assertSame('chime', $sfx[0]['type']);

        $filter = SovereignVideoJobHandler::buildLoudnessNormalisationFilter();
        $this->assertStringContainsString('loudnorm=I=-14:TP=-1.0', $filter);
    }

    public function test_sovereign_video_renderer_handwriting_diagrams_and_variety_audit(): void
    {
        $renderer = new \Src\Explainer\SovereignVideoRenderer();

        $hwHalf = $renderer->renderHandwritingRevealSvg('Savings +24%', 0.5);
        $this->assertStringContainsString('stroke-dasharray="320 640"', $hwHalf);

        foreach (['diagram', '168h_ring', 'time_debt', 'cashflow_pareto', 'decision_tree'] as $type) {
            $svg = $renderer->renderSelfDrawingDiagramSvg($type, [
                'title' => 'Test ' . $type,
                'labels' => ['A', 'B', 'C'],
                'values' => [50, 30, 20],
            ], 0.75);
            $this->assertStringContainsString('data-diagram-type="' . $type . '"', $svg);
        }

        $audit = $renderer->auditStoryboardVariety([
            ['motion' => 'push_in', 'palette' => 'heritage_gold', 'transition' => 'whip_pan', 'cameraAngle' => 'wide_establishing'],
            ['motion' => 'push_in', 'palette' => 'heritage_gold', 'transition' => 'whip_pan', 'cameraAngle' => 'wide_establishing'],
        ]);
        $this->assertSame(4, $audit['adjustments']);
        $this->assertNotSame($audit['scenes'][0]['transition'], $audit['scenes'][1]['transition']);
        $this->assertNotSame($audit['scenes'][0]['cameraAngle'], $audit['scenes'][1]['cameraAngle']);
    }
}
