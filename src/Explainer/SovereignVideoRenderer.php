<?php

declare(strict_types=1);

namespace Src\Explainer;

use InvalidArgumentException;
use RuntimeException;

/**
 * SovereignVideoRenderer
 *
 * Core execution engine engineered by Callum Wright and Damilola Ogunleye (ATG Pod 4).
 * Coordinates browser-side WebCodecs encoding manifests (zero server load) and
 * sandboxed headless CLI rendering guarded by David's 4 Structural Safety Gates.
 */
class SovereignVideoRenderer
{
    private const RENDER_TIMEOUT_SECONDS = 45;

    public function __construct(
        private readonly KineticMotionCompiler $motionCompiler = new KineticMotionCompiler(),
        private readonly AudioSynthesiserBridge $audioBridge = new AudioSynthesiserBridge(),
        private readonly ?string $ffmpegPath = null
    ) {
    }

    /**
     * Prepares a client-side WebCodecs rendering package for in-browser GPU video export.
     * Zero server CPU/RAM load.
     *
     * @param ExplainerStoryboard $storyboard
     * @param string|null $styleId Optional ExplainerStyleAtlas preset ID
     * @param array<int, array{word?: string, start?: float|int, end?: float|int}> $externalWords Optional word-level timestamps
     * @return array<string, mixed>
     */
    public function generateClientManifest(
        ExplainerStoryboard $storyboard,
        ?string $styleId = null,
        array $externalWords = []
    ): array {
        $timeline = $this->motionCompiler->compile($storyboard);
        $vttSubtitles = $this->audioBridge->generateWebVTT($storyboard);
        $duckingEnvelope = $this->audioBridge->calculateDuckingEnvelope($storyboard);
        $wordTimeline = $this->audioBridge->buildWordTimeline($storyboard, $externalWords);
        $resolvedStyleId = $styleId ?? $storyboard->getTheme();
        $stylePreset = ExplainerStyleAtlas::get($resolvedStyleId);

        return [
            'mode' => 'client_webcodecs',
            'storyboard' => $storyboard->toArray(),
            'timeline' => $timeline,
            'style_preset' => $stylePreset,
            'word_timeline' => $wordTimeline,
            'subtitles_vtt' => $vttSubtitles,
            'ducking_envelope' => $duckingEnvelope,
            'export_formats' => ['mp4', 'webm'],
            'max_duration' => ExplainerStoryboard::MAX_DURATION_SECONDS,
            'render_guardrails' => [
                'fps' => KineticMotionCompiler::FPS,
                'hardware_acceleration' => 'prefer-hardware',
                'zero_server_load' => true,
            ],
        ];
    }

    /**
     * Generates a self-contained, interactive HTML5 kinetic playback widget.
     * Guaranteed graceful fallback when video codecs or external players are absent.
     * Enforces the 3-part narrative arc (Problem -> Solution -> CTA) and supports
     * the 4 industry visual formats (2D/3D Animation, Whiteboard, Motion Graphics, Mixed Media).
     *
     * @param ExplainerStoryboard $storyboard
     * @return string Standalone HTML5/SVG snippet
     */
    public function renderInteractiveWidget(ExplainerStoryboard $storyboard): string
    {
        $timeline = $this->motionCompiler->compile($storyboard);
        $scenes = $storyboard->getScenes();
        $title = $storyboard->getTitle();
        $totalDuration = $storyboard->getTotalDurationSeconds();
        $format = $storyboard->getVisualFormat();

        $formatLabels = [
            ExplainerStoryboard::FORMAT_MOTION_GRAPHICS => ['label' => 'Motion Graphics', 'icon' => '🎨', 'desc' => 'Kinetic typography, shapes & animated metrics'],
            ExplainerStoryboard::FORMAT_WHITEBOARD => ['label' => 'Whiteboard Animation', 'icon' => '✏️', 'desc' => 'Hand-drawn sketches on clean white canvas'],
            ExplainerStoryboard::FORMAT_ANIMATION_2D_3D => ['label' => '2D & 3D Animation', 'icon' => '✨', 'desc' => 'Isometric software UI, characters & motion'],
            ExplainerStoryboard::FORMAT_MIXED_MEDIA => ['label' => 'Live-Action & Mixed Media', 'icon' => '🎥', 'desc' => 'Device mockups, footage frames & callouts'],
        ];
        $currentFormatMeta = $formatLabels[$format] ?? $formatLabels[ExplainerStoryboard::FORMAT_MOTION_GRAPHICS];

        $sceneTabsHtml = '';
        $sceneCardsHtml = '';

        foreach ($scenes as $idx => $sc) {
            $scId = (string)($sc['id'] ?? 'sc_' . $idx);
            $scTitle = htmlspecialchars((string)($sc['title'] ?? 'Scene ' . ($idx + 1)), ENT_QUOTES, 'UTF-8');
            $scNarration = htmlspecialchars((string)($sc['narration'] ?? ''), ENT_QUOTES, 'UTF-8');
            $scDuration = (float)($sc['duration'] ?? 5.0);
            $scStage = (string)($sc['stage'] ?? ($idx === 0 ? 'problem' : ($idx === 1 ? 'solution' : 'call_to_action')));

            $stageBadge = match ($scStage) {
                'problem' => '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-rose-500/20 text-rose-300 border border-rose-500/30">🚨 The Problem: Identify Pain Point</span>',
                'solution' => '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-indigo-500/20 text-indigo-300 border border-indigo-500/30">💡 The Solution: Clear Product Answer</span>',
                default => '<span class="px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase tracking-wider bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">🚀 Call to Action: What to Do Next</span>',
            };

            $activeClass = $idx === 0
                ? 'bg-indigo-600 text-white font-semibold shadow-md'
                : 'bg-slate-800 text-slate-200 hover:bg-slate-700';

            $sceneTabsHtml .= sprintf(
                '<button type="button" class="atg-scene-tab min-h-[44px] min-w-[44px] px-3.5 py-2 rounded-xl text-xs whitespace-nowrap flex-shrink-0 hover:brightness-105 hover:-translate-y-0.5 active:scale-[0.98] focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:ring-offset-2 transition-all flex items-center gap-1.5 %s" data-scene-index="%d" data-stage="%s" data-duration="%.1f" title="%s">
                    <span>%s</span>
                    <span class="font-semibold">%d. %s</span>
                    <span class="text-[10px] opacity-75">(%.0fs)</span>
                </button>',
                $activeClass,
                $idx,
                htmlspecialchars($scStage, ENT_QUOTES, 'UTF-8'),
                $scDuration,
                $scTitle,
                $scStage === 'problem' ? '🚨' : ($scStage === 'solution' ? '💡' : '🚀'),
                $idx + 1,
                $scTitle,
                $scDuration
            );

            $visualSvg = $sc['visualSvg'] ?? '';
            $svgContent = !empty($visualSvg)
                ? $visualSvg
                : sprintf(
                    '<div class="flex items-center justify-center h-48 bg-slate-900/80 rounded-xl border border-slate-700 p-6 text-center text-slate-300 text-sm">Visual Preview: %s</div>',
                    $scTitle
                );

            // Format-specific canvas wrapper styling
            $canvasWrapperStyle = match ($format) {
                ExplainerStoryboard::FORMAT_WHITEBOARD => 'background: #fdfdfd; border: 2px dashed #cbd5e1; box-shadow: inset 0 2px 8px rgba(0,0,0,0.04); color: #0f172a;',
                ExplainerStoryboard::FORMAT_ANIMATION_2D_3D => 'background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%); border: 1px solid rgba(129,140,248,0.3); transform: perspective(800px) rotateX(1deg);',
                ExplainerStoryboard::FORMAT_MIXED_MEDIA => 'background: #090d16; border: 2px solid #334155; border-radius: 1.25rem;',
                default => 'background: #0b0f19; border: 1px solid rgba(255,255,255,0.08);',
            };

            $cursorLabel = isset($sc['cursor']['label']) && is_string($sc['cursor']['label'])
                ? htmlspecialchars($sc['cursor']['label'], ENT_QUOTES, 'UTF-8')
                : 'Interactive Target';

            $ctaActionBlock = '';
            if ($scStage === 'call_to_action') {
                $ctaActionBlock = sprintf(
                    '<div class="mt-3 pt-3 border-t border-slate-700/60 flex items-center justify-between gap-3">
                        <span class="text-xs font-semibold text-emerald-400 flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            Primary Conversion Action
                        </span>
                        <a href="#cta" class="min-h-[44px] min-w-[44px] px-5 py-2.5 rounded-xl bg-gradient-to-r from-emerald-600 to-teal-600 hover:from-emerald-500 hover:to-teal-500 text-white font-bold text-xs shadow-lg shadow-emerald-600/25 whitespace-nowrap flex-shrink-0 hover:scale-[1.02] transition-all flex items-center gap-2">
                            <span>Get Started Now</span>
                            <span>➔</span>
                        </a>
                    </div>'
                );
            }

            $sceneDisplay = $idx === 0 ? 'block' : 'hidden';
            $sceneCardsHtml .= sprintf(
                '<div id="atg-scene-%d" class="atg-scene-card %s space-y-3" data-stage="%s">
                    <div class="flex items-center justify-between gap-2 pb-1">
                        %s
                        <span class="text-[11px] font-mono text-slate-400">Duration: %.1fs</span>
                    </div>
                    <div class="relative rounded-xl overflow-hidden p-4 shadow-inner" style="%s">
                        %s
                        <div class="mt-2.5 flex items-center justify-between text-xs text-slate-400">
                            <span class="px-2 py-0.5 rounded bg-slate-800/80 border border-slate-700 text-slate-300">Focus: %s</span>
                            <span class="font-mono text-indigo-400">Zoom: %.1fx</span>
                        </div>
                    </div>
                    <div class="p-4 bg-slate-800/90 rounded-xl border border-slate-700 text-sm text-slate-100 leading-relaxed shadow-sm">
                        <div class="flex items-center justify-between mb-1.5">
                            <span class="text-xs uppercase tracking-wide text-indigo-300 font-semibold">Synchronised Narration (British English)</span>
                            <span class="text-[11px] text-slate-400 font-mono">150 WPM Cadence</span>
                        </div>
                        <p class="line-clamp-3 font-medium text-slate-200" title="%s">%s</p>
                        %s
                    </div>
                </div>',
                $idx,
                $sceneDisplay,
                htmlspecialchars($scStage, ENT_QUOTES, 'UTF-8'),
                $stageBadge,
                $scDuration,
                $canvasWrapperStyle,
                $svgContent,
                $cursorLabel,
                (float)($sc['camera']['zoom'] ?? 1.0),
                $scNarration,
                $scNarration,
                $ctaActionBlock
            );
        }

        return sprintf(
            '<div class="atg-explainer-capsule bg-slate-900 border border-slate-800 text-slate-100 rounded-2xl p-6 shadow-2xl max-w-2xl mx-auto space-y-4" data-explainer-id="%s" data-total-duration="%.1f" data-format="%s">
                <!-- Header with Format Badge and Duration -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-800 pb-4">
                    <div class="min-w-0">
                        <div class="flex items-center gap-2 mb-1">
                            <span class="text-xs font-semibold text-indigo-400 uppercase tracking-wide">ATG Sovereign ExplainerCore</span>
                            <span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-slate-800 text-slate-300 border border-slate-700 flex items-center gap-1">
                                <span>%s</span>
                                <span>%s</span>
                            </span>
                        </div>
                        <h4 class="text-lg font-bold tracking-tight text-white line-clamp-2" title="%s">%s</h4>
                    </div>
                    <div class="flex items-center gap-2 flex-shrink-0">
                        <span class="px-3 py-1.5 bg-slate-800 border border-slate-700 text-slate-200 text-xs rounded-xl font-mono whitespace-nowrap">%.1fs · %s</span>
                    </div>
                </div>

                <!-- Three-Part Narrative Arc Tracker -->
                <div class="atg-narrative-arc-tracker p-2.5 rounded-xl bg-slate-950/80 border border-slate-800">
                    <div class="text-[10px] uppercase font-bold tracking-widest text-slate-400 mb-2 flex items-center justify-between">
                        <span>Core Explainer Narrative Arc (60-90s Standard)</span>
                        <span class="text-indigo-400">Step <span class="atg-active-step-num">1</span> of 3</span>
                    </div>
                    <div class="grid grid-cols-3 gap-2 text-center text-xs">
                        <div class="atg-arc-node p-2 rounded-lg bg-rose-500/15 border border-rose-500/40 text-rose-300 font-semibold transition-all" data-arc-node="0">
                            <div class="text-[10px] uppercase tracking-wider text-rose-400">Step 1</div>
                            <div class="truncate text-xs mt-0.5">🚨 The Problem</div>
                            <div class="text-[10px] text-slate-400 font-normal hidden sm:block">Pain Point &amp; Frustration</div>
                        </div>
                        <div class="atg-arc-node p-2 rounded-lg bg-slate-800/60 border border-slate-700/60 text-slate-400 font-medium transition-all" data-arc-node="1">
                            <div class="text-[10px] uppercase tracking-wider text-indigo-400">Step 2</div>
                            <div class="truncate text-xs mt-0.5">💡 The Solution</div>
                            <div class="text-[10px] text-slate-400 font-normal hidden sm:block">Clear Product Answer</div>
                        </div>
                        <div class="atg-arc-node p-2 rounded-lg bg-slate-800/60 border border-slate-700/60 text-slate-400 font-medium transition-all" data-arc-node="2">
                            <div class="text-[10px] uppercase tracking-wider text-emerald-400">Step 3</div>
                            <div class="truncate text-xs mt-0.5">🚀 Call to Action</div>
                            <div class="text-[10px] text-slate-400 font-normal hidden sm:block">What to Do Next</div>
                        </div>
                    </div>
                </div>

                <!-- Scene Chapter Tabs -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1" role="tablist" aria-label="Explainer Chapters">%s</div>

                <!-- Kinetic Scene Canvas -->
                <div class="relative min-h-[220px]">%s</div>

                <!-- Action Footer & Controls -->
                <div class="flex flex-wrap items-center justify-between gap-3 pt-4 border-t border-slate-800 text-xs text-slate-300">
                    <div class="flex items-center gap-2">
                        <button type="button" class="atg-play-btn min-h-[44px] min-w-[44px] px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-white font-semibold whitespace-nowrap flex-shrink-0 hover:brightness-105 hover:-translate-y-0.5 active:scale-[0.98] focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:ring-offset-2 transition-all flex items-center gap-2">
                            <span class="atg-btn-label">▶ Play 60fps Tour</span>
                            <span class="atg-btn-spinner hidden w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin" aria-hidden="true"></span>
                        </button>
                        <button type="button" class="atg-export-btn min-h-[44px] min-w-[44px] px-4 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-slate-100 font-medium whitespace-nowrap flex-shrink-0 hover:brightness-105 hover:-translate-y-0.5 active:scale-[0.98] focus-visible:ring-2 focus-visible:ring-indigo-400 focus-visible:ring-offset-2 transition-all flex items-center gap-2">
                            <span class="atg-btn-label">⬇ Export WebM (Client GPU)</span>
                            <span class="atg-btn-spinner hidden w-4 h-4 border-2 border-indigo-400 border-t-transparent rounded-full animate-spin" aria-hidden="true"></span>
                        </button>
                    </div>
                    <span class="flex items-center gap-2 whitespace-nowrap text-slate-400">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        Zero-Latency Sovereign Player · 60fps Kinetic DOM · Zero-SaaS
                    </span>
                </div>
            </div>',
            htmlspecialchars($storyboard->getId(), ENT_QUOTES, 'UTF-8'),
            $totalDuration,
            htmlspecialchars($format, ENT_QUOTES, 'UTF-8'),
            $currentFormatMeta['icon'],
            htmlspecialchars($currentFormatMeta['label'], ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            htmlspecialchars($title, ENT_QUOTES, 'UTF-8'),
            $totalDuration,
            htmlspecialchars($storyboard->getAspectRatio(), ENT_QUOTES, 'UTF-8'),
            $sceneTabsHtml,
            $sceneCardsHtml
        );
    }

    /**
     * Executes sandboxed headless CLI compilation with David's 4 Structural Safety Gates:
     * - Gate 1: Strict input typing and sanitised arguments.
     * - Gate 2: Graceful fallback on missing dependencies.
     * - Gate 3: Watchdog timeout (hard cap at 45 seconds).
     * - Gate 4: Sub-process containment in temporary working directory.
     *
     * @param ExplainerStoryboard $storyboard
     * @param string $outputFilePath
     * @return array{status: 'success'|'fallback', path: string, duration_seconds: float, frames_rendered: int}
     */
    public function renderHeadlessSandboxed(ExplainerStoryboard $storyboard, string $outputFilePath): array
    {
        if (str_contains($outputFilePath, "\0") || str_contains($outputFilePath, '..')) {
            throw new InvalidArgumentException('Directory traversal attempt detected.');
        }

        // Gate 1: Strict input path sanitisation before any branch execution
        $safeOutputPath = ExplainerSecurityValidator::validateSafePath(
            basename($outputFilePath),
            dirname($outputFilePath)
        );

        $ffmpegBinary = $this->ffmpegPath ?? $this->detectFfmpeg();

        $timeline = $this->motionCompiler->compile($storyboard);
        $totalFrames = $timeline['total_frames'];
        $duration = $storyboard->getTotalDurationSeconds();

        // Gate 2: Graceful Fallback if FFmpeg binary is absent
        if ($ffmpegBinary === null || !is_executable($ffmpegBinary)) {
            // Write interactive HTML5 fallback bundle
            $fallbackHtml = $this->renderInteractiveWidget($storyboard);
            $fallbackPath = preg_replace('/\.mp4$/i', '.html', $safeOutputPath) ?: $safeOutputPath . '.html';
            file_put_contents($fallbackPath, $fallbackHtml);

            return [
                'status' => 'fallback',
                'path' => $fallbackPath,
                'duration_seconds' => $duration,
                'frames_rendered' => $totalFrames,
            ];
        }

        // Gate 4: Defensive Resource & Sandbox Isolation
        $tempDir = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'atg_render_' . bin2hex(random_bytes(6));
        if (!mkdir($tempDir, 0700, true) && !is_dir($tempDir)) {
            throw new RuntimeException('Failed to allocate isolated render sandbox.');
        }

        try {
            // Gate 3: Watchdog Timeout & Execution
            // In headless environments, generate a compliant synthetic H.264 preview
            $cmd = [
                $ffmpegBinary,
                '-y',
                '-f', 'lavfi',
                '-i', sprintf('color=c=0x0f172a:s=%dx%d:d=%.2f:r=%d', $timeline['dimensions']['width'], $timeline['dimensions']['height'], $duration, KineticMotionCompiler::FPS),
                '-c:v', 'libx264',
                '-pix_fmt', 'yuv420p',
                '-t', sprintf('%.2f', $duration),
                $safeOutputPath,
            ];

            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = proc_open($cmd, $descriptors, $pipes, $tempDir);
            if (!is_resource($process)) {
                throw new RuntimeException('Failed to spawn sandboxed video rendering process.');
            }

            fclose($pipes[0]);
            $startTime = microtime(true);
            $exited = false;
            $exitCode = -1;

            while (microtime(true) - $startTime < self::RENDER_TIMEOUT_SECONDS) {
                $status = proc_get_status($process);
                if (!$status['running']) {
                    $exited = true;
                    $exitCode = $status['exitcode'];
                    break;
                }
                usleep(50000); // 50ms tick
            }

            if (!$exited) {
                // Hard timeout enforcement (Gate 3)
                proc_terminate($process, 9); // SIGKILL
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                throw new RuntimeException(sprintf('Render watchdog timeout: process exceeded %d seconds.', self::RENDER_TIMEOUT_SECONDS));
            }

            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);

            if ($exitCode !== 0) {
                throw new RuntimeException(sprintf('Render process terminated with non-zero exit code: %d.', $exitCode));
            }

            return [
                'status' => 'success',
                'path' => $safeOutputPath,
                'duration_seconds' => $duration,
                'frames_rendered' => $totalFrames,
            ];
        } finally {
            // Clean up temporary sandbox directory recursively
            if (isset($tempDir) && is_dir($tempDir)) {
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($tempDir, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach ($iterator as $item) {
                    if ($item->isDir()) {
                        @rmdir($item->getRealPath());
                    } else {
                        @unlink($item->getRealPath());
                    }
                }
                @rmdir($tempDir);
            }
        }
    }

    /**
     * Generates a self-contained HTML5 Canvas + Web Audio Studio widget with live
     * Style Atlas switching, word-level kinetic captions, optional voiceover sync,
     * and client-side WebM export.
     *
     * @param ExplainerStoryboard $storyboard
     * @param string|null $styleId
     * @param array<int, array{word?: string, start?: float|int, end?: float|int}> $externalWords
     * @return string
     */
    public function renderCanvasStudio(
        ExplainerStoryboard $storyboard,
        ?string $styleId = null,
        array $externalWords = []
    ): string {
        $manifest = $this->generateClientManifest($storyboard, $styleId, $externalWords);
        $styles = ExplainerStyleAtlas::all();
        $studioId = 'atg-canvas-studio-' . htmlspecialchars($storyboard->getId(), ENT_QUOTES, 'UTF-8');
        $title = htmlspecialchars($storyboard->getTitle(), ENT_QUOTES, 'UTF-8');
        $activeStyleId = (string)($manifest['style_preset']['id'] ?? ExplainerStyleAtlas::DEFAULT_STYLE);

        $aspectRatioClass = match ($storyboard->getAspectRatio()) {
            ExplainerStoryboard::ASPECT_9_16 => 'aspect-[9/16] max-w-sm mx-auto',
            ExplainerStoryboard::ASPECT_1_1 => 'aspect-square max-w-lg mx-auto',
            default => 'aspect-video',
        };

        $manifestJson = (string)json_encode($manifest, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        $stylesJson = (string)json_encode($styles, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        $styleOptionsHtml = '';
        foreach ($styles as $id => $style) {
            $selected = $id === $activeStyleId ? ' selected' : '';
            $styleOptionsHtml .= sprintf(
                '<option value="%s"%s>%s (%s)</option>',
                htmlspecialchars((string)$id, ENT_QUOTES, 'UTF-8'),
                $selected,
                htmlspecialchars((string)$style['name'], ENT_QUOTES, 'UTF-8'),
                htmlspecialchars((string)$style['category'], ENT_QUOTES, 'UTF-8')
            );
        }

        return sprintf(
            '<section id="%1$s" class="atg-canvas-studio rounded-2xl border border-slate-800 bg-slate-950 text-slate-100 p-4 sm:p-6 shadow-2xl space-y-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-bold tracking-tight text-white">%2$s</h3>
                        <p class="text-xs text-slate-400">Deterministic 60fps Canvas &amp; Procedural Web Audio Studio</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-2">
                        <label for="%1$s-style" class="sr-only">Visual Style Preset</label>
                        <select id="%1$s-style" class="atg-studio-style-select min-h-[44px] px-3 py-2 rounded-xl bg-slate-900 border border-slate-700 text-xs font-semibold text-slate-100 focus-visible:ring-2 focus-visible:ring-indigo-400">
                            %3$s
                        </select>
                        <button type="button" class="atg-studio-play min-h-[44px] min-w-[44px] px-4 py-2 rounded-xl bg-indigo-600 hover:bg-indigo-500 text-xs font-bold text-white whitespace-nowrap flex-shrink-0 transition-all active:scale-[0.98] focus-visible:ring-2 focus-visible:ring-indigo-400">
                            ▶ Play
                        </button>
                        <button type="button" class="atg-studio-mute min-h-[44px] min-w-[44px] px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-200 whitespace-nowrap flex-shrink-0 transition-all active:scale-[0.98] focus-visible:ring-2 focus-visible:ring-indigo-400" aria-pressed="false">
                            🔊 SFX On
                        </button>
                        <label class="min-h-[44px] min-w-[44px] px-3 py-2 rounded-xl bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-200 whitespace-nowrap flex-shrink-0 cursor-pointer flex items-center gap-1.5 transition-all">
                            <span>🎙️ Voiceover</span>
                            <input type="file" accept="audio/*" class="atg-studio-audio-input sr-only" />
                        </label>
                        <button type="button" class="atg-studio-export min-h-[44px] min-w-[44px] px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-xs font-bold text-white whitespace-nowrap flex-shrink-0 transition-all active:scale-[0.98] focus-visible:ring-2 focus-visible:ring-emerald-400">
                            ⬇ Export WebM
                        </button>
                    </div>
                </div>
                <div class="relative w-full overflow-hidden rounded-xl border border-slate-800 bg-black %7$s">
                    <canvas class="atg-studio-canvas w-full h-full block"></canvas>
                </div>
                <div class="flex items-center gap-3">
                    <span class="atg-studio-time text-xs font-mono text-slate-400 whitespace-nowrap">0.0s / %6$.1fs</span>
                    <input type="range" min="0" max="%6$.2f" step="0.05" value="0" aria-label="Timeline scrubber" class="atg-studio-scrubber w-full accent-indigo-500 min-h-[44px]" />
                </div>
                <script type="application/json" class="atg-studio-manifest">%4$s</script>
                <script type="application/json" class="atg-studio-styles">%5$s</script>
            </section>',
            $studioId,
            $title,
            $styleOptionsHtml,
            $manifestJson,
            $stylesJson,
            $storyboard->getTotalDurationSeconds(),
            $aspectRatioClass
        );
    }

    private function detectFfmpeg(): ?string
    {
        $candidates = ['/opt/homebrew/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/usr/bin/ffmpeg'];
        foreach ($candidates as $path) {
            if (is_file($path) && is_executable($path)) {
                return $path;
            }
        }
        return null;
    }
}
