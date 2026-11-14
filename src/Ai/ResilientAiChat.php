<?php

declare(strict_types=1);

namespace Src\Ai;

/**
 * ResilientAiChat — Multi-Tier AI Provider with Zero-Cost Sovereign Fallbacks
 *
 * Execution Waterfall:
 * 1.  Tier 1  (Free): Hugging Face Open-Weights (Llama 3.2 / Qwen 2.5)
 * 1b. Tier 1b (Free): Google AI Studio (Gemini Flash Free Tier, OpenAI-compatible)
 * 1c. Tier 1c (Free): Cloudflare Workers AI (10k neurons/day Free Tier, OpenAI-compatible)
 * 2.  Tier 2  (Commercial Failover): DeepSeek
 * 3.  Tier 3  (Commercial Failover): OpenAI
 *
 * Guarantees zero-downtime: if proprietary API keys are revoked, expire, or hit quota limits,
 * the application continues to answer autonomously for £0, with AiPrivacyGuard PII scrubbing
 * enforced before any external dispatch.
 */
final class ResilientAiChat
{
    private HuggingFaceInferenceGateway $hfGateway;

    /** @var (callable(string, string, string, array<int, array{role: string, content: string}>, array<string, mixed>): ?string)|null */
    private $openAiTransport;

    /**
     * @param (callable(string, string, string, array<int, array{role: string, content: string}>, array<string, mixed>): ?string)|null $openAiTransport
     */
    public function __construct(
        ?HuggingFaceInferenceGateway $hfGateway = null,
        ?callable $openAiTransport = null
    ) {
        $this->hfGateway = $hfGateway ?? new HuggingFaceInferenceGateway();
        $this->openAiTransport = $openAiTransport;
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

        // 1b–3. OpenAI-Compatible Waterfall: Free Tiers (Gemini, Cloudflare) -> Commercial (DeepSeek, OpenAI)
        $cfAccountId = $this->resolveEnv('CF_ACCOUNT_ID');
        $providers = [
            [
                'url'      => 'https://generativelanguage.googleapis.com/v1beta/openai/chat/completions',
                'apiKey'   => $this->resolveEnv('GEMINI_API_KEY', 'GOOGLE_AI_STUDIO_API_KEY'),
                'model'    => $this->resolveEnv('GEMINI_MODEL') ?: 'gemini-2.5-flash',
                'provider' => 'Google Gemini (Free Tier 1b)',
            ],
            [
                'url'      => $cfAccountId !== '' ? "https://api.cloudflare.com/client/v4/accounts/{$cfAccountId}/ai/v1/chat/completions" : '',
                'apiKey'   => $this->resolveEnv('CF_API_TOKEN'),
                'model'    => $this->resolveEnv('CF_AI_MODEL') ?: '@cf/meta/llama-3.1-8b-instruct',
                'provider' => 'Cloudflare Workers AI (Free Tier 1c)',
            ],
            [
                'url'      => 'https://api.deepseek.com/v1/chat/completions',
                'apiKey'   => $this->resolveEnv('DEEPSEEK_API_KEY'),
                'model'    => $this->resolveEnv('DEEPSEEK_MODEL') ?: 'deepseek-chat',
                'provider' => 'DeepSeek (Tier 2 Failover)',
            ],
            [
                'url'      => 'https://api.openai.com/v1/chat/completions',
                'apiKey'   => $this->resolveEnv('OPENAI_API_KEY', 'CHATGPT_API'),
                'model'    => $this->resolveEnv('CHATGPT_MODEL') ?: 'gpt-4o-mini',
                'provider' => 'OpenAI (Tier 3 Failover)',
            ],
        ];

        foreach ($providers as $tier) {
            if ($tier['url'] === '' || $tier['apiKey'] === '') {
                continue;
            }
            $res = $this->dispatchOpenAiCompatible(
                url: $tier['url'],
                apiKey: $tier['apiKey'],
                model: $tier['model'],
                messages: $scrubbedMessages,
                options: $options
            );
            if ($res !== null && trim($res) !== '') {
                return [
                    'success'  => true,
                    'content'  => $res,
                    'provider' => $tier['provider'],
                    'model'    => $tier['model'],
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

    private function resolveEnv(string $primaryKey, ?string $fallbackKey = null): string
    {
        $val = $_ENV[$primaryKey] ?? ($fallbackKey !== null ? ($_ENV[$fallbackKey] ?? null) : null);
        if (is_string($val) && $val !== '') {
            return $val;
        }
        $env = getenv($primaryKey);
        if (is_string($env) && $env !== '') {
            return $env;
        }
        if ($fallbackKey !== null) {
            $fallbackEnv = getenv($fallbackKey);
            if (is_string($fallbackEnv) && $fallbackEnv !== '') {
                return $fallbackEnv;
            }
        }
        return '';
    }

    /**
     * @param array<int, array{role: string, content: string}> $messages
     * @param array<string, mixed> $options
     */
    private function dispatchOpenAiCompatible(
        string $url,
        string $apiKey,
        string $model,
        array $messages,
        array $options
    ): ?string {
        if ($this->openAiTransport !== null) {
            return ($this->openAiTransport)($url, $apiKey, $model, $messages, $options);
        }
        return $this->callOpenAiCompatible($url, $apiKey, $model, $messages, $options);
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
