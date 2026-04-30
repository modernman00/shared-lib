<?php 

use Src\SmsProviderInterface;
use Src\SmsResult;
use Twilio\Rest\Client;

class TwilioProvider implements SmsProviderInterface
{
    private Client $client;
    private string $from;

    public function __construct(string $sid, string $token, string $from)
    {
        $this->client = new Client($sid, $token);
        $this->from = $from;
    }

    public function send(string $to, string $message, array $options = []): SmsResult
    {
        try {
            $msg = $this->client->messages->create($to, [
                'from' => $this->from,
                'body' => $message
            ]);

            return new SmsResult(true, 'twilio', $msg->sid);
        } catch (\Throwable $e) {
            return new SmsResult(false, 'twilio', null, $e->getMessage());
        }
    }
}
