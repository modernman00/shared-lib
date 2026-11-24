<?php

declare(strict_types=1);

namespace Src\Cinematic;

use Src\Exceptions\ValidationException;

/**
 * DirectorApi
 *
 * Single entry point for Director Mode: shots to ShotGraph, audio-first timing, identity lock,
 * then routing (sidecar or deterministic). Always reports a recurring cost of £0.
 */
final class DirectorApi
{
    public const ALLOWED_STYLES = ['storytelling', 'social_content', 'marketing_campaign', 'creative_project'];

    /**
     * @param array<string, mixed> $payload
     * @return array{ok: bool, costGbp: float, totalDurationSec: float, timeline: array<int, array<string, mixed>>, renders: array<int, array<string, mixed>>, fallbackCount: int}
     * @throws ValidationException
     */
    public static function plan(array $payload, ?RenderRouter $router = null): array
    {
        $rawShots = $payload['shots'] ?? null;
        $assets = $payload['assets'] ?? [];
        if (!is_array($rawShots) || !is_array($assets)) {
            throw new ValidationException('Shots and assets must be lists.');
        }

        $assetIds = [];
        foreach ($assets as $a) {
            $assetIds[] = (string) $a;
        }

        $style = (string) ($payload['style'] ?? 'storytelling');
        if (!in_array($style, self::ALLOWED_STYLES, true)) {
            throw new ValidationException('Unsupported style.');
        }

        $beats = $payload['beats'] ?? [];
        if (!is_array($beats) || count($beats) > 5000) {
            throw new ValidationException('Beat grid is invalid or too large.');
        }

        $lockPayload = $payload['lock'] ?? [];
        $graph = ShotGraph::fromArray(array_values(array_filter($rawShots, 'is_array')), $assetIds);
        $lock = IdentityLock::fromArray(is_array($lockPayload) ? $lockPayload : []);
        $timeline = AudioFirstPlanner::plan($graph, $beats);
        $renders = ($router ?? new RenderRouter())->routeAll($graph, $lock, $style);

        $fallbacks = 0;
        foreach ($renders as $r) {
            if ($r['fallbackUsed']) {
                $fallbacks++;
            }
        }
        $total = $timeline === [] ? 0.0 : $timeline[count($timeline) - 1]['endSec'];

        return [
            'ok'               => true,
            'costGbp'          => 0.0,
            'totalDurationSec' => $total,
            'timeline'         => $timeline,
            'renders'          => $renders,
            'fallbackCount'    => $fallbacks,
        ];
    }

    /**
     * Plan and render a multi-shot film to an MP4 + SRT sidecar at £0 recurring cost.
     *
     * @param array<string, mixed> $payload
     * @return array{ok: bool, costGbp: float, totalDurationSec: float, timeline: array<int, array<string, mixed>>, render: array{status: string, videoPath: ?string, srtPath: string, durationSec: float, clips: int, costGbp: float, elapsedMs: float, reason?: string}}
     * @throws ValidationException
     */
    public static function render(
        array $payload,
        string $outputDir,
        string $baseName = 'film',
        int $width = 1280,
        int $height = 720,
        ?ShotRenderer $renderer = null
    ): array {
        $rawShots = $payload['shots'] ?? null;
        $assets = $payload['assets'] ?? [];
        if (!is_array($rawShots) || !is_array($assets)) {
            throw new ValidationException('Shots and assets must be lists.');
        }

        $assetIds = [];
        foreach ($assets as $a) {
            $assetIds[] = (string) $a;
        }

        $style = (string) ($payload['style'] ?? 'storytelling');
        if (!in_array($style, self::ALLOWED_STYLES, true)) {
            throw new ValidationException('Unsupported style.');
        }

        $beats = $payload['beats'] ?? [];
        if (!is_array($beats) || count($beats) > 5000) {
            throw new ValidationException('Beat grid is invalid or too large.');
        }

        $lockPayload = $payload['lock'] ?? [];
        $graph = ShotGraph::fromArray(array_values(array_filter($rawShots, 'is_array')), $assetIds);
        $lock = IdentityLock::fromArray(is_array($lockPayload) ? $lockPayload : []);
        $timeline = AudioFirstPlanner::plan($graph, $beats);
        $renderResult = ($renderer ?? new ShotRenderer())->renderFilm($graph, $timeline, $lock, $outputDir, $baseName, $width, $height);

        return [
            'ok'               => true,
            'costGbp'          => 0.0,
            'totalDurationSec' => $renderResult['durationSec'],
            'timeline'         => $timeline,
            'render'           => $renderResult,
        ];
    }
}
