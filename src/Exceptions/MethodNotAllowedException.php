<?php

namespace Src\Exceptions;

class MethodNotAllowedException extends HttpException
{
  public function __construct(string $message = "Not Found")
  {
    parent::__construct($message, 405);
  }
}
