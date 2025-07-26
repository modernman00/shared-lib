<?php

declare(strict_types=1);

namespace Src\Exceptions;

class CaptchaVerificationException extends HttpException
{
    public function __construct(string $message = 'reCAPTCHA verification failed.')
    {
        parent::__construct($message, 400); // 400 = Bad Request
    }
}
