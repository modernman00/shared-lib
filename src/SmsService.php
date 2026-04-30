<?php

namespace Src;
// use Src\SmsProviderInterface;
use Src\SmsResult;

class SmsService
{
    /** @var SmsProviderInterface[] */
    private array $providers = [];

    public function __construct(array $providers)
    {
        $this->providers = $providers;
    }

    public function send(string $to, string $message): SmsResult
    {
        foreach ($this->providers as $provider) {
            $result = $provider->send($to, $message);

            if ($result->success) {
                return $result;
            }

            // optional: log failure here
        }

        return new SmsResult(false, null, null, 'All providers failed');
    }
}