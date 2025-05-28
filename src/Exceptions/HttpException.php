<?php

namespace App\shared\Exceptions;

use Exception;

/**Perfect — creating a custom HttpException class makes your code cleaner and allows you to throw exceptions that carry HTTP status codes and optional messages directly. */

class HttpException extends Exception
{
  protected int $statusCode;

  public function __construct(string $message = "", int $statusCode = 500)
  {
    parent::__construct($message, $statusCode);
    $this->statusCode = $statusCode;
  }

  public function getStatusCode(): int
  {
    return $this->statusCode;
  }
}
