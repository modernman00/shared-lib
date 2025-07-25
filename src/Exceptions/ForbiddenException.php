<?php

declare(strict_types=1);

namespace Src\Exceptions;

class ForbiddenException extends HttpException
{
    public function __construct(string $message = 'Forbidden')
    {
        parent::__construct($message, 403);
    }
}
