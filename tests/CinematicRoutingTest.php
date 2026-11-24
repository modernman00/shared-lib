<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\Cinematic\IdentityLock;
use Src\Cinematic\RenderRouter;
use Src\Cinematic\ShotGraph;
use Src\Exceptions\ValidationException;

final class CinematicRoutingTest extends TestCase
{
    private function graph(): ShotGraph
    {
        return ShotGraph::fromArray([
            ['id' => 's1', 'durationSec' => 3.0, 'camera' => 'zoom_in', 'prompt' => '@hero smiles', 'continuityKey' => 'scene-a'],
            ['id' => 's2', 'durationSec' => 3.0, 'camera' => 'static', 'prompt' => 'wide room', 'continuityKey' => 'scene-a'],
        ], ['hero']);
    }

    private function lock(): IdentityLock
    {
        return IdentityLock::fromArray(['enabled' => true, 'characterId' => 'hero', 'worldId' => 'kitchen', 'styleSeed' => 7]);
    }

    public function testSameContinuityKeyGivesSameSeed(): void
    {
        $l = $this->lock();
        $this->assertSame($l->seedFor('scene-a'), $l->seedFor('scene-a'));
        $this->assertNotSame($l->seedFor('scene-a'), $l->seedFor('scene-b'));
    }

    public function testBiometricReferenceWithoutConsentIsBlocked(): void
    {
        $this->expectException(ValidationException::class);
        IdentityLock::fromArray(['usesBiometricReference' => true, 'subjectUserId' => 'u1', 'gdprArticle9Consent' => false]);
    }

    public function testBiometricReferenceWithConsentPasses(): void
    {
        $l = IdentityLock::fromArray(['usesBiometricReference' => true, 'subjectUserId' => 'u1', 'gdprArticle9Consent' => true, 'characterId' => 'hero']);
        $this->assertSame('hero', $l->forShot('k')['characterId']);
    }

    public function testRejectsBadIdentityId(): void
    {
        $this->expectException(ValidationException::class);
        IdentityLock::fromArray(['characterId' => '../x']);
    }

    public function testOfflineSidecarFallsBackDeterministically(): void
    {
        $router = new RenderRouter(static fn (string $a, array $p): ?array => null);
        $r = $router->routeAll($this->graph(), $this->lock());
        $this->assertTrue($r[0]['fallbackUsed']);
        $this->assertSame('sidecar_offline', $r[0]['reason']);
        $this->assertStringContainsString('zoompan', $r[0]['filterGraph']);
    }

    public function testLowConfidenceFallsBack(): void
    {
        $router = new RenderRouter(static fn (string $a, array $p): ?array => ['status' => 'ok', 'confidence' => 0.80, 'artifactPath' => 'out/a.mp4']);
        $this->assertSame('low_confidence', $router->routeAll($this->graph(), $this->lock())[0]['reason']);
    }

    public function testConfidentSidecarIsAccepted(): void
    {
        $router = new RenderRouter(static fn (string $a, array $p): ?array => ['status' => 'ok', 'confidence' => 0.97, 'artifactPath' => 'out/a.mp4']);
        $r = $router->routeAll($this->graph(), $this->lock())[0];
        $this->assertSame('local_sidecar', $r['mode']);
        $this->assertFalse($r['fallbackUsed']);
        $this->assertSame('out/a.mp4', $r['artifactPath']);
    }

    public function testTraversalPathFromSidecarFallsBack(): void
    {
        $router = new RenderRouter(static fn (string $a, array $p): ?array => ['status' => 'ok', 'confidence' => 0.99, 'artifactPath' => '../../etc/x.mp4']);
        $r = $router->routeAll($this->graph(), $this->lock())[0];
        $this->assertTrue($r['fallbackUsed']);
        $this->assertSame('sidecar_error', $r['reason']);
    }

    public function testThrowingSidecarFallsBack(): void
    {
        $router = new RenderRouter(static function (string $a, array $p): ?array {
            throw new \RuntimeException('gpu busy');
        });
        $this->assertSame('sidecar_error', $router->routeAll($this->graph(), $this->lock())[0]['reason']);
    }

    public function testNoSidecarAtAllStillRenders(): void
    {
        $r = (new RenderRouter())->routeAll($this->graph(), $this->lock());
        $this->assertCount(2, $r);
        $this->assertTrue($r[0]['fallbackUsed']);
    }
}
