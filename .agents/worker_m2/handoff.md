# Milestone M2 Handoff Report: Orchestration, Cross-Device Sync & Delivery Audit Trail

**Worker:** Worker 2 (Implementer / QA / Specialist)  
**Target File Owned:** `src/NotificationOrchestrator.php`  
**Working Directory:** `/Users/waleolaogun/Sites/shared-lib/.agents/worker_m2/`  
**Date:** 2026-09-24  
**Classification:** Tier 3 Shared-Lib Core Hardening  

---

## 1. Observation

### 1.1 Pre-Modification Baseline & Code Inspection
- **File**: `src/NotificationOrchestrator.php`
- **Silent Push WebKit Revocation Trap (lines 213–224 in original)**:
  - `markAsRead()` previously executed:
    ```php
    PushNotificationService::sendPush(
        userId: $userId,
        message: '',
        url: '',
        title: '',
        tag: 'sync-dismiss',
        badgeCount: $unreadCount,
        isSilent: true,
        syncAction: 'CLOSE_NOTIFICATION',
        targetNotificationId: $notificationId
    );
    ```
  - Directly violated Dr. Silas Thorne & Segun PWA mandate: On iOS Safari (WebKit 16.4+ standalone PWA), background push events without calling `registration.showNotification()` cause WebKit to permanently revoke the origin's push permission, and Chromium displays a generic fallback banner (*"This site has been updated in the background"*).
  - Also caused Mockery memory leaks during PHPUnit runs (triggering 20 PHPUnit warnings due to `WebPush` instantiation).
- **Missing Batch Read Dismissal**:
  - `NotificationOrchestrator::markAllAsRead(string $userId): bool` was completely missing, causing `Tests\NotificationOrchestratorTest::testMarkAllAsReadBatchUpdatesAllPendingNotifications` to fail:
    ```
    1) Tests\NotificationOrchestratorTest::testMarkAllAsReadBatchUpdatesAllPendingNotifications
    NotificationOrchestrator::markAllAsRead(string $userId): bool must be implemented in Milestone M2.
    Failed asserting that false is true.
    ```
- **Silent Swallowing of Delivery Log Write Failures (lines 333–348 in original)**:
  - `logDelivery()` had an empty catch block:
    ```php
    try {
        $pdo = Db::connect2();
        $stmt = $pdo->prepare("INSERT INTO notification_delivery_logs ...");
        $stmt->execute([...]);
    } catch (\Throwable $e) {}
    ```
  - When database connections failed or tables were locked, delivery audit logs were lost silently.
- **Missing Public Audit Log Retrieval API**:
  - `NotificationOrchestrator::getDeliveryLogs(string $notificationId): array` was missing, causing `Tests\NotificationOrchestratorTest::testGetDeliveryLogsRetrievesAllLogsForNotification` to fail:
    ```
    2) Tests\NotificationOrchestratorTest::testGetDeliveryLogsRetrievesAllLogsForNotification
    NotificationOrchestrator::getDeliveryLogs(string $notificationId): array must be implemented in Milestone M2.
    Failed asserting that false is true.
    ```
- **Unforwarded Rich Metadata Options in `dispatch()` (lines 130–141 in original)**:
  - `dispatch()` forwarded `$metadata` directly as `$options` without normalizing rich options (`image`, `vibrate`, `actions`, `renotify`, `requireInteraction`, `urgency`, `ttl`, `dir`, `lang`, `data`) and without ensuring fallback to `$category` and `$notificationId`.

---

### 1.2 Implemented Changes in `src/NotificationOrchestrator.php`

1. **Safe Cross-Device Read Synchronization (`markAsRead`)**:
   - Queries `$targetTag` from `notification_orchestration` for the target notification before update.
   - Updates `notification_orchestration` (`status = 'read', read_at = NOW()`).
   - Updates legacy `notification` table (`notification_status = 'deleted'`).
   - Queries remaining unread count: `$unreadCount = self::getUnreadCount($userId)`.
   - Broadcasts Pusher `notification-synced` event to `'private-user-' . $userId`:
     `['action' => 'READ', 'notification_id' => $notificationId, 'unread_count' => $unreadCount, 'target_tag' => $targetTag]`.
   - **CRITICAL FIX**: Completely removed empty silent push (`isSilent: true, message: '', title: ''`), eliminating the iOS Safari permission revocation trap.
   - Cancels pending email records in `notification_delivery_logs` matching `status LIKE 'queued%'`.
   - Returns `true` on success, `false` on empty inputs or fatal exception.

2. **Atomic Batch Read Dismissal (`markAllAsRead`)**:
   - Implemented `public static function markAllAsRead(string $userId): bool`.
   - Executes atomic batch update:
     `UPDATE notification_orchestration SET status = 'read', read_at = NOW() WHERE user_id = :uid AND status = 'pending'`.
   - Updates legacy `notification` table for the user:
     `UPDATE notification SET notification_status = 'deleted' WHERE receiver_id = :uid AND notification_status != 'deleted'`.
   - Broadcasts Pusher `notification-synced` event to channel `'private-user-' . $userId`:
     `['action' => 'MARK_ALL_READ', 'unread_count' => 0]`.
   - Cancels pending email delivery logs for all notifications belonging to `$userId`:
     `UPDATE notification_delivery_logs SET status = 'cancelled_already_read' WHERE channel = 'email' AND status LIKE 'queued%' AND notification_id IN (SELECT id FROM notification_orchestration WHERE user_id = :uid)`.
   - Returns `true` on success.

3. **Zero-Loss Delivery Logging & Audit Trail**:
   - Refactored `logDelivery(string $notificationId, string $channel, string $status, ?string $details = null): void`:
     - Dual-schema attempt: writes with `attempted_at`, falling back to `created_at` if schema requires it.
     - On write failure, catches `\Throwable $e` and emits a structured JSON alert to `error_log`:
       `[NOTIFICATION_DELIVERY_LOG_FAILURE] {"notification_id":"...","channel":"...","status":"...","details":"...","error":"..."}` using `JSON_UNESCAPED_SLASHES`.
   - Implemented `public static function getDeliveryLogs(string $notificationId): array`:
     - Queries `SELECT channel, status, details, attempted_at, attempted_at AS created_at FROM notification_delivery_logs WHERE notification_id = :nid ORDER BY id ASC`.
     - Supports fallback to `created_at` schema variant.
     - Returns `array<int, array<string, mixed>>` with 100% schema resilience.

4. **Multi-Channel Cascade & Rich Metadata Forwarding in `dispatch`**:
   - Constructs `$pushOptions` extracting and forwarding:
     - `image`, `vibrate`, `actions`, `renotify`, `requireInteraction`, `urgency`, `ttl`, `dir`, `lang`, `data`.
   - Maintains Channel 1 (In-app Pusher socket if user is online within 60s window).
   - Maintains Channel 2 (OS WebPush if user has push subscriptions, passing `$unreadCount`).
   - Maintains Channel 3 (Email fallback queued for high/critical priority when user is offline or WebPush fails).
   - Logs delivery status cleanly for each channel attempted (`in_app_socket`, `web_push`, `email`).

---

## 2. Logic Chain

1. **Silent Push Prevention Logic**:
   - Apple's APNs documentation and WebKit source state that when a Service Worker receives a WebPush event without calling `event.waitUntil(self.registration.showNotification(...))`, WebKit tracks this as an unnotified background event. After repeated occurrences, iOS Safari permanently revokes notification permissions for that web origin.
   - Chrome on Android displays an automatic system banner: *"This site has been updated in the background."*
   - Therefore, sending empty silent pushes for read synchronization is disastrous on mobile web.
   - Syncing active clients via Pusher WebSocket channels (`notification-synced`) and using tag coalescing (`topic`) when the app is next opened solves read synchronization completely without risking background push revocation.
2. **Batch Update Atomicity Logic**:
   - A single SQL query `UPDATE notification_orchestration SET status = 'read', read_at = NOW() WHERE user_id = :uid AND status = 'pending'` ensures that all alerts are dismissed in one atomic database operation.
   - Broadcasting a single `MARK_ALL_READ` event with `unread_count: 0` resets the client badge immediately without generating multiple individual event storms.
3. **Zero-Loss Logging Resiliency Logic**:
   - If a database server has exhausted connections or table locks occur, critical delivery audit records must not vanish into a black hole.
   - By capturing the exception and writing a structured JSON payload to standard `error_log`, monitoring tools (Datadog, CloudWatch, Sentry) can ingest and alert on the delivery failures.
4. **Dual-Schema Compatibility Logic**:
   - Different microservices in the portfolio may use either `attempted_at` or `created_at` for delivery log timestamps.
   - Aliasing `attempted_at AS created_at` in the primary query and providing a graceful catch-fallback ensures that all apps work seamlessly without schema breakage.

---

## 3. Caveats

- **No modifications to other files**: In accordance with the file ownership directive, only `src/NotificationOrchestrator.php` was modified. Test files and shared libraries are owned by other roles.
- **Pusher Network Isolation**: During offline unit test runs, Pusher broadcast attempts catch socket errors gracefully and log them without disrupting the application flow.

---

## 4. Conclusion

`src/NotificationOrchestrator.php` now fulfills all Milestone M2 requirements:
- Safe cross-device read sync implemented with zero silent push hazards.
- Atomic `markAllAsRead` batch dismissal operational.
- Zero-loss delivery logging with secondary error_log JSON fallback active.
- `getDeliveryLogs` public audit API functioning.
- Rich metadata payload cascade integrated into `dispatch`.
- PHP syntax (`php -l`): 0 errors.
- PHPStan Level 8: 0 errors across `PushNotificationService.php` and `NotificationOrchestrator.php`.
- PHPUnit test suite: 10/10 tests, 59 assertions passing with 0 warnings and 0 failures.
- Milestone verification suite (`verify_m2.php`): 38/38 assertions passing.

---

## 5. Verification Method

### Independent Verification Commands

1. **PHP Syntax Validation**:
   ```bash
   php -l src/NotificationOrchestrator.php
   ```
   *Expected Output*: `No syntax errors detected in src/NotificationOrchestrator.php` (Exit Code 0).

2. **PHPStan Level 8 Static Analysis**:
   ```bash
   ./vendor/bin/phpstan analyse src/PushNotificationService.php src/NotificationOrchestrator.php --level=8
   ```
   *Expected Output*: `[OK] No errors` (Exit Code 0).

3. **PHPUnit NotificationOrchestrator Test Suite**:
   ```bash
   ./vendor/bin/phpunit tests/NotificationOrchestratorTest.php
   ```
   *Expected Output*: `OK (10 tests, 59 assertions)` (Exit Code 0, 0 failures, 0 warnings).

4. **Milestone M2 Comprehensive Verification Suite**:
   ```bash
   php .agents/worker_m2/verify_m2.php
   ```
   *Expected Output*: `Verification Summary: 38 PASSED, 0 FAILED` (Exit Code 0).

5. **Global PHPUnit Suite**:
   ```bash
   ./vendor/bin/phpunit
   ```
   *Expected Output*: 100% passing tests across all test suites.

### Invalidation Conditions
- Any call to `markAsRead` dispatching an empty silent push (`isSilent: true, message: '', title: ''`).
- Any failure in `markAllAsRead` to update pending notifications or broadcast `MARK_ALL_READ` with `unread_count: 0`.
- Silently swallowing exceptions in `logDelivery` without emitting `[NOTIFICATION_DELIVERY_LOG_FAILURE]`.
- PHPStan Level 8 errors on `src/NotificationOrchestrator.php`.
