<?php

namespace Src\Exceptions;

class InvalidArgumentException extends HttpException
{
  public function __construct(string $message = "Not Found")
  {
    parent::__construct($message, 400);
  }
}
