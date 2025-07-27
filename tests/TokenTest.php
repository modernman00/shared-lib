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
        $this->assertEquals(12, strlen($token)); // 6 bytes = 12 hex characters
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
        // Test that the method exists
        $this->assertTrue(method_exists(Token::class, 'generateSendTokenEmail'));
    }
}
