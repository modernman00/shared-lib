<?php

declare(strict_types=1);

namespace Tests\Ai;

use PHPUnit\Framework\TestCase;
use Src\Ai\VoiceTranscriptionService;
use Src\Ai\ContentModerationService;
use Src\Ai\EmbeddingService;
use Src\Ai\ResilientAiChat;
use Src\Ai\HuggingFaceInferenceGateway;

/**
 * AiServicesTest
 *
 * Verifies the 4 core AI capabilities (A, B, C, D) in shared-lib:
 * - A: Voice transcription guardrails
 * - B: Content moderation & NSFW threshold evaluation
 * - C: Dense vector cosine similarity & in-memory semantic ranking
 * - D: Resilient failover with Hugging Face fallback
 */
class AiServicesTest extends TestCase
{
    public function testVoiceTranscriptionRejectsMissingFile(): void
    {
        $service = new VoiceTranscriptionService();
        $result = $service->transcribe('/tmp/non_existent_voice_note_' . uniqid() . '.mp3');

        $this->assertFalse($result['success']);
        $this->assertSame('', $result['text']);
        $this->assertNotNull($result['error']);
    }

    public function testContentModerationSafeOnEmptyInput(): void
    {
        $service = new ContentModerationService();
        $textRes = $service->inspectText('');

        $this->assertTrue($textRes['is_safe']);
        $this->assertSame('empty', $textRes['label']);
    }

    public function testContentModerationMissingPhoto(): void
    {
        $service = new ContentModerationService();
        $photoRes = $service->inspectPhoto('/tmp/missing_photo_' . uniqid() . '.jpg');

        $this->assertFalse($photoRes['is_safe']);
        $this->assertTrue($photoRes['flagged']);
        $this->assertSame('missing_file', $photoRes['label']);
    }

    public function testEmbeddingCosineSimilarityIdenticalVectors(): void
    {
        $vecA = [1.0, 0.5, -0.2, 0.8];
        $vecB = [1.0, 0.5, -0.2, 0.8];

        $similarity = EmbeddingService::cosineSimilarity($vecA, $vecB);
        $this->assertEqualsWithDelta(1.0, $similarity, 0.0001);
    }

    public function testEmbeddingCosineSimilarityOppositeVectors(): void
    {
        $vecA = [1.0, 0.0, 0.0];
        $vecB = [-1.0, 0.0, 0.0];

        $similarity = EmbeddingService::cosineSimilarity($vecA, $vecB);
        $this->assertEqualsWithDelta(-1.0, $similarity, 0.0001);
    }

    public function testEmbeddingCosineSimilarityOrthogonalVectors(): void
    {
        $vecA = [1.0, 0.0, 0.0];
        $vecB = [0.0, 1.0, 0.0];

        $similarity = EmbeddingService::cosineSimilarity($vecA, $vecB);
        $this->assertEqualsWithDelta(0.0, $similarity, 0.0001);
    }

    public function testEmbeddingRankBySimilarity(): void
    {
        $query = [1.0, 0.0, 0.0];

        $items = [
            ['id' => 'doc_unrelated', 'vector' => [0.0, 1.0, 0.0], 'data' => ['title' => 'Finance Report']],
            ['id' => 'doc_close',     'vector' => [0.8, 0.2, 0.0], 'data' => ['title' => 'Cornwall Family Trip']],
            ['id' => 'doc_exact',     'vector' => [1.0, 0.0, 0.0], 'data' => ['title' => 'Grandfather Fishing in Cornwall']],
        ];

        $ranked = EmbeddingService::rankBySimilarity($query, $items, topK: 2);

        $this->assertCount(2, $ranked);
        $this->assertSame('doc_exact', $ranked[0]['id']);
        $this->assertEqualsWithDelta(1.0, $ranked[0]['similarity'], 0.0001);
        $this->assertSame('doc_close', $ranked[1]['id']);
    }

    public function testResilientAiChatFailsGracefullyWhenAllKeysEmpty(): void
    {
        $prevDeepSeek = $_ENV['DEEPSEEK_API_KEY'] ?? null;
        $prevOpenAi = $_ENV['OPENAI_API_KEY'] ?? null;
        $prevChatGpt = $_ENV['CHATGPT_API'] ?? null;

        unset($_ENV['DEEPSEEK_API_KEY'], $_ENV['OPENAI_API_KEY'], $_ENV['CHATGPT_API']);

        $mockGateway = new class extends HuggingFaceInferenceGateway {
            public function __construct()
            {
                parent::__construct(['api_token' => 'mock']);
            }
            public function chatCompletion(array $messages, string $model = 'meta-llama/Llama-3.2-3B-Instruct', array $options = []): ?array
            {
                return [
                    'choices' => [
                        [
                            'message' => ['content' => 'Hello from Hugging Face Sovereign Fallback!']
                        ]
                    ]
                ];
            }
        };

        $chat = new ResilientAiChat($mockGateway);
        $res = $chat->complete([
            ['role' => 'user', 'content' => 'Hello']
        ]);

        $this->assertTrue($res['success']);
        $this->assertSame('Hello from Hugging Face Sovereign Fallback!', $res['content']);
        $this->assertStringContainsString('HuggingFace', $res['provider']);

        // Test chat() method directly
        $chatContent = $chat->chat([
            ['role' => 'user', 'content' => 'Hello']
        ]);
        $this->assertSame('Hello from Hugging Face Sovereign Fallback!', $chatContent);

        if ($prevDeepSeek !== null) $_ENV['DEEPSEEK_API_KEY'] = $prevDeepSeek;
        if ($prevOpenAi !== null) $_ENV['OPENAI_API_KEY'] = $prevOpenAi;
        if ($prevChatGpt !== null) $_ENV['CHATGPT_API'] = $prevChatGpt;
    }

    public function testGenerateFamilyTrivia(): void
    {
        $mockGateway = new class extends HuggingFaceInferenceGateway {
            public function __construct()
            {
                parent::__construct(['api_token' => 'mock']);
            }
            public function chatCompletion(array $messages, string $model = 'meta-llama/Llama-3.2-3B-Instruct', array $options = []): ?array
            {
                return [
                    'choices' => [
                        [
                            'message' => [
                                'content' => json_encode([
                                    'quiz_title' => 'The Adebisi Dynasty Clash',
                                    'questions' => [
                                        [
                                            'question' => 'Who made the legendary 1995 trip to Edinburgh?',
                                            'options' => ['Grandpa', 'Uncle Femi', 'Auntie Ronke', 'Cousin Bayo'],
                                            'correct_index' => 1,
                                            'fun_fact' => 'He brought back 40 tartan scarves!'
                                        ]
                                    ]
                                ])
                            ]
                        ]
                    ]
                ];
            }
        };

        $service = new \Src\Ai\AiEntertainmentService($mockGateway);
        $result = $service->generateFamilyTrivia('Adebisi Family', ['Uncle Femi visited Edinburgh in 1995']);

        $this->assertTrue($result['success']);
        $this->assertSame('The Adebisi Dynasty Clash', $result['quiz_title']);
        $this->assertCount(1, $result['questions']);
        $this->assertSame('Who made the legendary 1995 trip to Edinburgh?', $result['questions'][0]['question']);
        $this->assertSame(1, $result['questions'][0]['correct_index']);
    }
}
