<?php

declare(strict_types=1);

namespace Src\Vision;

/** Network failure or an HTTP error from the provider (rate limit, outage, rejected request). */
final class VisionRequestFailedException extends VisionException
{
    public function __construct(string $message, private readonly int $httpStatus = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $httpStatus, $previous);
    }

    /** 0 when there was no HTTP response (connection failure). */
    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
