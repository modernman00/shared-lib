<?php

declare(strict_types=1);

namespace Src\Exceptions;

class TooManyRequestsException extends HttpException
{
    /*************  ✨ Windsurf Command ⭐  *************/
    /**
     * Thrown when the client sends too many requests in a given amount of time.
     *
     * @param string $message
     */
    /*******  cdd7f967-33e6-4f97-8dd4-f4796ff5fe6b  *******/
    public function __construct(string $message = 'Bad Request')
    {
        parent::__construct($message, 429);
    }
}
