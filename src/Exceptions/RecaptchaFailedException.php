<?php

declare(strict_types=1);

namespace Src\Exceptions;

/**
 * When users mess up (they can try again)
 * - Forgot to check the box
 * - Expired token
 * - Incorrect solution.
 */
class RecaptchaFailedException extends RecaptchaException
{
    public function __construct(string $message = 'CAPTCHA verification failed')
    {
        parent::__construct($message, 400); // HTTP 400 Bad Request
    }
}
