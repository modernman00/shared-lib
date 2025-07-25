<?php

declare(strict_types=1);

namespace Src\Exceptions;

class UnauthorisedException extends HttpException
{
    public function __construct(string $message = 'Unauthorized')
    {
        parent::__construct($message, 401);
    }
}
