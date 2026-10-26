<?php

declare(strict_types=1);

namespace Src\Ai;

/**
 * Universal Hugging Face Inference Gateway & Micro-Model Orchestrator
 *
 * Implements:
 * - Serverless Inference API & Dedicated Private Endpoint orchestration
 * - Strict SSRF URL allowlisting (mitigating cloud metadata & loopback SSRF attacks)
 * - OpenAI-compatible chat completions interface for Llama-3, Mistral, Qwen
 * - Audio transcription pipeline (Whisper)
 * - Zero-latency binary image classification & document vision (Donut/Florence-2)
 * - Vector embedding generation (sentence-transformers)
 * - Gate 3 bounded cURL timeouts & graceful error handling
 */
class HuggingFaceInferenceGateway
{
    private string $apiToken;
    private string $defaultBaseUrl;
    private int $connectTimeout;
    private int $timeout;

    /**
     * @param array{
     *     api_token?: string,
     *     base_url?: string,
     *     connect_timeout?: int,
     *     timeout?: int
     * } $config
     */
    public function __construct(array $config = [])
    {
        $this->apiToken = (string)($config['api_token'] ?? $_ENV['HF_API_TOKEN'] ?? getenv('HF_API_TOKEN') ?: '');
        $this->defaultBaseUrl = rtrim((string)($config['base_url'] ?? $_ENV['HF_BASE_URL'] ?? 'https://api-inference.huggingface.co'), '/');
        $this->connectTimeout = (int)($config['connect_timeout'] ?? 2);
        $this->timeout = (int)($config['timeout'] ?? 5);
    }

    /**
     * Red Team SSRF Mitigation Gate: Validates that the endpoint URL is a legitimate Hugging Face destination.
     *
     * @param string $url
     * @return bool
     */
    public static function isAllowedEndpoint(string $url): bool
    {
        $parsed = parse_url($url);
        if (!$parsed || empty($parsed['host']) || empty($parsed['scheme'])) {
            return false;
        }

        // Scheme MUST be strictly https (or http ONLY if explicitly targeting internal local VPC container)
        $scheme = strtolower($parsed['scheme']);
        if ($scheme !== 'https') {
            return false;
        }

        $host = strtolower(trim($parsed['host'], '[]'));

        // Disallow bare IP addresses, localhost, and cloud metadata IPs
        if (filter_var($host, FILTER_VALIDATE_IP) !== false || $host === 'localhost') {
            return false;
        }

        // Exact allowed hosts or valid HF subdomains
        if ($host === 'api-inference.huggingface.co') {
            return true;
        }

        if ($host === 'endpoints.huggingface.cloud' || str_ends_with($host, '.endpoints.huggingface.cloud')) {
            return true;
        }

        if ($host === 'huggingface.co' || str_ends_with($host, '.huggingface.co')) {
            return true;
        }

        return false;
    }

    /**
     * OpenAI-compatible chat completion dispatch for open-weights LLMs (Llama, Mistral, Qwen).
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @param string $model
     * @param array<string, mixed> $options
     * @return array<string, mixed>|null
     */
    public function chatCompletion(
        array $messages,
        string $model = 'meta-llama/Llama-3.2-3B-Instruct',
        array $options = []
    ): ?array {
        $endpoint = "{$this->defaultBaseUrl}/v1/chat/completions";
        if (!self::isAllowedEndpoint($endpoint)) {
            error_log('[HFInferenceGateway] Blocked disallowed endpoint: ' . $endpoint);
            return null;
        }

        // Zero-PII Enforcer: scrub names, dates of birth, UK addresses, and postcodes before model dispatch
        $scrubbedMessages = AiPrivacyGuard::scrubMessages($messages);

        $payload = array_merge([
            'model' => $model,
            'messages' => $scrubbedMessages,
            'max_tokens' => $options['max_tokens'] ?? 512,
            'temperature' => $options['temperature'] ?? 0.7,
        ], $options);

        $response = $this->executeCurl($endpoint, json_encode($payload), [
            'Content-Type: application/json',
        ]);

        if (!is_string($response)) {
            return null;
        }

        $decoded = json_decode($response, true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Classify text using specialized task classifiers (e.g. sentiment, toxicity, intent).
     *
     * @param string $text
     * @param string $model
     * @return array<int, array{label: string, score: float}>|null
     */
    public function classifyText(
        string $text,
        string $model = 'distilbert-base-uncased-finetuned-sst-2-english'
    ): ?array {
        $endpoint = "{$this->defaultBaseUrl}/models/{$model}";
        if (!self::isAllowedEndpoint($endpoint)) {
            return null;
        }

        $scrubbedText = AiPrivacyGuard::scrubText($text);
        $payload = json_encode(['inputs' => $scrubbedText]);
        $response = $this->executeCurl($endpoint, $payload, [
            'Content-Type: application/json',
        ]);

        if (!is_string($response)) {
            return null;
        }

        $decoded = json_decode($response, true);
        if (!is_array($decoded)) {
            return null;
        }

        // Hugging Face returns an array of label/score arrays (often nested: [[{label, score}]])
        if (isset($decoded[0]) && is_array($decoded[0])) {
            /** @var array<int, array{label: string, score: float}> $first */
            $first = isset($decoded[0][0]) && is_array($decoded[0][0]) ? $decoded[0] : $decoded;
            return $first;
        }

        return null;
    }

    /**
     * Transcribe audio files using OpenAI's Whisper model hosted on Hugging Face.
     *
     * @param string $audioFilePath Absolute path to mp3, wav, m4a, ogg, or webm file
     * @param string $model
     * @return string|null Transcribed plain text
     */
    public function transcribeAudio(
        string $audioFilePath,
        string $model = 'openai/whisper-large-v3-turbo'
    ): ?string {
        if (!file_exists($audioFilePath) || filesize($audioFilePath) === 0) {
            return null;
        }

        $endpoint = "{$this->defaultBaseUrl}/models/{$model}";
        if (!self::isAllowedEndpoint($endpoint)) {
            return null;
        }

        $audioBytes = file_get_contents($audioFilePath);
        if ($audioBytes === false) {
            return null;
        }

        // Bounded 15-second timeout for audio workloads
        $response = $this->executeCurl($endpoint, $audioBytes, [
            'Content-Type: application/octet-stream',
        ], 15);

        if (!is_string($response)) {
            return null;
        }

        $decoded = json_decode($response, true);
        if (is_array($decoded) && isset($decoded['text']) && is_string($decoded['text'])) {
            return trim($decoded['text']);
        }

        return null;
    }

    /**
     * Classify or inspect an image using Vision Transformer models (NSFW guard, OCR, layout).
     *
     * @param string $imageFilePath Absolute path to image file
     * @param string $model
     * @return array<int, array{label: string, score: float}>|null
     */
    public function classifyImage(
        string $imageFilePath,
        string $model = 'Falconsai/nsfw_image_detection'
    ): ?array {
        if (!file_exists($imageFilePath) || filesize($imageFilePath) === 0) {
            return null;
        }

        $endpoint = "{$this->defaultBaseUrl}/models/{$model}";
        if (!self::isAllowedEndpoint($endpoint)) {
            return null;
        }

        $imageBytes = file_get_contents($imageFilePath);
        if ($imageBytes === false) {
            return null;
        }

        $response = $this->executeCurl($endpoint, $imageBytes, [
            'Content-Type: application/octet-stream',
        ], 4);

        if (!is_string($response)) {
            return null;
        }

        $decoded = json_decode($response, true);
        /** @var array<int, array{label: string, score: float}>|null $result */
        $result = is_array($decoded) && isset($decoded[0]) ? $decoded : null;
        return $result;
    }

    /**
     * Generate an image from a text prompt using state-of-the-art diffusion models (e.g. FLUX.1-schnell, SDXL).
     *
     * @param string $prompt
     * @param string $model
     * @param array<string, mixed> $parameters
     * @return string|null Raw binary image bytes or null on failure
     */
    public function generateImage(
        string $prompt,
        string $model = 'black-forest-labs/FLUX.1-schnell',
        array $parameters = []
    ): ?string {
        $scrubbedPrompt = AiPrivacyGuard::scrubText(trim($prompt));
        if ($scrubbedPrompt === '') {
            return null;
        }

        $endpoint = "{$this->defaultBaseUrl}/models/{$model}";
        if (!self::isAllowedEndpoint($endpoint)) {
            return null;
        }

        $payloadData = ['inputs' => $scrubbedPrompt];
        if (!empty($parameters)) {
            $payloadData['parameters'] = $parameters;
        }

        $payload = json_encode($payloadData);
        if ($payload === false) {
            return null;
        }

        // Bounded 25-second timeout for diffusion workloads (Gate 3)
        $response = $this->executeCurl($endpoint, $payload, [
            'Content-Type: application/json',
            'Accept: image/jpeg, image/png',
        ], 25);

        return is_string($response) && strlen($response) > 0 ? $response : null;
    }

    /**
     * Multimodal Vision-Language analysis (e.g. Qwen2-VL, Llama-3.2-Vision) for receipts, invoices, or scene recognition.
     *
     * @param string $imageFilePath Absolute path to image
     * @param string $prompt User instruction
     * @param string $model
     * @param string|null $systemPrompt
     * @return string|null Text analysis / structured JSON reply
     */
    public function analyzeImageWithPrompt(
        string $imageFilePath,
        string $prompt,
        string $model = 'Qwen/Qwen2-VL-7B-Instruct',
        ?string $systemPrompt = null
    ): ?string {
        if (!file_exists($imageFilePath) || filesize($imageFilePath) === 0) {
            return null;
        }

        $imageBytes = file_get_contents($imageFilePath);
        if ($imageBytes === false) {
            return null;
        }

        $mime = mime_content_type($imageFilePath) ?: 'image/jpeg';
        $base64Image = 'data:' . $mime . ';base64,' . base64_encode($imageBytes);

        $scrubbedPrompt = AiPrivacyGuard::scrubText($prompt);
        $messages = [];
        if ($systemPrompt !== null && trim($systemPrompt) !== '') {
            $messages[] = [
                'role' => 'system',
                'content' => $systemPrompt,
            ];
        }

        $messages[] = [
            'role' => 'user',
            'content' => [
                ['type' => 'text', 'text' => $scrubbedPrompt],
                ['type' => 'image_url', 'image_url' => ['url' => $base64Image]],
            ],
        ];

        // Call OpenAI-compatible chat completions endpoint
        $endpoint = "{$this->defaultBaseUrl}/v1/chat/completions";
        if (!self::isAllowedEndpoint($endpoint)) {
            return null;
        }

        $payload = json_encode([
            'model' => $model,
            'messages' => $messages,
            'max_tokens' => 1024,
            'temperature' => 0.2,
        ]);

        if ($payload === false) {
            return null;
        }

        // Bounded 20-second timeout for multimodal vision
        $response = $this->executeCurl($endpoint, $payload, [
            'Content-Type: application/json',
        ], 20);

        if (!is_string($response)) {
            return null;
        }

        $decoded = json_decode($response, true);
        if (is_array($decoded) && isset($decoded['choices'][0]['message']['content']) && is_string($decoded['choices'][0]['message']['content'])) {
            return trim($decoded['choices'][0]['message']['content']);
        }

        return null;
    }

    /**
     * Generate dense vector embeddings for semantic search and document retrieval.
     *
     * @param string|array<int, string> $input Single text or batch of texts
     * @param string $model
     * @return array<int, float>|array<int, array<int, float>>|null
     */
    public function generateEmbeddings(
        string|array $input,
        string $model = 'sentence-transformers/all-MiniLM-L6-v2'
    ): ?array {
        $endpoint = "{$this->defaultBaseUrl}/models/{$model}";
        if (!self::isAllowedEndpoint($endpoint)) {
            return null;
        }

        $scrubbedInput = is_string($input)
            ? AiPrivacyGuard::scrubText($input)
            : array_map([AiPrivacyGuard::class, 'scrubText'], $input);

        $payload = json_encode(['inputs' => $scrubbedInput]);
        $response = $this->executeCurl($endpoint, $payload, [
            'Content-Type: application/json',
        ], 4);

        if (!is_string($response)) {
            return null;
        }

        $decoded = json_decode($response, true);
        /** @var array<int, float>|array<int, array<int, float>>|null $result */
        $result = is_array($decoded) ? $decoded : null;
        return $result;
    }

    /**
     * Low-level cURL execution wrapper with Gate 3 bounded timeouts.
     *
     * @param string $url
     * @param string|false $payload
     * @param array<int, string> $headers
     * @param int|null $customTimeout
     * @return string|null Raw response string or null on failure
     */
    private function executeCurl(
        string $url,
        string|false $payload,
        array $headers = [],
        ?int $customTimeout = null
    ): ?string {
        if ($payload === false) {
            return null;
        }

        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        $defaultHeaders = [];
        if (!empty($this->apiToken)) {
            $defaultHeaders[] = 'Authorization: Bearer ' . $this->apiToken;
        }
        $mergedHeaders = array_merge($defaultHeaders, $headers);

        $timeout = $customTimeout ?? $this->timeout;

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => $mergedHeaders,
            CURLOPT_CONNECTTIMEOUT => $this->connectTimeout,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_FAILONERROR    => false, // We inspect HTTP status manually
        ]);

        $response = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        if (PHP_VERSION_ID < 80500) {
            @curl_close($ch);
        }

        if ($response === false || $httpCode < 200 || $httpCode >= 300) {
            error_log(sprintf(
                '[HFInferenceGateway] Request failed [HTTP %d] [Error: %s] Endpoint: %s',
                $httpCode,
                $error,
                $url
            ));
            return null;
        }

        return is_string($response) ? $response : null;
    }
}
