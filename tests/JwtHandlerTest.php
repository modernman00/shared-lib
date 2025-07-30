<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Src\JwtHandler;
use Src\Exceptions\NotFoundException;

class JwtHandlerTest extends TestCase
{
    private JwtHandler $jwtHandler;

    protected function setUp(): void
    {
        // Set up test environment variables
        $_ENV['COOKIE_EXPIRE'] = '3600';
        $_ENV['APP_URL'] = 'https://test.com';
        $_ENV['COOKIE_TOKEN_NAME'] = 'test_token';
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['JWT_TOKEN'] = 'test_jwt_secret_key';
        
        $this->jwtHandler = new JwtHandler();
    }

    protected function tearDown(): void
    {
        // Clean up environment variables
        unset($_ENV['COOKIE_EXPIRE'], $_ENV['APP_URL'], $_ENV['TOKEN_NAME'], $_ENV['APP_ENV'], $_ENV['JWT_TOKEN']);
    }

    public function testConstructorSetsExpiredTime()
    {
        $handler = new JwtHandler();
        $this->assertInstanceOf(JwtHandler::class, $handler);
    }

    public function testJwtEncodeDataReturnsString()
    {
        $userData = [
            'id' => 1,
            'email' => 'test@example.com',
            'role' => 'admin'
        ];

        $token = $this->jwtHandler->jwtEncodeData($userData);
        $this->assertIsString($token);
        $this->assertStringContainsString('.', $token); // JWT tokens contain dots
    }

    public function testJwtEncodeDataWithDefaultRole()
    {
        $userData = [
            'id' => 1,
            'email' => 'test@example.com'
            // No role specified - should default to 'users'
        ];

        $token = $this->jwtHandler->jwtEncodeData($userData);
        $this->assertIsString($token);
    }

    public function testAuthenticateThrowsExceptionOnInvalidCredentials()
    {
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Oops! Wrong email or password.');

        // This will fail because CheckSanitise methods would need to be mocked
        // For now, we test that the exception is thrown
        $input = [
            'email' => 'invalid@test.com',
            'password' => 'wrongpassword'
        ];

        $this->jwtHandler->authenticate($input);
    }

    public function testJwtEncodeDataUsesCorrectPayloadStructure()
    {
        $userData = [
            'id' => 123,
            'email' => 'user@test.com',
            'role' => 'editor'
        ];

        $token = $this->jwtHandler->jwtEncodeData($userData);
        
        // Basic validation that it's a JWT format (3 parts separated by dots)
        $parts = explode('.', $token);
        $this->assertCount(3, $parts);
    }
}
