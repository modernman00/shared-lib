<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Src\Sanitise\Validate;

class ValidateTest extends TestCase
{
    protected function setUp(): void
    {
        // Clear POST data before each test
        $_POST = [];
    }

    protected function tearDown(): void
    {
        // Clean up POST data after each test
        $_POST = [];
    }

    public function testCleanArrayWithValidData()
    {
        $_POST = [
            'email' => 'test@example.com',
            'password' => 'password123'
        ];

        $validationArray = [
            'login' => ['email', 'password']
        ];

        $result = Validate::cleanArray($validationArray);
        $this->assertEquals('', $result);
    }

    public function testCleanArrayWithMissingFields()
    {
        $_POST = [
            'email' => 'test@example.com'
            // password is missing
        ];

        $validationArray = [
            'login' => ['email', 'password']
        ];

        $result = Validate::cleanArray($validationArray);
        $this->assertStringContainsString('PASSWORD is required', $result);
        $this->assertStringContainsString('<br>', $result);
    }

    public function testCleanArrayWithEmptyFields()
    {
        $_POST = [
            'email' => '',
            'password' => '   ' // whitespace only
        ];

        $validationArray = [
            'login' => ['email', 'password']
        ];

        $result = Validate::cleanArray($validationArray);
        $this->assertStringContainsString('EMAIL is required', $result);
        $this->assertStringContainsString('PASSWORD is required', $result);
    }

    public function testCleanArrayWithPasswordMismatch()
    {
        $_POST = [
            'password' => 'password123',
            'confirm_password' => 'different_password'
        ];

        $validationArray = [
            'createAccount' => ['password', 'confirm_password']
        ];

        $result = Validate::cleanArray($validationArray);
        $this->assertStringContainsString('Your passwords do not match', $result);
    }

    public function testCleanArrayWithMatchingPasswords()
    {
        $_POST = [
            'password' => 'password123',
            'confirm_password' => 'password123'
        ];

        $validationArray = [
            'createAccount' => ['password', 'confirm_password']
        ];

        $result = Validate::cleanArray($validationArray);
        $this->assertEquals('', $result);
    }

    public function testCleanArrayFieldNameCleaning()
    {
        $_POST = [];

        $validationArray = [
            'test' => ['user_name', 'email@address']
        ];

        $result = Validate::cleanArray($validationArray);
        $this->assertStringContainsString('USER NAME is required', $result);
        $this->assertStringContainsString('EMAIL@ADDRESS is required', $result);
    }

    public function testCleanArrayWithNonArrayGroup()
    {
        $_POST = [];

        $validationArray = [
            'stringValue' => 'not_an_array',
            'validGroup' => ['email']
        ];

        $result = Validate::cleanArray($validationArray);
        $this->assertStringContainsString('EMAIL is required', $result);
        // Should skip 'stringValue' since it's not an array
    }
}
