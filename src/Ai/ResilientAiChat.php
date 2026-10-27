<?php

declare(strict_types=1);

namespace Src\Ai;

/**
 * ResilientAiChat — Multi-Tier AI Provider with Zero-Cost Hugging Face Fallback
 *
 * Execution Waterfall:
 * 1. Primary Commercial Provider (DeepSeek / OpenAI)
 * 2. Secondary Provider (Google Gemini)
 * 3. Free Sovereign Fallback (Hugging Face Open-Weights: Llama 3.2 / Qwen 2.5)
 *
 * Guarantees zero-downtime: if proprietary API keys are revoked, expire, or hit quota limits,
 * the application continues to answer autonomously for £0.
 */
final class ResilientAiChat
{
    private HuggingFaceInferenceGateway $hfGateway;

    public function __construct(?HuggingFaceInferenceGateway $hfGateway = null)
    {
        $this->hfGateway = $hfGateway ?? new HuggingFaceInferenceGateway();
    }

    /**
     * Send chat messages and return the generated text response, or null on failure.
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     * @return string|null
     */
    public function chat(array $messages, array $options = []): ?string
    {
        $result = $this->complete($messages, $options);
        return ($result['success'] && trim($result['content']) !== '') ? $result['content'] : null;
    }

    /**
     * Generate chat completion with automatic resilient multi-provider failover.
     *
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     * @return array{success: bool, content: string, provider: string, model: string, error: ?string}
     */
    public function complete(array $messages, array $options = []): array
    {
        // Zero-PII Enforcer: Scrub names, DOBs, UK addresses, and postcodes before model dispatch
        $scrubbedMessages = AiPrivacyGuard::scrubMessages($messages);

        // 1. TIER 1 (First Line): Free Sovereign Hugging Face Serverless API (£0)
        $hfModel = (string)($options['hf_model'] ?? $_ENV['HF_FALLBACK_MODEL'] ?? 'meta-llama/Llama-3.2-3B-Instruct');
        $hfResult = $this->hfGateway->chatCompletion($scrubbedMessages, $hfModel, $options);

        if ($hfResult !== null && isset($hfResult['choices'][0]['message']['content']) && trim((string)$hfResult['choices'][0]['message']['content']) !== '') {
            return [
                'success'  => true,
                'content'  => (string)$hfResult['choices'][0]['message']['content'],
                'provider' => 'HuggingFace (Free Tier 1)',
                'model'    => $hfModel,
                'error'    => null,
            ];
        }

        // 2. TIER 2: DeepSeek Commercial Failover (High-speed secondary fallback)
        $deepseekKey = (string)($_ENV['DEEPSEEK_API_KEY'] ?? getenv('DEEPSEEK_API_KEY') ?: '');
        if ($deepseekKey !== '') {
            $res = $this->callOpenAiCompatible(
                url: 'https://api.deepseek.com/v1/chat/completions',
                apiKey: $deepseekKey,
                model: (string)($_ENV['DEEPSEEK_MODEL'] ?? 'deepseek-chat'),
                messages: $scrubbedMessages,
                options: $options
            );
            if ($res !== null) {
                return [
                    'success'  => true,
                    'content'  => $res,
                    'provider' => 'DeepSeek (Tier 2 Failover)',
                    'model'    => (string)($_ENV['DEEPSEEK_MODEL'] ?? 'deepseek-chat'),
                    'error'    => null,
                ];
            }
        }

        // 3. TIER 3: OpenAI Commercial Failover
        $openaiKey = (string)($_ENV['OPENAI_API_KEY'] ?? $_ENV['CHATGPT_API'] ?? getenv('OPENAI_API_KEY') ?: '');
        if ($openaiKey !== '') {
            $res = $this->callOpenAiCompatible(
                url: 'https://api.openai.com/v1/chat/completions',
                apiKey: $openaiKey,
                model: (string)($_ENV['CHATGPT_MODEL'] ?? 'gpt-4o-mini'),
                messages: $scrubbedMessages,
                options: $options
            );
            if ($res !== null) {
                return [
                    'success'  => true,
                    'content'  => $res,
                    'provider' => 'OpenAI (Tier 3 Failover)',
                    'model'    => (string)($_ENV['CHATGPT_MODEL'] ?? 'gpt-4o-mini'),
                    'error'    => null,
                ];
            }
        }

        return [
            'success'  => false,
            'content'  => '',
            'provider' => 'none',
            'model'    => 'none',
            'error'    => 'All AI providers in resilience waterfall were unreachable or unconfigured.',
        ];
    }

    /**
     * Opt-in Web-Grounded Chat Completion via ResilientWebSearchService.
     * Standard chat() and complete() never invoke billable Search/Fetch APIs unless groundedChat() is explicitly called.
     *
     * @param string $searchQuery Live web query to ground the response
     * @param string $systemInstruction Domain instruction
     * @param array{
     *     count?: int,
     *     min_authority_tier?: int,
     *     site_filter?: string,
     *     bypass_cache?: bool,
     *     max_tokens?: int
     * } $options
     * @return array{
     *     success: bool,
     *     query_dispatched: string,
     *     synthesis: string,
     *     citations: list<array{title: string, url: string, snippet: string, authority_tier: int}>,
     *     search_provider: string,
     *     chat_provider: string,
     *     cache_hit: bool,
     *     estimated_cost_usd: float,
     *     error: ?string
     * }
     */
    public function groundedChat(string $searchQuery, string $systemInstruction = 'You are a helpful UK assistant.', array $options = []): array
    {
        $webSearch = new ResilientWebSearchService($this);
        return $webSearch->groundedResearch($searchQuery, $systemInstruction, $options);
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     */
    private function callOpenAiCompatible(
        string $url,
        string $apiKey,
        string $model,
        array $messages,
        array $options
    ): ?string {
        $ch = curl_init($url);
        if ($ch === false) {
            return null;
        }

        $payload = json_encode([
            'model'       => $model,
            'messages'    => $messages,
            'temperature' => $options['temperature'] ?? 0.7,
            'max_tokens'  => $options['max_tokens'] ?? 512,
        ]);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ],
            CURLOPT_CONNECTTIMEOUT => 2,
            CURLOPT_TIMEOUT        => 4,
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
        if (is_array($decoded) && isset($decoded['choices'][0]['message']['content'])) {
            return (string)$decoded['choices'][0]['message']['content'];
        }

        return null;
    }
}
