<?php 

use MessageBird\Client as MBClient;
use MessageBird\Objects\Message;
use Src\SmsProviderInterface;
use Src\SmsResult;

class MessageBirdProvider implements SmsProviderInterface
{
    private MBClient $client;
    private string $from;

    public function __construct(string $apiKey, string $from)
    {
        $this->client = new MBClient($apiKey);
        $this->from = $from;
    }

    public function send(string $to, string $message, array $options = []): SmsResult
    {
        try {
            $msg = new Message();
            $msg->originator = $this->from;
            $msg->recipients = [$to];
            $msg->body = $message;

            $response = $this->client->messages->create($msg);

            return new SmsResult(true, 'messagebird', $response->id);
        } catch (\Throwable $e) {
            return new SmsResult(false, 'messagebird', null, $e->getMessage());
        }
    }
}