<?php

namespace App\shared\Exceptions;

class UnauthorisedException extends HttpException
{
  public function __construct(string $message = "Unauthorized")
  {
    parent::__construct($message, 401);
  }
}
