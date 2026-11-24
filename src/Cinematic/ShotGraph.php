<?php

declare(strict_types=1);

namespace Src\Cinematic;

use Src\Exceptions\ValidationException;

/**
 * ShotGraph
 *
 * Validated, immutable shot-level storyboard (camera, references, audio intent and
 * continuity key per shot). Zero I/O.
 */
final class ShotGraph
{
    public const CAMERAS = ['static', 'zoom_in', 'zoom_out', 'pan_left', 'pan_right', 'dolly', 'whip_pan'];
    public const AUDIO_INTENTS = ['dialogue', 'sfx', 'music_beat', 'silence'];
    public const MAX_SHOTS = 60;
    public const MIN_SHOT_SEC = 0.5;
    public const MAX_SHOT_SEC = 15.0;

    /** @var array<int, array{id: string, durationSec: float, camera: string, refs: array<int, string>, audioIntent: string, continuityKey: string, prompt: string}> */
    private array $shots;

    /**
     * @param array<int, array{id: string, durationSec: float, camera: string, refs: array<int, string>, audioIntent: string, continuityKey: string, prompt: string}> $shots
     */
    private function __construct(array $shots)
    {
        $this->shots = $shots;
    }

    /**
     * @param array<int, array<string, mixed>> $rawShots
     * @param array<int, string> $allowedAssetIds
     * @throws ValidationException
     */
    public static function fromArray(array $rawShots, array $allowedAssetIds): self
    {
        if ($rawShots === [] || count($rawShots) > self::MAX_SHOTS) {
            throw new ValidationException('A shot graph needs between 1 and ' . self::MAX_SHOTS . ' shots.');
        }

        $shots = [];
        $seen = [];
        foreach (array_values($rawShots) as $i => $raw) {
            $id = (string) ($raw['id'] ?? 'shot-' . ($i + 1));
            if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id) !== 1 || isset($seen[$id])) {
                throw new ValidationException('Invalid or duplicate shot id: ' . $id);
            }
            $seen[$id] = true;

            $duration = (float) ($raw['durationSec'] ?? 0.0);
            if ($duration < self::MIN_SHOT_SEC || $duration > self::MAX_SHOT_SEC) {
                throw new ValidationException('Shot duration out of range for ' . $id);
            }

            $camera = (string) ($raw['camera'] ?? 'static');
            if (!in_array($camera, self::CAMERAS, true)) {
                throw new ValidationException('Unsupported camera move for ' . $id);
            }

            $audio = (string) ($raw['audioIntent'] ?? 'silence');
            if (!in_array($audio, self::AUDIO_INTENTS, true)) {
                throw new ValidationException('Unsupported audio intent for ' . $id);
            }

            $continuity = (string) ($raw['continuityKey'] ?? 'default');
            if (preg_match('/^[A-Za-z0-9_-]{1,64}$/', $continuity) !== 1) {
                throw new ValidationException('Invalid continuity key for ' . $id);
            }

            $parsed = AssetTagParser::parse((string) ($raw['prompt'] ?? ''), $allowedAssetIds);

            $shots[] = [
                'id'            => $id,
                'durationSec'   => round($duration, 3),
                'camera'        => $camera,
                'refs'          => $parsed['refs'],
                'audioIntent'   => $audio,
                'continuityKey' => $continuity,
                'prompt'        => $parsed['prompt'],
            ];
        }

        return new self($shots);
    }

    /** @return array<int, array{id: string, durationSec: float, camera: string, refs: array<int, string>, audioIntent: string, continuityKey: string, prompt: string}> */
    public function getShots(): array
    {
        return $this->shots;
    }

    public function getTotalDurationSeconds(): float
    {
        $total = 0.0;
        foreach ($this->shots as $shot) {
            $total += $shot['durationSec'];
        }
        return round($total, 3);
    }
}
