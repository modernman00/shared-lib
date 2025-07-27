<?php 

namespace Src\Exceptions;

class DatabaseException extends \Exception
{
    public function __construct($message = 'Database error', $code = 500)
    {
        parent::__construct($message, $code);
    }
}
    