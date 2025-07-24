<?php

namespace Src\Exceptions;

use Exception;


class CaptchaVerificationException extends HttpException
{
  public function __construct(string $message = 'reCAPTCHA verification failed.')
  {
    parent::__construct($message, 400); // 400 = Bad Request
  }
}

/**
 * 🚨 reCAPTCHA Exception Family
 * 
 * Like special alarm bells that ring differently 
 * depending on what went wrong
 */

/**
 * Grandpa of all reCAPTCHA exceptions
 * (Used when we don't need to be specific)
 */
class RecaptchaException extends Exception {
    public function __construct(string $message = "CAPTCHA verification problem", int $code = 400) {
        parent::__construct($message, $code);
    }
}

/**
 * When users mess up (they can try again)
 * - Forgot to check the box
 * - Expired token
 * - Incorrect solution
 */
class RecaptchaFailedException extends RecaptchaException {
    public function __construct(string $message = "CAPTCHA verification failed") {
        parent::__construct($message, 400); // HTTP 400 Bad Request
    }
}

/**
 * When we catch suspicious behavior
 * - Action mismatch
 * - Low score
 * - Potential bot activity
 */
class RecaptchaCheatingException extends RecaptchaException {
    public function __construct(string $message = "Suspicious activity detected") {
        parent::__construct($message, 403); // HTTP 403 Forbidden
    }
}

/**
 * When our system has problems
 * - Missing API key
 * - Google service down
 * - Network issues
 */
class RecaptchaBrokenException extends RecaptchaException {
    public function __construct(string $message = "CAPTCHA service unavailable") {
        parent::__construct($message, 503); // HTTP 503 Service Unavailable
    }
}

/* Example Usage in the Recaptcha class:

if (empty($token)) {
    throw new RecaptchaFailedException("Missing CAPTCHA token");
}

if ($score < 0.5) {
    throw new RecaptchaCheatingException("Score too low: $score");
}

if (empty($secret)) {
    throw new RecaptchaBrokenException("Missing API secret");
}
*/