<?php

declare(strict_types=1);

namespace Src\Exceptions;

use Src\Exceptions\RecaptchaException;

/**
 * When our system has problems
 * - Missing API key
 * - Google service down
 * - Network issues.
 */
class RecaptchaBrokenException extends RecaptchaException
{
    public function __construct(string $message = 'CAPTCHA service unavailable')
    {
        parent::__construct($message, 503); // HTTP 503 Service Unavailable
    }
}
