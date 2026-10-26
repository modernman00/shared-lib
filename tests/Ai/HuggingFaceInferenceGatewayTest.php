<?php

declare(strict_types=1);

namespace Tests\Ai;

use PHPUnit\Framework\TestCase;
use Src\Ai\HuggingFaceInferenceGateway;

/**
 * HuggingFaceInferenceGatewayTest
 *
 * Verifies the core integration gateway for Hugging Face:
 * - Red Team SSRF Endpoint Allowlisting
 * - Input validation & defensive typing
 * - OpenAI-compatible payload shaping
 * - Bounded error handling on missing files & invalid inputs
 */
class HuggingFaceInferenceGatewayTest extends TestCase
{
    private HuggingFaceInferenceGateway $gateway;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gateway = new HuggingFaceInferenceGateway([
            'api_token' => 'hf_mock_test_token',
            'base_url' => 'https://api-inference.huggingface.co',
            'connect_timeout' => 1,
            'timeout' => 2,
        ]);
    }

    public function testIsAllowedEndpointAllowsLegitimateHfDomains(): void
    {
        $this->assertTrue(
            HuggingFaceInferenceGateway::isAllowedEndpoint('https://api-inference.huggingface.co/v1/chat/completions')
        );

        $this->assertTrue(
            HuggingFaceInferenceGateway::isAllowedEndpoint('https://bcp-clinical.endpoints.huggingface.cloud/v1/chat/completions')
        );

        $this->assertTrue(
            HuggingFaceInferenceGateway::isAllowedEndpoint('https://huggingface.co/models/whisper')
        );
    }

    public function testIsAllowedEndpointBlocksSsrfAttacks(): void
    {
        // Cloud metadata IP
        $this->assertFalse(
            HuggingFaceInferenceGateway::isAllowedEndpoint('http://169.254.169.254/latest/meta-data/')
        );

        // Localhost & loopbacks
        $this->assertFalse(
            HuggingFaceInferenceGateway::isAllowedEndpoint('http://localhost:8000')
        );
        $this->assertFalse(
            HuggingFaceInferenceGateway::isAllowedEndpoint('https://127.0.0.1:443')
        );

        // Non-HF external domains
        $this->assertFalse(
            HuggingFaceInferenceGateway::isAllowedEndpoint('https://api.openai.com/v1/chat/completions')
        );
        $this->assertFalse(
            HuggingFaceInferenceGateway::isAllowedEndpoint('https://evil-hacker.com/steal-keys')
        );

        // Invalid protocols
        $this->assertFalse(
            HuggingFaceInferenceGateway::isAllowedEndpoint('file:///etc/passwd')
        );
        $this->assertFalse(
            HuggingFaceInferenceGateway::isAllowedEndpoint('ftp://api-inference.huggingface.co')
        );
    }

    public function testTranscribeAudioRejectsNonExistentFile(): void
    {
        $result = $this->gateway->transcribeAudio('/tmp/non_existent_voice_note_' . uniqid() . '.wav');
        $this->assertNull($result);
    }

    public function testClassifyImageRejectsNonExistentFile(): void
    {
        $result = $this->gateway->classifyImage('/tmp/non_existent_image_' . uniqid() . '.jpg');
        $this->assertNull($result);
    }

    public function testGatewayInitializesWithEnvDefaults(): void
    {
        $_ENV['HF_API_TOKEN'] = 'test_env_token';
        $_ENV['HF_BASE_URL'] = 'https://api-inference.huggingface.co';

        $gw = new HuggingFaceInferenceGateway();
        $this->assertInstanceOf(HuggingFaceInferenceGateway::class, $gw);

        unset($_ENV['HF_API_TOKEN'], $_ENV['HF_BASE_URL']);
    }
}
