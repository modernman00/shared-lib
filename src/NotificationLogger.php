<?php

declare(strict_types=1);

namespace Src;

use Src\functionality\SubmitPostData;

class NotificationLogger
{
 public static function log(mixed $eventId, mixed $inviteeId, string $channel, string $status, ?string $message = null, ?string $providerResponse = null): void
 {
  $_POST = [
   'event_id' => $eventId,
   'invitee_id' => $inviteeId,
   'channel' => $channel,
   'status' => $status,
   'message' => $message,
   'provider_response' => $providerResponse
  ];

  // don’t throw if logging fails; logging must never break the core flow
  try {
   SubmitPostData::submitToOneTablenImage('notification_logs',newInput: $_POST);
  } catch (\Throwable $e) {
   // swallow
  }
 }
}
