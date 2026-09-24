# Architectural & Gap Survey Report: PWA Notification System

**Investigation Target:** `/Users/waleolaogun/Sites/shared-lib`  
**Components:** `src/PushNotificationService.php`, `src/NotificationOrchestrator.php`, `composer.json`, `tests/`, `./dev`, `helpers/pwa-notifications.js`  
**Lead Investigator:** Codebase Architecture & Gap Survey Explorer  
**Charter Reference:** Master Engineering, Board & Security Governance Charter (v3.0 Executive Edition)  

---

## 1. Observation

### 1.1 Source Code Inspection: `src/PushNotificationService.php` (275 lines)
- **Class Structure:** `class PushNotificationService` in namespace `Src`. Not declared `final`.
- **Existing Public Methods:**
  1. `public static function isAllowedPushEndpoint(string $endpoint): bool` (lines 27–51):
     - Parses URL via `parse_url($endpoint)`. Checks `scheme === 'https'` and non-empty `host`.
     - Allowed suffixes list (lines 35–42):
       ```php
       $allowedSuffixes = [
           'push.apple.com',
           'fcm.googleapis.com',
           'googleapis.com',
           'push.services.mozilla.com',
           'notify.windows.com',
           'push.amazon.com',
       ];
       ```
     - Validates via `$host === $suffix || str_ends_with($host, '.' . $suffix)`.
     - **Observed Defect / Vulnerability:**
       - Port is **not validated**: an endpoint like `https://push.apple.com:22/` or `https://push.apple.com:6379/` passes validation, allowing potential port-scan or service interaction SSRF.
       - URL `user` / `pass` components are not validated (e.g., `https://attacker@push.apple.com/`).
       - `googleapis.com` is overly permissive: matches `storage.googleapis.com`, `run.app.googleapis.com`, or arbitrary Google Cloud endpoints rather than strictly FCM push services (`fcm.googleapis.com`, `android.googleapis.com`).
  2. `public static function sendPush(...)` (lines 68–217):
     - Signature:
       ```php
       public static function sendPush(
           string|int|array|null $userId,
           string $message,
           ?string $url = null,
           string $title = 'Platform Alert',
           string $tag = 'general',
           ?int $badgeCount = null,
           bool $isSilent = false,
           ?string $syncAction = null,
           ?string $targetNotificationId = null,
           ?array $options = null
       ): bool
       ```
     - **VAPID Config Retrieval** (lines 85–89): reads `getenv()` and `$_ENV` for `VAPID_PUBLIC_KEY`, `VAPID_PRIVATE_KEY`, `VAPID_SUBJECT`, `APP_LOGO`.
     - **WebPush Initialization & Gate 4 Timeout Bug** (lines 104–108):
       ```php
       $defaultOptions = [
           'timeout' => 3, // Bounded 3-second timeout (Gate 4)
       ];
       $webPush = new WebPush($auth, $defaultOptions);
       ```
       - **Observed Critical Bug in Minishlink WebPush Constructor:** In `vendor/minishlink/web-push/src/WebPush.php`:
         ```php
         public function __construct(array $auth = [], array $defaultOptions = [], ?int $timeout = 30, array $clientOptions = [])
         ```
         `$defaultOptions` only supports keys: `'TTL'`, `'urgency'`, `'topic'`, `'batchSize'`, `'requestConcurrency'`, `'contentType'`. The key `'timeout'` in `$defaultOptions` is completely ignored! The request timeout is controlled by the 3rd argument `$timeout = 30` and 4th argument `$clientOptions`. Because only 2 arguments are passed, **the actual cURL request timeout is defaulted to 30 seconds, violating Gate 4's bounded 3-second limit!**
     - **Payload Assembly** (lines 110–122):
       ```php
       $basePayloadArray = [
           'title'                => $title,
           'body'                 => $message,
           'url'                  => $url ?: '/',
           'icon'                 => $appLogo,
           'badge'                => '/public/img/favicon/favicon-32x32.png',
           'tag'                  => $tag,
           'isSilent'             => $isSilent,
           'syncAction'           => $syncAction,
           'targetNotificationId' => $targetNotificationId,
           'actions'              => $options['actions'] ?? [],
           'timestamp'            => time() * 1000,
       ];
       ```
       - **Observed Deficiencies for R1:**
         - Missing `image`: No inline preview / hero media image attachment field.
         - Missing `vibrate`: No vibration pattern (array of milliseconds) included.
         - Missing `renotify`: Replacing a notification with the same tag will silently update without vibrating/alerting unless `renotify: true` is set.
         - Missing `requireInteraction`: Cannot keep high-priority alerts on screen until user dismissal.
         - Missing structured W3C `data` object: modern service workers access payload properties through `event.data.json().data` in addition to top-level fields.
     - **WebPush Protocol Level Deficiencies:**
       - Line 174: `$webPush->queueNotification($subscriptionObject, $payload ?: null);`
       - Calls `queueNotification` without passing `$options` (3rd parameter). Therefore:
         - `topic` (RFC 8030 section 5.4 coalescing tag) is NOT sent in HTTP headers, preventing push gateways (Apple APNs / Google FCM) from collapsing offline message storms.
         - `urgency` (RFC 8030 section 5.3) is NOT set (silent sync should send `urgency: very-low` or `low`, high-priority alerts should send `urgency: high`).
         - `TTL` (RFC 8030 section 5.2) is not adjusted (silent sync dismissals linger for weeks instead of having a bounded short TTL of 60–300s).
     - **Pruning Expired Subscriptions** (lines 191–208):
       - On `$report->isSubscriptionExpired()`, executes `DELETE FROM pushNotification WHERE endpoint = ?` and `DELETE FROM user_push_subscriptions WHERE endpoint = ?`.
  3. `public static function getUserPushSubscriptions(string $userId): array` (lines 225–273):
     - Queries `user_push_subscriptions` (`endpoint, p256dh, auth_token WHERE user_id = ?`) with fallback to `pushNotification` (`endpoint, p256dhKey, authKey WHERE id = ?`).

---

### 1.2 Source Code Inspection: `src/NotificationOrchestrator.php` (350 lines)
- **Class Structure:** `final class NotificationOrchestrator` in namespace `Src`.
- **Existing Public Methods:**
  1. `public static function dispatch(...)` (lines 40–163):
     - Signature:
       ```php
       public static function dispatch(
           string $userId,
           string $category,
           string $priority,
           string $title,
           string $body,
           string $actionUrl = '/',
           string $tag = 'general',
           ?string $scopeCode = null,
           ?array $metadata = null
       ): string
       ```
     - Generates `$notificationId = 'notif_' . bin2hex(random_bytes(12))`.
     - Inserts into `notification_orchestration` with status `'pending'` (lines 61–82).
     - Inserts into legacy `notification` table (lines 85–101).
     - Computes `$unreadCount = self::getUnreadCount($userId)`.
     - **Channel 1 (In-App Pusher Socket):** Checks `self::isUserOnline($userId)`. If online, broadcasts to channel `'private-user-' . $userId` event `'new-notification'`. Logs delivery via `logDelivery(..., 'in_app_socket', 'sent')`.
     - **Channel 2 (OS WebPush):** Checks `!empty(PushNotificationService::getUserPushSubscriptions($userId))`. If true, calls `PushNotificationService::sendPush(...)`. Logs delivery via `logDelivery(..., 'web_push', $pushed ? 'sent' : 'failed')`.
     - **Channel 3 (Email Fallback):** If priority is `'high'` or `'critical'` and user is offline or has no push subscriptions, logs `'queued_immediate'` or `'queued'`.
  2. `public static function markAsRead(string $notificationId, string $userId): bool` (lines 168–241):
     - Updates `notification_orchestration` (`status = 'read', read_at = NOW()`).
     - Updates `notification` (`notification_status = 'deleted'`).
     - Recomputes `$unreadCount = self::getUnreadCount($userId)`.
     - Broadcasts Pusher event `'notification-synced'` with `['action' => 'READ', 'notification_id' => $notificationId, 'unread_count' => $unreadCount]`.
     - Triggers silent push:
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
     - **Observed Defect for R2:** Notice `tag: 'sync-dismiss'`. The original notification had a tag (e.g. `tag: 'post-123'`). By hardcoding `tag: 'sync-dismiss'`, service workers attempting to query `registration.getNotifications({ tag: ... })` cannot find or dismiss the open banner by tag! The payload must include `targetTag` or the original tag.
     - Cancels pending email in `notification_delivery_logs`.
  3. `public static function getUnreadCount(string $userId): int` (lines 246–260):
     - `SELECT COUNT(*) FROM notification_orchestration WHERE user_id = :uid AND status = 'pending'`.
  4. `public static function isUserOnline(string $userId): bool` (lines 264–279):
     - `SELECT COUNT(*) FROM user_socket_presence WHERE user_id = :uid AND is_active = 1 AND last_heartbeat >= DATE_SUB(NOW(), INTERVAL 60 SECOND)`.
  5. `public static function updatePresence(string $userId, string $channelName, bool $isActive): void` (lines 284–302):
     - Upserts into `user_socket_presence` table.
- **Existing Private Methods:**
  1. `private static function broadcastPusher(string $channel, string $event, array $data): void` (lines 307–328):
     - Connects to Pusher using `PusherClient` with credentials from `$_ENV` or `getenv`. Sets cluster (default `'eu'`), `useTLS => true`, `timeout => 2`.
  2. `private static function logDelivery(string $notificationId, string $channel, string $status, ?string $details = null): void` (lines 333–348):
     - Inserts into `notification_delivery_logs`.
     - **Observed Defect for R2 (Zero-Loss Logging):**
       - Silent swallow: `catch (\Throwable $e) {}` with no fallback! If DB connection fails, the log entry is permanently lost.
       - Method is `private static`: external callers cannot query or verify delivery status.
       - No method exists to query delivery logs (`getDeliveryLogs`).
       - `markAllAsRead(string $userId): bool` is completely missing from `NotificationOrchestrator`.

---

### 1.3 Client Integration: `helpers/pwa-notifications.js` (175 lines)
- Implements:
  - `navigator.clearAppBadge()` on app focus/open.
  - BroadcastChannel (`fp_notification_sync`) and Service Worker `message` listener for `{ type: 'ON_APP_NOTIFICATION', payload: ... }`.
  - Floating toast UI with backdrop blur (`rgba(18, 24, 38, 0.96)`, `backdrop-filter: blur(12px)`), smooth entrance animation, 6-second auto-dismiss, and URL navigation (`payload.url || payload.data?.url || '/'`).
  - Real-time presence heartbeat: POST to `/api/notifications/presence` on `visibilitychange`, `focus`, and 60-second interval. Matches the 60-second window in `NotificationOrchestrator::isUserOnline`.

---

### 1.4 Dependencies & Project Configuration: `composer.json`
- PHP Platform: `8.4.0` (Local runtime: `PHP 8.5.6`).
- Autoloading:
  - `"Src\\": "src/"`
  - `"Tests\\": "tests/"`
  - `"helpers\\": "helpers/"`
- Key Existing Required Dependencies:
  - `"minishlink/web-push": "^9.0 || ^10.0"` (Installed: v9.x / v10.x with Guzzle 7.9)
  - `"pusher/pusher-php-server": "^7.0"` (Installed)
  - `"guzzlehttp/guzzle": "^7.9"` (Installed)
  - `"monolog/monolog": "^3.9"` (Installed)
- Key Dev Dependencies:
  - `"phpstan/phpstan": "^1.12"` (Installed)
  - `"phpunit/phpunit": "^10.0"` (Installed: PHPUnit 10.5.64)
  - `"mockery/mockery": "^1.6"` (Installed)
  - `"php-mock/php-mock": "^2.6"` & `"php-mock/php-mock-phpunit": "^2.13"` (Installed)
- Composer Scripts: None defined in `composer.json`.

---

### 1.5 Testing Architecture & Current Status in `tests/`
- **Total Test Files:** 32 test files in `tests/`.
- **Existing Tests for Notification System:** **0 tests.**
  - `grep_search` for `PushNotificationService` in `tests/` returned 0 matches.
  - `grep_search` for `NotificationOrchestrator` in `tests/` returned 0 matches.
- **Database Mocking Support:**
  - `Src\Db` contains `Db::setMockConnection(PDO $pdo)` and `Db::clearMockConnection()` (lines 90–98 of `src/Db.php`).
  - Unit tests in `tests/SelectFn2Test.php` successfully use this mechanism to mock database queries without requiring a live MySQL instance.
- **PHPStan Analysis:**
  - `vendor/bin/phpstan analyse src/PushNotificationService.php src/NotificationOrchestrator.php --level=8` exits with code 0 (`[OK] No errors`).
- **PHP Syntax:**
  - `php -l src/PushNotificationService.php` and `php -l src/NotificationOrchestrator.php` return 0 syntax errors.
- **Development & Preflight Scripts:**
  - `./dev` script exists in repository root with commands: `syntax`, `analyze`, `cs-fix`, `audit`, `validate`, `ci`.
  - `scripts/preflight.sh` referenced in the Master Governance Charter does NOT exist yet in `shared-lib`.

---

## 2. Logic Chain

1. **Premise 1 (R1 - Payload Completeness & Native Parity):** Best-in-Class PWA notifications on modern mobile operating systems (iOS 16.4+ Safari PWA and Android Chromium) rely on standard W3C Push and Notification API specifications.
   - **Observation:** `PushNotificationService::$basePayloadArray` only packages `title`, `body`, `url`, `icon`, `badge`, `tag`, `isSilent`, `syncAction`, `targetNotificationId`, and `actions`.
   - **Inference:** The payload cannot trigger rich media banners (missing `image`), cannot produce haptic feedback (missing `vibrate`), cannot re-alert on tag updates (missing `renotify`), and omits the standard nested `data` container required by modern service workers.
   - **Inference 2 (WebPush Protocol Headers):** Because `queueNotification` does not pass options to Minishlink WebPush, the HTTP request sent to push services lacks the `Topic` header (RFC 8030 §5.4) and `Urgency` header (RFC 8030 §5.3). Offline devices will experience message pileups rather than coalescing, and silent syncs will be delivered with default high urgency.

2. **Premise 2 (R2 - Cross-Device Sync & Multi-Channel Orchestration):** When an alert is read on one device, all other endpoints belonging to the user must close the banner and synchronize badge counts with zero delivery loss.
   - **Observation:** `NotificationOrchestrator::markAsRead` sends a silent push with `tag: 'sync-dismiss'`.
   - **Inference:** Because the original alert had a specific tag (e.g. `post-123`), client service workers querying `registration.getNotifications({ tag: ... })` fail to identify which notification to dismiss. Passing `targetTag` or the original tag is required.
   - **Observation:** `NotificationOrchestrator` lacks a `markAllAsRead($userId)` method.
   - **Inference:** Users clearing all notifications in UI must execute N single-item read calls, flooding Pusher and push gateways with N separate sync pushes rather than one atomic broadcast.
   - **Observation:** `NotificationOrchestrator::logDelivery` silently catches exceptions (`catch (\Throwable $e) {}`) and is private.
   - **Inference:** A temporary database deadlock or disconnect results in silent, unrecoverable delivery log loss. A zero-loss architecture requires fallback logging (via `LoggerFactory` or file/syslog) and a public retrieval API (`getDeliveryLogs`).

3. **Premise 3 (R3 - Security, Gate 4 Timeouts & Governance):**
   - **Observation:** In `PushNotificationService.php`, lines 104–107 configure `$defaultOptions = ['timeout' => 3]` and call `new WebPush($auth, $defaultOptions)`.
   - **Inference:** Minishlink `WebPush` constructor signature ignores `'timeout'` in `$defaultOptions` and defaults the Guzzle timeout to 30 seconds. This directly violates Master Charter Gate 4 (3-second bounded cURL timeouts). To strictly enforce Gate 4, `$timeout = 3` must be passed as parameter 3 and `$clientOptions = ['timeout' => 3.0, 'connect_timeout' => 2.0]` as parameter 4.
   - **Observation:** In `isAllowedPushEndpoint()`, the allowlist includes `googleapis.com` without restricting to FCM subdomains, lacks port restrictions, and does not check for userinfo.
   - **Inference:** An attacker or compromised payload could target non-push Google Cloud services or arbitrary ports on allowed hosts. Restricting to `fcm.googleapis.com` and `android.googleapis.com`, enforcing port 443, and rejecting userinfo is mandatory for Red Team / SecOps compliance.
   - **Observation:** There are currently 0 automated PHPUnit tests for both classes in `tests/`.
   - **Inference:** Changes cannot be certified under Master Charter Rule 1 (Pre-Flight Gate) or Rule 4 (Automated Dual-Test Invariant) without dedicated unit tests verifying SSRF filtering, payload assembly, badging calculations, and orchestration cascade.

---

## 3. Caveats

1. **Portfolio Apps Runtime Environment:** `shared-lib` is a Composer library consumed by 7 separate portfolio applications. The database connections are provided via `Src\Db::connect2()`, which in production connects to MySQL. While unit tests run in memory using `Db::setMockConnection()`, production schema migrations (`notification_orchestration`, `user_socket_presence`, `notification_delivery_logs`) reside within the consuming applications. All database queries must remain defensive with table existence fallbacks.
2. **Backward-Compatibility Invariant:** Any changes to public method signatures in `PushNotificationService` and `NotificationOrchestrator` must strictly preserve existing parameter positions, types, and defaults to avoid breaking callers across the portfolio apps. New capabilities must be exposed via optional parameters or extensible options arrays.
3. **WebKit (iOS Safari PWA) Platform Restrictions:** iOS 16.4+ WebKit PWA push delivery requires that the web application is added to the Home Screen (standalone display mode). WebKit restricts silent pushes if they do not update the application badge. The implementation must ensure silent sync payloads explicitly deliver a numeric `badgeCount` and `clearBadge` flag so iOS Safari can invoke `navigator.setAppBadge()` or `navigator.clearAppBadge()`.
4. **Preflight Script:** While `./dev` exists, `scripts/preflight.sh` as named in the Master Charter is not yet present in `shared-lib`.

---

## 4. Conclusion & Actionable Gap Matrix

### Summary Assessment
The existing notification foundation in `shared-lib` provides working VAPID push and basic Pusher orchestration, but suffers from:
1. A hidden **30-second timeout bug** violating Gate 4.
2. Critical **SSRF allowlist loopholes** (overly broad `googleapis.com`, missing port enforcement).
3. **Missing modern PWA capabilities** (rich media images, custom vibration patterns, coalescing tags with `renotify`, and W3C `data` payload nesting).
4. **Sub-optimal orchestration & cross-device sync** (hardcoded `tag: 'sync-dismiss'` preventing target banner closure, missing `markAllAsRead`, silent delivery log loss, missing `getDeliveryLogs`).
5. **Zero test coverage** in `tests/`.

### Detailed Gap Matrix

| Req | Feature / Capability | Current Implementation Status | Gap & Required Implementation |
| :--- | :--- | :--- | :--- |
| **R1** | Rich Action Buttons | Passes `$options['actions'] ?? []` blindly | Validate action schema (`action`, `title`, `icon`, `type`); sanitize identifiers; embed in both root and `data.actions`. |
| **R1** | Inline Images / Media Attachments | Completely absent from `$basePayloadArray` | Add `image` support (via parameter/options); validate URL/path; embed in payload for Chromium and iOS 17+ Safari. |
| **R1** | Custom Vibration Patterns | Completely absent | Add `vibrate` pattern support (`array<int>`); provide intelligent defaults based on priority (critical, high, medium, low); sanitize integer array. |
| **R1** | Coalescing Tags & Renotify | `$tag` in JSON only; no protocol `Topic` header; no `renotify` | Set `topic` in WebPush protocol options (RFC 8030 §5.4, clamped $\le 32$ chars base64url); add `renotify` boolean flag. |
| **R1** | Silent Background Sync | Sets `isSilent` and `syncAction` in JSON | Set `urgency: 'low'`/`'very-low'` and bounded `TTL: 300` in WebPush options; include `targetTag` for precise banner dismissal. |
| **R1** | Web Badging API Integration | Computes `$effectiveBadgeCount` | Include explicit `clearBadge: true` when count is 0; embed badge count in both root and `data.badgeCount` for WebKit & Chromium. |
| **R1** | iOS Safari WebKit / Chromium Parity | Flat payload structure | Provide structured dual-level payload (W3C standard root properties + nested `data` container) ensuring full cross-browser compatibility. |
| **R2** | Multi-Device Cross-Device Badge Sync | Implemented in `markAsRead` only | Add `markAllAsRead(string $userId): bool` to perform batch read update and broadcast single badge-reset sync. |
| **R2** | Auto Banner Dismissal (`CLOSE_NOTIFICATION`) | Hardcodes `tag: 'sync-dismiss'` | Send original notification tag (`targetTag`) so service workers can match and close specific notification instances across devices. |
| **R2** | Pusher Socket vs WebPush Cascade | Sends socket if online; always sends WebPush | Add configurable cascade policies (`CASCADE_ALL`, `CASCADE_SOCKET_FIRST`, `CASCADE_PUSH_ONLY`); calibrate push urgency when user is online. |
| **R2** | Zero-Loss Delivery Logging | Private method, silently swallows errors | Add fallback logging (`LoggerFactory` / Monolog / error_log) if DB write fails; make retrieval public via `getDeliveryLogs($notificationId)`. |
| **R3** | SSRF Endpoint Allowlist | Matches loose `googleapis.com`, no port check | Enforce port 443; reject userinfo; replace `googleapis.com` with `fcm.googleapis.com` and `android.googleapis.com`; disallow IP addresses. |
| **R3** | VAPID RFC 8292 / RFC 8291 Encryption | Handled by `minishlink/web-push` | Validate key formats defensive checks; gracefully handle malformed keys with descriptive logging. |
| **R3** | Gate 4 Bounded cURL Timeouts | **BUG: Running with 30s timeout** | Pass `$timeout = 3` (3rd arg) and `$clientOptions = ['timeout' => 3.0, 'connect_timeout' => 2.0]` (4th arg) to `WebPush::__construct`. |
| **R3** | PHPStan Level 8 Compliance | Currently 0 errors on target files | Maintain 100% Level 8 compliance with strict array generics (`array<string, mixed>`), null coalescing, and type declarations. |
| **R3** | Automated PHPUnit Tests | **0 tests exist** | Create `tests/PushNotificationServiceTest.php` and `tests/NotificationOrchestratorTest.php` with 100% coverage using `Db::setMockConnection`. |

---

## 5. Verification Method

To independently verify the observations and findings in this report, execute the following commands in `/Users/waleolaogun/Sites/shared-lib`:

1. **Verify Current PHPStan Level 8 Status on Target Files:**
   ```bash
   vendor/bin/phpstan analyse src/PushNotificationService.php src/NotificationOrchestrator.php --level=8
   ```
   *Expected Result:* `[OK] No errors`.

2. **Verify Minishlink WebPush Constructor Timeout Signature (Proof of Gate 4 Timeout Bug):**
   ```bash
   php -r '
   require "vendor/autoload.php";
   $ref = new ReflectionMethod(\Minishlink\WebPush\WebPush::class, "__construct");
   foreach ($ref->getParameters() as $p) {
       echo $p->getName() . ": " . ($p->isDefaultValueAvailable() ? var_export($p->getDefaultValue(), true) : "required") . PHP_EOL;
   }
   '
   ```
   *Expected Output:*
   ```
   auth: array ( )
   defaultOptions: array ( )
   timeout: 30
   clientOptions: array ( )
   ```
   Confirms that `$defaultOptions['timeout'] = 3` does NOT set the request timeout; parameter 3 `$timeout` defaults to 30!

3. **Verify Zero Existing Test Coverage for Target Services:**
   ```bash
   vendor/bin/phpunit --list-tests | grep -E "PushNotification|NotificationOrchestrator"
   ```
   *Expected Result:* Zero matches.

4. **Verify PHP Syntax of Target Files:**
   ```bash
   php -l src/PushNotificationService.php && php -l src/NotificationOrchestrator.php
   ```
   *Expected Result:* `No syntax errors detected`.

5. **Verify SSRF Allowlist Behavior:**
   ```bash
   php -r '
   require "vendor/autoload.php";
   var_dump(\Src\PushNotificationService::isAllowedPushEndpoint("https://storage.googleapis.com/evil")); // currently true! (vulnerability)
   var_dump(\Src\PushNotificationService::isAllowedPushEndpoint("https://push.apple.com:22/evil")); // currently true! (port vulnerability)
   '
   ```
   *Expected Output:* Both return `bool(true)`, demonstrating the security loopholes identified in this survey.
