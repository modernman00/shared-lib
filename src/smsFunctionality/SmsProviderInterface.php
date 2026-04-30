<?php 

namespace Src\smsFunctionality;

use Src\smsFunctionality\SmsResult;

interface SmsProviderInterface
{
    public function send(string $to, string $message, array $options = []): SmsResult;
}