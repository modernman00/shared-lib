<?php

declare(strict_types=1);

namespace Src\Ai;

/**
 * VoiceTranscriptionService — Resilient Multi-Tier Speech-to-Text Engine
 *
 * Waterfall Execution:
 * 1. Tier 1: Google Gemini Multimodal Audio (gemini-3.8-flash / gemini-3.5-transcribe)
 * 2. Tier 2: OpenAI Whisper API (whisper-1)
 * 3. Tier 3: Hugging Face Inference Router (openai/whisper-large-v3-turbo)
 *
 * Applicable to:
 * - PartyPlatform: Celebratory audio keepsakes & DJ voice notes
 * - FamilyPlatform: Oral histories & family voice memories / Voice Notes
 * - BCP: Frontline carer hands-free shift dictation
 */
final class VoiceTranscriptionService
{
    private HuggingFaceInferenceGateway $gateway;
    private string $hfModel;

    public function __construct(?HuggingFaceInferenceGateway $gateway = null, ?string $hfModel = null)
    {
        $this->gateway = $gateway ?? new HuggingFaceInferenceGateway();
        $this->hfModel = $hfModel ?? 'openai/whisper-large-v3-turbo';
    }

    /**
     * Transcribe an audio file into clean, formatted text.
     *
     * @param string $audioFilePath Absolute path to audio file (mp3, wav, m4a, webm, ogg)
     * @return array{success: bool, text: string, provider: string, error: ?string}
     */
    public function transcribe(string $audioFilePath): array
    {
        if (!file_exists($audioFilePath)) {
            return [
                'success'  => false,
                'text'     => '',
                'provider' => 'none',
                'error'    => 'Audio file not found on disk.',
            ];
        }

        $size = filesize($audioFilePath);
        if ($size === false || $size === 0) {
            return [
                'success'  => false,
                'text'     => '',
                'provider' => 'none',
                'error'    => 'Audio file is empty.',
            ];
        }

        // Limit to 25MB to keep within bounded memory and timeout
        if ($size > 26_214_400) {
            return [
                'success'  => false,
                'text'     => '',
                'provider' => 'none',
                'error'    => 'Audio file exceeds 25MB limit.',
            ];
        }

        // Detect MIME type safely
        $mime = $this->detectMimeType($audioFilePath);

        // 1. TIER 1: Google Gemini Multimodal Audio (Fastest, zero-cost high quota)
        $geminiKey = (string)($_ENV['GEMINI_API_KEY'] ?? $_ENV['GOOGLE_API_KEY'] ?? getenv('GEMINI_API_KEY') ?: getenv('GOOGLE_API_KEY') ?: '');
        if ($geminiKey !== '') {
            $geminiText = $this->transcribeWithGemini($audioFilePath, $mime, $geminiKey);
            if ($geminiText !== null && trim($geminiText) !== '') {
                return [
                    'success'  => true,
                    'text'     => trim($geminiText),
                    'provider' => 'Google Gemini (Tier 1)',
                    'error'    => null,
                ];
            }
        }

        // 2. TIER 2: OpenAI Whisper API Failover
        $openaiKey = (string)($_ENV['OPENAI_API_KEY'] ?? $_ENV['CHATGPT_API'] ?? getenv('OPENAI_API_KEY') ?: getenv('CHATGPT_API') ?: '');
        if ($openaiKey !== '') {
            $whisperText = $this->transcribeWithOpenAiWhisper($audioFilePath, $openaiKey);
            if ($whisperText !== null && trim($whisperText) !== '') {
                return [
                    'success'  => true,
                    'text'     => trim($whisperText),
                    'provider' => 'OpenAI Whisper (Tier 2)',
                    'error'    => null,
                ];
            }
        }

        // 3. TIER 3: Hugging Face Serverless Whisper Router Failover
        try {
            $hfText = $this->gateway->transcribeAudio($audioFilePath, $this->hfModel);
            if ($hfText !== null && trim($hfText) !== '') {
                return [
                    'success'  => true,
                    'text'     => trim($hfText),
                    'provider' => 'Hugging Face Whisper (Tier 3)',
                    'error'    => null,
                ];
            }
        } catch (\Throwable $th) {
            error_log('[VoiceTranscriptionService] HuggingFace exception: ' . $th->getMessage());
        }

        return [
            'success'  => false,
            'text'     => '',
            'provider' => 'none',
            'error'    => 'Transcription failed or returned empty response. All AI speech engines are currently unreachable.',
        ];
    }

    /**
     * Transcribe using Google Gemini Multimodal Audio
     */
    private function transcribeWithGemini(string $audioFilePath, string $mime, string $apiKey): ?string
    {
        $rawBytes = file_get_contents($audioFilePath);
        if ($rawBytes === false || strlen($rawBytes) === 0) {
            return null;
        }

        $base64 = base64_encode($rawBytes);

        // Normalize mime for Gemini
        $supportedMimes = [
            'audio/wav'   => 'audio/wav',
            'audio/x-wav' => 'audio/wav',
            'audio/mp3'   => 'audio/mp3',
            'audio/mpeg'  => 'audio/mp3',
            'audio/m4a'   => 'audio/m4a',
            'audio/x-m4a' => 'audio/m4a',
            'audio/mp4'   => 'audio/mp4',
            'video/mp4'   => 'audio/mp4',
            'audio/ogg'   => 'audio/ogg',
            'application/ogg' => 'audio/ogg',
            'audio/webm'  => 'audio/webm',
            'video/webm'  => 'audio/webm',
            'audio/flac'  => 'audio/flac',
        ];

        $geminiMime = $supportedMimes[$mime] ?? 'audio/webm';

        $models = [
            'gemini-3.8-flash',
            'gemini-3.5-transcribe',
            'gemini-2.5-flash-lite',
            'gemini-flash-latest'
        ];

        $prompt = "Transcribe the spoken audio verbatim in British English. Do not add any commentary, conversational intro, markdown, or timestamps. Return ONLY the transcribed text. If the audio is silent or contains no discernible speech, return an empty string.";

        foreach ($models as $model) {
            $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key=" . $apiKey;
            $payload = json_encode([
                'contents' => [
                    [
                        'parts' => [
                            [
                                'inlineData' => [
                                    'mimeType' => $geminiMime,
                                    'data'     => $base64,
                                ],
                            ],
                            [
                                'text' => $prompt,
                            ],
                        ],
                    ],
                ],
            ]);

            if ($payload === false) {
                continue;
            }

            $ch = curl_init($url);
            if ($ch === false) {
                continue;
            }

            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => $payload,
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT        => 8,
            ]);

            $response = curl_exec($ch);
            $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (PHP_VERSION_ID < 80500) {
                @curl_close($ch);
            }

            if ($httpCode !== 200 || !is_string($response)) {
                continue;
            }

            $decoded = json_decode($response, true);
            if (is_array($decoded) && isset($decoded['candidates'][0]['content']['parts'][0]['text'])) {
                $text = trim((string)$decoded['candidates'][0]['content']['parts'][0]['text']);
                if ($text !== '') {
                    return $text;
                }
            }
        }

        return null;
    }

    /**
     * Transcribe using OpenAI Whisper API
     */
    private function transcribeWithOpenAiWhisper(string $audioFilePath, string $apiKey): ?string
    {
        $ch = curl_init('https://api.openai.com/v1/audio/transcriptions');
        if ($ch === false) {
            return null;
        }

        $cfile = new \CURLFile($audioFilePath);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => [
                'file'  => $cfile,
                'model' => 'whisper-1',
            ],
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT        => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (PHP_VERSION_ID < 80500) {
            @curl_close($ch);
        }

        if ($httpCode !== 200 || !is_string($response)) {
            return null;
        }

        $decoded = json_decode($response, true);
        if (is_array($decoded) && isset($decoded['text']) && is_string($decoded['text'])) {
            return trim($decoded['text']);
        }

        return null;
    }

    /**
     * Detect MIME type with safe fallback
     */
    private function detectMimeType(string $path): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? finfo_file($finfo, $path) : false;
        if (is_string($mime) && $mime !== 'application/octet-stream') {
            return $mime;
        }

        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        return match ($ext) {
            'mp3'  => 'audio/mp3',
            'wav'  => 'audio/wav',
            'ogg'  => 'audio/ogg',
            'm4a'  => 'audio/m4a',
            'flac' => 'audio/flac',
            default => 'audio/webm',
        };
    }
}
