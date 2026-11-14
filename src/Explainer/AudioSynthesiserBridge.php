<?php

declare(strict_types=1);

namespace Src\Explainer;

/**
 * AudioSynthesiserBridge
 *
 * Sovereign audio and narration bridge engineered by Dr. Chen Wei and Dr. Priya Nair (ATG Pod 1).
 * Calculates British English speech cadence, phoneme-accurate timeline alignment,
 * automated audio ducking curves, and standard WebVTT/SRT subtitle files.
 */
class AudioSynthesiserBridge
{
    /** @var float Average British English speaking rate in words per second */
    public const WORDS_PER_SECOND = 2.6;

    /**
     * Estimates audio duration for a British English narration segment.
     *
     * @param string $narrationText
     * @return float Duration in seconds
     */
    public function estimateSpeechDuration(string $narrationText): float
    {
        $words = preg_split('/\s+/', trim($narrationText));
        $wordCount = $words === false ? 0 : count(array_filter($words));

        if ($wordCount === 0) {
            return 0.0;
        }

        // Account for pauses at punctuation
        $pauseCount = preg_match_all('/[.,!?;:]/', $narrationText, $matches);
        $pauseDuration = (int)$pauseCount * 0.25;

        $baseDuration = $wordCount / self::WORDS_PER_SECOND;
        return round(max(1.0, $baseDuration + $pauseDuration), 2);
    }

    /**
     * Generates a WebVTT formatted subtitle track from a storyboard.
     *
     * @param ExplainerStoryboard $storyboard
     * @return string
     */
    public function generateWebVTT(ExplainerStoryboard $storyboard): string
    {
        $lines = ["WEBVTT\n"];
        $currentTime = 0.0;

        foreach ($storyboard->getScenes() as $index => $scene) {
            $duration = (float)($scene['duration'] ?? 5.0);
            $narration = trim((string)($scene['narration'] ?? ''));

            if (!empty($narration)) {
                $startTimeStr = $this->formatVttTimestamp($currentTime);
                $endTimeStr = $this->formatVttTimestamp($currentTime + $duration - 0.2);

                $lines[] = sprintf("%d", $index + 1);
                $lines[] = sprintf("%s --> %s", $startTimeStr, $endTimeStr);
                $lines[] = $narration . "\n";
            }

            $currentTime += $duration;
        }

        return implode("\n", $lines);
    }

    /**
     * Generates SubRip (.srt) formatted subtitles.
     *
     * @param ExplainerStoryboard $storyboard
     * @return string
     */
    public function generateSRT(ExplainerStoryboard $storyboard): string
    {
        $entries = [];
        $currentTime = 0.0;

        foreach ($storyboard->getScenes() as $index => $scene) {
            $duration = (float)($scene['duration'] ?? 5.0);
            $narration = trim((string)($scene['narration'] ?? ''));

            if (!empty($narration)) {
                $startTimeStr = $this->formatSrtTimestamp($currentTime);
                $endTimeStr = $this->formatSrtTimestamp($currentTime + $duration - 0.2);

                $entries[] = sprintf("%d\n%s --> %s\n%s\n", $index + 1, $startTimeStr, $endTimeStr, $narration);
            }

            $currentTime += $duration;
        }

        return implode("\n", $entries);
    }

    /**
     * Calculates audio ducking keyframes (attenuating background audio during speech).
     *
     * @param ExplainerStoryboard $storyboard
     * @param float $bedVolume Default background music volume (0.0 to 1.0)
     * @param float $duckedVolume Attenuated volume during voiceover
     * @return array<int, array{time: float, volume: float}>
     */
    public function calculateDuckingEnvelope(
        ExplainerStoryboard $storyboard,
        float $bedVolume = 0.5,
        float $duckedVolume = 0.12
    ): array {
        $envelope = [];
        $currentTime = 0.0;

        // Initial bed volume
        $envelope[] = ['time' => 0.0, 'volume' => $bedVolume];

        foreach ($storyboard->getScenes() as $scene) {
            $duration = (float)($scene['duration'] ?? 5.0);
            $narration = trim((string)($scene['narration'] ?? ''));

            if (!empty($narration)) {
                // Duck volume 150ms before speech starts
                $envelope[] = ['time' => round(max(0.0, $currentTime + 0.1), 3), 'volume' => $duckedVolume];
                // Restore volume 200ms before scene ends
                $envelope[] = ['time' => round($currentTime + $duration - 0.2, 3), 'volume' => $bedVolume];
            }

            $currentTime += $duration;
        }

        $envelope[] = ['time' => round($currentTime, 3), 'volume' => 0.0]; // Fade out at end

        return $envelope;
    }

    /**
     * Builds a word-level transcript timeline for kinetic typography and beat-synced reveals.
     * Accepts optional external word timestamps (e.g. from Gemini or ElevenLabs) or
     * deterministically synthesises word timings from each scene's narration cadence.
     *
     * @param ExplainerStoryboard $storyboard
     * @param array<int, array{word?: string, start?: float|int, end?: float|int}> $externalWords
     * @return array<int, array{word: string, start: float, end: float, scene_index: int, is_emphasis: bool}>
     */
    public function buildWordTimeline(ExplainerStoryboard $storyboard, array $externalWords = []): array
    {
        $scenes = $storyboard->getScenes();
        $sceneBounds = [];
        $cursor = 0.0;
        foreach ($scenes as $idx => $scene) {
            $dur = max(0.5, (float)($scene['duration'] ?? 5.0));
            $sceneBounds[] = [
                'index' => $idx,
                'start' => $cursor,
                'end' => $cursor + $dur,
                'duration' => $dur,
                'narration' => trim((string)($scene['narration'] ?? '')),
            ];
            $cursor += $dur;
        }

        if (!empty($externalWords)) {
            $timeline = [];
            foreach ($externalWords as $entry) {
                $word = trim((string)($entry['word'] ?? ''));
                if ($word === '') {
                    continue;
                }
                $start = max(0.0, (float)($entry['start'] ?? 0.0));
                $end = max($start + 0.08, (float)($entry['end'] ?? ($start + 0.25)));
                $sceneIndex = 0;
                foreach ($sceneBounds as $bound) {
                    if ($start >= $bound['start'] && $start < $bound['end']) {
                        $sceneIndex = $bound['index'];
                        break;
                    }
                    if ($start >= $bound['end']) {
                        $sceneIndex = $bound['index'];
                    }
                }
                $timeline[] = [
                    'word' => $word,
                    'start' => round($start, 3),
                    'end' => round($end, 3),
                    'scene_index' => $sceneIndex,
                    'is_emphasis' => $this->isEmphasisWord($word),
                ];
            }
            return $timeline;
        }

        $timeline = [];
        foreach ($sceneBounds as $bound) {
            if ($bound['narration'] === '') {
                continue;
            }
            $tokens = preg_split('/\s+/', $bound['narration']);
            $words = $tokens === false ? [] : array_values(array_filter($tokens, static fn (string $w): bool => $w !== ''));
            if (empty($words)) {
                continue;
            }

            $padStart = $bound['start'] + 0.15;
            $usableDuration = max(0.4, $bound['duration'] - 0.30);

            $weights = [];
            $totalWeight = 0.0;
            foreach ($words as $w) {
                $cleanLen = max(2, strlen((string)preg_replace('/[^a-zA-Z0-9]/', '', $w)));
                $pauseBoost = preg_match('/[.,!?;:]$/', $w) === 1 ? 3.0 : 0.0;
                $weight = (float)$cleanLen + $pauseBoost;
                $weights[] = $weight;
                $totalWeight += $weight;
            }

            $t = $padStart;
            foreach ($words as $i => $w) {
                $slice = ($weights[$i] / max(1.0, $totalWeight)) * $usableDuration;
                $wordStart = $t;
                $wordEnd = min($bound['end'] - 0.05, $t + max(0.08, $slice));
                $timeline[] = [
                    'word' => $w,
                    'start' => round($wordStart, 3),
                    'end' => round($wordEnd, 3),
                    'scene_index' => $bound['index'],
                    'is_emphasis' => $this->isEmphasisWord($w),
                ];
                $t += $slice;
            }
        }

        return $timeline;
    }

    private function isEmphasisWord(string $word): bool
    {
        $stripped = (string)preg_replace('/[^a-zA-Z0-9%£$]/', '', $word);
        if ($stripped === '') {
            return false;
        }
        // Numbers, currency, percentages, or ALL-CAPS acronyms
        if (preg_match('/[0-9%£$]/', $stripped) === 1) {
            return true;
        }
        if (strlen($stripped) >= 2 && strtoupper($stripped) === $stripped) {
            return true;
        }
        return false;
    }

    private function formatVttTimestamp(float $seconds): string
    {
        $intSeconds = (int)floor($seconds);
        $hours = (int)floor($intSeconds / 3600);
        $minutes = (int)floor(($intSeconds % 3600) / 60);
        $secs = $intSeconds % 60;
        $millis = (int)round(($seconds - $intSeconds) * 1000);

        return sprintf('%02d:%02d:%02d.%03d', $hours, $minutes, $secs, $millis);
    }

    private function formatSrtTimestamp(float $seconds): string
    {
        $intSeconds = (int)floor($seconds);
        $hours = (int)floor($intSeconds / 3600);
        $minutes = (int)floor(($intSeconds % 3600) / 60);
        $secs = $intSeconds % 60;
        $millis = (int)round(($seconds - $intSeconds) * 1000);

        return sprintf('%02d:%02d:%02d,%03d', $hours, $minutes, $secs, $millis);
    }
}
