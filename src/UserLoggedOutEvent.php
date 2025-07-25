<?php

declare(strict_types=1);

namespace Src; // A common namespace for events

use Symfony\Contracts\EventDispatcher\Event;

class UserLoggedOutEvent extends Event
{
    public const NAME = 'user.logged_out';

    private int $userId;
    private string $sessionId;

    public function __construct(int $userId, string $sessionId)
    {
        $this->userId = $userId;
        $this->sessionId = $sessionId;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function getSessionId(): string
    {
        return $this->sessionId;
    }
}