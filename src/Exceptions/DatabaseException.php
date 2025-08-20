<?php

declare(strict_types=1);

namespace Src\Exceptions;

class DatabaseException extends \Exception
{
    public function __construct(string $message = 'Database Error')
    {
        parent::__construct($message, 500);
    }
}
