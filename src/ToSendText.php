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
  try {
     if ($provider == 'twilio') {
         // Implement Twilio sending logic here
     }
     
     if ($provider == 'textlocal') {
      // remove the + from the phone number if it exists
      $to = str_replace('+', '', $to);
         $textlocal = new Textlocal($_ENV['TEXTLOCAL_USERNAME'], $_ENV['TEXTLOCAL_HASH'], $_ENV['TEXTLOCAL_APIKEY']);
         return $textlocal->sendSms($to, $message, $sender);
     }

 }catch (\Throwable $th) {
      // Log the error or handle it as needed
      \showError($th);
  }
}
}