<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Src\Recaptcha;
use Src\Exceptions\RecaptchaFailedException;
use Src\Exceptions\RecaptchaBrokenException;

/**
 * Recaptcha guard-path tests. No network calls: every case fails before Google is contacted.
 * verifyCaptcha() / verifyCaptchaEnterprise() take the request input array (the old
 * string-action signature was removed), and globals are restored after each test.
 */
class RecaptchaTest extends TestCase
{
    private array $env;
    private array $server;

    protected function setUp(): void
    {
        $this->env = $_ENV;
        $this->server = $_SERVER;
        $_ENV = ['APP_ENV' => 'production'];
        $_SERVER = ['REMOTE_ADDR' => '127.0.0.1', 'HTTP_HOST' => 'example.com'];
    }

    protected function tearDown(): void
    {
        $_ENV = $this->env;
        $_SERVER = $this->server;
    }

    public function testVerifyCaptchaThrowsExceptionWhenTokenMissing(): void
    {
        $this->expectException(RecaptchaFailedException::class);
        $this->expectExceptionMessage("🚨 Oops! Forgot the 'I'm not a robot' box!");
        Recaptcha::verifyCaptcha([]);
    }

    public function testVerifyCaptchaThrowsExceptionWhenSecretMissing(): void
    {
        $this->expectException(RecaptchaBrokenException::class);
        $this->expectExceptionMessage('🔐 Our guard is asleep! Tell the admin!');
        Recaptcha::verifyCaptcha(['g-recaptcha-response' => 'test-token']);
    }

    public function testVerifyCaptchaThrowsExceptionWhenSecretFormatInvalid(): void
    {
        $_ENV['SECRET_RECAPTCHA_KEY'] = 'invalid-secret-key';
        $_ENV['SECRET_RECAPTCHA_KEY_TWO_START_LETTER'] = '6L';

        $this->expectException(RecaptchaBrokenException::class);
        $this->expectExceptionMessage('Invalid reCAPTCHA secret key format');
        Recaptcha::verifyCaptcha(['g-recaptcha-response' => 'test-token']);
    }

    public function testEnterpriseThrowsWhenSiteKeyMissing(): void
    {
        $this->expectException(RecaptchaFailedException::class);
        $this->expectExceptionMessage('Missing reCAPTCHA SiteKey');
        Recaptcha::verifyCaptchaEnterprise(['action' => 'LOGIN'], 'LOGIN');
    }

    public function testEnterpriseThrowsWhenConfigurationMissing(): void
    {
        $this->expectException(RecaptchaBrokenException::class);
        $this->expectExceptionMessage('Missing reCAPTCHA Enterprise configuration');
        Recaptcha::verifyCaptchaEnterprise(['siteKey' => 'client-token'], 'LOGIN');
    }

    public function testEnterpriseIsSkippedInLocalDevelopment(): void
    {
        $_ENV['APP_ENV'] = 'local';
        $this->assertTrue(Recaptcha::verifyCaptchaEnterprise([], 'LOGIN'));
    }

    public function testVerifyCaptchaMethodExists(): void
    {
        $this->assertTrue(method_exists(Recaptcha::class, 'verifyCaptcha'));
    }
}
