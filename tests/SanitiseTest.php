<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Src\sanitise\Sanitise;
use Src\Exceptions\InvalidArgumentException;

class SanitiseTest extends TestCase
{
    public function testConstructorThrowsExceptionForEmptyData()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Form data cannot be empty');
        
        new Sanitise([]);
    }

    public function testConstructorThrowsExceptionForInvalidDataLength()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid dataLength structure');
        
        new Sanitise(['email' => 'test@example.com'], [
            'data' => ['email'],
            'min' => [5, 10], // mismatched array lengths
            'max' => [50]
        ]);
    }

    public function testValidCsrfToken()
    {
        $_SESSION['token'] = 'valid_token';
        try {
            $clean = (new Sanitise(['email' => 'test@example.com', 'token' => 'valid_token']))->getCleanData();
            $this->assertSame('test@example.com', $clean['email']);
        } finally {
            unset($_SESSION['token']);
        }
    }

    public function testInvalidCsrfToken()
    {
        $_SESSION['token'] = 'valid_token';
        $this->expectException(\Src\Exceptions\UnauthorisedException::class);
        try {
            (new Sanitise(['email' => 'test@example.com', 'token' => 'invalid_token']))->getCleanData();
        } finally {
            unset($_SESSION['token']);
        }
    }

    public function testGetCleanDataWithValidInput()
    {
        $sanitise = new Sanitise([
            'email' => 'test@example.com',
            'name' => '  John Doe  '
        ]);

        $cleanData = $sanitise->getCleanData();

        $this->assertEquals('test@example.com', $cleanData['email']);
        $this->assertEquals('John Doe', $cleanData['name']);
        $this->assertSame([], $sanitise->getErrors());
    }

    // Validation problems are collected, not thrown — callers check getErrors().
    public function testGetCleanDataWithInvalidEmail()
    {
        $sanitise = new Sanitise(['email' => 'invalid-email']);
        $sanitise->getCleanData();
        $this->assertContains('Invalid email format', $sanitise->getErrors());
    }

    public function testGetCleanDataWithPasswordMismatch()
    {
        $sanitise = new Sanitise(['password' => 'password123', 'confirm_password' => 'different_password']);
        $sanitise->getCleanData();
        $this->assertContains('Passwords do not match', $sanitise->getErrors());
    }

    public function testGetCleanDataWithMatchingPasswords()
    {
        $sanitise = new Sanitise([
            'email' => 'test@example.com',
            'password' => 'password123',
            'confirm_password' => 'password123'
        ]);

        $cleanData = $sanitise->getCleanData();

        $this->assertSame([], $sanitise->getErrors());
        $this->assertEquals('test@example.com', $cleanData['email']);
        // Passwords are trimmed but not hashed here; hashing happens later in hashPasswordsInArray().
        $this->assertSame('password123', $cleanData['password']);
    }

    public function testGetCleanDataWithEmptyFields()
    {
        $sanitise = new Sanitise(['email' => '', 'name' => 'John']);
        $sanitise->getCleanData();
        $this->assertContains('The EMAIL field is required', $sanitise->getErrors());
    }

    public function testGetCleanDataWithLengthConstraints()
    {
        $sanitise = new Sanitise(
            ['email' => 'test@example.com'],
            ['data' => ['email'], 'min' => [20], 'max' => [50]]
        );
        $sanitise->getCleanData();
        $this->assertContains('Field EMAIL is below minimum length', $sanitise->getErrors());
    }

    public function testGetErrors()
    {
        $sanitise = new Sanitise([
            'email' => 'invalid-email'
        ]);

        try {
            $sanitise->getCleanData();
        } catch (RuntimeException $e) {
            // Expected exception
        }

        $errors = $sanitise->getErrors();
        $this->assertContains('Invalid email format', $errors);
    }

    public function testSanitizeHtmlContent()
    {
        $sanitise = new Sanitise([
            'name' => '<script>alert("xss")</script>John'
        ]);

        $cleanData = $sanitise->getCleanData();
        $this->assertStringNotContainsString('<script>', $cleanData['name']);
        $this->assertStringContainsString('John', $cleanData['name']);
    }

    public function testEmptyHoneypotFieldsDoNotTriggerRequiredFieldError(): void
    {
        $sanitise = new Sanitise([
            'name' => 'Wally Doe',
            'email' => 'wally@example.com',
            'website_hp' => '',
            'hp_username' => '',
        ]);

        $cleanData = $sanitise->getCleanData();
        $this->assertEmpty($sanitise->errors);
        $this->assertEquals('Wally Doe', $cleanData['name']);
        $this->assertEquals('wally@example.com', $cleanData['email']);
    }
}
