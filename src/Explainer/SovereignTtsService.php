<?php

declare(strict_types=1);

namespace Src\Explainer;

use Src\Exceptions\TooManyRequestsException;
use Src\Limiter;

/**
 * SovereignTtsService
 *
 * Centralised multi-tier British English TTS synthesiser with content-addressed disk caching:
 *   1. Piper TTS (Primary, open-source MIT, $0 API cost, local ONNX) -> audio/wav
 *   2. ElevenLabs (Fallback #1, rate-limited via \Src\Limiter) -> audio/mpeg
 *   3. Hugging Face Inference API (Fallback #2, if HF_API_TOKEN configured) -> audio/flac
 *   4. Browser en-GB window.speechSynthesis (Final graceful fallback)
 */
class SovereignTtsService
{
    /**
     * Synthesise British English narration audio with content-addressed caching and graceful fallbacks.
     *
     * @param string $text
     * @param string $voice 'oliver'|'serena'|'elders'
     * @param array{
     *   cache_dir?: string,
     *   piper_bin?: string|null,
     *   piper_models_dir?: string|null,
     *   piper_runner?: callable|null,
     *   http_transport?: callable|null,
     *   rate_limiter?: callable|null,
     *   user_key?: string,
     *   enable_huggingface?: bool
     * } $options
     * @return array<string, mixed>
     */
    public static function synthesise(string $text, string $voice = 'oliver', array $options = []): array
    {
        $cleanText = trim((string)preg_replace('/\s+/', ' ', strip_tags($text)));
        if ($cleanText === '') {
            return [
                'success' => false,
                'error' => 'Narration text cannot be empty.',
            ];
        }
        if (mb_strlen($cleanText, 'UTF-8') > 1000) {
            $cleanText = mb_substr($cleanText, 0, 1000, 'UTF-8');
        }

        $allowedVoices = ['oliver', 'serena', 'elders'];
        $safeVoice = in_array($voice, $allowedVoices, true) ? $voice : 'oliver';

        $bridge = new AudioSynthesiserBridge();
        $estimatedDuration = $bridge->estimateSpeechDuration($cleanText);

        $cacheDir = isset($options['cache_dir']) && is_string($options['cache_dir']) && $options['cache_dir'] !== ''
            ? rtrim($options['cache_dir'], DIRECTORY_SEPARATOR)
            : sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sovereign_tts_cache';

        $piperRunner = isset($options['piper_runner']) && is_callable($options['piper_runner']) ? $options['piper_runner'] : null;
        $httpTransport = isset($options['http_transport']) && is_callable($options['http_transport']) ? $options['http_transport'] : null;
        $rateLimiter = isset($options['rate_limiter']) && is_callable($options['rate_limiter']) ? $options['rate_limiter'] : null;

        // ------------------------------------------------------------------
        // Tier 1: Piper TTS (Primary, $0 local ONNX)
        // ------------------------------------------------------------------
        $piperModelsDir = isset($options['piper_models_dir']) && is_string($options['piper_models_dir'])
            ? rtrim($options['piper_models_dir'], DIRECTORY_SEPARATOR)
            : trim((string)($_ENV['PIPER_MODELS_DIR'] ?? (getenv('PIPER_MODELS_DIR') !== false ? getenv('PIPER_MODELS_DIR') : '')));

        $piperBin = isset($options['piper_bin']) && is_string($options['piper_bin'])
            ? $options['piper_bin']
            : trim((string)($_ENV['PIPER_BIN'] ?? (getenv('PIPER_BIN') !== false ? getenv('PIPER_BIN') : '')));

        $piperModelPath = self::resolvePiperModelPath($safeVoice, $piperModelsDir);
        $piperModelId = $piperModelPath !== '' ? pathinfo($piperModelPath, PATHINFO_FILENAME) : ('en_GB-' . $safeVoice);
        $piperCacheFile = $cacheDir . DIRECTORY_SEPARATOR . hash('sha256', 'piper|' . $safeVoice . '|' . $piperModelId . '|' . $cleanText) . '.wav';

        $cachedWav = self::readCacheFile($piperCacheFile);
        if ($cachedWav !== null) {
            return [
                'success' => true,
                'fallback' => false,
                'provider' => 'piper',
                'cached' => true,
                'voice' => $safeVoice,
                'estimated_duration_sec' => $estimatedDuration,
                'audio_base64' => base64_encode($cachedWav),
                'mime' => 'audio/wav',
            ];
        }

        $canRunPiper = ($piperRunner !== null) || ($piperBin !== '' && is_executable($piperBin) && $piperModelPath !== '' && is_file($piperModelPath));
        if ($canRunPiper) {
            self::ensureDir($cacheDir);
            $tmpWav = $piperCacheFile . '.tmp.' . bin2hex(random_bytes(4)) . '.wav';
            $piperOk = false;

            try {
                if ($piperRunner !== null) {
                    $piperOk = (bool)$piperRunner($piperBin !== '' ? $piperBin : 'piper', $piperModelPath, $cleanText, $tmpWav);
                } else {
                    $piperOk = self::runPiperProcess($piperBin, $piperModelPath, $cleanText, $tmpWav);
                }
            } catch (\Throwable $e) {
                error_log('SovereignTtsService Piper warning: ' . $e->getMessage());
                $piperOk = false;
            }

            if ($piperOk && is_file($tmpWav) && filesize($tmpWav) > 44) {
                $wavBytes = @file_get_contents($tmpWav);
                if (is_string($wavBytes) && $wavBytes !== '') {
                    @rename($tmpWav, $piperCacheFile);
                    return [
                        'success' => true,
                        'fallback' => false,
                        'provider' => 'piper',
                        'cached' => false,
                        'voice' => $safeVoice,
                        'estimated_duration_sec' => $estimatedDuration,
                        'audio_base64' => base64_encode($wavBytes),
                        'mime' => 'audio/wav',
                    ];
                }
            }
            @unlink($tmpWav);
        }

        // ------------------------------------------------------------------
        // Tier 2: ElevenLabs TTS (Fallback #1, rate-limited)
        // ------------------------------------------------------------------
        $elevenVoiceMap = [
            'oliver' => 'JBFqnCBsd6RMkjVDRZzb', // George (British)
            'serena' => 'Xb7hH8MSUJpSbSDYk0k2', // Alice (British)
            'elders' => 'onwK4e9ZLuTAKqWW03F9', // Daniel (British)
        ];
        $envElevenVoice = trim((string)($_ENV['ELEVENLABS_VOICE_ID'] ?? (getenv('ELEVENLABS_VOICE_ID') !== false ? getenv('ELEVENLABS_VOICE_ID') : '')));
        $elevenVoiceId = $envElevenVoice !== '' ? $envElevenVoice : $elevenVoiceMap[$safeVoice];

        $envElevenModel = trim((string)($_ENV['ELEVENLABS_MODEL_ID'] ?? (getenv('ELEVENLABS_MODEL_ID') !== false ? getenv('ELEVENLABS_MODEL_ID') : '')));
        $elevenModelId = $envElevenModel !== '' ? $envElevenModel : 'eleven_flash_v2_5';

        $elevenCacheFile = $cacheDir . DIRECTORY_SEPARATOR . hash('sha256', 'elevenlabs|' . $elevenVoiceId . '|' . $elevenModelId . '|' . $cleanText) . '.mp3';
        $cachedMp3 = self::readCacheFile($elevenCacheFile);
        if ($cachedMp3 !== null) {
            return [
                'success' => true,
                'fallback' => false,
                'provider' => 'elevenlabs',
                'cached' => true,
                'voice' => $safeVoice,
                'estimated_duration_sec' => $estimatedDuration,
                'audio_base64' => base64_encode($cachedMp3),
                'mime' => 'audio/mpeg',
            ];
        }

        $elevenKey = trim((string)($_ENV['ELEVENLABS_API_KEY'] ?? (getenv('ELEVENLABS_API_KEY') !== false ? getenv('ELEVENLABS_API_KEY') : '')));
        if ($elevenKey !== '' || $httpTransport !== null) {
            $userKey = isset($options['user_key']) && is_string($options['user_key']) && $options['user_key'] !== ''
                ? $options['user_key']
                : (string)($_SESSION['user_id'] ?? (session_id() ?: 'anon'));

            $rateLimited = false;
            try {
                if ($rateLimiter !== null) {
                    $rateLimiter('elevenlabs_tts:' . $userKey, 'default');
                } else {
                    Limiter::limit('elevenlabs_tts:' . $userKey, 'default');
                }
            } catch (TooManyRequestsException $e) {
                $rateLimited = true;
            } catch (\Throwable $e) {
                error_log('SovereignTtsService rate-limiter warning: ' . $e->getMessage());
            }

            if (!$rateLimited) {
                $endpoint = 'https://api.elevenlabs.io/v1/text-to-speech/' . rawurlencode($elevenVoiceId);
                $payload = json_encode([
                    'text' => $cleanText,
                    'model_id' => $elevenModelId,
                    'voice_settings' => [
                        'stability' => 0.5,
                        'similarity_boost' => 0.75,
                    ],
                ]);

                if (is_string($payload)) {
                    $res = self::postJson(
                        $endpoint,
                        $payload,
                        [
                            'xi-api-key: ' . $elevenKey,
                            'Content-Type: application/json',
                            'Accept: audio/mpeg',
                        ],
                        $httpTransport,
                        $elevenKey
                    );
                    $mime = strtolower(trim(explode(';', $res['content_type'])[0]));
                    $isAudio = $mime === '' || str_starts_with($mime, 'audio/') || $mime === 'application/octet-stream';
                    if ($res['status'] === 200 && $res['body'] !== '' && $isAudio) {
                        self::writeCacheFile($cacheDir, $elevenCacheFile, $res['body']);
                        return [
                            'success' => true,
                            'fallback' => false,
                            'provider' => 'elevenlabs',
                            'cached' => false,
                            'voice' => $safeVoice,
                            'estimated_duration_sec' => $estimatedDuration,
                            'audio_base64' => base64_encode($res['body']),
                            'mime' => ($mime !== '' && str_starts_with($mime, 'audio/')) ? $mime : 'audio/mpeg',
                        ];
                    }
                }
            }
        }

        // ------------------------------------------------------------------
        // Tier 3: Hugging Face Inference API (Fallback #2, if HF_API_TOKEN set)
        // ------------------------------------------------------------------
        $enableHf = $options['enable_huggingface'] ?? true;
        $hfToken = trim((string)($_ENV['HF_API_TOKEN'] ?? (getenv('HF_API_TOKEN') !== false ? getenv('HF_API_TOKEN') : '')));
        if ($enableHf && $hfToken !== '') {
            $hfModel = trim((string)($_ENV['HF_TTS_MODEL'] ?? 'facebook/mms-tts-eng')) ?: 'facebook/mms-tts-eng';
            $baseUrl = rtrim((string)($_ENV['HF_BASE_URL'] ?? 'https://router.huggingface.co/hf-inference'), '/');
            $hfEndpoint = "{$baseUrl}/models/{$hfModel}";
            $hfCacheFile = $cacheDir . DIRECTORY_SEPARATOR . hash('sha256', 'huggingface|' . $hfModel . '|' . $cleanText) . '.flac';

            $cachedHf = self::readCacheFile($hfCacheFile);
            if ($cachedHf !== null) {
                return [
                    'success' => true,
                    'fallback' => false,
                    'provider' => 'huggingface',
                    'cached' => true,
                    'voice' => $safeVoice,
                    'estimated_duration_sec' => $estimatedDuration,
                    'audio_base64' => base64_encode($cachedHf),
                    'mime' => 'audio/flac',
                ];
            }

            if (class_exists(\Src\Ai\HuggingFaceInferenceGateway::class) && \Src\Ai\HuggingFaceInferenceGateway::isAllowedEndpoint($hfEndpoint)) {
                $hfPayload = json_encode(['inputs' => $cleanText]);
                if (is_string($hfPayload)) {
                    $res = self::postJson(
                        $hfEndpoint,
                        $hfPayload,
                        [
                            'Authorization: Bearer ' . $hfToken,
                            'Content-Type: application/json',
                            'Accept: audio/flac, audio/wav, audio/mpeg, application/json',
                        ],
                        null,
                        $hfToken
                    );
                    $mime = strtolower(trim(explode(';', $res['content_type'])[0]));
                    $isAudio = str_starts_with($mime, 'audio/') || $mime === 'application/octet-stream';
                    if ($res['status'] === 200 && $res['body'] !== '' && $isAudio) {
                        self::writeCacheFile($cacheDir, $hfCacheFile, $res['body']);
                        return [
                            'success' => true,
                            'fallback' => false,
                            'provider' => 'huggingface',
                            'cached' => false,
                            'voice' => $safeVoice,
                            'estimated_duration_sec' => $estimatedDuration,
                            'audio_base64' => base64_encode($res['body']),
                            'mime' => $mime === 'application/octet-stream' ? 'audio/flac' : $mime,
                        ];
                    }
                }
            }
        }

        // ------------------------------------------------------------------
        // Tier 4: Browser en-GB window.speechSynthesis (Final Fallback)
        // ------------------------------------------------------------------
        return [
            'success' => true,
            'fallback' => true,
            'provider' => 'browser',
            'reason' => 'browser_speech_synthesis',
            'voice' => $safeVoice,
            'estimated_duration_sec' => $estimatedDuration,
            'audio_base64' => null,
            'mime' => null,
        ];
    }

    private static function resolvePiperModelPath(string $safeVoice, string $modelsDir): string
    {
        if ($modelsDir === '' || !is_dir($modelsDir)) {
            return '';
        }

        $candidates = match ($safeVoice) {
            'serena' => ['en_GB-alba-medium.onnx', 'en_GB-jenny_dioco-medium.onnx', 'en_GB-alan-medium.onnx'],
            'elders' => ['en_GB-northern_english_male-medium.onnx', 'en_GB-alan-medium.onnx', 'en_GB-alba-medium.onnx'],
            default => ['en_GB-alan-medium.onnx', 'en_GB-alba-medium.onnx'],
        };

        foreach ($candidates as $file) {
            $path = $modelsDir . DIRECTORY_SEPARATOR . $file;
            if (is_file($path) && is_readable($path)) {
                return $path;
            }
        }

        $glob = glob($modelsDir . DIRECTORY_SEPARATOR . 'en_GB-*.onnx');
        if (is_array($glob) && isset($glob[0]) && is_file($glob[0])) {
            return $glob[0];
        }

        return '';
    }

    private static function runPiperProcess(string $piperBin, string $modelPath, string $text, string $outputWavPath): bool
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $cmd = [$piperBin, '--model', $modelPath, '--output_file', $outputWavPath];
        $proc = @proc_open($cmd, $descriptors, $pipes);
        if (!is_resource($proc)) {
            return false;
        }

        fwrite($pipes[0], $text . "\n");
        fclose($pipes[0]);
        stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        $exitCode = proc_close($proc);
        return $exitCode === 0 && is_file($outputWavPath);
    }

    /**
     * @param list<string> $headers
     * @return array{status: int, content_type: string, body: string}
     */
    private static function postJson(string $url, string $payload, array $headers, ?callable $httpTransport, string $token): array
    {
        if ($httpTransport !== null) {
            /** @var array{status?: int, content_type?: string, body?: string} $res */
            $res = $httpTransport($url, $payload, $token);
            return [
                'status' => (int)($res['status'] ?? 0),
                'content_type' => (string)($res['content_type'] ?? ''),
                'body' => (string)($res['body'] ?? ''),
            ];
        }

        if (!function_exists('curl_init')) {
            return ['status' => 0, 'content_type' => '', 'body' => ''];
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return ['status' => 0, 'content_type' => '', 'body' => ''];
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 12,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        $rawBody = curl_exec($ch);
        $status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        unset($ch);

        return [
            'status' => $status,
            'content_type' => $contentType,
            'body' => is_string($rawBody) ? $rawBody : '',
        ];
    }

    private static function readCacheFile(string $path): ?string
    {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }
        $size = @filesize($path);
        if ($size === false || $size <= 0) {
            return null;
        }
        $data = @file_get_contents($path);
        return (is_string($data) && $data !== '') ? $data : null;
    }

    private static function ensureDir(string $dir): void
    {
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
    }

    private static function writeCacheFile(string $dir, string $path, string $bytes): void
    {
        self::ensureDir($dir);
        if (!is_dir($dir) || !is_writable($dir)) {
            return;
        }
        $tmpFile = $path . '.tmp.' . bin2hex(random_bytes(4));
        if (@file_put_contents($tmpFile, $bytes, LOCK_EX) !== false) {
            @rename($tmpFile, $path);
        }
    }
}
