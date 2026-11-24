<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\Cinematic\AudioFirstPlanner;
use Src\Cinematic\DirectorApi;
use Src\Cinematic\IdentityLock;
use Src\Cinematic\ShotGraph;
use Src\Cinematic\ShotRenderer;
use Src\Exceptions\ValidationException;

final class CinematicRendererTest extends TestCase
{
    private string $tempOut;

    protected function setUp(): void
    {
        $this->tempOut = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'sce_test_' . bin2hex(random_bytes(4));
        mkdir($this->tempOut, 0700, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempOut)) {
            foreach (scandir($this->tempOut) ?: [] as $f) {
                if ($f !== '.' && $f !== '..') {
                    @unlink($this->tempOut . DIRECTORY_SEPARATOR . $f);
                }
            }
            @rmdir($this->tempOut);
        }
    }

    public function testFallbackWhenFfmpegMissingStillWritesSrt(): void
    {
        $graph = ShotGraph::fromArray([
            ['id' => 's1', 'durationSec' => 2.0, 'camera' => 'zoom_in', 'prompt' => '@hero smiles'],
        ], ['hero']);
        $timeline = AudioFirstPlanner::plan($graph, []);
        $lock = IdentityLock::fromArray(['enabled' => true, 'characterId' => 'hero']);

        $renderer = new ShotRenderer('/nonexistent/ffmpeg');
        $res = $renderer->renderFilm($graph, $timeline, $lock, $this->tempOut, 'fallback_film', 320, 180);

        $this->assertSame('fallback', $res['status']);
        $this->assertSame('ffmpeg_missing', $res['reason'] ?? '');
        $this->assertNull($res['videoPath']);
        $this->assertFileExists($res['srtPath']);
        $this->assertStringContainsString('hero smiles', (string) file_get_contents($res['srtPath']));
    }

    public function testRejectsUnsafeBaseName(): void
    {
        $graph = ShotGraph::fromArray([
            ['id' => 's1', 'durationSec' => 2.0, 'camera' => 'static', 'prompt' => 'hello'],
        ], []);
        $timeline = AudioFirstPlanner::plan($graph, []);
        $lock = IdentityLock::fromArray([]);

        $this->expectException(ValidationException::class);
        (new ShotRenderer('/nonexistent/ffmpeg'))->renderFilm($graph, $timeline, $lock, $this->tempOut, '../escape', 320, 180);
    }

    public function testRejectsMissingOutputDirectory(): void
    {
        $graph = ShotGraph::fromArray([
            ['id' => 's1', 'durationSec' => 2.0, 'camera' => 'static', 'prompt' => 'hello'],
        ], []);
        $timeline = AudioFirstPlanner::plan($graph, []);
        $lock = IdentityLock::fromArray([]);

        $this->expectException(ValidationException::class);
        (new ShotRenderer('/nonexistent/ffmpeg'))->renderFilm($graph, $timeline, $lock, $this->tempOut . '/missing', 'film', 320, 180);
    }

    public function testDirectorApiRenderProducesRealMp4AndSrt(): void
    {
        if (ShotRenderer::detectFfmpeg() === null) {
            $this->markTestSkipped('FFmpeg binary not installed.');
        }

        $res = DirectorApi::render([
            'assets' => ['host', 'venue'],
            'style'  => 'storytelling',
            'beats'  => [1.5, 3.0],
            'lock'   => ['enabled' => true, 'characterId' => 'host', 'styleSeed' => 42],
            'shots'  => [
                ['id' => 'a', 'durationSec' => 1.5, 'camera' => 'zoom_in', 'prompt' => '@host arrives at @venue'],
                ['id' => 'b', 'durationSec' => 1.5, 'camera' => 'pan_right', 'prompt' => 'Warm applause'],
            ],
        ], $this->tempOut, 'celebration_reel', 320, 180);

        $this->assertTrue($res['ok']);
        $this->assertSame(0.0, $res['costGbp']);
        $this->assertSame('success', $res['render']['status']);
        $this->assertSame(2, $res['render']['clips']);
        $this->assertSame(3.0, $res['totalDurationSec']);
        $this->assertNotNull($res['render']['videoPath']);
        $this->assertFileExists((string) $res['render']['videoPath']);
        $this->assertGreaterThan(1000, filesize((string) $res['render']['videoPath']));
        $this->assertFileExists($res['render']['srtPath']);
    }

    public function testSingleShotRenderSucceeds(): void
    {
        if (ShotRenderer::detectFfmpeg() === null) {
            $this->markTestSkipped('FFmpeg binary not installed.');
        }

        $res = DirectorApi::render([
            'assets' => ['host'],
            'style'  => 'social_content',
            'shots'  => [
                ['id' => 'solo', 'durationSec' => 1.0, 'camera' => 'dolly', 'prompt' => '@host waves'],
            ],
        ], $this->tempOut, 'solo_reel', 320, 180);

        $this->assertSame('success', $res['render']['status']);
        $this->assertFileExists((string) $res['render']['videoPath']);
    }
}
