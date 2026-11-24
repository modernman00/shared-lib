<?php

declare(strict_types=1);

namespace Src\Cinematic;

use Src\Exceptions\ValidationException;

/**
 * AudioFirstPlanner
 *
 * The narration/beat grid decides shot boundaries: each shot is snapped to the nearest
 * beat so cuts land on the audio. Beats are sorted once (O(n log n)); snapping uses a
 * forward pointer, so the overall cost stays O(n log n).
 */
final class AudioFirstPlanner
{
    /**
     * @param array<int, float|int> $beatTimesSec Beat or word-boundary timestamps from the TTS/beat grid.
     * @return array<int, array{shotId: string, startSec: float, endSec: float, durationSec: float, snapped: bool}>
     * @throws ValidationException
     */
    public static function plan(ShotGraph $graph, array $beatTimesSec, float $snapToleranceSec = 0.35): array
    {
        $beats = [];
        foreach ($beatTimesSec as $t) {
            $f = (float) $t;
            if ($f < 0.0 || $f > 3600.0) {
                throw new ValidationException('Beat timestamp out of range.');
            }
            $beats[] = $f;
        }
        sort($beats);

        $tolerance = max(0.0, min(2.0, $snapToleranceSec));
        $plan = [];
        $cursor = 0.0;
        $ptr = 0;
        $count = count($beats);

        foreach ($graph->getShots() as $shot) {
            $target = $cursor + $shot['durationSec'];
            while ($ptr < $count && $beats[$ptr] < $target - $tolerance) {
                $ptr++;
            }

            $end = $target;
            $snapped = false;
            if ($ptr < $count && abs($beats[$ptr] - $target) <= $tolerance && $beats[$ptr] - $cursor >= ShotGraph::MIN_SHOT_SEC) {
                $end = $beats[$ptr];
                $snapped = true;
            }

            $plan[] = [
                'shotId'      => $shot['id'],
                'startSec'    => round($cursor, 3),
                'endSec'      => round($end, 3),
                'durationSec' => round($end - $cursor, 3),
                'snapped'     => $snapped,
            ];
            $cursor = $end;
        }

        return $plan;
    }
}
