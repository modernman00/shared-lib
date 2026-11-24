<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\Cinematic\AssetTagParser;
use Src\Cinematic\AudioFirstPlanner;
use Src\Cinematic\ShotChainer;
use Src\Cinematic\ShotGraph;
use Src\Exceptions\ValidationException;

final class CinematicEngineTest extends TestCase
{
    /** @return array<int, array<string, mixed>> */
    private function shots(): array
    {
        return [
            ['id' => 's1', 'durationSec' => 3.0, 'camera' => 'zoom_in', 'audioIntent' => 'dialogue', 'prompt' => '@hero enters @kitchen'],
            ['id' => 's2', 'durationSec' => 4.0, 'camera' => 'pan_left', 'audioIntent' => 'sfx', 'prompt' => 'Close on @hero'],
        ];
    }

    public function testParserResolvesAllowedTagsAndStripsMarkers(): void
    {
        $r = AssetTagParser::parse('@hero walks into @kitchen', ['hero', 'kitchen']);
        $this->assertSame(['hero', 'kitchen'], $r['refs']);
        $this->assertSame('hero walks into kitchen', $r['prompt']);
    }

    /** @dataProvider hostilePrompts */
    public function testParserRejectsHostileInput(string $prompt): void
    {
        $this->expectException(ValidationException::class);
        AssetTagParser::parse($prompt, ['hero']);
    }

    /** @return array<string, array<int, string>> */
    public static function hostilePrompts(): array
    {
        return [
            'unknown tag'    => ['@etc'],
            'traversal tag'  => ['@../../etc/passwd'],
            'oversize'       => [str_repeat('a', 2001)],
        ];
    }

    public function testShotGraphValidatesAndTotals(): void
    {
        $g = ShotGraph::fromArray($this->shots(), ['hero', 'kitchen']);
        $this->assertCount(2, $g->getShots());
        $this->assertSame(7.0, $g->getTotalDurationSeconds());
    }

    public function testShotGraphRejectsBadCameraAndDuplicates(): void
    {
        $bad = $this->shots();
        $bad[1]['id'] = 's1';
        $this->expectException(ValidationException::class);
        ShotGraph::fromArray($bad, ['hero', 'kitchen']);
    }

    public function testShotGraphRejectsUnknownCamera(): void
    {
        $bad = $this->shots();
        $bad[0]['camera'] = 'teleport';
        $this->expectException(ValidationException::class);
        ShotGraph::fromArray($bad, ['hero', 'kitchen']);
    }

    public function testPlannerSnapsToBeatsAndStaysContiguous(): void
    {
        $g = ShotGraph::fromArray($this->shots(), ['hero', 'kitchen']);
        $plan = AudioFirstPlanner::plan($g, [7.2, 3.1, 0.5]);
        $this->assertTrue($plan[0]['snapped']);
        $this->assertSame(3.1, $plan[0]['endSec']);
        $this->assertSame($plan[0]['endSec'], $plan[1]['startSec']);
    }

    public function testPlannerWithoutBeatsKeepsOriginalTiming(): void
    {
        $g = ShotGraph::fromArray($this->shots(), ['hero', 'kitchen']);
        $plan = AudioFirstPlanner::plan($g, []);
        $this->assertSame(7.0, $plan[1]['endSec']);
        $this->assertFalse($plan[0]['snapped']);
    }

    public function testChainerBuildsCumulativeCrossfadeOffsets(): void
    {
        $graph = ShotChainer::buildCrossfadeGraph([3.0, 4.0, 5.0], 'dissolve', 0.5);
        $this->assertStringContainsString('offset=2.50[v1]', $graph);
        $this->assertStringContainsString('offset=6.00[vout]', $graph);
    }

    public function testChainerRejectsUnsafePaths(): void
    {
        $this->expectException(ValidationException::class);
        ShotChainer::buildLastFrameArgs('../secret.mp4', 'out.png');
    }

    public function testChainerLastFrameArgs(): void
    {
        $args = ShotChainer::buildLastFrameArgs('clip.mp4', 'last.png');
        $this->assertContains('-sseof', $args);
        $this->assertSame('last.png', end($args));
    }
}
