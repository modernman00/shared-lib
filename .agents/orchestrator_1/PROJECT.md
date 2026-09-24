# Project: World-Class PWA Notification System (Shared-Lib)

## Architecture
- **Package**: `modernman00/shared-lib`
- **Target Files**:
  - `src/PushNotificationService.php`: Core Web Push engine. Handles push subscription validation, SSRF filtering, payload assembly (W3C Notifications / Web Badging), VAPID authorization (RFC 8292), payload encryption (RFC 8291), RFC 8030 gateway headers, and bounded cURL execution (Gate 4).
  - `src/NotificationOrchestrator.php`: Multi-channel orchestration engine. Manages in-app Pusher WebSocket presence routing, OS WebPush dispatch, email queue cascade, cross-device read synchronization, badge count tracking, and zero-loss delivery logging.
- **Client Bridge**:
  - `helpers/pwa-notifications.js`: PWA client-side bridge for floating glass toasts, audio/haptic alert triggers, Web Badging API integration, and presence heartbeats.
- **Test Infrastructure**:
  - `tests/PushNotificationServiceTest.php`: Offline PHPUnit unit tests covering SSRF endpoint filtering, payload formatting, option normalization, and WebPush configuration.
  - `tests/NotificationOrchestratorTest.php`: Offline PHPUnit unit tests covering multi-channel cascade, presence detection, read synchronization, badge calculations, and delivery logging using `Db::setMockConnection()`.

## Code Layout
- `src/PushNotificationService.php` (Owned by Worker)
- `src/NotificationOrchestrator.php` (Owned by Worker)
- `tests/PushNotificationServiceTest.php` (Owned by Test Writer / Worker)
- `tests/NotificationOrchestratorTest.php` (Owned by Test Writer / Worker)

## Feature Inventory
| # | Feature | Description | Milestone | Source |
|---|---------|-------------|-----------|--------|
| 1 | SSRF Endpoint Allowlist Hardening | Restrict to strict exact hosts ('fcm.googleapis.com', 'android.googleapis.com') and exact suffixes ('.push.apple.com', '.push.services.mozilla.com', '.notify.windows.com', '.push.amazon.com'); require HTTPS; enforce port 443 or null; reject userinfo; disable redirects. | M1 | Survey (Security & Codebase) |
| 2 | Gate 4 Bounded cURL Timeouts | Fix WebPush instantiation bug: pass `$timeout = 3` as 3rd param and `$clientOptions` (`['timeout' => 3.0, 'connect_timeout' => 2.0, 'allow_redirects' => false, 'http_errors' => false]`) as 4th param. | M1 | Survey (Security & Codebase) |
| 3 | Modern VAPID & RFC 8291 Encryption | Specify `'contentEncoding' => 'aes128gcm'` in `Subscription::create()`; support RFC 8292 `Authorization: vapid t=..., k=...`. | M1 | Survey (Security & Spec) |
| 4 | Rich Media & Inline Images | Add `image` hero preview option to payload; resolve relative URLs; embed in dual root and `data.image` containers. | M1 | Survey (Spec & Codebase) |
| 5 | Tactile Custom Vibration Patterns | Add `vibrate` pattern array option; provide intelligent priority presets; normalize WHATWG conflict (strip vibrate if silent: true). | M1 | Survey (Spec & Codebase) |
| 6 | RFC 8030 Gateway Headers | Pass `TTL`, `urgency` ('very-low'|'low'|'normal'|'high'), and `topic` (coalescing tag $\le 32$ chars URL-safe) to `WebPush::queueNotification()`. | M1 | Survey (Spec & Codebase) |
| 7 | Interactive Action Buttons | Support `actions` array (max 2) with action, title, icon, and Chromium type/placeholder; defensive sanitization. | M1 | Survey (Spec & Codebase) |
| 8 | Web Badging API Integration | Embed `badgeCount` in root and `data.badgeCount`; set `clearBadge: true` when 0; full iOS 16.4+ Home Screen and Android WebAPK parity. | M1 | Survey (Spec & Codebase) |
| 9 | iOS Safari Safe Cross-Device Read Sync | Eliminate empty silent push to background devices on `markAsRead` (preventing iOS permission revocation); sync active tabs via Pusher `notification-synced` and tag coalescing. | M2 | Survey (Spec & Codebase) |
| 10 | Batch Mark All As Read | Add `markAllAsRead(string $userId): bool` in `NotificationOrchestrator` to batch update unread status and broadcast single atomic badge reset. | M2 | Survey (Codebase & Spec) |
| 11 | Zero-Loss Delivery Logging & Audit API | Add fallback logging (Monolog/error_log) if DB write fails; expose `public static function getDeliveryLogs(string $notificationId): array`. | M2 | Survey (Codebase & Security) |
| 12 | Multi-Channel Cascade Policy | Configurable cascade policies (Pusher active socket first -> OS WebPush -> email fallback); presence-aware delivery. | M2 | Survey (Spec & Codebase) |
| 13 | Comprehensive Automated PHPUnit Tests | Create `PushNotificationServiceTest.php` and `NotificationOrchestratorTest.php` covering 100% of happy/sad paths and offline DB mocking. | E2E | Survey (Security & Codebase) |
| 14 | PHPStan Level 8 Static Analysis | Strict typing, generic array annotations, defensive null checks with 0 errors. | M1/M2/E2E | Survey (Security & Codebase) |

## Milestones
| # | Name | Scope | Dependencies | Status |
|---|------|-------|-------------|--------|
| M1 | Push Payload, Gateway & Security Hardening | Enhance `src/PushNotificationService.php`: Features 1, 2, 3, 4, 5, 6, 7, 8, 14. | none | DONE |
| M2 | Orchestration, Sync & Zero-Loss Cascade | Enhance `src/NotificationOrchestrator.php`: Features 9, 10, 11, 12, 14. | M1 | DONE |
| E2E | Automated Testing & Adversarial Hardening | Implement comprehensive test suites in `tests/`, verify with PHPStan L8, run Marcus/Ghost attack PoC, pass 100% tests. | M1, M2 | DONE |

## Interface Contracts

### PushNotificationService Public API
```php
namespace Src;

class PushNotificationService
{
    public static function isAllowedPushEndpoint(string $endpoint): bool;

    /**
     * @param string|int|array<int, string|int>|null $userId
     * @param string $message
     * @param string|null $url
     * @param string $title
     * @param string $tag
     * @param int|null $badgeCount
     * @param bool $isSilent (backward-compatibility)
     * @param string|null $syncAction (backward-compatibility)
     * @param string|null $targetNotificationId (backward-compatibility)
     * @param array{
     *     image?: string|null,
     *     icon?: string|null,
     *     badge?: string|null,
     *     actions?: array<int, array{action: string, title: string, icon?: string, type?: string, placeholder?: string}>,
     *     vibrate?: array<int, int>,
     *     renotify?: bool,
     *     silent?: bool,
     *     requireInteraction?: bool,
     *     urgency?: 'very-low'|'low'|'normal'|'high',
     *     ttl?: int,
     *     dir?: 'auto'|'ltr'|'rtl',
     *     lang?: string,
     *     data?: array<string, mixed>,
     *     targetNotificationId?: string|null,
     * }|null $options
     * @return bool
     */
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
    ): bool;

    /**
     * @param string $userId
     * @return array<int, array{endpoint: string, p256dh: string, auth: string}>
     */
    public static function getUserPushSubscriptions(string $userId): array;
}
```

### NotificationOrchestrator Public API
```php
namespace Src;

final class NotificationOrchestrator
{
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
    ): string;

    public static function markAsRead(string $notificationId, string $userId): bool;

    public static function markAllAsRead(string $userId): bool;

    public static function getUnreadCount(string $userId): int;

    public static function isUserOnline(string $userId): bool;

    public static function updatePresence(string $userId, string $channelName, bool $isActive): void;

    /**
     * @param string $notificationId
     * @return array<int, array<string, mixed>>
     */
    public static function getDeliveryLogs(string $notificationId): array;
}
```
