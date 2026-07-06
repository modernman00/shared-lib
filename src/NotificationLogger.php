<?php

declare(strict_types=1);

namespace Src;

use Src\functionality\SubmitPostData;

class NotificationLogger
{
 public static function log(mixed $eventId, mixed $inviteeId, string $channel, string $status, ?string $message = null, ?string $providerResponse = null, string $category = 'general'): void
 {
  $_POST = [
   'event_id' => $eventId,
   'invitee_id' => $inviteeId,
   'channel' => $channel,
   'status' => $status,
   'category' => $category,
   'message' => $message,
   'provider_response' => $providerResponse
  ];

  // don’t throw if logging fails; logging must never break the core flow
  try {
   SubmitPostData::submitToOneTablenImage(
                table: 'notification_logs',
                newInput: $_POST,
                isCaptcha: false,
            );
  } catch (\Throwable $e) {
   // swallow
   \showError($e);
  }
 }

 public static function hasSent(string $eventId, string $inviteeId, string $messageSnippet, string $channel = 'email'): bool
 {
    try {
        $db = \Src\Db::connect2();
        $stmt = $db->prepare("SELECT 1 FROM notification_logs WHERE event_id = ? AND invitee_id = ? AND channel = ? AND message LIKE ? LIMIT 1");
        $stmt->execute([$eventId, $inviteeId, $channel, "%$messageSnippet%"]);
        return $stmt->rowCount() > 0;
    } catch (\Throwable $e) {
        \showError($e);
        return false;
    }
 }
}
