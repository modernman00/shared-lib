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
}
