<?php

declare(strict_types=1);

namespace Tests\Ai;

use PHPUnit\Framework\TestCase;
use Src\Ai\HuggingFaceInferenceGateway;
use Src\Ai\ResilientAiChat;

final class ResilientAiChatTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $originalEnv = [];

    private const ENV_KEYS = [
        'GEMINI_API_KEY',
        'GOOGLE_AI_STUDIO_API_KEY',
        'GEMINI_MODEL',
        'CF_ACCOUNT_ID',
        'CF_API_TOKEN',
        'CF_AI_MODEL',
        'DEEPSEEK_API_KEY',
        'DEEPSEEK_MODEL',
        'OPENAI_API_KEY',
        'CHATGPT_API',
        'CHATGPT_MODEL',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        foreach (self::ENV_KEYS as $key) {
            $this->originalEnv[$key] = $_ENV[$key] ?? null;
            unset($_ENV[$key]);
            putenv($key);
        }
    }

    protected function tearDown(): void
    {
        foreach (self::ENV_KEYS as $key) {
            $val = $this->originalEnv[$key] ?? null;
            if (is_string($val)) {
                $_ENV[$key] = $val;
                putenv("{$key}={$val}");
            } else {
                unset($_ENV[$key]);
                putenv($key);
            }
        }
        parent::tearDown();
    }

    public function testFallsBackToGoogleGeminiFreeTierBeforeCommercialProviders(): void
    {
        $_ENV['GEMINI_API_KEY'] = 'test-gemini-key';
        $_ENV['DEEPSEEK_API_KEY'] = 'test-deepseek-key';

        $hf = $this->createMock(HuggingFaceInferenceGateway::class);
        $hf->method('chatCompletion')->willReturn(null);

        $calledUrls = [];
        $chat = new ResilientAiChat($hf, function (string $url, string $apiKey, string $model, array $messages) use (&$calledUrls): ?string {
            $calledUrls[] = $url;
            return str_contains($url, 'generativelanguage.googleapis.com') ? 'Hello from Gemini' : null;
        });

        $result = $chat->complete([['role' => 'user', 'content' => 'Ping']]);

        $this->assertTrue($result['success']);
        $this->assertSame('Google Gemini (Free Tier 1b)', $result['provider']);
        $this->assertSame('gemini-2.5-flash', $result['model']);
        $this->assertSame('Hello from Gemini', $result['content']);
        $this->assertCount(1, $calledUrls);
    }

    public function testFallsBackToCloudflareWorkersAiFreeTierWhenGeminiFails(): void
    {
        $_ENV['GEMINI_API_KEY'] = 'test-gemini-key';
        $_ENV['CF_ACCOUNT_ID'] = 'cf-acc-123';
        $_ENV['CF_API_TOKEN'] = 'cf-token-456';
        $_ENV['DEEPSEEK_API_KEY'] = 'test-deepseek-key';

        $hf = $this->createMock(HuggingFaceInferenceGateway::class);
        $hf->method('chatCompletion')->willReturn(null);

        $calledUrls = [];
        $chat = new ResilientAiChat($hf, function (string $url) use (&$calledUrls): ?string {
            $calledUrls[] = $url;
            return str_contains($url, 'api.cloudflare.com/client/v4/accounts/cf-acc-123/ai/v1/chat/completions')
                ? 'Hello from Cloudflare'
                : null;
        });

        $result = $chat->complete([['role' => 'user', 'content' => 'Ping']]);

        $this->assertTrue($result['success']);
        $this->assertSame('Cloudflare Workers AI (Free Tier 1c)', $result['provider']);
        $this->assertSame('@cf/meta/llama-3.1-8b-instruct', $result['model']);
        $this->assertSame('Hello from Cloudflare', $result['content']);
        $this->assertCount(2, $calledUrls);
    }

    public function testScrubsPiiBeforeDispatchingToFreeTiers(): void
    {
        $_ENV['GEMINI_API_KEY'] = 'test-gemini-key';

        $hf = $this->createMock(HuggingFaceInferenceGateway::class);
        $hf->method('chatCompletion')->willReturn(null);

        $dispatchedContent = '';
        $chat = new ResilientAiChat($hf, function (string $url, string $apiKey, string $model, array $messages) use (&$dispatchedContent): string {
            $dispatchedContent = $messages[0]['content'] ?? '';
            return 'OK';
        });

        $chat->complete([
            ['role' => 'user', 'content' => 'Contact me at 10 Downing Street SW1A 1AA born 1985-05-14'],
        ]);

        $this->assertStringNotContainsString('SW1A 1AA', $dispatchedContent);
        $this->assertStringNotContainsString('1985-05-14', $dispatchedContent);
    }

    public function testReturnsGracefulFailureWhenNoProvidersConfigured(): void
    {
        $hf = $this->createMock(HuggingFaceInferenceGateway::class);
        $hf->method('chatCompletion')->willReturn(null);

        $chat = new ResilientAiChat($hf, fn (): ?string => null);
        $result = $chat->complete([['role' => 'user', 'content' => 'Ping']]);

        $this->assertFalse($result['success']);
        $this->assertSame('none', $result['provider']);
        $this->assertSame('', $result['content']);
    }
}
