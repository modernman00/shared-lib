<?php

declare(strict_types=1);

namespace Src\Ai;

/**
 * VoiceTranscriptionService — Free Serverless Speech-to-Text via Hugging Face Whisper
 *
 * Applicable to:
 * - PartyPlatform: Celebratory audio keepsakes & DJ voice notes
 * - FamilyPlatform: Oral histories & family voice memories
 * - BCP: Frontline carer hands-free shift dictation
 */
final class VoiceTranscriptionService
{
    private HuggingFaceInferenceGateway $gateway;
    private string $model;

    public function __construct(?HuggingFaceInferenceGateway $gateway = null, ?string $model = null)
    {
        $this->gateway = $gateway ?? new HuggingFaceInferenceGateway();
        $this->model = $model ?? 'openai/whisper-large-v3-turbo';
    }

    /**
     * Transcribe an audio file into clean, formatted text.
     *
     * @param string $audioFilePath Absolute path to audio file (mp3, wav, m4a, webm, ogg)
     * @return array{success: bool, text: string, error: ?string}
     */
    public function transcribe(string $audioFilePath): array
    {
        if (!file_exists($audioFilePath)) {
            return [
                'success' => false,
                'text'    => '',
                'error'   => 'Audio file not found on disk.',
            ];
        }

        $size = filesize($audioFilePath);
        if ($size === false || $size === 0) {
            return [
                'success' => false,
                'text'    => '',
                'error'   => 'Audio file is empty.',
            ];
        }

        // Limit to 25MB to keep within bounded memory and timeout
        if ($size > 26_214_400) {
            return [
                'success' => false,
                'text'    => '',
                'error'   => 'Audio file exceeds 25MB limit.',
            ];
        }

        try {
            $text = $this->gateway->transcribeAudio($audioFilePath, $this->model);

            if ($text === null || trim($text) === '') {
                return [
                    'success' => false,
                    'text'    => '',
                    'error'   => 'Transcription failed or returned empty response.',
                ];
            }

            return [
                'success' => true,
                'text'    => $text,
                'error'   => null,
            ];
        } catch (\Throwable $th) {
            error_log('[VoiceTranscriptionService] Error: ' . $th->getMessage());
            return [
                'success' => false,
                'text'    => '',
                'error'   => 'Transcription error occurred.',
            ];
        }
    }
}
