<?php

declare(strict_types=1);

namespace Tests\Ai;

use PHPUnit\Framework\TestCase;
use Src\Ai\AiImageGenerationService;
use Src\Ai\AiVisionService;
use Src\Ai\AiEntertainmentService;
use Src\Ai\HuggingFaceInferenceGateway;
use Src\Ai\ResilientAiChat;

class AiCreativeServicesTest extends TestCase
{
    public function testAiImageGenerationServiceRejectsEmptyTheme(): void
    {
        $service = new AiImageGenerationService();
        $res = $service->generatePosterBackground('   ');

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('cannot be empty', $res['error'] ?? '');
    }

    public function testAiImageGenerationServiceReturnsAvailableStyles(): void
    {
        $styles = AiImageGenerationService::getAvailableStyles();
        $this->assertArrayHasKey('neon_cyber_party', $styles);
        $this->assertArrayHasKey('kids_storybook_dino', $styles);
        $this->assertArrayHasKey('elegant_gold_luxury', $styles);
        $this->assertArrayHasKey('retro_disco_funk', $styles);
    }

    public function testAiVisionServiceRejectsNonExistentFile(): void
    {
        $service = new AiVisionService();
        $res = $service->parseReceipt('/tmp/non_existent_receipt_' . uniqid() . '.jpg');

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('File does not exist', $res['error'] ?? '');
    }

    public function testAiVisionServiceInspectFridgeRejectsEmptyFile(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'test_empty_fridge');
        file_put_contents($tmp, '');

        $service = new AiVisionService();
        $res = $service->inspectFridge($tmp);

        unlink($tmp);

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('empty', $res['error'] ?? '');
    }

    public function testAiEntertainmentServiceRejectsEmptyHonouree(): void
    {
        $service = new AiEntertainmentService();
        $res = $service->generateSpeech('', '30th Birthday');

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('required', $res['error'] ?? '');
    }

    public function testAiEntertainmentServiceRejectsEmptyDisputeClaims(): void
    {
        $service = new AiEntertainmentService();
        $res = $service->arbitrateDispute('Maya', '', 'Liam', 'He made a mess');

        $this->assertFalse($res['success']);
        $this->assertStringContainsString('must provide names and claims', $res['error'] ?? '');
    }

    public function testAiEntertainmentSpeechMockedSuccessfulFlow(): void
    {
        $mockGateway = $this->createMock(HuggingFaceInferenceGateway::class);
        $mockGateway->method('chatCompletion')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'title' => 'Dave: Master of Sourdough and Pool Dives',
                            'speech_text' => 'Good evening everyone! [pause for laughter] Here is why Dave is unforgettable...',
                            'suggested_toast' => 'Raise your glasses to Dave!',
                            'delivery_tips' => ['Make eye contact', 'Pause after the pool joke'],
                        ]),
                    ],
                ],
            ],
        ]);

        $service = new AiEntertainmentService($mockGateway);
        $res = $service->generateSpeech('Dave', '30th Birthday', ['Fell into pool in Ibiza', 'Obsessed with bread'], 'humorous_roast');

        $this->assertTrue($res['success']);
        $this->assertSame('Dave: Master of Sourdough and Pool Dives', $res['title']);
        $this->assertStringContainsString('pause for laughter', $res['speech_text']);
        $this->assertSame('Raise your glasses to Dave!', $res['suggested_toast']);
        $this->assertCount(2, $res['delivery_tips']);
    }

    public function testAiEntertainmentArbitrateDisputeMockedSuccessfulFlow(): void
    {
        $mockGateway = $this->createMock(HuggingFaceInferenceGateway::class);
        $mockGateway->method('chatCompletion')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'verdict' => 'The court finds Liam guilty of leaving pots, but Maya also shirked wiping duties.',
                            'compromise_action_plan' => [
                                'Liam' => 'Scrub the remaining pots.',
                                'Maya' => 'Wipe down the countertops.',
                            ],
                            'penalty_or_chore' => 'Both parties share unloading the dishwasher tomorrow.',
                            'closing_quip' => 'Justice has been served warm and soapy.',
                        ]),
                    ],
                ],
            ],
        ]);

        $service = new AiEntertainmentService($mockGateway);
        $res = $service->arbitrateDispute('Liam', 'I unloaded dishwasher', 'Maya', 'He left all the pots', 'household_chores');

        $this->assertTrue($res['success']);
        $this->assertStringContainsString('Liam guilty of leaving pots', $res['verdict']);
        $this->assertSame('Scrub the remaining pots.', $res['compromise_action_plan']['Liam'] ?? '');
        $this->assertSame('Wipe down the countertops.', $res['compromise_action_plan']['Maya'] ?? '');
        $this->assertSame('Justice has been served warm and soapy.', $res['closing_quip']);
    }

    public function testAiVisionServiceParseReceiptMockedFlow(): void
    {
        // Create a 10x10 fake PNG image
        $tmpPng = tempnam(sys_get_temp_dir(), 'test_receipt_') . '.png';
        $img = imagecreatetruecolor(10, 10);
        imagepng($img, $tmpPng);
        if (PHP_VERSION_ID < 80500) {
            @imagedestroy($img);
        }

        $mockGateway = $this->createMock(HuggingFaceInferenceGateway::class);
        $mockGateway->method('analyzeImageWithPrompt')->willReturn(json_encode([
            'items' => [
                ['name' => 'Loaded Nachos', 'price' => 9.50, 'quantity' => 1],
                ['name' => 'Mojito', 'price' => 12.00, 'quantity' => 2],
            ],
            'subtotal' => 33.50,
            'tax' => 6.70,
            'total' => 40.20,
            'currency' => 'GBP',
        ]));

        $service = new AiVisionService($mockGateway);
        $res = $service->parseReceipt($tmpPng);

        unlink($tmpPng);

        $this->assertTrue($res['success']);
        $this->assertCount(2, $res['items']);
        $this->assertSame('Loaded Nachos', $res['items'][0]['name']);
        $this->assertSame(9.50, $res['items'][0]['price']);
        $this->assertSame('Mojito', $res['items'][1]['name']);
        $this->assertSame(2, $res['items'][1]['quantity']);
        $this->assertSame(40.20, $res['total']);
        $this->assertSame('GBP', $res['currency']);
    }

    public function testAiEntertainmentTriviaFallbackWhenHuggingFaceFails(): void
    {
        $mockGateway = $this->createMock(HuggingFaceInferenceGateway::class);
        $mockGateway->method('chatCompletion')->willReturn(null);

        $mockChatGateway = $this->createMock(HuggingFaceInferenceGateway::class);
        $mockChatGateway->method('chatCompletion')->willReturn([
            'choices' => [
                [
                    'message' => [
                        'content' => json_encode([
                            'quiz_title' => 'The Fallback Clash',
                            'questions' => [
                                [
                                    'question' => 'Where did we spend Christmas 2021?',
                                    'options' => ['London', 'Glasgow', 'York', 'Cardiff'],
                                    'correct_index' => 2,
                                    'fun_fact' => 'It snowed on Christmas Eve!'
                                ]
                            ]
                        ]),
                    ],
                ],
            ],
        ]);

        $realChat = new ResilientAiChat($mockChatGateway);
        $service = new AiEntertainmentService($mockGateway, $realChat);
        $res = $service->generateFamilyTrivia('Taylor Dynasty', ['Christmas 2021 in York']);

        $this->assertTrue($res['success']);
        $this->assertSame('The Fallback Clash', $res['quiz_title']);
        $this->assertCount(1, $res['questions']);
        $this->assertSame(2, $res['questions'][0]['correct_index']);
    }
}
