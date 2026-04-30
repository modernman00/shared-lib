<?php

namespace Src\smsFunctionality;

class SmsResult
{
    public bool $success;
    public ?string $provider;
    public ?string $messageId;
    public ?string $error;

    public function __construct(bool $success, ?string $provider = null, ?string $messageId = null, ?string $error = null)
    {
        $this->success = $success;
        $this->provider = $provider;
        $this->messageId = $messageId;
        $this->error = $error;
    }
}