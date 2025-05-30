<?php

namespace Src\Exceptions; // Adjust path if needed

class TooManyLoginAttemptsException extends HttpException
{

  public function __construct(string $message = "Too many login attempts. Please try again later.")
  {
    parent::__construct($message, 429);
  }
}
