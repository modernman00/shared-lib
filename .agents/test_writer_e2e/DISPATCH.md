## 2026-09-24T06:52:39Z
You are the Test Writer for the PWA Notification System in shared-lib.

Your working directory is: /Users/waleolaogun/Sites/shared-lib/.agents/test_writer_e2e/
The project root is: /Users/waleolaogun/Sites/shared-lib
The user's original request is at: /Users/waleolaogun/Sites/shared-lib/.agents/ORIGINAL_REQUEST.md
Master Governance Charter is at: /Users/waleolaogun/Sites/shared-lib/AGENTS.md
The project plan is at: /Users/waleolaogun/Sites/shared-lib/.agents/orchestrator_1/PROJECT.md
The TAT Board Review consensus is at: /Users/waleolaogun/Sites/shared-lib/.agents/orchestrator_1/tat_review.md

FILE OWNERSHIP: You EXCLUSIVELY own files in `tests/`:
- `tests/PushNotificationServiceTest.php`
- `tests/NotificationOrchestratorTest.php`
Do NOT modify any production source files in `src/`.

YOUR OBJECTIVE:
Create comprehensive, zero-network, offline PHPUnit test suites covering `PushNotificationService` and `NotificationOrchestrator` per the 4-tier methodology:

1. `tests/PushNotificationServiceTest.php`:
   - Tier 1: Feature Coverage
     * Test `isAllowedPushEndpoint()` with all valid gateways: Google FCM (`fcm.googleapis.com`, `android.googleapis.com`), Apple APNs (`web.push.apple.com`, `api.push.apple.com`), Mozilla Autopush (`updates.push.services.mozilla.com`), Microsoft WNS (`db5.notify.windows.com`), Amazon (`push.amazon.com`).
     * Test payload assembly structure: verify `image`, `vibrate`, `badgeCount`, `renotify`, `actions`, `data` dictionary are properly generated.
     * Test Web Badging API integration: verify `badgeCount` and `clearBadge` behavior for positive and zero values.
     * Test backward compatibility: verify calling `sendPush` with legacy positional arguments (`$isSilent`, `$syncAction`, `$targetNotificationId`) works without errors.
   - Tier 2: Boundary & Corner Cases (SecOps & PoC Neutralization)
     * Test SSRF rejection of Cloud Metadata: `http://169.254.169.254/latest/meta-data/`.
     * Test SSRF rejection of Loopback/Private IPs: `http://127.0.0.1:6379`, `http://10.0.0.1/`, `http://localhost/`.
     * Test SSRF rejection of non-HTTPS schemes: `http://fcm.googleapis.com/fcm/send`.
     * Test SSRF rejection of port probing: `https://fcm.googleapis.com:22/`, `https://push.apple.com:6379/`.
     * Test SSRF rejection of userinfo obfuscation: `https://user:pass@fcm.googleapis.com/`.
     * Test SSRF rejection of generic Google Cloud APIs: `https://storage.googleapis.com/bucket`, `https://iam.googleapis.com/`.
     * Test WHATWG conflict normalization: `silent: true` with `vibrate` strips vibrate; `renotify: true` with empty tag normalizes safely.
     * Test tag sanitization and truncation: non-alphanumeric chars stripped, clamped to 32 chars.
   - Tier 3: Gateway & Encryption Checks
     * Test WebPush options: `TTL`, `urgency` ('very-low', 'low', 'normal', 'high'), and `topic`.
     * Test VAPID content encoding: verify subscription creation configures `aes128gcm`.

2. `tests/NotificationOrchestratorTest.php`:
   - Use `Db::setMockConnection(new PDO('sqlite::memory:'))` in `setUp()` and `Db::clearMockConnection()` in `tearDown()`.
   - Setup in-memory SQLite tables with necessary schemas:
     * `notification_orchestration`
     * `notification` (legacy)
     * `user_socket_presence`
     * `notification_delivery_logs`
     * `user_push_subscriptions`
   - Test `dispatch()`:
     * In-app socket delivery when user is online (`isUserOnline = true`).
     * OS WebPush delivery when user has push subscriptions.
     * Email fallback queuing for high/critical priority when user is offline.
     * Sanitization of title, body, and tag (`strip_tags`).
   - Test `markAsRead()`:
     * Updates notification status to 'read' and legacy to 'deleted'.
     * Decrements unread count.
     * Broadcasts Pusher `notification-synced` event.
     * Verifies safe cross-device sync.
   - Test `markAllAsRead()`:
     * Marks all pending notifications as read for the user.
     * Broadcasts badge reset sync.
   - Test `getDeliveryLogs()`:
     * Retrieves delivery logs for a given notification ID.
     * Verifies fallback logging when DB logging fails.
   - Test `updatePresence()` and `isUserOnline()`:
     * Verifies online presence within 60 seconds heartbeat window.

VERIFICATION:
- Run `php -l tests/PushNotificationServiceTest.php` and `php -l tests/NotificationOrchestratorTest.php`.
- Run `./vendor/bin/phpunit tests/PushNotificationServiceTest.php`.
- Run `./vendor/bin/phpstan analyse tests/PushNotificationServiceTest.php tests/NotificationOrchestratorTest.php --level=8`.
- Write your report in `/Users/waleolaogun/Sites/shared-lib/.agents/test_writer_e2e/handoff.md`.
- Send a completion message back to the orchestrator.
