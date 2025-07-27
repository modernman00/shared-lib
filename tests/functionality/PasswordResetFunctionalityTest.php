<?php

use PHPUnit\Framework\TestCase;
use Mockery as m;
use Src\functionality\PasswordResetFunctionality;

class PasswordResetFunctionalityTest extends TestCase
{
    protected function tearDown(): void
    {
        m::close();
    }

    public function testProcessRequestExecutesFullPasswordResetFlow()
    {
        // Arrange
        $post = ['email' => 'user@example.com', 'password' => 'NewPassword123'];
        $session = ['token' => 'some-valid-token'];
        $table = 'users';
        $redirectPath = '/login';

        // Expected clean data
        $cleanData = [
            'email' => 'user@example.com',
            'password' => 'NewPassword123',
        ];

        // --- Mock CheckSanitise
        $checkSanitise = m::mock('alias:Src\Sanitise\CheckSanitise');
        $checkSanitise->shouldReceive('getSanitisedInputData')
            ->once()
            ->with($post)
            ->andReturn($cleanData);

        // --- Mock Utility
        $utility = m::mock('alias:Src\Utility');
        $utility->shouldReceive('checkInputEmail')
            ->once()
            ->with('user@example.com')
            ->andReturn('user@example.com');

        // --- Mock CheckToken
        $checkToken = m::mock('alias:Src\CheckToken');
        $checkToken->shouldReceive('tokenCheck')
            ->once()
            ->with('token');

        // --- Mock Update class instance
        $mockUpdate = m::mock('Src\Update');
        $mockUpdate->shouldReceive('updateTable')
            ->once()
            ->with('password', 'NewPassword123', $table, 'email', 'user@example.com');

        // Use Mockery to override the `new Update()` call
        // We need to bind it somehow (could require refactoring to allow DI)
        // For this test, assume Update is replaced via test double:
        m::mock('overload:Src\Update', $mockUpdate);

        // --- Mock redirect (global function)
        if (!function_exists('Src\functionality\redirect')) {
            eval('namespace Src\functionality; function redirect($path) { \Src\functionality\PasswordResetFunctionalityTest::$redirected = $path; }');
        }
        PasswordResetFunctionalityTest::$redirected = null;

        // Act
        PasswordResetFunctionality::processRequest($post, $table, $session, $redirectPath);

        // Assert
        $this->assertSame([], $session, 'Session should be cleared.');
        $this->assertEquals($redirectPath, PasswordResetFunctionalityTest::$redirected);
    }

    public static $redirected;
}
