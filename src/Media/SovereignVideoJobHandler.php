<?php

declare(strict_types=1);

namespace Src\Media;

use Src\Exceptions\ValidationException;

/**
 * SovereignVideoJobHandler
 *
 * Deterministic PHP 8.2+ / FFmpeg server fallback and sanitisation engine for the
 * 12-module Sovereign Kinship & Event Video Studio.
 *
 * Enforces:
 * - Zero external SaaS dependencies, zero Python/PyTorch servers
 * - <= O(n log n) deterministic algorithms
 * - GDPR Article 9 biometric/voice consent gate for Voice Cloning & Dubbing
 * - Marcus & Ghost adversarial sanitisation (strict bounds, memory-bomb guards, zero SSRF)
 */
class SovereignVideoJobHandler
{
    public const MAX_WIDTH = 3840;
    public const MAX_HEIGHT = 2160;
    public const MAX_DURATION_SECONDS = 600.0;
    public const MAX_PROMPT_LENGTH = 2000;
    public const MAX_CLIPS = 50;
    public const MAX_AUDIO_SAMPLES = 5_292_000; // 120s @ 44.1kHz mono
    public const MAX_TEXT_OVERLAY_LENGTH = 500;

    /** @var array<string, array{label: string, accent: string, pitchHz: float, rate: float, warmthDb: float}> */
    public const BRITISH_VOICE_PROFILES = [
        'oliver' => [
            'label'   => 'Oliver (British RP)',
            'accent'  => 'en-GB',
            'pitchHz' => 118.0,
            'rate'    => 1.0,
            'warmthDb' => 2.5,
        ],
        'serena' => [
            'label'   => 'Serena (Home Counties)',
            'accent'  => 'en-GB',
            'pitchHz' => 205.0,
            'rate'    => 1.02,
            'warmthDb' => 3.0,
        ],
        'elders' => [
            'label'   => 'Elders (Warm Heritage)',
            'accent'  => 'en-GB',
            'pitchHz' => 104.0,
            'rate'    => 0.92,
            'warmthDb' => 4.5,
        ],
    ];

    /** @var array<string, string> */
    public const LUT_PRESETS = [
        'heritage_gold'     => 'eq=contrast=1.08:brightness=0.02:saturation=1.18,colorbalance=rs=0.06:gs=0.03:bs=-0.04',
        'cinema_teal_orange' => 'eq=contrast=1.14:saturation=1.12,colorbalance=rs=0.08:gs=-0.02:bs=-0.09:rh=0.05:bh=0.07',
        'vintage_kodak'     => 'eq=contrast=1.05:brightness=0.01:saturation=0.92,colorbalance=rs=0.07:gs=0.02:bs=-0.06',
        'crisp_natural'     => 'eq=contrast=1.04:brightness=0.01:saturation=1.05',
    ];

    /**
     * Assert that a local media path is safe (no SSRF protocols, no null bytes, no path traversal).
     *
     * @throws ValidationException
     */
    public static function assertSafeLocalMediaPath(string $path): string
    {
        $trimmed = trim($path);
        if ($trimmed === '' || str_contains($trimmed, "\0")) {
            throw new ValidationException('SECURITY VIOLATION - Invalid empty or null-byte media path.');
        }

        if (preg_match('#^(https?|ftp|file|php|phar|data|gopher|ssh|dict)://#i', $trimmed) === 1) {
            throw new ValidationException('SECURITY VIOLATION - External or protocol-prefixed media URLs are blocked (Zero-SSRF).');
        }

        if (str_contains($trimmed, '..')) {
            throw new ValidationException('SECURITY VIOLATION - Directory traversal detected in media path.');
        }

        return $trimmed;
    }

    /**
     * Validate canvas/video dimensions against memory-bomb ceilings.
     *
     * @return array{width: int, height: int}
     * @throws ValidationException
     */
    public static function validateDimensions(int $width, int $height): array
    {
        if ($width < 16 || $height < 16) {
            throw new ValidationException('Dimensions must be at least 16x16 pixels.');
        }

        if ($width > self::MAX_WIDTH || $height > self::MAX_HEIGHT) {
            throw new ValidationException('SECURITY VIOLATION - Video dimensions exceed 4K UHD (3840x2160) memory-bomb ceiling.');
        }

        // Align to even dimensions for YUV420p compatibility
        $evenW = $width - ($width % 2);
        $evenH = $height - ($height % 2);

        return ['width' => $evenW, 'height' => $evenH];
    }

    /**
     * Sanitise a multitrack timeline payload with strict bounds.
     *
     * @param array<int, array<string, mixed>> $clips
     * @return array<int, array{id: string, title: string, startSec: float, durationSec: float, track: string}>
     * @throws ValidationException
     */
    public static function sanitiseTimelinePayload(array $clips): array
    {
        if (count($clips) > self::MAX_CLIPS) {
            throw new ValidationException('Timeline exceeds maximum allowed clip count (' . self::MAX_CLIPS . ').');
        }

        $sanitised = [];
        $totalDuration = 0.0;

        foreach ($clips as $index => $clip) {
            $id = substr(preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($clip['id'] ?? ('clip_' . $index))) ?? ('clip_' . $index), 0, 64);
            $title = self::sanitiseOverlayText((string) ($clip['title'] ?? 'Untitled Scene'));
            $startSec = max(0.0, min(self::MAX_DURATION_SECONDS, (float) ($clip['startSec'] ?? 0.0)));
            $durationSec = max(0.2, min(180.0, (float) ($clip['durationSec'] ?? 3.5)));
            $track = in_array((string) ($clip['track'] ?? 'V1'), ['V1', 'V2', 'T1', 'A1', 'A2'], true)
                ? (string) ($clip['track'] ?? 'V1')
                : 'V1';

            $totalDuration += $durationSec;
            if ($totalDuration > self::MAX_DURATION_SECONDS) {
                throw new ValidationException('Total timeline duration exceeds 600-second ceiling.');
            }

            $sanitised[] = [
                'id'          => $id !== '' ? $id : ('clip_' . $index),
                'title'       => $title,
                'startSec'    => round($startSec, 3),
                'durationSec' => round($durationSec, 3),
                'track'       => $track,
            ];
        }

        return $sanitised;
    }

    /**
     * Module 1: Text-to-Video Generation (Procedural Storyboard Assembler).
     *
     * @return array{
     *   template: string,
     *   totalDurationSec: float,
     *   scenes: array<int, array{index: int, headline: string, subline: string, startSec: float, durationSec: float, palette: string, motion: string}>
     * }
     * @throws ValidationException
     */
    public static function buildStoryboardManifest(string $prompt, string $template = 'heritage_gold', float $targetDurationSec = 15.0): array
    {
        $cleanPrompt = trim(strip_tags($prompt));
        if ($cleanPrompt === '') {
            throw new ValidationException('Story prompt cannot be empty.');
        }
        if (mb_strlen($cleanPrompt) > self::MAX_PROMPT_LENGTH) {
            throw new ValidationException('Story prompt exceeds ' . self::MAX_PROMPT_LENGTH . ' character limit.');
        }

        $safeDuration = max(4.0, min(120.0, $targetDurationSec));
        $allowedTemplates = ['heritage_gold', 'royal_crimson', 'modern_editorial', 'celebration_neon'];
        $safeTemplate = in_array($template, $allowedTemplates, true) ? $template : 'heritage_gold';

        $sentences = preg_split('/(?<=[.!?])\s+|\n+/', $cleanPrompt, -1, PREG_SPLIT_NO_EMPTY);
        if (!is_array($sentences) || count($sentences) === 0) {
            $sentences = [$cleanPrompt];
        }
        $sentences = array_slice($sentences, 0, 8);

        $sceneCount = count($sentences);
        $perScene = round($safeDuration / $sceneCount, 3);
        $motions = ['ken_burns_in', 'parallax_pan_right', 'kinetic_zoom_burst', 'smooth_drift_up'];

        $scenes = [];
        $cursor = 0.0;
        foreach ($sentences as $idx => $sentence) {
            $words = preg_split('/\s+/', trim($sentence), -1, PREG_SPLIT_NO_EMPTY) ?: [];
            $headline = implode(' ', array_slice($words, 0, 6));
            $subline = count($words) > 6 ? implode(' ', array_slice($words, 6, 12)) : 'Sovereign Studio Edition';

            $scenes[] = [
                'index'       => $idx,
                'headline'    => self::sanitiseOverlayText($headline),
                'subline'     => self::sanitiseOverlayText($subline),
                'startSec'    => round($cursor, 3),
                'durationSec' => $perScene,
                'palette'     => $safeTemplate,
                'motion'      => $motions[$idx % count($motions)],
            ];
            $cursor += $perScene;
        }

        return [
            'template'         => $safeTemplate,
            'totalDurationSec' => round($cursor, 3),
            'scenes'           => $scenes,
        ];
    }

    /**
     * Module 2: Image-to-Video Animation (2.5D Parallax / Ken Burns FFmpeg filtergraph).
     *
     * @throws ValidationException
     */
    public static function buildParallaxFilterGraph(int $width, int $height, float $durationSec = 4.0, string $motion = 'zoom_in'): string
    {
        $dims = self::validateDimensions($width, $height);
        $fps = 30;
        $frames = max(30, min(1800, (int) round($durationSec * $fps)));
        $w = $dims['width'];
        $h = $dims['height'];

        $zoomExpr = match ($motion) {
            'zoom_out' => "if(lte(zoom,1.0),1.25,max(1.0,zoom-0.0015))",
            'pan_left' => '1.12',
            default    => "min(zoom+0.0015,1.25)",
        };

        $xExpr = $motion === 'pan_left'
            ? "max(0,(iw-iw/zoom)*(1-on/{$frames}))"
            : "iw/2-(iw/zoom/2)";

        return sprintf(
            'zoompan=z=\'%s\':x=\'%s\':y=\'ih/2-(ih/zoom/2)\':d=%d:s=%dx%d:fps=%d,format=yuv420p',
            $zoomExpr,
            $xExpr,
            $frames,
            $w,
            $h,
            $fps
        );
    }

    /**
     * Module 3: AI Avatars (Procedural Viseme + Blink + Head-Nod Keyframe Timeline).
     *
     * @param array<int, float> $rmsSamples Normalised RMS amplitudes [0.0..1.0]
     * @return array<int, array{timeSec: float, viseme: string, mouthOpen: float, blink: bool, headNodDeg: float}>
     * @throws ValidationException
     */
    public static function buildAvatarVisemeTimeline(array $rmsSamples, float $sampleIntervalSec = 0.05): array
    {
        if (count($rmsSamples) > 12000) {
            throw new ValidationException('Avatar timeline exceeds maximum 12,000 keyframe samples.');
        }

        $interval = max(0.016, min(1.0, $sampleIntervalSec));
        $visemes = ['sil', 'ih', 'E', 'aa', 'oh'];
        $timeline = [];

        foreach ($rmsSamples as $idx => $rawAmp) {
            $amp = max(0.0, min(1.0, (float) $rawAmp));
            $timeSec = round($idx * $interval, 3);
            $vIdx = $amp < 0.05 ? 0 : min(4, 1 + (int) floor($amp * 3.99));
            $blink = ($idx > 0) && (($idx % 70) === 0 || ($idx % 70) === 1);
            $headNodDeg = round(sin($timeSec * 2.2) * (1.5 + ($amp * 3.5)), 2);

            $timeline[] = [
                'timeSec'    => $timeSec,
                'viseme'     => $visemes[$vIdx],
                'mouthOpen'  => round($amp, 3),
                'blink'      => $blink,
                'headNodDeg' => $headNodDeg,
            ];
        }

        return $timeline;
    }

    /**
     * Module 4: Auto Captions (Deterministic Word-Level Karaoke SRT Generator).
     *
     * @param array<int, mixed> $words
     */
    public static function buildKaraokeSrt(array $words, int $wordsPerLine = 5): string
    {
        if (count($words) === 0) {
            return '';
        }

        $safeChunk = max(1, min(10, $wordsPerLine));
        $chunks = array_chunk(array_slice($words, 0, 2000), $safeChunk);
        $srt = [];

        foreach ($chunks as $i => $group) {
            $start = (float) ($group[0]['startSec'] ?? 0.0);
            $end = (float) ($group[count($group) - 1]['endSec'] ?? ($start + 1.5));
            if ($end <= $start) {
                $end = $start + 0.5;
            }

            $textParts = [];
            foreach ($group as $w) {
                if (!is_array($w)) {
                    continue;
                }
                $cleanWord = self::sanitiseOverlayText((string) ($w['word'] ?? ''));
                if ($cleanWord !== '') {
                    $textParts[] = $cleanWord;
                }
            }

            if (count($textParts) === 0) {
                continue;
            }

            $srt[] = sprintf(
                "%d\n%s --> %s\n%s\n",
                $i + 1,
                self::formatSrtTimestamp($start),
                self::formatSrtTimestamp($end),
                implode(' ', $textParts)
            );
        }

        return implode("\n", $srt);
    }

    /**
     * Module 5: Text-to-Speech Voiceovers (British English Profile Descriptor).
     *
     * @return array{profile: string, label: string, accent: string, pitchHz: float, rate: float, warmthDb: float, text: string}
     * @throws ValidationException
     */
    public static function buildBritishTtsDescriptor(string $text, string $profile = 'oliver', float $rateOverride = 1.0, float $pitchShiftSemitones = 0.0): array
    {
        $cleanText = self::sanitiseOverlayText($text);
        if ($cleanText === '') {
            throw new ValidationException('Voiceover script cannot be empty.');
        }

        $key = array_key_exists($profile, self::BRITISH_VOICE_PROFILES) ? $profile : 'oliver';
        $base = self::BRITISH_VOICE_PROFILES[$key];
        $safeRate = max(0.6, min(1.6, $rateOverride));
        $safePitchFactor = pow(2.0, max(-6.0, min(6.0, $pitchShiftSemitones)) / 12.0);

        return [
            'profile'  => $key,
            'label'    => $base['label'],
            'accent'   => $base['accent'],
            'pitchHz'  => round($base['pitchHz'] * $safePitchFactor, 2),
            'rate'     => round($base['rate'] * $safeRate, 3),
            'warmthDb' => $base['warmthDb'],
            'text'     => $cleanText,
        ];
    }

    /**
     * Module 6: Voice Cloning & Dubbing — Mandatory GDPR Article 9 Biometric Consent Gate.
     *
     * @throws ValidationException
     */
    public static function validateBiometricVoiceConsent(bool $gdprArticle9Consent, string $subjectUserId): void
    {
        if (trim($subjectUserId) === '') {
            throw new ValidationException('GDPR Article 9 Violation - Subject user ID is required for voice biometric processing.');
        }

        if (!$gdprArticle9Consent) {
            throw new ValidationException('GDPR Article 9 Gate - Explicit biometric voice consent is required before cloning or dubbing.');
        }
    }

    /**
     * Module 6: Voice Cloning & Dubbing DSP FFmpeg Filtergraph (after consent gate passes).
     *
     * @throws ValidationException
     */
    public static function buildVoiceDubbingFilterGraph(bool $gdprArticle9Consent, string $subjectUserId, float $targetPitchRatio = 1.0, float $formantShiftHz = 0.0): string
    {
        self::validateBiometricVoiceConsent($gdprArticle9Consent, $subjectUserId);

        $safePitch = max(0.7, min(1.4, $targetPitchRatio));
        $safeFormant = max(-250.0, min(250.0, $formantShiftHz));
        $centerFreq = (int) round(1100.0 + $safeFormant);

        return sprintf(
            'asetrate=44100*%.4f,aresample=44100,atempo=%.4f,equalizer=f=%d:t=q:w=1.5:g=3',
            $safePitch,
            1.0 / $safePitch,
            $centerFreq
        );
    }

    /**
     * Module 7: Auto-Cut & Silence Removal (O(n) PCM RMS Silence Detector).
     *
     * @param array<int, float> $pcmSamples Mono PCM samples [-1.0..1.0]
     * @return array<int, array{startSec: float, endSec: float, durationSec: float}>
     * @throws ValidationException
     */
    public static function detectSilencePcm(
        array $pcmSamples,
        int $sampleRate = 44100,
        float $thresholdRms = 0.02,
        float $minSilenceSec = 0.4
    ): array {
        if (count($pcmSamples) > self::MAX_AUDIO_SAMPLES) {
            throw new ValidationException('SECURITY VIOLATION - PCM buffer exceeds maximum allowed sample ceiling.');
        }

        $safeRate = max(8000, min(96000, $sampleRate));
        $windowSize = max(1, (int) round($safeRate * 0.05)); // 50ms windows
        $safeThreshold = max(0.001, min(0.5, $thresholdRms));
        $safeMinSilence = max(0.1, min(10.0, $minSilenceSec));

        $intervals = [];
        $silenceStart = null;
        $total = count($pcmSamples);

        for ($i = 0; $i < $total; $i += $windowSize) {
            $sumSq = 0.0;
            $count = 0;
            $end = min($total, $i + $windowSize);
            for ($j = $i; $j < $end; $j++) {
                $v = (float) $pcmSamples[$j];
                $sumSq += ($v * $v);
                $count++;
            }

            $rms = $count > 0 ? sqrt($sumSq / $count) : 0.0;
            $timeSec = $i / $safeRate;

            if ($rms < $safeThreshold) {
                if ($silenceStart === null) {
                    $silenceStart = $timeSec;
                }
            } else {
                if ($silenceStart !== null) {
                    $dur = $timeSec - $silenceStart;
                    if ($dur >= $safeMinSilence) {
                        $intervals[] = [
                            'startSec'    => round($silenceStart, 3),
                            'endSec'      => round($timeSec, 3),
                            'durationSec' => round($dur, 3),
                        ];
                    }
                    $silenceStart = null;
                }
            }
        }

        if ($silenceStart !== null) {
            $endSec = $total / $safeRate;
            $dur = $endSec - $silenceStart;
            if ($dur >= $safeMinSilence) {
                $intervals[] = [
                    'startSec'    => round($silenceStart, 3),
                    'endSec'      => round($endSec, 3),
                    'durationSec' => round($dur, 3),
                ];
            }
        }

        return $intervals;
    }

    /**
     * Module 8: Background Removal (Deterministic Chroma-Key FFmpeg Filtergraph).
     *
     * @throws ValidationException
     */
    public static function buildChromaKeyFilterGraph(string $hexColour = '00FF00', float $similarity = 0.15, float $blend = 0.08): string
    {
        $cleanHex = strtoupper(ltrim(trim($hexColour), '#'));
        if (preg_match('/^[0-9A-F]{6}$/', $cleanHex) !== 1) {
            throw new ValidationException('Invalid chroma-key hex colour.');
        }

        $safeSim = max(0.01, min(0.60, $similarity));
        $safeBlend = max(0.0, min(0.40, $blend));

        return sprintf('chromakey=0x%s:%.3f:%.3f', $cleanHex, $safeSim, $safeBlend);
    }

    /**
     * Module 9: Subject Tracking (Validated ROI & Privacy Blur Mask Bounds).
     *
     * @return array{x: int, y: int, width: int, height: int, mode: string, filterGraph: string}
     * @throws ValidationException
     */
    public static function validateTrackingRegion(int $x, int $y, int $boxW, int $boxH, int $frameW, int $frameH, string $mode = 'callout'): array
    {
        $dims = self::validateDimensions($frameW, $frameH);
        $safeX = max(0, min($dims['width'] - 16, $x));
        $safeY = max(0, min($dims['height'] - 16, $y));
        $safeW = max(16, min($dims['width'] - $safeX, $boxW));
        $safeH = max(16, min($dims['height'] - $safeY, $boxH));
        $safeMode = in_array($mode, ['callout', 'pan_scan', 'privacy_blur'], true) ? $mode : 'callout';

        $filter = match ($safeMode) {
            'privacy_blur' => sprintf('delogo=x=%d:y=%d:w=%d:h=%d:show=0', $safeX, $safeY, $safeW, $safeH),
            'pan_scan'     => sprintf('crop=%d:%d:%d:%d', $safeW, $safeH, $safeX, $safeY),
            default        => sprintf('drawbox=x=%d:y=%d:w=%d:h=%d:color=gold@0.85:t=3', $safeX, $safeY, $safeW, $safeH),
        };

        return [
            'x'           => $safeX,
            'y'           => $safeY,
            'width'       => $safeW,
            'height'      => $safeH,
            'mode'        => $safeMode,
            'filterGraph' => $filter,
        ];
    }

    /**
     * Module 10: Video Upscaling (Lanczos-3 + Unsharp-Mask + Temporal De-Noising).
     *
     * @throws ValidationException
     */
    public static function buildLanczosUpscaleFilterGraph(int $targetWidth, int $targetHeight, float $sharpenAmount = 0.8): string
    {
        $dims = self::validateDimensions($targetWidth, $targetHeight);
        $safeSharpen = max(0.0, min(2.0, $sharpenAmount));

        return sprintf(
            'hqdn3d=1.5:1.5:6:6,scale=%d:%d:flags=lanczos,unsharp=5:5:%.2f:5:5:0.0,format=yuv420p',
            $dims['width'],
            $dims['height'],
            $safeSharpen
        );
    }

    /**
     * Module 11: AI Colour Matching & Enhancement (LUT + Skin-Tone Safe EQ).
     */
    public static function buildColourMatchFilterGraph(string $lutPreset = 'heritage_gold', float $skinProtection = 0.85): string
    {
        $key = array_key_exists($lutPreset, self::LUT_PRESETS) ? $lutPreset : 'heritage_gold';
        $safeProtection = max(0.0, min(1.0, $skinProtection));
        $base = self::LUT_PRESETS[$key];

        // Slightly dampen saturation shift when high skin-tone protection is enabled
        $gamma = round(1.0 + ((1.0 - $safeProtection) * 0.04), 3);

        return sprintf('%s,eq=gamma=%.3f', $base, $gamma);
    }

    /**
     * Module 12: Audio Noise Reduction (Dual-Pass Spectral Subtraction + 50/60Hz Notch).
     */
    public static function buildNoiseReductionFilterGraph(int $humHz = 50, float $noiseFloorDb = -28.0): string
    {
        $safeHum = in_array($humHz, [50, 60], true) ? $humHz : 50;
        $safeFloor = max(-60.0, min(-10.0, $noiseFloorDb));

        return sprintf(
            'highpass=f=80,bandreject=f=%d:width_type=q:w=4,afftdn=nf=%.1f:tn=1',
            $safeHum,
            $safeFloor
        );
    }

    /**
     * Build a safe, deterministic FFmpeg CLI command using escapeshellarg() and Zero-SSRF path checks.
     *
     * @throws ValidationException
     */
    public static function buildDeterministicFfmpegCommand(
        string $inputPath,
        string $outputPath,
        string $videoFilterGraph = '',
        string $audioFilterGraph = ''
    ): string {
        $safeIn = self::assertSafeLocalMediaPath($inputPath);
        $safeOut = self::assertSafeLocalMediaPath($outputPath);

        $parts = [
            'ffmpeg -y -i',
            escapeshellarg($safeIn),
        ];

        if (trim($videoFilterGraph) !== '') {
            $parts[] = '-vf ' . escapeshellarg(trim($videoFilterGraph));
        }
        if (trim($audioFilterGraph) !== '') {
            $parts[] = '-af ' . escapeshellarg(trim($audioFilterGraph));
        }

        $parts[] = '-c:v libx264 -preset fast -crf 20 -c:a aac -b:a 192k -movflags +faststart';
        $parts[] = escapeshellarg($safeOut);

        return implode(' ', $parts);
    }

    /**
     * NLE Core: Sanitise full 4-Quadrant NLE project payload (aspect ratio, tracks, clips, keyframes, masks, speed).
     *
     * @param array<string, mixed> $project
     * @return array{aspectRatio: string, clips: array<int, array<string, mixed>>}
     * @throws ValidationException
     */
    public static function sanitiseNleProjectPayload(array $project): array
    {
        $aspect = in_array((string) ($project['aspectRatio'] ?? '16:9'), ['16:9', '9:16', '1:1'], true)
            ? (string) $project['aspectRatio']
            : '16:9';

        $rawClips = is_array($project['clips'] ?? null) ? $project['clips'] : [];
        if (count($rawClips) > self::MAX_CLIPS) {
            throw new ValidationException('NLE project exceeds maximum clip ceiling (' . self::MAX_CLIPS . ').');
        }

        $allowedBlend = ['normal', 'multiply', 'screen', 'overlay', 'add', 'soft_light'];
        $allowedMasks = ['none', 'split', 'filmstrip', 'circle', 'rectangle', 'linear_gradient', 'star', 'heart'];
        $allowedTypes = ['video', 'image', 'pip', 'overlay', 'adjustment', 'audio', 'text', 'sticker', 'chart'];

        $clips = [];
        foreach ($rawClips as $idx => $clip) {
            if (!is_array($clip)) {
                continue;
            }
            $id = substr(preg_replace('/[^a-zA-Z0-9_-]/', '', (string) ($clip['id'] ?? ('clip_' . $idx))) ?? ('clip_' . $idx), 0, 64);
            $rawTrack = strtoupper(trim((string) ($clip['track'] ?? 'V1')));
            $track = preg_match('/^(V|A|T|ADJ)\d{1,2}$/', $rawTrack) === 1 ? $rawTrack : 'V1';
            $rawType = (string) ($clip['type'] ?? 'video');
            $type = in_array($rawType, $allowedTypes, true) ? $rawType : 'video';
            $startSec = max(0.0, min(self::MAX_DURATION_SECONDS, (float) ($clip['startSec'] ?? 0.0)));
            $durationSec = max(0.2, min(180.0, (float) ($clip['durationSec'] ?? 4.0)));
            $transform = is_array($clip['transform'] ?? null) ? $clip['transform'] : [];
            $mask = is_array($clip['mask'] ?? null) ? $clip['mask'] : [];
            $speed = is_array($clip['speed'] ?? null) ? $clip['speed'] : [];
            $lighting = is_array($clip['lighting'] ?? null) ? $clip['lighting'] : [];

            $clips[] = [
                'id'          => $id !== '' ? $id : ('clip_' . $idx),
                'track'       => $track,
                'type'        => $type,
                'title'       => self::sanitiseOverlayText((string) ($clip['title'] ?? 'Untitled Clip')),
                'startSec'    => round($startSec, 3),
                'durationSec' => round($durationSec, 3),
                'reversed'    => (bool) ($clip['reversed'] ?? false),
                'mirrored'    => (bool) ($clip['mirrored'] ?? false),
                'transform'   => [
                    'scaleX'    => max(0.2, min(3.0, (float) ($transform['scaleX'] ?? 1.0))),
                    'scaleY'    => max(0.2, min(3.0, (float) ($transform['scaleY'] ?? 1.0))),
                    'x'         => max(-1920.0, min(1920.0, (float) ($transform['x'] ?? 0.0))),
                    'y'         => max(-1920.0, min(1920.0, (float) ($transform['y'] ?? 0.0))),
                    'rotateDeg' => max(-360.0, min(360.0, (float) ($transform['rotateDeg'] ?? 0.0))),
                    'opacity'   => max(0.0, min(1.0, (float) ($transform['opacity'] ?? 1.0))),
                    'blendMode' => in_array((string) ($transform['blendMode'] ?? 'normal'), $allowedBlend, true)
                        ? (string) ($transform['blendMode'] ?? 'normal')
                        : 'normal',
                ],
                'mask' => [
                    'type'    => in_array((string) ($mask['type'] ?? 'none'), $allowedMasks, true) ? (string) ($mask['type'] ?? 'none') : 'none',
                    'feather' => max(0.0, min(40.0, (float) ($mask['feather'] ?? 0.0))),
                    'invert'  => (bool) ($mask['invert'] ?? false),
                    'size'    => max(0.1, min(1.0, (float) ($mask['size'] ?? 0.8))),
                ],
                'speed' => [
                    'rate'          => max(0.2, min(8.0, (float) ($speed['rate'] ?? 1.0))),
                    'pitchPreserve' => (bool) ($speed['pitchPreserve'] ?? true),
                ],
                'lighting' => [
                    'strobeFrequency'  => max(0.0, min(30.0, (float) ($lighting['strobeFrequency'] ?? 0.0))),
                    'flickerIntensity' => max(0.0, min(1.0, (float) ($lighting['flickerIntensity'] ?? 0.0))),
                    'glowPreset'       => in_array((string) ($lighting['glowPreset'] ?? 'none'), ['none', 'warm_glowing_tan', 'golden_hour', 'cool_moonlight'], true)
                        ? (string) ($lighting['glowPreset'] ?? 'none')
                        : 'none',
                    'glowIntensity'    => max(0.0, min(1.0, (float) ($lighting['glowIntensity'] ?? 0.0))),
                ],
            ];
        }

        return [
            'aspectRatio' => $aspect,
            'clips'       => $clips,
        ];
    }

    /**
     * NLE Core (Cap 14): Built-in zero-SaaS atmospheric overlay library.
     *
     * @return array<int, array{id: string, title: string, category: string, defaultBlendMode: string, loopable: bool}>
     */
    public static function getAtmosphericOverlayLibrary(): array
    {
        return [
            ['id' => 'bonfire_flames', 'title' => 'Bonfire Flames & Embers', 'category' => 'fire', 'defaultBlendMode' => 'screen', 'loopable' => true],
            ['id' => 'dust_motes', 'title' => 'Floating Dust Motes', 'category' => 'particles', 'defaultBlendMode' => 'screen', 'loopable' => true],
            ['id' => 'sunbeams', 'title' => 'Volumetric Sunbeams', 'category' => 'lighting', 'defaultBlendMode' => 'add', 'loopable' => true],
            ['id' => 'moving_skies', 'title' => 'Drifting Heritage Skies', 'category' => 'environment', 'defaultBlendMode' => 'overlay', 'loopable' => true],
            ['id' => 'rain', 'title' => 'Cinematic Rain Streaks', 'category' => 'weather', 'defaultBlendMode' => 'screen', 'loopable' => true],
        ];
    }

    /**
     * NLE Core (Cap 14): Build deterministic FFmpeg procedural atmospheric overlay filtergraph.
     *
     * @throws ValidationException
     */
    public static function buildAtmosphericOverlayFilterGraph(string $overlayId, int $width = 1280, int $height = 720): string
    {
        $dims = self::validateDimensions($width, $height);
        $w = $dims['width'];
        $h = $dims['height'];
        return match ($overlayId) {
            'bonfire_flames' => sprintf('color=c=black:s=%dx%d,noise=alls=40:allf=t,hue=h=25:s=2.0,eq=contrast=1.5:brightness=-0.1', $w, $h),
            'dust_motes'     => sprintf('color=c=black:s=%dx%d,noise=alls=18:allf=t,gblur=sigma=1.5,eq=contrast=1.6:brightness=-0.25', $w, $h),
            'sunbeams'       => sprintf('color=c=#3b2a10:s=%dx%d,gblur=sigma=24,eq=brightness=0.15:contrast=1.2', $w, $h),
            'moving_skies'   => sprintf('color=c=#1e3a5f:s=%dx%d,noise=alls=12:allf=t,gblur=sigma=16', $w, $h),
            'rain'           => sprintf('color=c=black:s=%dx%d,noise=alls=30:allf=t,boxblur=1:6,eq=contrast=1.7:brightness=-0.2', $w, $h),
            default          => throw new ValidationException('Unsupported atmospheric overlay ID.'),
        };
    }

    /**
     * NLE Core (Cap 15): Build deterministic FFmpeg blend mode filtergraph.
     */
    public static function buildBlendModeFilterGraph(string $blendMode = 'screen', float $opacity = 1.0): string
    {
        $map = [
            'normal'     => 'normal',
            'multiply'   => 'multiply',
            'screen'     => 'screen',
            'overlay'    => 'overlay',
            'add'        => 'addition',
            'soft_light' => 'softlight',
        ];
        $mode = $map[$blendMode] ?? 'normal';
        $safeOpacity = max(0.0, min(1.0, $opacity));
        return sprintf('blend=all_mode=%s:all_opacity=%.3f', $mode, $safeOpacity);
    }

    /**
     * NLE Core: Build deterministic FFmpeg mask filtergraph.
     *
     * @throws ValidationException
     */
    public static function buildMaskFilterGraph(string $maskType, float $feather = 0.0, bool $invert = false, int $width = 1280, int $height = 720): string
    {
        $dims = self::validateDimensions($width, $height);
        $allowed = ['split', 'filmstrip', 'circle', 'rectangle', 'linear_gradient', 'star', 'heart'];
        if (!in_array($maskType, $allowed, true)) {
            return 'format=yuva420p';
        }
        $safeFeather = max(0.0, min(40.0, $feather));
        $w = $dims['width'];
        $h = $dims['height'];
        if ($maskType === 'linear_gradient') {
            $alpha = $invert
                ? sprintf('255*(Y/%d)', max(1, $h))
                : sprintf('255*(1-(Y/%d))', max(1, $h));
            $blur = $safeFeather > 0 ? sprintf(',boxblur=%.1f:1', $safeFeather * 0.25) : '';
            return sprintf("format=yuva420p,geq=r='r(X,Y)':g='g(X,Y)':b='b(X,Y)':a='%s'%s", $alpha, $blur);
        }
        $r = (int) round(min($w, $h) * 0.4);
        $expr = match ($maskType) {
            'split'     => sprintf('lte(X,%d)', intdiv($w, 2)),
            'filmstrip' => sprintf('lte(abs(Y-%d),%d)', intdiv($h, 2), intdiv($h, 4)),
            'circle'    => sprintf('lte((X-%d)*(X-%d)+(Y-%d)*(Y-%d),%d)', intdiv($w, 2), intdiv($w, 2), intdiv($h, 2), intdiv($h, 2), $r * $r),
            default     => sprintf('lte(abs(X-%d),%d)*lte(abs(Y-%d),%d)', intdiv($w, 2), intdiv($w, 3), intdiv($h, 2), intdiv($h, 3)),
        };
        $alpha = $invert ? sprintf('if(%s,0,255)', $expr) : sprintf('if(%s,255,0)', $expr);
        $blur = $safeFeather > 0 ? sprintf(',boxblur=%.1f:1', $safeFeather * 0.25) : '';

        return sprintf("format=yuva420p,geq=r='r(X,Y)':g='g(X,Y)':b='b(X,Y)':a='%s'%s", $alpha, $blur);
    }

    /**
     * NLE Core: Build deterministic FFmpeg speed-ramping filtergraph (0.2x..8.0x).
     *
     * @return array{video: string, audio: string}
     */
    public static function buildSpeedCurveFilterGraph(float $speedRate = 1.0, bool $pitchPreserve = true): array
    {
        $rate = max(0.2, min(8.0, $speedRate));
        $ptsFactor = round(1.0 / $rate, 4);
        $video = sprintf('setpts=%.4f*PTS', $ptsFactor);

        if (!$pitchPreserve) {
            $audio = sprintf('asetrate=44100*%.4f,aresample=44100', $rate);
        } else {
            // Chain atempo filters to stay within FFmpeg's [0.5, 2.0] per-stage bounds
            $stages = [];
            $rem = $rate;
            while ($rem > 2.0) {
                $stages[] = 'atempo=2.0';
                $rem /= 2.0;
            }
            while ($rem < 0.5) {
                $stages[] = 'atempo=0.5';
                $rem /= 0.5;
            }
            $stages[] = sprintf('atempo=%.4f', $rem);
            $audio = implode(',', $stages);
        }

        return ['video' => $video, 'audio' => $audio];
    }

    /**
     * NLE Core (Cap 17): Build deterministic FFmpeg colour adjustment filtergraph.
     * Supports Exposure, Contrast, Saturation, Tint, Highlights, Brightness, and Sharpness.
     *
     * @param array<string, mixed> $adjustments
     */
    public static function buildAdjustmentFilterGraph(array $adjustments): string
    {
        $brightness = max(-0.5, min(0.5, ((float) ($adjustments['brightness'] ?? 0.0)) / 100.0));
        $exposure = max(-0.5, min(0.5, ((float) ($adjustments['exposure'] ?? 0.0)) / 100.0));
        $combinedBrightness = max(-0.5, min(0.5, $brightness + ($exposure * 0.6)));
        $contrast = max(0.5, min(1.5, 1.0 + ((float) ($adjustments['contrast'] ?? 0.0)) / 100.0));
        $saturation = max(0.0, min(2.0, 1.0 + ((float) ($adjustments['saturation'] ?? 0.0)) / 100.0));
        $tint = max(-45.0, min(45.0, (float) ($adjustments['tint'] ?? 0.0)));
        $highlights = max(-50.0, min(50.0, (float) ($adjustments['highlights'] ?? 0.0)));
        $sharp = max(0.0, min(2.0, ((float) ($adjustments['sharpness'] ?? 0.0)) / 50.0));

        $parts = [
            sprintf('eq=brightness=%.3f:contrast=%.3f:saturation=%.3f', $combinedBrightness, $contrast, $saturation),
        ];
        if (abs($tint) > 0.01) {
            $parts[] = sprintf('hue=h=%.2f', $tint);
        }
        if (abs($highlights) > 0.01) {
            $rimax = max(0.75, min(1.0, 1.0 - ($highlights / 200.0)));
            $parts[] = sprintf('colorlevels=rimax=%.3f:gimax=%.3f:bimax=%.3f', $rimax, $rimax, $rimax);
        }
        if ($sharp > 0.0) {
            $parts[] = sprintf('unsharp=5:5:%.2f:5:5:0.0', $sharp);
        }
        return implode(',', $parts);
    }

    /**
     * NLE Core (Cap 18): Build deterministic FFmpeg procedural lighting & mood shader filtergraph.
     */
    public static function buildLightingMoodFilterGraph(
        float $strobeFrequency = 0.0,
        float $flickerIntensity = 0.0,
        string $glowPreset = 'none',
        float $glowIntensity = 0.0
    ): string {
        $freq = max(0.0, min(30.0, $strobeFrequency));
        $flicker = max(0.0, min(1.0, $flickerIntensity));
        $glow = max(0.0, min(1.0, $glowIntensity));
        $parts = [];

        if ($freq > 0.0 && $flicker > 0.0) {
            $parts[] = sprintf("eq=brightness='%.3f*sin(2*PI*%.2f*t)'", $flicker * 0.25, $freq);
        }
        if ($glow > 0.0 && $glowPreset !== 'none') {
            $parts[] = sprintf('gblur=sigma=%.2f', 2.0 + ($glow * 8.0));
            if ($glowPreset === 'warm_glowing_tan' || $glowPreset === 'golden_hour') {
                $parts[] = sprintf('colorbalance=rs=%.3f:gs=%.3f:bs=%.3f', 0.18 * $glow, 0.08 * $glow, -0.14 * $glow);
            } elseif ($glowPreset === 'cool_moonlight') {
                $parts[] = sprintf('colorbalance=rs=%.3f:gs=%.3f:bs=%.3f', -0.12 * $glow, 0.04 * $glow, 0.20 * $glow);
            }
        }

        return $parts !== [] ? implode(',', $parts) : 'null';
    }

    private static function sanitiseOverlayText(string $text): string
    {
        $clean = strip_tags($text);
        $clean = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $clean) ?? '';
        $clean = trim(preg_replace('/\s+/', ' ', $clean) ?? '');
        if (mb_strlen($clean) > self::MAX_TEXT_OVERLAY_LENGTH) {
            $clean = mb_substr($clean, 0, self::MAX_TEXT_OVERLAY_LENGTH);
        }
        return $clean;
    }

    private static function formatSrtTimestamp(float $seconds): string
    {
        $totalMs = max(0, (int) round($seconds * 1000));
        $ms = $totalMs % 1000;
        $totalSec = intdiv($totalMs, 1000);
        $s = $totalSec % 60;
        $totalMin = intdiv($totalSec, 60);
        $m = $totalMin % 60;
        $h = intdiv($totalMin, 60);

        return sprintf('%02d:%02d:%02d,%03d', $h, $m, $s, $ms);
    }
}
