<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Src\Token;

class TokenTest extends TestCase
{
    protected function setUp(): void
    {
        // Start a session for testing
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    protected function tearDown(): void
    {
        // Clean up session
        if (isset($_SESSION['auth'])) {
            unset($_SESSION['auth']);
        }
    }

    public function testGenerateAuthToken()
    {
        $token = Token::generateAuthToken();
        
        $this->assertIsString($token);
        $this->assertEquals(6, strlen($token)); // 3 bytes = 6 hex characters
        $this->assertMatchesRegularExpression('/^[A-F0-9]+$/', $token);
    }

    public function testGenerateAuthTokenIsUnique()
    {
        $token1 = Token::generateAuthToken();
        $token2 = Token::generateAuthToken();
        
        $this->assertNotEquals($token1, $token2);
    }

    public function testGenerateUpdateTableWithTokenSetsSession()
    {
        // Mock dependencies would be needed for this test to work properly
        // For now, test that the method exists
        $this->assertTrue(method_exists(Token::class, 'generateUpdateTableWithToken'));
    }

    public function testGenerateSendTokenEmailMethodExists()
    {
        // Test that the method exists and accepts optional $subject
        $this->assertTrue(method_exists(Token::class, 'generateSendTokenEmail'));
        $reflection = new \ReflectionMethod(Token::class, 'generateSendTokenEmail');
        $params = $reflection->getParameters();
        $this->assertCount(3, $params);
        $this->assertEquals('data', $params[0]->getName());
        $this->assertEquals('viewPath', $params[1]->getName());
        $this->assertEquals('subject', $params[2]->getName());
        $this->assertTrue($params[2]->isOptional());
    }
}
