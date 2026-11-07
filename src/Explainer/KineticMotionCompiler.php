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

        foreach ($storyboard->getScenes() as $index => $scene) {
            $duration = (float)($scene['duration'] ?? 5.0);
            $sceneFrames = (int)ceil($duration * self::FPS);

            $camera = $scene['camera'] ?? ['x' => 0.5, 'y' => 0.5, 'zoom' => 1.0];
            $cursor = $scene['cursor'] ?? null;

            // Generate smooth Bezier camera transitions
            $cameraKeyframe = [
                'start_frame' => $currentFrameOffset,
                'end_frame' => $currentFrameOffset + $sceneFrames,
                'start_time' => $currentTimeOffset,
                'end_time' => $currentTimeOffset + $duration,
                'target_x' => (float)($camera['x'] ?? 0.5),
                'target_y' => (float)($camera['y'] ?? 0.5),
                'zoom' => (float)($camera['zoom'] ?? 1.0),
                'easing' => 'cubic-bezier(0.25, 0.1, 0.25, 1.0)',
            ];
            $cameraTrack[] = $cameraKeyframe;

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

            // Generate synchronised captions
            $captions = $scene['captions'] ?? [];
            if (!empty($captions)) {
                foreach ($captions as $caption) {
                    $captionTrack[] = [
                        'start_time' => $currentTimeOffset + (float)($caption['start'] ?? 0.0),
                        'end_time' => $currentTimeOffset + (float)($caption['end'] ?? $duration),
                        'text' => (string)($caption['text'] ?? ''),
                        'scene_index' => $index,
                    ];
                }
            } elseif (!empty($scene['narration'])) {
                // Auto-generate a default caption chunk from narration if not explicitly timed
                $captionTrack[] = [
                    'start_time' => $currentTimeOffset + 0.2,
                    'end_time' => $currentTimeOffset + $duration - 0.2,
                    'text' => (string)$scene['narration'],
                    'scene_index' => $index,
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
            ],
        ];
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
