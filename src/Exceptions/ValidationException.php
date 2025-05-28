<?php

namespace App\shared\Exceptions;



class ValidationException extends HttpException
{
  public function __construct(string $message = "Bad Request")
  {
    parent::__construct($message, 422);
  }
}
