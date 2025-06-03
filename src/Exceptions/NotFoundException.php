<?php

namespace Src\Exceptions;

class NotFoundException extends HttpException
{
  public function __construct(string $message = "Not Found")
  {
    parent::__construct($message, 404);
  }
}
