<?php

declare(strict_types=1);

namespace Src;

use Exception;

class TwilioSms
{
 public static function send(string $to, string $message): bool
 {
  $sid = $_ENV['TWILIO_SID'] ?? '';
  $token = $_ENV['TWILIO_TOKEN'] ?? '';
  $from = $_ENV['TWILIO_FROM'] ?? '';

  if ($sid === '' || $token === '' || $from === '') {
   throw new Exception('Twilio credentials missing in .env (TWILIO_SID/TWILIO_TOKEN/TWILIO_FROM).');
  }

  // Basic normalisation: allow only + and digits
  $to = preg_replace('/[^\d\+]/', '', $to);

  $url = "https://api.twilio.com/2010-04-01/Accounts/{$sid}/Messages.json";

  $postFields = http_build_query([
   'From' => $from,
   'To' => $to,
   'Body' => $message
  ]);

  $ch = curl_init($url);
  curl_setopt_array($ch, [
   CURLOPT_POST => true,
   CURLOPT_POSTFIELDS => $postFields,
   CURLOPT_RETURNTRANSFER => true,
   CURLOPT_USERPWD => $sid . ':' . $token,
   CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
   CURLOPT_HTTPHEADER => [
    'Content-Type: application/x-www-form-urlencoded'
   ],
   CURLOPT_TIMEOUT => 20,
  ]);

  $response = curl_exec($ch);
  $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $err = curl_error($ch);
  curl_close($ch);

  if ($response === false) {
   throw new Exception("Twilio SMS failed: {$err}");
  }

  if ($httpCode < 200 || $httpCode >= 300) {
   throw new Exception("Twilio SMS failed: HTTP {$httpCode} - {$response}");
  }

  return true;
 }
}
