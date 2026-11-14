<?php

declare(strict_types=1);

namespace Src\Explainer;

use InvalidArgumentException;

/**
 * ExplainerStoryboard
 *
 * Immutable, high-density storyboard domain model engineered by the ATG.
 * Enforces Sarah & Chloe's 60-second micro-explainer cap, strict British English copy,
 * and 4-state visual scene specifications across portfolio applications.
 */
class ExplainerStoryboard
{
    public const MIN_DURATION_SECONDS = 5.0;
    public const MAX_DURATION_SECONDS = 60.0;

    public const ASPECT_16_9 = '16:9';
    public const ASPECT_9_16 = '9:16';
    public const ASPECT_1_1 = '1:1';

    // Three-Part Narrative Arc Stages
    public const STAGE_PROBLEM = 'problem';
    public const STAGE_SOLUTION = 'solution';
    public const STAGE_CTA = 'call_to_action';

    // Industry Visual Formats
    public const FORMAT_ANIMATION_2D_3D = '2d_3d_animation';
    public const FORMAT_WHITEBOARD = 'whiteboard';
    public const FORMAT_MOTION_GRAPHICS = 'motion_graphics';
    public const FORMAT_MIXED_MEDIA = 'mixed_media';

    /**
     * @param string $id Unique identifier for the explainer capsule
     * @param string $title British English title for the explainer
     * @param string $appName Target portfolio application (e.g. 'bcp', 'party', 'loaneasy', 'family')
     * @param string $aspectRatio Video aspect ratio ('16:9', '9:16', '1:1')
     * @param string $theme Visual presentation theme
     * @param array<int, array{
     *     id: string,
     *     duration: float,
     *     title: string,
     *     narration: string,
     *     stage?: 'problem'|'solution'|'call_to_action',
     *     targetSelector?: string|null,
     *     camera: array{x: float, y: float, zoom: float},
     *     cursor?: array{action: 'move'|'click'|'type'|'scroll', targetX: float, targetY: float, label?: string|null}|null,
     *     visualSvg?: string|null,
     *     captions?: array<int, array{start: float, end: float, text: string}>
     * }> $scenes
     * @param array<string, mixed> $metadata
     * @param string $visualFormat Industry visual format (2D/3D Animation, Whiteboard, Motion Graphics, Mixed Media)
     */
    public function __construct(
        private readonly string $id,
        private readonly string $title,
        private readonly string $appName,
        private readonly string $aspectRatio,
        private readonly string $theme,
        private readonly array $scenes,
        private readonly array $metadata = [],
        private readonly string $visualFormat = self::FORMAT_MOTION_GRAPHICS
    ) {
        $this->validate();
    }

    /**
     * Factory method to build a storyboard from a declarative definition.
     *
     * @param array<string, mixed> $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $id = (string)($data['id'] ?? 'exp_' . bin2hex(random_bytes(6)));
        $title = ExplainerSecurityValidator::validateSafeText((string)($data['title'] ?? 'Explainer Video'));
        $appName = strtolower((string)($data['app_name'] ?? 'platform'));
        $aspectRatio = (string)($data['aspect_ratio'] ?? self::ASPECT_16_9);
        $theme = (string)($data['theme'] ?? 'glassmorphic-dark');
        $visualFormat = (string)($data['visual_format'] ?? self::FORMAT_MOTION_GRAPHICS);
        if (!in_array($visualFormat, [
            self::FORMAT_ANIMATION_2D_3D,
            self::FORMAT_WHITEBOARD,
            self::FORMAT_MOTION_GRAPHICS,
            self::FORMAT_MIXED_MEDIA,
        ], true)) {
            $visualFormat = self::FORMAT_MOTION_GRAPHICS;
        }
        $rawScenes = (array)($data['scenes'] ?? []);
        $metadata = (array)($data['metadata'] ?? []);

        $validatedScenes = [];
        foreach ($rawScenes as $index => $scene) {
            if (!is_array($scene)) {
                continue;
            }

            $sceneId = (string)($scene['id'] ?? 'scene_' . ($index + 1));
            $duration = (float)($scene['duration'] ?? 5.0);
            $sceneTitle = ExplainerSecurityValidator::validateSafeText((string)($scene['title'] ?? 'Scene ' . ($index + 1)));
            $narration = ExplainerSecurityValidator::validateSafeText((string)($scene['narration'] ?? ''));

            // Three-Part Narrative Arc Stage: Problem -> Solution -> Call to Action
            $defaultStage = match ($index) {
                0 => self::STAGE_PROBLEM,
                1 => self::STAGE_SOLUTION,
                default => self::STAGE_CTA,
            };
            $rawStage = (string)($scene['stage'] ?? $defaultStage);
            $stage = in_array($rawStage, [self::STAGE_PROBLEM, self::STAGE_SOLUTION, self::STAGE_CTA], true)
                ? $rawStage
                : $defaultStage;

            $targetSelector = isset($scene['target_selector']) ? (string)$scene['target_selector'] : null;
            if ($targetSelector !== null) {
                // Ensure selector contains no shell injection
                $targetSelector = ExplainerSecurityValidator::validateSafeText($targetSelector, 120);
            }

            $camera = is_array($scene['camera'] ?? null) ? [
                'x' => (float)($scene['camera']['x'] ?? 0.5),
                'y' => (float)($scene['camera']['y'] ?? 0.5),
                'zoom' => max(1.0, min(3.0, (float)($scene['camera']['zoom'] ?? 1.0))),
            ] : ['x' => 0.5, 'y' => 0.5, 'zoom' => 1.0];

            $cursor = null;
            if (is_array($scene['cursor'] ?? null)) {
                $action = in_array($scene['cursor']['action'] ?? '', ['move', 'click', 'type', 'scroll'], true)
                    ? (string)$scene['cursor']['action']
                    : 'move';

                $cursor = [
                    'action' => $action,
                    'targetX' => (float)($scene['cursor']['targetX'] ?? 0.5),
                    'targetY' => (float)($scene['cursor']['targetY'] ?? 0.5),
                    'label' => isset($scene['cursor']['label'])
                        ? ExplainerSecurityValidator::validateSafeText((string)$scene['cursor']['label'], 60)
                        : null,
                ];
            }

            $visualSvg = null;
            if (isset($scene['visual_svg']) && is_string($scene['visual_svg'])) {
                $visualSvg = ExplainerSecurityValidator::sanitizeSvgMarkup($scene['visual_svg']);
            }

            $captions = [];
            if (isset($scene['captions']) && is_array($scene['captions'])) {
                foreach ($scene['captions'] as $cap) {
                    if (is_array($cap)) {
                        $captions[] = [
                            'start' => (float)($cap['start'] ?? 0.0),
                            'end' => (float)($cap['end'] ?? $duration),
                            'text' => ExplainerSecurityValidator::validateSafeText((string)($cap['text'] ?? ''), 200),
                        ];
                    }
                }
            }

            $validatedScenes[] = [
                'id' => $sceneId,
                'duration' => $duration,
                'title' => $sceneTitle,
                'stage' => $stage,
                'narration' => $narration,
                'targetSelector' => $targetSelector,
                'camera' => $camera,
                'cursor' => $cursor,
                'visualSvg' => $visualSvg,
                'captions' => $captions,
            ];
        }

        return new self($id, $title, $appName, $aspectRatio, $theme, $validatedScenes, $metadata, $visualFormat);
    }

    /**
     * Validates structural integrity and time constraints.
     *
     * @throws InvalidArgumentException
     */
    private function validate(): void
    {
        if (empty($this->scenes)) {
            throw new InvalidArgumentException('A storyboard must contain at least one scene.');
        }

        $totalDuration = $this->getTotalDurationSeconds();

        if ($totalDuration < self::MIN_DURATION_SECONDS) {
            throw new InvalidArgumentException(sprintf(
                'Total explainer duration (%.1fs) is below the minimum threshold of %.1fs.',
                $totalDuration,
                self::MIN_DURATION_SECONDS
            ));
        }

        if ($totalDuration > self::MAX_DURATION_SECONDS) {
            throw new InvalidArgumentException(sprintf(
                'Total explainer duration (%.1fs) violates the 60-second micro-explainer cap (max %.1fs).',
                $totalDuration,
                self::MAX_DURATION_SECONDS
            ));
        }

        if (!in_array($this->aspectRatio, [self::ASPECT_16_9, self::ASPECT_9_16, self::ASPECT_1_1], true)) {
            throw new InvalidArgumentException(sprintf('Unsupported aspect ratio: "%s".', $this->aspectRatio));
        }
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getAppName(): string
    {
        return $this->appName;
    }

    public function getAspectRatio(): string
    {
        return $this->aspectRatio;
    }

    public function getTheme(): string
    {
        return $this->theme;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getScenes(): array
    {
        return $this->scenes;
    }

    /**
     * Calculates the cumulative duration of all scenes.
     *
     * @return float
     */
    public function getTotalDurationSeconds(): float
    {
        $sum = 0.0;
        foreach ($this->scenes as $scene) {
            $sum += (float)($scene['duration'] ?? 0.0);
        }
        return $sum;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function getVisualFormat(): string
    {
        return $this->visualFormat;
    }

    /**
     * Checks whether the storyboard implements the full 3-part narrative arc
     * (The Problem -> The Solution -> The Call to Action).
     */
    public function hasCompleteNarrativeArc(): bool
    {
        $stages = array_column($this->scenes, 'stage');
        return in_array(self::STAGE_PROBLEM, $stages, true)
            && in_array(self::STAGE_SOLUTION, $stages, true)
            && in_array(self::STAGE_CTA, $stages, true);
    }

    /**
     * Returns an ordered summary of the narrative arc stages.
     *
     * @return array<string, string>
     */
    public function getNarrativeArc(): array
    {
        $arc = [];
        foreach ($this->scenes as $scene) {
            $stage = (string)($scene['stage'] ?? self::STAGE_SOLUTION);
            $arc[$stage] = (string)($scene['title'] ?? '');
        }
        return $arc;
    }

    /**
     * Serialises the storyboard to an array for JSON exchange.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'app_name' => $this->appName,
            'aspect_ratio' => $this->aspectRatio,
            'theme' => $this->theme,
            'visual_format' => $this->visualFormat,
            'narrative_arc' => $this->getNarrativeArc(),
            'has_complete_narrative_arc' => $this->hasCompleteNarrativeArc(),
            'total_duration' => $this->getTotalDurationSeconds(),
            'scenes' => $this->scenes,
            'metadata' => $this->metadata,
        ];
    }
}
