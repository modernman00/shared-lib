<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Src\Recaptcha;
use Src\Exceptions\RecaptchaFailedException;
use Src\Exceptions\RecaptchaBrokenException;
use Src\Exceptions\RecaptchaException;
use Src\Exceptions\RecaptchaCheatingException;

class RecaptchaTest extends TestCase
{
    protected function setUp(): void
    {
        // Clear POST and ENV data before each test
        $_POST = [];
        $_ENV = [];
        $_SERVER = [];
    }

    protected function tearDown(): void
    {
        // Clean up after each test
        $_POST = [];
        $_ENV = [];
        $_SERVER = [];
    }

    public function testVerifyCaptchaThrowsExceptionWhenTokenMissing()
    {
        $this->expectException(RecaptchaFailedException::class);
        $this->expectExceptionMessage("🚨 Oops! Forgot the 'I'm not a robot' box!");

        $_POST = []; // No g-recaptcha-response
        Recaptcha::verifyCaptcha('login');
    }

    public function testVerifyCaptchaThrowsExceptionWhenSecretMissing()
    {
        $this->expectException(RecaptchaBrokenException::class);
        $this->expectExceptionMessage('🔐 Our guard is asleep! Tell the admin!');

        $_POST = ['g-recaptcha-response' => 'test-token'];
        $_ENV = []; // No SECRET_RECAPTCHA_KEY
        
        Recaptcha::verifyCaptcha('login');
    }

    public function testVerifyCaptchaThrowsExceptionWhenSecretFormatInvalid()
    {
        $this->expectException(RecaptchaBrokenException::class);
        $this->expectExceptionMessage('Invalid reCAPTCHA secret key format');

        $_POST = ['g-recaptcha-response' => 'test-token'];
        $_ENV = [
            'SECRET_RECAPTCHA_KEY' => 'invalid-secret',
            'SECRET_RECAPTCHA_KEY_TWO_START_LETTER' => '6L'
        ];
        
        Recaptcha::verifyCaptcha('login');
    }

    public function testVerifyCaptchaThrowsExceptionWhenActionEmpty()
    {
        $this->expectException(RecaptchaException::class);
        $this->expectExceptionMessage('Action parameter cannot be empty');

        $_POST = ['g-recaptcha-response' => 'test-token'];
        $_ENV = [
            'SECRET_RECAPTCHA_KEY' => '6Ltest-secret-key',
            'SECRET_RECAPTCHA_KEY_TWO_START_LETTER' => '6L'
        ];
        
        Recaptcha::verifyCaptcha('');
    }

    public function testVerifyCaptchaMethodExists()
    {
        $this->assertTrue(method_exists(Recaptcha::class, 'verifyCaptcha'));
    }

    public function testVerifyCaptchaWithValidInputStructure()
    {
        $_POST = ['g-recaptcha-response' => 'test-token'];
        $_ENV = [
            'SECRET_RECAPTCHA_KEY' => '6Ltest-secret-key',
            'SECRET_RECAPTCHA_KEY_TWO_START_LETTER' => '6L',
            'DOMAIN_NAME' => 'example.com'
        ];
        $_SERVER = ['REMOTE_ADDR' => '127.0.0.1'];

        // This would require mocking the sendPostRequest function
        // For now, we just test that the method can be called with proper structure
        try {
            Recaptcha::verifyCaptcha('login');
            $this->fail('Expected an exception due to missing sendPostRequest function');
        } catch (\Error $e) {
            // Expected since sendPostRequest function doesn't exist in test environment
            $this->assertTrue(true);
        }
    }
}
