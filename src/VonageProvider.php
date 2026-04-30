<?php 

use Vonage\Client;
use Vonage\Client\Credentials\Basic;
use Vonage\SMS\Message\SMS;
use Src\SmsProviderInterface;
use Src\SmsResult;

class VonageProvider implements SmsProviderInterface
{
    private Client $client;
    private string $from;

    public function __construct(string $key, string $secret, string $from)
    {
        $this->client = new Client(new Basic($key, $secret));
        $this->from = $from;
    }

    public function send(string $to, string $message, array $options = []): SmsResult
    {
        try {
            $response = $this->client->sms()->send(
                new SMS($to, $this->from, $message)
            );

            $msg = $response->current();

            if ($msg->getStatus() == 0) {
                return new SmsResult(true, 'vonage', $msg->getMessageId());
            }

            return new SmsResult(false, 'vonage', null, $msg->getErrorText());
        } catch (\Throwable $e) {
            return new SmsResult(false, 'vonage', null, $e->getMessage());
        }
    }
}