<?php

declare(strict_types=1);

namespace Src\Cinematic;

use Src\Exceptions\ValidationException;

/**
 * ShotChainer
 *
 * Builds FFmpeg argument fragments only (no process execution): last-frame extraction so
 * shot N seeds shot N+1, and a crossfade chain joining the rendered shots.
 */
final class ShotChainer
{
    private const TRANSITIONS = ['fade', 'dissolve', 'wipeleft', 'wiperight', 'slideup', 'slidedown', 'circleopen'];

    /**
     * Extract the final frame of a clip (used as the reference image for the next shot).
     *
     * @return array<int, string> argv for ffmpeg (safe to pass to proc_open without a shell).
     */
    public static function buildLastFrameArgs(string $inputPath, string $outputPngPath): array
    {
        foreach ([$inputPath, $outputPngPath] as $p) {
            if ($p === '' || str_contains($p, "\0") || str_contains($p, '..') || str_starts_with($p, '-')) {
                throw new ValidationException('Unsafe media path.');
            }
        }
        if (!str_ends_with(strtolower($outputPngPath), '.png')) {
            throw new ValidationException('Last-frame output must be a PNG.');
        }

        return ['-y', '-sseof', '-0.1', '-i', $inputPath, '-frames:v', '1', '-update', '1', $outputPngPath];
    }

    /**
     * Crossfade chain for N rendered clips. Offsets are cumulative, so cost is O(n).
     *
     * @param array<int, float> $clipDurationsSec
     * @return string filter_complex graph; final labels are [vout].
     */
    public static function buildCrossfadeGraph(array $clipDurationsSec, string $transition = 'fade', float $overlapSec = 0.5): string
    {
        $n = count($clipDurationsSec);
        if ($n < 1 || $n > ShotGraph::MAX_SHOTS) {
            throw new ValidationException('Crossfade chain needs 1 to ' . ShotGraph::MAX_SHOTS . ' clips.');
        }
        $trans = in_array($transition, self::TRANSITIONS, true) ? $transition : 'fade';
        $overlap = max(0.1, min(2.0, $overlapSec));

        if ($n === 1) {
            return '[0:v]null[vout]';
        }

        $parts = [];
        $label = '[0:v]';
        $elapsed = 0.0;
        for ($i = 1; $i < $n; $i++) {
            $d = (float) $clipDurationsSec[$i - 1];
            if ($d <= $overlap) {
                throw new ValidationException('Clip shorter than the crossfade overlap.');
            }
            $elapsed += $d - $overlap;
            $out = $i === $n - 1 ? '[vout]' : sprintf('[v%d]', $i);
            $parts[] = sprintf('%s[%d:v]xfade=transition=%s:duration=%.2f:offset=%.2f%s', $label, $i, $trans, $overlap, $elapsed, $out);
            $label = $out;
        }

        return implode(';', $parts);
    }
}
