<?php

declare(strict_types=1);

use Src\Exceptions\RecaptchaException;

/**
 * When we catch suspicious behavior
 * - Action mismatch
 * - Low score
 * - Potential bot activity.
 */
class RecaptchaCheatingException extends RecaptchaException
{
    public function __construct(string $message = 'Suspicious activity detected')
    {
        parent::__construct($message, 403); // HTTP 403 Forbidden
    }
}
