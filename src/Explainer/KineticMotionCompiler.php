<?php

declare(strict_types=1);

namespace Src\Explainer;

/**
 * KineticMotionCompiler
 *
 * Programmatic 60fps motion compiler engineered by Maya Lindqvist and Kaelen Vance (ATG Pod 3).
 * Transforms declarative storyboard scenes into mathematically smooth Bezier camera paths,
 * natural cursor trajectories, tactile click ripples, and kinetic typography keyframes.
 */
class KineticMotionCompiler
{
    public const FPS = 60;
    public const DEFAULT_BEAT_INTERVAL_SECONDS = 2.0;

    /** @var array<string, string> */
    public const EASING_PRESETS = [
        'spring_snappy' => 'cubic-bezier(0.22, 1.28, 0.36, 1.0)',
        'spring_smooth' => 'cubic-bezier(0.16, 1.0, 0.3, 1.0)',
        'editorial' => 'cubic-bezier(0.25, 0.1, 0.25, 1.0)',
    ];

    /**
     * Compiles a storyboard into a frame-accurate motion timeline.
     *
     * @param ExplainerStoryboard $storyboard
     * @return array{
     *     fps: int,
     *     total_frames: int,
     *     total_duration: float,
     *     dimensions: array{width: int, height: int},
     *     scenes: array<int, array<string, mixed>>,
     *     motion_tracks: array<string, mixed>
     * }
     */
    public function compile(ExplainerStoryboard $storyboard): array
    {
        $totalDuration = $storyboard->getTotalDurationSeconds();
        $totalFrames = (int)ceil($totalDuration * self::FPS);
        $dimensions = $this->resolveDimensions($storyboard->getAspectRatio());

        $compiledScenes = [];
        $currentFrameOffset = 0;
        $currentTimeOffset = 0.0;

        $cameraTrack = [];
        $cursorTrack = [];
        $captionTrack = [];
        $beatTrack = [];

        foreach ($storyboard->getScenes() as $index => $scene) {
            $duration = (float)($scene['duration'] ?? 5.0);
            $sceneFrames = (int)ceil($duration * self::FPS);

            $camera = $scene['camera'] ?? ['x' => 0.5, 'y' => 0.5, 'zoom' => 1.0];
            $cursor = $scene['cursor'] ?? null;
            $zoom = (float)($camera['zoom'] ?? 1.0);
            $isFocusedZoom = $zoom > 1.05;
            $easingKey = (string)($camera['easing'] ?? ($isFocusedZoom ? 'spring_snappy' : 'editorial'));

            // Generate smooth Bezier / spring camera transitions with focal auto-dim
            $cameraKeyframe = [
                'start_frame' => $currentFrameOffset,
                'end_frame' => $currentFrameOffset + $sceneFrames,
                'start_time' => $currentTimeOffset,
                'end_time' => $currentTimeOffset + $duration,
                'target_x' => (float)($camera['x'] ?? 0.5),
                'target_y' => (float)($camera['y'] ?? 0.5),
                'zoom' => $zoom,
                'focus_dim' => isset($camera['focusDim']) ? (float)$camera['focusDim'] : ($isFocusedZoom ? 0.35 : 0.0),
                'backdrop_blur_px' => isset($camera['backdropBlurPx']) ? (int)$camera['backdropBlurPx'] : ($isFocusedZoom ? 6 : 0),
                'easing' => self::EASING_PRESETS[$easingKey] ?? $easingKey,
            ];
            $cameraTrack[] = $cameraKeyframe;

            // Subdivide scene into 1.5s-2.5s visual beat cues for high-retention pacing
            $beatInterval = max(1.5, min(2.5, (float)($scene['beatInterval'] ?? self::DEFAULT_BEAT_INTERVAL_SECONDS)));
            $beatTrack = array_merge(
                $beatTrack,
                $this->buildSceneBeats($index, $currentTimeOffset, $duration, $beatInterval, $isFocusedZoom)
            );

            // Generate virtual cursor trajectory
            if ($cursor !== null) {
                $cursorKeyframe = [
                    'start_frame' => $currentFrameOffset,
                    'action_frame' => $currentFrameOffset + (int)($sceneFrames * 0.6), // Action triggers at 60% of scene
                    'end_frame' => $currentFrameOffset + $sceneFrames,
                    'action' => (string)($cursor['action'] ?? 'move'),
                    'target_x' => (float)($cursor['targetX'] ?? 0.5),
                    'target_y' => (float)($cursor['targetY'] ?? 0.5),
                    'label' => $cursor['label'] ?? null,
                    'ripple_radius_px' => 32,
                ];
                $cursorTrack[] = $cursorKeyframe;
            }

            // Generate synchronised captions + 2-4 word kinetic chunks
            $captions = $scene['captions'] ?? [];
            if (!empty($captions)) {
                foreach ($captions as $caption) {
                    $cStart = $currentTimeOffset + (float)($caption['start'] ?? 0.0);
                    $cEnd = $currentTimeOffset + (float)($caption['end'] ?? $duration);
                    $cText = (string)($caption['text'] ?? '');
                    $captionTrack[] = [
                        'start_time' => $cStart,
                        'end_time' => $cEnd,
                        'text' => $cText,
                        'scene_index' => $index,
                        'kinetic_chunks' => $this->chunkKineticCaption($cText, $cStart, $cEnd),
                    ];
                }
            } elseif (!empty($scene['narration'])) {
                // Auto-generate a default caption chunk from narration if not explicitly timed
                $cStart = $currentTimeOffset + 0.2;
                $cEnd = $currentTimeOffset + max(0.4, $duration - 0.2);
                $cText = (string)$scene['narration'];
                $captionTrack[] = [
                    'start_time' => $cStart,
                    'end_time' => $cEnd,
                    'text' => $cText,
                    'scene_index' => $index,
                    'kinetic_chunks' => $this->chunkKineticCaption($cText, $cStart, $cEnd),
                ];
            }

            $compiledScenes[] = [
                'index' => $index,
                'id' => (string)($scene['id'] ?? 'scene_' . $index),
                'title' => (string)($scene['title'] ?? ''),
                'start_time' => $currentTimeOffset,
                'end_time' => $currentTimeOffset + $duration,
                'duration' => $duration,
                'start_frame' => $currentFrameOffset,
                'frames' => $sceneFrames,
                'target_selector' => $scene['targetSelector'] ?? null,
                'visual_svg' => $scene['visualSvg'] ?? null,
            ];

            $currentFrameOffset += $sceneFrames;
            $currentTimeOffset += $duration;
        }

        return [
            'fps' => self::FPS,
            'total_frames' => $totalFrames,
            'total_duration' => round($totalDuration, 3),
            'dimensions' => $dimensions,
            'scenes' => $compiledScenes,
            'motion_tracks' => [
                'camera' => $cameraTrack,
                'cursor' => $cursorTrack,
                'captions' => $captionTrack,
                'beats' => $beatTrack,
            ],
        ];
    }

    /**
     * Builds 1.5s-2.5s visual beat markers with alternating motion presets and SFX cues.
     *
     * @return array<int, array<string, mixed>>
     */
    private function buildSceneBeats(
        int $sceneIndex,
        float $sceneStart,
        float $duration,
        float $beatInterval,
        bool $isFocusedZoom
    ): array {
        $presets = ['card_pop', 'focal_push', 'highlight_pulse'];
        $beats = [];
        $step = 0;

        for ($offset = 0.0; $offset < $duration; $offset += $beatInterval) {
            $preset = ($step === 0 && $isFocusedZoom)
                ? 'focal_push'
                : $presets[$step % count($presets)];

            $beats[] = [
                'scene_index' => $sceneIndex,
                'beat_index' => $step,
                'time' => round($sceneStart + $offset, 3),
                'frame' => (int)round(($sceneStart + $offset) * self::FPS),
                'preset' => $preset,
                'easing' => self::EASING_PRESETS['spring_snappy'],
                'sfx_cue' => $step === 0 ? 'sfx_riser' : 'ui_click',
            ];
            $step++;
        }

        return $beats;
    }

    /**
     * Splits caption text into punchy 2-4 word kinetic chunks evenly timed across [start, end].
     *
     * @return array<int, array{text: string, start_time: float, end_time: float}>
     */
    private function chunkKineticCaption(string $text, float $startTime, float $endTime, int $wordsPerChunk = 3): array
    {
        $words = preg_split('/\s+/', trim($text)) ?: [];
        $words = array_values(array_filter($words, static fn (string $w): bool => $w !== ''));

        if ($words === []) {
            return [];
        }

        $groups = array_chunk($words, max(2, min(4, $wordsPerChunk)));
        $span = max(0.2, $endTime - $startTime);
        $sliceDuration = $span / count($groups);
        $chunks = [];

        foreach ($groups as $i => $group) {
            $cStart = $startTime + ($i * $sliceDuration);
            $cEnd = ($i === count($groups) - 1) ? $endTime : ($cStart + $sliceDuration);
            $chunks[] = [
                'text' => implode(' ', $group),
                'start_time' => round($cStart, 3),
                'end_time' => round($cEnd, 3),
            ];
        }

        return $chunks;
    }

    /**
     * Resolves canvas dimensions based on standard aspect ratios.
     *
     * @param string $aspectRatio
     * @return array{width: int, height: int}
     */
    private function resolveDimensions(string $aspectRatio): array
    {
        return match ($aspectRatio) {
            ExplainerStoryboard::ASPECT_9_16 => ['width' => 1080, 'height' => 1920], // Mobile Reels / Social
            ExplainerStoryboard::ASPECT_1_1 => ['width' => 1080, 'height' => 1080],  // Square / Feeds
            default => ['width' => 1920, 'height' => 1080],                         // 16:9 Standard HD
        };
    }
}
