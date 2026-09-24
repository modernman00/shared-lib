## 2026-09-24T07:00:41Z

<USER_REQUEST>
You are Worker 2 for Milestone M2 of the PWA Notification System hardening in shared-lib.

Your working directory is: /Users/waleolaogun/Sites/shared-lib/.agents/worker_m2/
The project root is: /Users/waleolaogun/Sites/shared-lib
The user's original request is at: /Users/waleolaogun/Sites/shared-lib/.agents/ORIGINAL_REQUEST.md
Master Governance Charter is at: /Users/waleolaogun/Sites/shared-lib/AGENTS.md
The project plan is at: /Users/waleolaogun/Sites/shared-lib/.agents/orchestrator_1/PROJECT.md
The TAT Board Review consensus is at: /Users/waleolaogun/Sites/shared-lib/.agents/orchestrator_1/tat_review.md
The Explorer Survey reports are at:
- /Users/waleolaogun/Sites/shared-lib/.agents/explorer_survey_codebase/handoff.md
- /Users/waleolaogun/Sites/shared-lib/.agents/spec_miner_pwa_webpush/handoff.md
- /Users/waleolaogun/Sites/shared-lib/.agents/explorer_survey_security/handoff.md
Milestone M1 handoff report is at:
- /Users/waleolaogun/Sites/shared-lib/.agents/worker_m1/handoff.md

FILE OWNERSHIP: You EXCLUSIVELY own `src/NotificationOrchestrator.php`. Do NOT modify any other files.

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

YOUR OBJECTIVE:
Enhance `src/NotificationOrchestrator.php` to achieve Best-In-Class multi-channel orchestration, safe cross-device sync, and zero-loss delivery logging:

1. Safe Cross-Device Read Synchronization (`markAsRead`):
   - In `markAsRead(string $notificationId, string $userId): bool`:
     * Update `notification_orchestration` (`status = 'read', read_at = NOW()`).
     * Update legacy `notification` table (`notification_status = 'deleted'`).
     * Query remaining unread count: `$unreadCount = self::getUnreadCount($userId)`.
     * Query original tag/metadata for the notification if available to extract `$targetTag`.
     * Broadcast Pusher `notification-synced` event to channel `'private-user-' . $userId` with:
       `['action' => 'READ', 'notification_id' => $notificationId, 'unread_count' => $unreadCount, 'target_tag' => $targetTag]`.
     * CRITICAL DR. SILAS THORNE / SEGUN GOVERNANCE FIX:
       DO NOT dispatch an empty silent push (`isSilent: true, message: '', title: ''`) to background mobile devices, because on iOS Safari WebKit PWA, push events without calling `showNotification()` cause Safari to permanently revoke site push permissions, and on Chromium it displays a generic fallback banner!
     * Cancel pending email records in `notification_delivery_logs`.
     * Return true on success.

2. Batch Read Dismissal (`markAllAsRead`):
   - Implement `public static function markAllAsRead(string $userId): bool`:
     * Execute atomic batch update on `notification_orchestration`:
       `UPDATE notification_orchestration SET status = 'read', read_at = NOW() WHERE user_id = :uid AND status = 'pending'`.
     * Update legacy `notification` table for the user.
     * Broadcast Pusher `notification-synced` event to channel `'private-user-' . $userId` with:
       `['action' => 'MARK_ALL_READ', 'unread_count' => 0]`.
     * Cancel pending email delivery logs for this user.
     * Return true.

3. Zero-Loss Delivery Logging & Audit Trail:
   - In `logDelivery(string $notificationId, string $channel, string $status, ?string $details = null): void`:
     * Try inserting into `notification_delivery_logs`.
     * If the PDO write throws an exception, DO NOT silently swallow it! Catch `\Throwable` and log to system logger / `error_log` with JSON payload: `[NOTIFICATION_DELIVERY_LOG_FAILURE] notification_id, channel, status, details, error`.
   - Implement public retrieval method:
     `public static function getDeliveryLogs(string $notificationId): array`:
     * Query `SELECT channel, status, details, created_at FROM notification_delivery_logs WHERE notification_id = :nid ORDER BY id ASC`.
     * Return `array<int, array<string, mixed>>`. Return empty array if not found or on error.

4. Multi-Channel Cascade & Rich Payload Integration in `dispatch`:
   - In `dispatch()`:
     * Support passing rich options from `$metadata` into `PushNotificationService::sendPush()`:
       Pass `image`, `vibrate`, `actions`, `renotify`, `requireInteraction`, `urgency`, `ttl`, `dir`, `lang`, `data`.
     * Maintain channel 1: Pusher socket if user is online (`isUserOnline($userId)`).
     * Maintain channel 2: OS WebPush if user has push subscriptions (`PushNotificationService::getUserPushSubscriptions($userId)`). Ensure `$unreadCount` is passed into `sendPush()`.
     * Maintain channel 3: Email fallback queued for high/critical alerts when user is offline or WebPush fails.
     * Log delivery status cleanly for each channel attempted.

5. Strict Quality & Governance:
   - Run `php -l src/NotificationOrchestrator.php` to verify 0 syntax errors.
   - Run `./vendor/bin/phpstan analyse src/NotificationOrchestrator.php --level=8` to verify 0 errors.
   - Document all changes and verification outputs in `/Users/waleolaogun/Sites/shared-lib/.agents/worker_m2/handoff.md`.
   - Send a completion message back when finished.
</USER_REQUEST>
