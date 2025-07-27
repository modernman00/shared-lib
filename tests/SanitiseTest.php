<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Src\Sanitise\Sanitise;
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
        $sanitise = new Sanitise([
            'email' => 'test@example.com',
            'token' => 'valid_token'
        ]);

        $result = $sanitise->validateCsrfToken('valid_token');
        $this->assertInstanceOf(Sanitise::class, $result);
    }

    public function testInvalidCsrfToken()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid or missing CSRF token');

        $sanitise = new Sanitise([
            'email' => 'test@example.com',
            'token' => 'invalid_token'
        ]);

        $sanitise->validateCsrfToken('valid_token');
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
    }

    public function testGetCleanDataWithInvalidEmail()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Validation failed');

        $sanitise = new Sanitise([
            'email' => 'invalid-email'
        ]);

        $sanitise->getCleanData();
    }

    public function testGetCleanDataWithPasswordMismatch()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Validation failed');

        $sanitise = new Sanitise([
            'password' => 'password123',
            'confirm_password' => 'different_password'
        ]);

        $sanitise->getCleanData();
    }

    public function testGetCleanDataWithMatchingPasswords()
    {
        $sanitise = new Sanitise([
            'email' => 'test@example.com',
            'password' => 'password123',
            'confirm_password' => 'password123'
        ]);

        $cleanData = $sanitise->getCleanData();
        
        $this->assertEquals('test@example.com', $cleanData['email']);
        $this->assertTrue(password_verify('password123', $cleanData['password']));
        $this->assertArrayNotHasKey('confirm_password', $cleanData);
    }

    public function testGetCleanDataWithEmptyFields()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Validation failed');

        $sanitise = new Sanitise([
            'email' => '',
            'name' => 'John'
        ]);

        $sanitise->getCleanData();
    }

    public function testGetCleanDataWithLengthConstraints()
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Validation failed');

        $sanitise = new Sanitise(
            ['email' => 'test@example.com'],
            [
                'data' => ['email'],
                'min' => [20], // email is too short
                'max' => [50]
            ]
        );

        $sanitise->getCleanData();
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
}
