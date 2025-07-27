<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Src\Exceptions\HttpException;
use Src\Exceptions\BadRequestException;
use Src\Exceptions\CaptchaVerificationException;
use Src\Exceptions\DatabaseException;
use Src\Exceptions\ForbiddenException;
use Src\Exceptions\InvalidArgumentException;
use Src\Exceptions\MethodNotAllowedException;
use Src\Exceptions\NotFoundException;
use Src\Exceptions\RecaptchaBrokenException;
use Src\Exceptions\RecaptchaCheatingException;
use Src\Exceptions\RecaptchaException;
use Src\Exceptions\RecaptchaFailedException;
use Src\Exceptions\TooManyLoginAttemptsException;
use Src\Exceptions\TooManyRequestsException;
use Src\Exceptions\UnauthorisedException;
use Src\Exceptions\ValidationException;

class ExceptionsTest extends TestCase
{
    public function testHttpException()
    {
        $exception = new HttpException('Test message', 404);
        
        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(404, $exception->getStatusCode());
        $this->assertEquals(404, $exception->getCode());
    }

    public function testHttpExceptionDefaultStatusCode()
    {
        $exception = new HttpException('Test message');
        
        $this->assertEquals('Test message', $exception->getMessage());
        $this->assertEquals(500, $exception->getStatusCode());
    }

    public function testBadRequestException()
    {
        $exception = new BadRequestException('Bad request');
        $this->assertInstanceOf(BadRequestException::class, $exception);
        $this->assertEquals('Bad request', $exception->getMessage());
    }

    public function testCaptchaVerificationException()
    {
        $exception = new CaptchaVerificationException('Captcha failed');
        $this->assertInstanceOf(CaptchaVerificationException::class, $exception);
        $this->assertEquals('Captcha failed', $exception->getMessage());
    }

    public function testDatabaseException()
    {
        $exception = new DatabaseException('Database error');
        $this->assertInstanceOf(DatabaseException::class, $exception);
        $this->assertEquals('Database error', $exception->getMessage());
    }

    public function testForbiddenException()
    {
        $exception = new ForbiddenException('Access forbidden');
        $this->assertInstanceOf(ForbiddenException::class, $exception);
        $this->assertEquals('Access forbidden', $exception->getMessage());
    }

    public function testInvalidArgumentException()
    {
        $exception = new InvalidArgumentException('Invalid argument');
        $this->assertInstanceOf(InvalidArgumentException::class, $exception);
        $this->assertEquals('Invalid argument', $exception->getMessage());
    }

    public function testMethodNotAllowedException()
    {
        $exception = new MethodNotAllowedException('Method not allowed');
        $this->assertInstanceOf(MethodNotAllowedException::class, $exception);
        $this->assertEquals('Method not allowed', $exception->getMessage());
    }

    public function testNotFoundException()
    {
        $exception = new NotFoundException('Not found');
        $this->assertInstanceOf(NotFoundException::class, $exception);
        $this->assertEquals('Not found', $exception->getMessage());
    }

    public function testRecaptchaBrokenException()
    {
        $exception = new RecaptchaBrokenException('Recaptcha broken');
        $this->assertInstanceOf(RecaptchaBrokenException::class, $exception);
        $this->assertEquals('Recaptcha broken', $exception->getMessage());
    }

    public function testRecaptchaCheatingException()
    {
        $exception = new RecaptchaCheatingException('Recaptcha cheating');
        $this->assertInstanceOf(RecaptchaCheatingException::class, $exception);
        $this->assertEquals('Recaptcha cheating', $exception->getMessage());
    }

    public function testRecaptchaException()
    {
        $exception = new RecaptchaException('Recaptcha error');
        $this->assertInstanceOf(RecaptchaException::class, $exception);
        $this->assertEquals('Recaptcha error', $exception->getMessage());
    }

    public function testRecaptchaFailedException()
    {
        $exception = new RecaptchaFailedException('Recaptcha failed');
        $this->assertInstanceOf(RecaptchaFailedException::class, $exception);
        $this->assertEquals('Recaptcha failed', $exception->getMessage());
    }

    public function testTooManyLoginAttemptsException()
    {
        $exception = new TooManyLoginAttemptsException('Too many attempts');
        $this->assertInstanceOf(TooManyLoginAttemptsException::class, $exception);
        $this->assertEquals('Too many attempts', $exception->getMessage());
    }

    public function testTooManyRequestsException()
    {
        $exception = new TooManyRequestsException('Too many requests');
        $this->assertInstanceOf(TooManyRequestsException::class, $exception);
        $this->assertEquals('Too many requests', $exception->getMessage());
    }

    public function testUnauthorisedException()
    {
        $exception = new UnauthorisedException('Unauthorised');
        $this->assertInstanceOf(UnauthorisedException::class, $exception);
        $this->assertEquals('Unauthorised', $exception->getMessage());
    }

    public function testValidationException()
    {
        $exception = new ValidationException('Validation error');
        $this->assertInstanceOf(ValidationException::class, $exception);
        $this->assertEquals('Validation error', $exception->getMessage());
    }
}
