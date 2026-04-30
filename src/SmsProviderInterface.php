<?php 

namespace Src;

use Src\SmsResult;

interface SmsProviderInterface
{
    public function send(string $to, string $message, array $options = []): SmsResult;
}