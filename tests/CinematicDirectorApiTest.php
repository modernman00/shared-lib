<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\Cinematic\DirectorApi;
use Src\Cinematic\RenderRouter;
use Src\Exceptions\ValidationException;

final class CinematicDirectorApiTest extends TestCase
{
    /** @return array<string, mixed> */
    private function payload(): array
    {
        return [
            'assets' => ['hero'],
            'style'  => 'storytelling',
            'beats'  => [3.1, 7.0],
            'lock'   => ['enabled' => true, 'characterId' => 'hero'],
            'shots'  => [
                ['id' => 'a', 'durationSec' => 3.0, 'camera' => 'zoom_in', 'prompt' => '@hero smiles'],
                ['id' => 'b', 'durationSec' => 4.0, 'camera' => 'static', 'prompt' => 'wide'],
            ],
        ];
    }

    public function testPlanReturnsZeroCostTimelineAndRenders(): void
    {
        $r = DirectorApi::plan($this->payload(), new RenderRouter(static fn (string $a, array $p): ?array => null));
        $this->assertTrue($r['ok']);
        $this->assertSame(0.0, $r['costGbp']);
        $this->assertSame(7.0, $r['totalDurationSec']);
        $this->assertCount(2, $r['renders']);
        $this->assertSame(2, $r['fallbackCount']);
    }

    public function testRejectsUnknownStyleAndMalformedShots(): void
    {
        $p = $this->payload();
        $p['style'] = 'hollywood';
        $this->expectException(ValidationException::class);
        DirectorApi::plan($p);
    }

    public function testRejectsUnknownAssetTag(): void
    {
        $p = $this->payload();
        $p['shots'][0]['prompt'] = '@stranger waves';
        $this->expectException(ValidationException::class);
        DirectorApi::plan($p);
    }

    public function testRejectsNonListShots(): void
    {
        $this->expectException(ValidationException::class);
        DirectorApi::plan(['shots' => 'x']);
    }
}
