<?php

declare(strict_types=1);

namespace Tests\Unit\Functionality;

use Src\functionality\SignIn;
use Src\functionality\middleware\RoleMiddleware;
use Src\Exceptions\UnauthorisedException;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the SignIn class.
 */
class SignInTest extends TestCase
{
    private MockInterface $roleMiddlewareMock;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize mocks
        $this->roleMiddlewareMock = Mockery::mock(RoleMiddleware::class);

        // Set up environment variables
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['LOGGER_NAME'] = 'app';
        $_ENV['LOGGER_PATH'] = '/../../bootstrap/log/idecide.log';

        // Set up server and session
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_ORIGIN'] = 'http://idecide.test';
        $_SESSION = [];
        $_COOKIE = [];
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_SERVER = [];
        $_COOKIE = [];
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Tests verify with valid role and authentication.
     */
    public function testVerifyWithValidRoleReturnsUserData(): void
    {
        // Arrange
        $role = 'users';
        $userData = ['id' => 1, 'email' => 'test@example.com', 'role' => 'users'];

        $this->roleMiddlewareMock->shouldReceive('__construct')
            ->once()
            ->with([$role]);
        $this->roleMiddlewareMock->shouldReceive('handle')
            ->once()
            ->andReturn($userData);

        // Act
        $result = SignIn::verify($role);

        // Assert
        $this->assertEquals($userData, $result);
    }

    /**
     * Tests verify with unauthorized access.
     */
    public function testVerifyThrowsUnauthorisedException(): void
    {
        // Arrange
        $role = 'admin';

        $this->roleMiddlewareMock->shouldReceive('__construct')
            ->once()
            ->with([$role]);
        $this->roleMiddlewareMock->shouldReceive('handle')
            ->once()
            ->andThrow(new UnauthorisedException('Invalid role'));

        // Act
        $result = SignIn::verify($role);

        // Assert
        $this->assertEquals([], $result);
    }
}