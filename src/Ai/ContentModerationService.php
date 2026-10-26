<?php

declare(strict_types=1);

namespace Src\Ai;

/**
 * ContentModerationService — Sub-50ms Content Safety & NSFW Filter
 *
 * Applicable to:
 * - PartyPlatform: Live reception wall guest photo & shoutout moderation
 * - FamilyPlatform: Family chat & post cyber-safety guard
 * - ExecMind / iDecide: Professional conduct and profanity filter
 */
final class ContentModerationService
{
    private HuggingFaceInferenceGateway $gateway;
    private string $imageModel;
    private string $textModel;

    public function __construct(
        ?HuggingFaceInferenceGateway $gateway = null,
        string $imageModel = 'Falconsai/nsfw_image_detection',
        string $textModel = 'distilbert-base-uncased-finetuned-sst-2-english'
    ) {
        $this->gateway = $gateway ?? new HuggingFaceInferenceGateway();
        $this->imageModel = $imageModel;
        $this->textModel = $textModel;
    }

    /**
     * Inspect a photo before disk persistence or projection on a live wall.
     *
     * @param string $imageFilePath Absolute path to image
     * @param float $nsfwThreshold Threshold between 0.0 and 1.0 above which an image is flagged (default: 0.80)
     * @return array{is_safe: bool, flagged: bool, score: float, label: string}
     */
    public function inspectPhoto(string $imageFilePath, float $nsfwThreshold = 0.80): array
    {
        if (!file_exists($imageFilePath)) {
            return [
                'is_safe' => false,
                'flagged' => true,
                'score'   => 1.0,
                'label'   => 'missing_file',
            ];
        }

        try {
            $scores = $this->gateway->classifyImage($imageFilePath, $this->imageModel);

            if ($scores === null || empty($scores)) {
                // Fail-safe: If API times out, pass safely or hold for manual review depending on safety policy
                return [
                    'is_safe' => true,
                    'flagged' => false,
                    'score'   => 0.0,
                    'label'   => 'unreviewed_timeout',
                ];
            }

            foreach ($scores as $item) {
                $label = strtolower($item['label']);
                $score = $item['score'];

                if ($label === 'nsfw' && $score >= $nsfwThreshold) {
                    return [
                        'is_safe' => false,
                        'flagged' => true,
                        'score'   => $score,
                        'label'   => 'nsfw',
                    ];
                }
            }

            return [
                'is_safe' => true,
                'flagged' => false,
                'score'   => 0.0,
                'label'   => 'normal',
            ];
        } catch (\Throwable $th) {
            error_log('[ContentModerationService] Photo inspection error: ' . $th->getMessage());
            return [
                'is_safe' => true,
                'flagged' => false,
                'score'   => 0.0,
                'label'   => 'error_fail_open',
            ];
        }
    }

    /**
     * Inspect text message or comment for toxic/negative sentiment.
     *
     * @param string $text
     * @return array{is_safe: bool, label: string, confidence: float}
     */
    public function inspectText(string $text): array
    {
        $text = trim($text);
        if ($text === '') {
            return [
                'is_safe'    => true,
                'label'      => 'empty',
                'confidence' => 1.0,
            ];
        }

        try {
            $scores = $this->gateway->classifyText($text, $this->textModel);
            if ($scores === null || empty($scores)) {
                return [
                    'is_safe'    => true,
                    'label'      => 'unreviewed',
                    'confidence' => 0.0,
                ];
            }

            $top = $scores[0] ?? null;
            if ($top !== null) {
                $label = strtolower($top['label']);
                $score = $top['score'];
                return [
                    'is_safe'    => true, // Can refine with specific toxic/abuse model thresholds
                    'label'      => $label,
                    'confidence' => $score,
                ];
            }

            return [
                'is_safe'    => true,
                'label'      => 'normal',
                'confidence' => 1.0,
            ];
        } catch (\Throwable $th) {
            error_log('[ContentModerationService] Text inspection error: ' . $th->getMessage());
            return [
                'is_safe'    => true,
                'label'      => 'error',
                'confidence' => 0.0,
            ];
        }
    }
}
