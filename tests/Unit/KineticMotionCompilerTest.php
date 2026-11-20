<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use Src\Explainer\ExplainerStoryboard;
use Src\Explainer\KineticMotionCompiler;
use Src\Explainer\SovereignVideoRenderer;

final class KineticMotionCompilerTest extends TestCase
{
    public function test_compiles_spring_zoom_beats_and_kinetic_caption_chunks(): void
    {
        $storyboard = ExplainerStoryboard::fromArray([
            'id' => 'exp_motion_test',
            'title' => 'Motion Presets Verification',
            'app_name' => 'shared-lib',
            'aspect_ratio' => ExplainerStoryboard::ASPECT_16_9,
            'theme' => 'fintech-navy',
            'scenes' => [
                [
                    'id' => 'scene_overview',
                    'duration' => 4.0,
                    'title' => 'Overview',
                    'narration' => 'Track every payment with instant clarity across your account.',
                    'camera' => ['x' => 0.5, 'y' => 0.5, 'zoom' => 1.0],
                ],
                [
                    'id' => 'scene_focus',
                    'duration' => 4.0,
                    'title' => 'Focused Action',
                    'narration' => 'Approve transfers in one tap with confidence.',
                    'camera' => ['x' => 0.65, 'y' => 0.35, 'zoom' => 1.45],
                    'beatInterval' => 2.0,
                ],
            ],
        ]);

        $compiler = new KineticMotionCompiler();
        $timeline = $compiler->compile($storyboard);

        $camera = $timeline['motion_tracks']['camera'];
        $this->assertSame(0.0, $camera[0]['focus_dim']);
        $this->assertSame(0, $camera[0]['backdrop_blur_px']);
        $this->assertSame(KineticMotionCompiler::EASING_PRESETS['editorial'], $camera[0]['easing']);

        $this->assertSame(0.35, $camera[1]['focus_dim']);
        $this->assertSame(6, $camera[1]['backdrop_blur_px']);
        $this->assertSame(KineticMotionCompiler::EASING_PRESETS['spring_snappy'], $camera[1]['easing']);

        $beats = $timeline['motion_tracks']['beats'];
        $this->assertCount(4, $beats);
        $this->assertSame('focal_push', $beats[2]['preset']);
        $this->assertSame('sfx_riser', $beats[2]['sfx_cue']);
        $this->assertSame('ui_click', $beats[3]['sfx_cue']);

        $captions = $timeline['motion_tracks']['captions'];
        $this->assertNotEmpty($captions[0]['kinetic_chunks']);
        foreach ($captions[0]['kinetic_chunks'] as $chunk) {
            $wordCount = count(explode(' ', $chunk['text']));
            $this->assertGreaterThanOrEqual(1, $wordCount);
            $this->assertLessThanOrEqual(3, $wordCount);
            $this->assertGreaterThan($chunk['start_time'], $chunk['end_time']);
        }

        $renderer = new SovereignVideoRenderer($compiler);
        $widgetHtml = $renderer->renderInteractiveWidget($storyboard);
        $this->assertStringContainsString('data-focus-dim="0.35"', $widgetHtml);
        $this->assertStringContainsString('data-backdrop-blur="6"', $widgetHtml);
        $this->assertStringContainsString('Kinetic caption beats', $widgetHtml);
    }
}
