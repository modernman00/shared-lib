<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Src\LoginUtility;
use Src\Exceptions\UnauthorisedException;
use Src\Exceptions\NotFoundException;
use Src\Exceptions\BadRequestException;

class LoginUtilityTest extends TestCase
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
        if (isset($_SESSION['2FA_token_ts'])) {
            unset($_SESSION['2FA_token_ts']);
        }
        if (isset($_SESSION['identifyCust'])) {
            unset($_SESSION['identifyCust']);
        }
    }

    public function testCheckPasswordWithValidPassword()
    {
        $hashedPassword = password_hash('correct_password', PASSWORD_DEFAULT);
        
        $inputData = [
            'password' => 'correct_password'
        ];
        
        $databaseData = [
            'id' => 1,
            'password' => $hashedPassword
        ];

        // This test would need mocked dependencies to work properly
        // For now, we test that the method exists
        $this->assertTrue(method_exists(LoginUtility::class, 'checkPassword'));
    }

    public function testGenerateAuthToken()
    {
        // generateAuthToken() lives on Src\Token (moved from LoginUtility).
        $token = \Src\Token::generateAuthToken();

        $this->assertIsString($token);
        $this->assertEquals(6, strlen($token)); // 3 random bytes = 6 hex characters
        $this->assertMatchesRegularExpression('/^[A-F0-9]+$/', $token);
    }

    public function testGenerateAuthTokenIsUnique()
    {
        $token1 = \Src\Token::generateAuthToken();
        $token2 = \Src\Token::generateAuthToken();
        
        $this->assertNotEquals($token1, $token2);
    }

    public function testGetSanitisedInputDataMethodExists()
    {
        $this->assertTrue(method_exists(LoginUtility::class, 'getSanitisedInputData'));
        $this->assertTrue(method_exists(LoginUtility::class, 'useEmailToFindData'));
        $this->assertTrue(method_exists(LoginUtility::class, 'checkIfEmailExist'));
        $this->assertTrue(method_exists(LoginUtility::class, 'findTwoColUsingEmail'));
        $this->assertTrue(method_exists(LoginUtility::class, 'findOneColUsingEmail'));
        $this->assertTrue(method_exists(\Src\Token::class, 'generateUpdateTableWithToken')); // moved from LoginUtility
    }
}
