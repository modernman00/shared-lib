<?php

declare(strict_types=1);

namespace Src\Cinematic;

use Src\Media\PythonSidecarBridge;
use Src\Media\SovereignVideoJobHandler;
use Throwable;

/**
 * RenderRouter
 *
 * Per shot: try the optional local sidecar (open-weight model on hardware we own); accept its
 * output only if it reports status ok, confidence >= 0.95 and a safe local path. Anything else
 * (offline, low confidence, bad payload, exception) renders through the deterministic
 * FFmpeg path. Zero SaaS, zero spend.
 */
final class RenderRouter
{
    public const MIN_CONFIDENCE = 0.95;

    /**
     * @param (callable(string, array<string, mixed>): ?array<string, mixed>)|null $dispatcher Defaults to PythonSidecarBridge::dispatch.
     */
    public function __construct(private readonly mixed $dispatcher = null)
    {
    }

    /**
     * @param array{id: string, durationSec: float, camera: string, refs: array<int, string>, audioIntent: string, continuityKey: string, prompt: string} $shot
     * @return array{shotId: string, mode: string, fallbackUsed: bool, reason: string, artifactPath: ?string, filterGraph: string, identity: array<string, mixed>}
     */
    public function route(array $shot, IdentityLock $lock, string $style = 'storytelling'): array
    {
        $identity = $lock->forShot($shot['continuityKey']);
        $width = $identity['aspectRatio'] === '9:16' ? 720 : ($identity['aspectRatio'] === '1:1' ? 720 : 1280);
        $height = $identity['aspectRatio'] === '9:16' ? 1280 : ($identity['aspectRatio'] === '1:1' ? 720 : 720);
        $fallbackGraph = SovereignVideoJobHandler::buildImageToVideoFilterGraph($style, $width, $height, $shot['durationSec']);

        $reason = 'sidecar_offline';
        try {
            $dispatch = $this->dispatcher ?? [PythonSidecarBridge::class, 'dispatch'];
            /** @var callable(string, array<string, mixed>): ?array<string, mixed> $dispatch */
            $res = $dispatch('generate_shot', [
                'prompt'     => $shot['prompt'],
                'refs'       => $shot['refs'],
                'camera'     => $shot['camera'],
                'durationSec' => $shot['durationSec'],
                'identity'   => $identity,
            ]);

            if (is_array($res)) {
                $reason = 'invalid_response';
                $confidence = (float) ($res['confidence'] ?? 0.0);
                $path = (string) ($res['artifactPath'] ?? '');
                if (($res['status'] ?? '') === 'ok' && $path !== '') {
                    if ($confidence < self::MIN_CONFIDENCE) {
                        $reason = 'low_confidence';
                    } else {
                        return [
                            'shotId'       => $shot['id'],
                            'mode'         => 'local_sidecar',
                            'fallbackUsed' => false,
                            'reason'       => 'accepted',
                            'artifactPath' => SovereignVideoJobHandler::assertSafeLocalMediaPath($path),
                            'filterGraph'  => $fallbackGraph,
                            'identity'     => $identity,
                        ];
                    }
                }
            }
        } catch (Throwable) {
            $reason = 'sidecar_error';
        }

        return [
            'shotId'       => $shot['id'],
            'mode'         => 'deterministic_zero_saas',
            'fallbackUsed' => true,
            'reason'       => $reason,
            'artifactPath' => null,
            'filterGraph'  => $fallbackGraph,
            'identity'     => $identity,
        ];
    }

    /**
     * @return array<int, array{shotId: string, mode: string, fallbackUsed: bool, reason: string, artifactPath: ?string, filterGraph: string, identity: array<string, mixed>}>
     */
    public function routeAll(ShotGraph $graph, IdentityLock $lock, string $style = 'storytelling'): array
    {
        $out = [];
        foreach ($graph->getShots() as $shot) {
            $out[] = $this->route($shot, $lock, $style);
        }
        return $out;
    }
}
