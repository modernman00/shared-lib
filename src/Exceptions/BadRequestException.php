<?php

declare(strict_types=1);

namespace Src\Exceptions;

class BadRequestException extends HttpException
{
    public function __construct(string $message = 'Bad Request')
    {
        parent::__construct($message, 400);
    }
}
