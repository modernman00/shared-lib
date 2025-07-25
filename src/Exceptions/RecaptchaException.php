<?php

declare(strict_types=1);

use Exception;

/**
 * Grandpa of all reCAPTCHA exceptions
 * (Used when we don't need to be specific).
 */
class RecaptchaException extends Exception
{
    public function __construct(string $message = 'CAPTCHA verification problem', int $code = 400)
    {
        parent::__construct($message, $code);
    }
}
