<?php

declare(strict_types=1);

namespace Tests\Explainer;

use PHPUnit\Framework\TestCase;
use Src\Explainer\AudioSynthesiserBridge;
use Src\Explainer\ExplainerStoryboard;
use Src\Explainer\ExplainerStyleAtlas;
use Src\Explainer\SovereignVideoRenderer;

class ExplainerStyleAtlasTest extends TestCase
{
    private function buildSampleStoryboard(): ExplainerStoryboard
    {
        return ExplainerStoryboard::fromArray([
            'id' => 'atlas_demo_01',
            'title' => 'Organise Your Finances With Clarity',
            'app_name' => 'iaccount',
            'aspect_ratio' => ExplainerStoryboard::ASPECT_16_9,
            'theme' => 'sovereign-fintech',
            'scenes' => [
                [
                    'id' => 'sc_problem',
                    'duration' => 4.0,
                    'title' => 'Manual Spreadsheets Break',
                    'narration' => 'Stop losing 100% of your Sunday to manual spreadsheets.',
                    'stage' => ExplainerStoryboard::STAGE_PROBLEM,
                    'camera' => ['x' => 0.5, 'y' => 0.5, 'zoom' => 1.0],
                    'cursor' => ['action' => 'click', 'targetX' => 0.6, 'targetY' => 0.4],
                ],
                [
                    'id' => 'sc_solution',
                    'duration' => 5.0,
                    'title' => 'Real-Time UK Ledger',
                    'narration' => 'Automate categorisation with zero SaaS lock-in.',
                    'stage' => ExplainerStoryboard::STAGE_SOLUTION,
                    'camera' => ['x' => 0.5, 'y' => 0.5, 'zoom' => 1.1],
                ],
            ],
        ]);
    }

    public function testStyleAtlasContainsCuratedPresetsAndFallsBackSafely(): void
    {
        $all = ExplainerStyleAtlas::all();
        $this->assertGreaterThanOrEqual(10, count($all));
        $this->assertTrue(ExplainerStyleAtlas::has('sovereign-fintech'));
        $this->assertFalse(ExplainerStyleAtlas::has('non-existent-style'));

        $fallback = ExplainerStyleAtlas::get('non-existent-style');
        $this->assertSame(ExplainerStyleAtlas::DEFAULT_STYLE, $fallback['id']);

        $fintech = ExplainerStyleAtlas::byCategory('fintech');
        $this->assertArrayHasKey('sovereign-fintech', $fintech);

        $summaries = ExplainerStyleAtlas::listSummaries();
        $this->assertNotEmpty($summaries);
        $this->assertArrayHasKey('primary', $summaries[0]);

        $prompt = ExplainerStyleAtlas::toPromptString('blueprint-architect');
        $this->assertStringContainsString('Blueprint Architect', $prompt);
        $this->assertStringContainsString('#0c2340', $prompt);
    }

    public function testWordTimelineDeterministicAndExternalSync(): void
    {
        $storyboard = $this->buildSampleStoryboard();
        $bridge = new AudioSynthesiserBridge();

        $deterministic = $bridge->buildWordTimeline($storyboard);
        $this->assertNotEmpty($deterministic);
        $this->assertSame('Stop', $deterministic[0]['word']);
        $this->assertSame(0, $deterministic[0]['scene_index']);

        // '100%' and 'SaaS' should be flagged as emphasis words
        $emphasisWords = array_values(array_filter(
            $deterministic,
            static fn (array $w): bool => $w['is_emphasis'] === true
        ));
        $this->assertNotEmpty($emphasisWords);

        // External word-level timestamps (e.g. Gemini / ElevenLabs)
        $external = $bridge->buildWordTimeline($storyboard, [
            ['word' => 'Hello', 'start' => 0.2, 'end' => 0.5],
            ['word' => 'UK', 'start' => 4.2, 'end' => 4.6],
        ]);
        $this->assertCount(2, $external);
        $this->assertSame(0, $external[0]['scene_index']);
        $this->assertSame(1, $external[1]['scene_index']);
        $this->assertTrue($external[1]['is_emphasis']);
    }

    public function testRendererManifestAndCanvasStudioIncludeAtlasAndWordTimeline(): void
    {
        $storyboard = $this->buildSampleStoryboard();
        $renderer = new SovereignVideoRenderer();

        $manifest = $renderer->generateClientManifest($storyboard, 'kinetic-mono');
        $this->assertSame('kinetic-mono', $manifest['style_preset']['id']);
        $this->assertNotEmpty($manifest['word_timeline']);

        $html = $renderer->renderCanvasStudio($storyboard, 'sovereign-fintech');
        $this->assertStringContainsString('atg-canvas-studio', $html);
        $this->assertStringContainsString('atg-studio-canvas', $html);
        $this->assertStringContainsString('atg-studio-style-select', $html);
        $this->assertStringContainsString('min-h-[44px]', $html);
        $this->assertStringContainsString('sovereign-fintech', $html);
    }
}
