<?php

declare(strict_types=1);

namespace Src;

use Src\smsFunctionality\Textlocal;

/**
 * Class ToSendText
 * @package Src
 */

class ToSendText
{

  /**
   * 
   * @param string $to 
   * @param string $message 
   * @param string $provider  - API provider - textlocal or twilio or any other provider you want to add
   * @param string $sender 
   * @return bool 
   */

  public static function send(string $to, string $message, string $provider, string $sender)
  {
    if (\isTestEnv()) {
      $GLOBALS['__testSms'][] = ['to' => $to, 'message' => $message, 'provider' => $provider, 'sender' => $sender];
      return true;
    }
    try {
      if ($provider == 'twilio') {
        // Implement Twilio sending logic here
      }

      if ($provider == 'textlocal') {
        // remove the + from the phone number if it exists
        $to = str_replace('+', '', $to);
        $to = [$to];
        $textlocal = new Textlocal($_ENV['TEXTLOCAL_USERNAME'], $_ENV['TEXTLOCAL_HASH'], $_ENV['TEXTLOCAL_APIKEY']);
        return $textlocal->sendSms($to, $message, $sender);
      }

      if ($provider == 'webex') {
        // Webex Interact SMS API implementation
        $payload = json_encode([
            'from' => $sender,
            'to' => [$to],
            'message_body' => $message
        ]);

        $ch = curl_init('https://api.webexinteract.com/v1/sms');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-AUTH-KEY: ' . ($_ENV['webex_text'] ?? $_ENV['WEBEX_TEXT'] ?? '')
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \Exception("Webex Interact API Error: HTTP $httpCode - $response");
        }
        return json_decode($response, true);
      }
    } catch (\Throwable $th) {
      // Log the error or handle it as needed
      \showError($th);
    }
  }
}
