# PWA & Web Push Specifications & Standards Mining Report

**Date:** 2026-09-24  
**Author:** Specification Miner (PWA & Web Push Standards)  
**Target Services:** `src/PushNotificationService.php`, `src/NotificationOrchestrator.php`, `helpers/pwa-notifications.js`  
**Governing Documents:** W3C Push API, WHATWG Notifications API, W3C Badging API, Apple WebKit Web Push Documentation (iOS 16.4+ / iOS 17+), IETF RFC 8030 / RFC 8291 / RFC 8292.

---

## Features Discovered

| # | Category | Feature | Description | Inputs | Outputs | Error Behavior | Discovered Via |
|---|----------|---------|-------------|--------|---------|----------------|----------------|
| 1 | W3C Notifications | `showNotification(title, options)` | ServiceWorker method to display persistent system notifications | `title: DOMString`, `options: NotificationOptions` | `Promise<undefined>` | Throws `TypeError` if invoked on invalid arguments or in incompatible scope | WHATWG Notifications Standard §4.2 |
| 2 | W3C Notifications | `options.title` | Primary bold alert headline | `DOMString` | Rendered notification title | Required 1st argument | WHATWG Notifications Standard §3 |
| 3 | W3C Notifications | `options.body` | Secondary body text describing the event | `DOMString` (default `""`) | Multi-line text rendered under title | Truncated by OS if too long | WHATWG Notifications Standard §3 |
| 4 | W3C Notifications | `options.icon` | Square logo / avatar representing the notification sender | `USVString` (URL, e.g. 192x192 PNG) | Rendered square icon beside title/body | Ignored if URL fails to parse or fails fetch | WHATWG Notifications Standard §3 |
| 5 | W3C Notifications | `options.image` | Rich inline hero image / preview media | `USVString` (URL, e.g. 16:9 banner) | Large media expanded below notification text | Ignored if platform does not support inline media or fetch fails | WHATWG Notifications Standard §3 |
| 6 | W3C Notifications | `options.badge` | Small monochrome stencil icon for notification tray | `USVString` (URL, e.g. 96x96 PNG with alpha) | Displayed in Android status bar & notification shade header | Ignored on platforms without tray icon (e.g. macOS / iOS) | WHATWG Notifications Standard §3 |
| 7 | W3C Notifications | `options.actions` | Interactive action buttons rendered on notification | `sequence<NotificationAction>` (max `Notification.maxActions`, typ. 2) | Action buttons rendered on banner; triggers `notificationclick` with `event.action` | Excess entries sliced / skipped; throws `TypeError` in non-persistent `new Notification()` | WHATWG Notifications Standard §3 |
| 8 | Chromium Extension | `NotificationAction.type: "text"` | Inline text reply input field within notification | `type: "text"`, `placeholder: string` | Text input box in notification banner; returns input in `event.reply` | Rendered as standard button if platform/browser does not support inline reply | Chromium Blink PlatformNotificationAction |
| 9 | W3C Notifications | `options.vibrate` | Custom haptic vibration pattern in ms | `VibratePattern` (e.g. `[200, 100, 200]`) | Device vibrator pulses according to pattern | Throws `TypeError` if `silent: true` is also present | WHATWG Notifications Standard §3; W3C Vibration API |
| 10 | W3C Notifications | `options.tag` | Coalescing string identifier | `DOMString` (e.g. `"post-1234"`, `"chat-88"`) | Replaces existing notification sharing the same tag | None; empty string behaves as untagged | WHATWG Notifications Standard §3 |
| 11 | W3C Notifications | `options.renotify` | Audible/haptic alert trigger on tag replacement | `boolean` (default `false`) | Plays sound/vibration when replacing existing notification | Throws `TypeError` if `renotify: true` and `tag` is empty | WHATWG Notifications Standard §3 |
| 12 | W3C Notifications | `options.silent` | Visual-only notification suppressing sound/vibration | `boolean?` (default `null`) | Renders banner quietly without sound/haptics | Throws `TypeError` if `silent: true` and `vibrate` is present | WHATWG Notifications Standard §3 |
| 13 | W3C Notifications | `options.requireInteraction` | Persistent desktop notification banner | `boolean` (default `false`) | Banner stays on screen until dismissed or clicked | Ignored on mobile OS (Android/iOS manage dismissal) | WHATWG Notifications Standard §3 |
| 14 | W3C Notifications | `options.timestamp` | Event creation epoch timestamp | `EpochTimeStamp` (ms since Unix epoch) | Displayed as notification timestamp / sort order | Fallback to current time if omitted | WHATWG Notifications Standard §3 |
| 15 | W3C Notifications | `options.data` | Structured cloneable custom payload dictionary | `any` (serializable object) | Accessible in `event.notification.data` in service worker events | `DataCloneError` if non-serializable | WHATWG Notifications Standard §3 |
| 16 | W3C Notifications | `options.dir` & `options.lang` | Bi-directional text direction and language | `dir: "auto"\|"ltr"\|"rtl"`, `lang: BCP 47 string` | Proper right-to-left alignment & pronunciation | Fallback to document default | WHATWG Notifications Standard §3 |
| 17 | W3C Notifications | `registration.getNotifications()` | Queries active notifications displayed by origin | `filter?: GetNotificationOptions` (`{ tag: string }`) | `Promise<sequence<Notification>>` | Empty array if no active notifications match | WHATWG Notifications Standard §4.2 |
| 18 | W3C Notifications | `Notification.close()` | Dismisses an active notification programmatically | None | Closes OS notification banner | Silently ignored if already closed | WHATWG Notifications Standard §3 |
| 19 | W3C Badging API | `navigator.setAppBadge(contents)` | Sets numerical red badge on PWA Home Screen icon / Dock | `optional unsigned long long contents` | Red badge counter rendered on PWA app icon | Throws `TypeError` if contents < 0 or invalid; requires user permission | W3C Badging API §3 |
| 20 | W3C Badging API | `navigator.clearAppBadge()` | Clears badge counter from PWA Home Screen icon | None | Removes badge dot/number from app icon | Silently handled if no badge set | W3C Badging API §3 |
| 21 | W3C Badging API | `WorkerNavigator.setAppBadge` | Background badging from Service Worker | `contents: number` | PWA badge set directly during `push` event | Available in `ServiceWorkerGlobalScope` | W3C Badging API §3.2 |
| 22 | Apple Web Push | Web App Home Screen PWA Requirement | Prerequisite for Web Push on iOS 16.4+ / iPadOS 16.4+ | Web Manifest with `display: "standalone"` or `"fullscreen"` | Permits user notification prompt in standalone PWA | Push subscription rejected if run in standard Safari browser tab | WebKit Blog (Feb 2023) / Apple Dev Docs |
| 23 | Apple Web Push | Direct User Gesture Requirement | Explicit user interaction to request push permission | Button click / tap calling `Notification.requestPermission()` | Triggers system permission prompt dialog | Blocked / rejected if called without user interaction | WebKit Blog / Apple Dev Docs |
| 24 | Apple Web Push | APNs RFC 8030 Edge Gateway | Push delivery endpoint hosted by Apple | URL matching `https://*.push.apple.com/...` | HTTP 201 Created with `apns-id` header | 400 Bad Request, 403 Forbidden, 410 Gone, 413 Payload Too Large | Apple Developer UserNotifications Guide |
| 25 | Apple Web Push | RFC 8030 Request Headers | Standard HTTP/1.1 or HTTP/2 headers for APNs web push | `TTL`, `Urgency`, `Topic`, `Authorization`, `Content-Encoding` | Configures APNs expiration, priority, and collapse | Error codes `BadTtl`, `BadUrgency`, `BadWebPushTopic` | Apple Developer UserNotifications Guide |
| 26 | Apple Web Push | APNs Urgency to Priority Mapping | Protocol translation from WebPush Urgency to APNs priority | `Urgency: high` -> 10, `Urgency: normal/low` -> 5 | Determines whether device wakes immediately | Rejection if `Urgency` is invalid value | RFC 8030 §5.3 / Apple APNs Guide |
| 27 | Apple Web Push | APNs Topic to Collapse-ID Mapping | Gateway-level message coalescing | `Topic: string` (max 32 chars URL-safe Base64) | Replaces pending notifications before device awakens | `BadWebPushTopic` if > 32 chars or invalid charset | RFC 8030 §5.4 / Apple Dev Docs |
| 28 | Apple Web Push | Strict Visible Push Enforcement | WebKit requirement for immediate user display | Service Worker MUST call `registration.showNotification()` | User sees push alert | **Safari actively REVOKES site push permission** if push does not show notification | Apple Developer UserNotifications Guide §30 |
| 29 | Chromium Web Push | `userVisibleOnly: true` Enforced | Mandatory subscription flag in Chromium | `pushManager.subscribe({ userVisibleOnly: true })` | Push subscription token | `NotAllowedError` if `userVisibleOnly: false` | Chromium Push Messaging Spec |
| 30 | Chromium Web Push | Push Budget & Silent Fallback | Heuristic enforcement for user-visible notifications | Push event without calling `showNotification()` | Consumes push budget | Displays default fallback banner: *"This site has been updated in the background"* | Chromium Push API Implementation |
| 31 | VAPID Standard | RFC 8292 Application Identification | ECDSA P-256 JWT identification of server | `Authorization: vapid t=..., k=...` or `Bearer ...` | Cryptographic proof of origin identity | 403 Forbidden (`VapidPkHashMismatch`, `BadJwtToken`) | IETF RFC 8292 / RFC 8291 |
| 32 | WebPush Encryption | RFC 8291 Message Encryption | ECDH P-256 + AES-128-GCM payload encryption | Encrypted ciphertext + salt + local public key | End-to-end encrypted payload (max 4096 octets) | 413 `PayloadTooLarge` if > 4096 bytes; 400 `BadWebPushRequest` | IETF RFC 8291 |

---

## Edge Cases

| # | Feature | Input | Observed Behavior |
|---|---------|-------|-------------------|
| 1 | W3C Notifications | `options.silent = true` AND `options.vibrate = [200, 100]` | **Browser throws `TypeError`**. WHATWG Notifications algorithm Step 2 explicitly mandates throwing a `TypeError` if both `silent: true` and `vibrate` are present. |
| 2 | W3C Notifications | `options.renotify = true` AND `options.tag = ""` | **Browser throws `TypeError`**. WHATWG Notifications algorithm Step 3 explicitly mandates throwing a `TypeError` if `renotify: true` and `tag` is empty. |
| 3 | W3C Notifications | `new Notification(title, { actions: [...] })` in Window scope | **Browser throws `TypeError`**. Non-persistent notifications constructed via `new Notification()` do not support action buttons; actions are only permitted in persistent Service Worker notifications (`registration.showNotification`). |
| 4 | W3C Notifications | `new Notification(...)` inside `ServiceWorkerGlobalScope` | **Browser throws `TypeError`**. The constructor algorithm Step 1 throws `TypeError` if `this`'s relevant global object is `ServiceWorkerGlobalScope`. Service workers must use `self.registration.showNotification()`. |
| 5 | Apple WebKit PWA | Web Push received without calling `registration.showNotification()` | **Safari permanently revokes push permission for the origin**. Apple documentation explicitly warns: *"Safari doesn't support invisible push notifications. Present push notifications to the user immediately after your service worker receives them. If you don't, Safari revokes the push notification permission for your site."* |
| 6 | Chromium Android | Web Push received without calling `registration.showNotification()` | **Chromium displays default system banner**: *"This site has been updated in the background."* Repeated failures deplete the push budget and trigger subscription revocation. |
| 7 | Web Badging API | `navigator.setAppBadge(-5)` or `NaN` | **Browser throws `TypeError`**. The WebIDL definition specifies `[EnforceRange] unsigned long long contents`. Negative values or NaN violate range enforcement. |
| 8 | Web Badging API | `navigator.setAppBadge(0)` | **Browser clears badge**. Passing `0` is equivalent to calling `navigator.clearAppBadge()`. |
| 9 | Web Badging API | `navigator.setAppBadge()` (undefined contents) | **Browser displays uncounted badge flag / dot**. Displays an active dot without a specific integer count. |
| 10 | Apple APNs Web Push | Payload ciphertext size > 4096 octets | **APNs returns HTTP 413 `PayloadTooLarge`**. Maximum WebPush payload size across Apple and Google gateways is strictly 4 KB (4096 bytes). |
| 11 | Apple APNs Web Push | `Topic` header length > 32 characters or invalid chars | **APNs returns HTTP 400 `BadWebPushTopic`**. Topic header must be $\le 32$ characters from URL-safe Base64 set (`[A-Za-z0-9_-]`). |
| 12 | Apple APNs Web Push | `Urgency` header contains unsupported string (e.g. `urgent`) | **APNs returns HTTP 400 `BadUrgency`**. Permitted values are strictly `very-low`, `low`, `normal`, `high`. |
| 13 | Cross-Device Read | Device A marks read; server sends empty silent push to Device B | **iOS revokes permission on Device B; Chrome shows fallback banner**. Server must NOT send empty silent push for cross-device dismissal to background mobile devices. Instead, sync is handled via Pusher for active tabs, and via coalescing tags or next-app-launch sync for background devices. |
| 14 | Notification Actions | Providing > 2 actions on mobile | **Excess actions are silently sliced/ignored**. `Notification.maxActions` is typically 2 on Android and 0-2 on desktop. excess actions beyond `Notification.maxActions` are discarded. |
| 15 | Rich Media Images | Providing `image` with relative URL (e.g. `image: '/img/card.png'`) | **Image fails to load if baseURL parsing fails in ServiceWorker**. All image and icon URLs should be resolved to absolute HTTPS URLs on the backend before transmission. |

---

## 5-Component Handoff Report

### 1. Observation

Direct examination of repository files, vendor libraries, and authoritative standards revealed:

1. **Backend Implementation (`src/PushNotificationService.php`)**:
   - Lines 35–48: `isAllowedPushEndpoint()` validates host suffixes: `push.apple.com`, `fcm.googleapis.com`, `googleapis.com`, `push.services.mozilla.com`, `notify.windows.com`, `push.amazon.com`. This correctly permits `web.push.apple.com`.
   - Lines 104–107: Instantiates `new WebPush($auth, ['timeout' => 3])`.
   - Lines 110–122: Base payload is hardcoded:
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
   - Lines 174: Calls `$webPush->queueNotification($subscriptionObject, $payload ?: null)`. Notice that the 3rd parameter `$options` (which accepts `TTL`, `urgency`, and `topic`) is completely omitted.
   - Missing fields in payload: `image` (inline rich media), `vibrate` (haptic array), `renotify` (boolean), `requireInteraction` (boolean), `silent` (boolean), `dir`, `lang`, and `data` object.

2. **Orchestrator Cross-Device Dismissal (`src/NotificationOrchestrator.php`)**:
   - Lines 214–224 in `markAsRead()`:
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
   - This dispatches an empty message with `isSilent: true` across all user push subscriptions.

3. **Vendor WebPush Library (`vendor/minishlink/web-push/src/WebPush.php`)**:
   - Lines 287–295 in `prepare()`:
     ```php
     $headers['TTL'] = $options['TTL'];
     if (isset($options['urgency'])) {
         $headers['Urgency'] = $options['urgency'];
     }
     if (isset($options['topic'])) {
         $headers['Topic'] = $options['topic'];
     }
     ```
   - Library natively supports RFC 8030 headers (`TTL`, `Urgency`, `Topic`) via the `$options` array passed to `queueNotification($sub, $payload, $options)`.

4. **Official Apple Documentation (`sending-web-push-notifications-in-web-apps-and-browsers.md`)**:
   - Lines 30: *"Safari doesn't support invisible push notifications. Present push notifications to the user immediately after your service worker receives them. If you don't, Safari revokes the push notification permission for your site."*
   - Lines 58–65: Confirms Apple APNs Web Push follows RFC 8030 standard headers: `TTL`, `Authorization` (VAPID), `Content-Encoding` (`aes128gcm`), `Topic` (max 32 chars), and `Urgency` (`very-low`, `low`, `normal`, `high`).
   - Lines 66–70: Home Screen web apps on iOS 16.4+ use standard `navigator.setAppBadge` and `navigator.clearAppBadge`.

5. **WHATWG Notifications API Specification (`https://notifications.spec.whatwg.org/`)**:
   - Lines 717–725: Throws `TypeError` if `silent: true` + `vibrate`, or `renotify: true` + empty `tag`.
   - Lines 1360–1375: Defines full `NotificationOptions` dictionary.

---

### 2. Logic Chain

1. **Native Parity Gap**:
   - Native mobile notifications on Android and iOS provide rich inline image previews (`image`), tactile haptic patterns (`vibrate`), persistent interaction controls (`requireInteraction`), re-alerting on updates (`renotify`), and status bar stencils (`badge`) alongside app icon counters (`badgeCount`).
   - `PushNotificationService.php` currently supports only text, a single icon, basic action buttons, and a badge count. Because it omits `image`, `vibrate`, `renotify`, `requireInteraction`, and `silent`, PWAs across the portfolio cannot match native app visual and haptic quality.

2. **The iOS WebKit Silent Push Hazard**:
   - In `NotificationOrchestrator.php:214-224`, `markAsRead()` attempts a cross-device dismissal by sending `sendPush(message: '', title: '', isSilent: true, syncAction: 'CLOSE_NOTIFICATION')`.
   - According to Apple's official documentation, if an iOS PWA receives a push event and does not call `registration.showNotification()`, Safari **permanently revokes the origin's notification permission**.
   - Furthermore, in Chromium, receiving a push without showing a notification exhausts the push budget and forces Chromium to display the generic fallback: *"This site has been updated in the background."*
   - Therefore, attempting to perform silent background sync or remote banner dismissal via empty Web Push payloads is catastrophic on WebKit and degraded on Chromium.

3. **Multi-Channel Cascade Solution**:
   - Cross-device dismissal and badge updates must be partitioned:
     - **Active / Foreground Tabs**: Synchronized instantly via Pusher WebSocket (`notification-synced` event). Tabs with focus update their in-app badges and call `navigator.setAppBadge(unreadCount)`.
     - **Background / Offline Devices**: Must NOT receive empty silent push messages. Instead, when a new notification is sent, it carries the updated `badgeCount` and uses coalescing `tag` values. When the user eventually opens the PWA on that device, `helpers/pwa-notifications.js` clears or updates the badge via `navigator.clearAppBadge()`.

4. **Gateway-Level Optimization (RFC 8030)**:
   - `minishlink/web-push` supports passing `$options = ['TTL' => ..., 'urgency' => ..., 'topic' => ...]` into `queueNotification()`.
   - By passing `urgency: 'high'` for critical/financial/security alerts, APNs translates this to priority 10 (waking low-power devices immediately).
   - Passing `topic: substr($tag, 0, 32)` ensures APNs and FCM collapse pending offline messages on their edge servers, preventing notification spam when an offline phone reconnects to cellular data.

---

### 3. Caveats

1. **iOS Safari In-Browser vs Home Screen**: Web Push and the Badging API on iOS are only available when the user adds the website to the Home Screen as a standalone PWA. When running directly inside the Safari browser tab, push permissions cannot be requested.
2. **Action Limit Heterogeneity**: While desktop browsers support up to 2–3 action buttons, Android and iOS notification trays may truncate action buttons depending on screen width and system font size. Payloads should restrict actions to at most 2 primary options.
3. **Payload Encryption Overhead**: The maximum WebPush payload limit is strictly 4096 bytes (4 KB) including encryption padding. All images and avatars must be passed as absolute URLs, never as embedded Base64 data strings.

---

### 4. Conclusion

To achieve true native parity across Android Chromium and iOS Safari without risking permission revocation or gateway rejection:
1. `PushNotificationService.php` must expand its contract to accept and defensively normalize rich notification properties: `image`, `vibrate`, `renotify`, `silent`, `requireInteraction`, `dir`, `lang`, and `data`.
2. `PushNotificationService.php` must pass RFC 8030 request options (`TTL`, `urgency`, `topic`) to `WebPush::queueNotification()`.
3. `NotificationOrchestrator.php` must eliminate raw empty silent push calls for cross-device read synchronization. Cross-device read sync must be handled via Pusher WebSocket for online tabs, and via tag coalescing and app-launch badge reset for offline devices.
4. Input validation must strictly enforce WHATWG throwing rules: stripping `vibrate` if `silent: true`, ensuring `tag` is present if `renotify: true`, and constraining payload size $\le 4096$ bytes.

---

### 5. Verification Method

1. **Syntax Check**:
   ```bash
   php -l src/PushNotificationService.php
   php -l src/NotificationOrchestrator.php
   ```
2. **Static Analysis**:
   ```bash
   vendor/bin/phpstan analyse src/PushNotificationService.php src/NotificationOrchestrator.php --level=8
   ```
3. **Automated Unit Tests**:
   Execute PHPUnit test suite covering:
   - RFC 8030 header options generation (`TTL`, `urgency`, `topic`).
   - Normalization of conflicting options (`silent: true` stripping `vibrate`; `renotify: true` requiring `tag`).
   - Web Badging integer badge resolution (`badgeCount >= 0`).
   - SSRF endpoint allowlisting (`isAllowedPushEndpoint`).
   - Payload byte size boundary verification ($\le 4096$ bytes).
4. **Invalidation Conditions**:
   - If Apple APNs returns 400 `BadUrgency` or `BadWebPushTopic`, header formatting is invalid.
   - If iOS Safari revokes push permission, a silent push was dispatched without triggering `showNotification()`.
   - If Chromium displays *"This site has been updated in the background"*, a push was received without displaying a notification.

---

## Detailed Specification Findings & Architecture

### 1. W3C Push & Notifications API Requirements

#### NotificationOptions Dictionary
```webidl
dictionary NotificationOptions {
  NotificationDirection dir = "auto";
  DOMString lang = "";
  DOMString body = "";
  USVString navigate;
  DOMString tag = "";
  USVString image;
  USVString icon;
  USVString badge;
  VibratePattern vibrate;
  EpochTimeStamp timestamp;
  boolean renotify = false;
  boolean? silent = null;
  boolean requireInteraction = false;
  any data = null;
  sequence<NotificationAction> actions = [];
};

dictionary NotificationAction {
  required DOMString action;
  required DOMString title;
  USVString navigate;
  USVString icon;
  // Chromium Blink Extension:
  DOMString type; // "button" | "text"
  DOMString placeholder;
};
```

#### Specification Constraints & Invariants
1. **Scope Safety**: `new Notification()` cannot be constructed inside `ServiceWorkerGlobalScope`. Persistent notifications must use `self.registration.showNotification(title, options)`.
2. **Action Safety**: `actions` are only valid in persistent notifications (`showNotification`).
3. **Mutual Exclusivity**:
   - `options.silent: true` with `options.vibrate` $\rightarrow$ Throws `TypeError`.
   - `options.renotify: true` with empty `options.tag` $\rightarrow$ Throws `TypeError`.

---

### 2. Web Badging API Integration

#### Specification Model
- **Interface**: Available on both `Window.navigator` and `WorkerNavigator` (`self.navigator` in Service Workers).
- `navigator.setAppBadge([EnforceRange] unsigned long long contents)`:
  - If `contents > 0`: Displays integer count on the PWA icon.
  - If `contents === 0`: Equivalent to `clearAppBadge()`.
  - If `contents` omitted: Displays an uncounted dot/flag.
- `navigator.clearAppBadge()`: Removes the badge.

#### Platform Behavior
- **iOS 16.4+ / iPadOS 16.4+**: Badging is supported for Home Screen web apps. The user can toggle Badges under iOS Settings > Notifications > [App Name]. Both foreground tabs and background service worker push events can invoke `setAppBadge`.
- **Android Chromium**: Supported via WebAPK. The badge appears as a numerical pill or dot depending on the Android launcher (Nova, Pixel Launcher, OneUI).
- **Desktop (macOS / Windows)**: Displays count on the macOS Dock icon or Windows taskbar icon.

---

### 3. Apple iOS Safari WebKit PWA Push Specifications

#### Requirements
1. **Manifest Configuration**:
   ```json
   {
     "name": "Platform App",
     "short_name": "App",
     "id": "/?app=portfolio",
     "start_url": "/",
     "display": "standalone",
     "icons": [
       { "src": "/public/img/icon-192.png", "sizes": "192x192", "type": "image/png" },
       { "src": "/public/img/icon-512.png", "sizes": "512x512", "type": "image/png" }
     ]
   }
   ```
   *Note: `id` is required in iOS 16.4+ for synchronizing Focus modes across devices.*
2. **Push Endpoint**: `https://web.push.apple.com/...`
3. **APNs Web Push Headers**:
   - `TTL`: Positive integer (seconds message remains queued; max 30 days).
   - `Urgency`: `high` (immediate, wakes radio), `normal` (standard), `low` (power-saving), `very-low`.
   - `Topic`: Max 32 characters (`[A-Za-z0-9_-]`). Maps directly to APNs collapse identifier.
   - `Authorization`: `vapid t=<JWT>, k=<PubKey>`. JWT subject must be `mailto:` or `https:`.
   - `Content-Encoding`: `aes128gcm` per RFC 8291.
4. **APNs Response Status Codes**:
   - `201 Created`: Success. Header `apns-id` contains the message UUID.
   - `400 Bad Request`: `BadTtl`, `BadUrgency`, `BadWebPushTopic`, `BadWebPushRequest`.
   - `403 Forbidden`: `VapidPkHashMismatch`, `BadJwtToken`.
   - `410 Gone`: Expired token; endpoint must be deleted from database.
   - `413 PayloadTooLarge`: Payload exceeds 4096 bytes.

---

### 4. Silent Background Sync vs Visible Notifications

#### Browser Policies
- **Apple Safari (iOS / macOS)**:
  - Invisible push is strictly prohibited.
  - Failure to show a notification immediately causes Safari to **revoke site notification permission**.
- **Chromium (Android / Desktop)**:
  - Subscriptions enforce `userVisibleOnly: true`.
  - Push messages without calling `showNotification()` deplete the push budget and trigger the default fallback: *"This site has been updated in the background."*
- **Remote Dismissal (`CLOSE_NOTIFICATION`) Architecture**:
  - Dismissing an existing notification banner via `registration.getNotifications({ tag })` and calling `n.close()` without showing a replacement notification must NEVER be sent as a background WebPush to iOS devices.
  - **Correct Pattern**:
    1. In-app open tabs receive `notification-synced` over Pusher WebSocket and dismiss toasts instantly.
    2. Background devices dismiss notifications upon user launch via `navigator.clearAppBadge()`.
    3. If an updated alert occurs, it coalesces over the same `tag` with the new remaining unread count.

---

### 5. Multi-Channel Cascade Pattern

```
                       +----------------------------------------+
                       | Trigger Event (e.g. Transaction/Alert) |
                       +-------------------+--------------------+
                                           |
                           Check Socket Presence (Heartbeat <= 60s)
                                           |
                 +-------------------------+-------------------------+
                 |                                                   |
           [User Online]                                       [User Offline]
                 |                                                   |
       Channel 1: Pusher WebSocket                         Channel 2: OS WebPush
       - Event: `new-notification`                         - RFC 8030 Urgency: high
       - Active Tab detects focus:                         - Coalescing Topic: tag
         * Shows floating glass toast                      - Full rich payload (image, actions)
         * Updates bell badge & audio                      - Sets `badgeCount`
         * Calls `navigator.setAppBadge`                             |
                 |                                      Did WebPush succeed?
      User reads within 15s?                                         |
         |               |                                +----------+----------+
       [Yes]            [No]                              |                     |
         |               |                              [Yes]                  [No]
    Dismissal Sync   Cascade to OS WebPush                |                     |
                     (Grace window fallback)          Wait for Read      Channel 3: Email
                                                          |              (Immediate Fallback)
                                                    User reads alert?
                                                          |
                                           +--------------+--------------+
                                           |                             |
                                         [Yes]                          [No]
                                           |                             |
                                  Cross-Device Read Sync          Email Fallback
                                  - Pusher `notification-synced`  (Queued 15 min)
                                  - Reset badge & in-app state
                                  - Cancel pending email
```

---

### 6. Synthesized JSON Schema & Payload Contracts

#### JSON Schema (`notification-payload.schema.json`)
```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "PwaWebPushPayload",
  "type": "object",
  "required": ["title", "body"],
  "properties": {
    "title": {
      "type": "string",
      "maxLength": 120
    },
    "body": {
      "type": "string",
      "maxLength": 500
    },
    "url": {
      "type": "string",
      "format": "uri-reference",
      "default": "/"
    },
    "icon": {
      "type": "string",
      "format": "uri-reference"
    },
    "badge": {
      "type": "string",
      "format": "uri-reference"
    },
    "image": {
      "type": "string",
      "format": "uri-reference"
    },
    "tag": {
      "type": "string",
      "maxLength": 32,
      "pattern": "^[A-Za-z0-9_-]*$"
    },
    "badgeCount": {
      "type": "integer",
      "minimum": 0
    },
    "vibrate": {
      "type": "array",
      "items": { "type": "integer", "minimum": 0 },
      "maxItems": 10
    },
    "renotify": {
      "type": "boolean",
      "default": false
    },
    "silent": {
      "type": "boolean",
      "default": false
    },
    "requireInteraction": {
      "type": "boolean",
      "default": false
    },
    "timestamp": {
      "type": "integer"
    },
    "dir": {
      "type": "string",
      "enum": ["auto", "ltr", "rtl"],
      "default": "auto"
    },
    "lang": {
      "type": "string",
      "default": "en-US"
    },
    "actions": {
      "type": "array",
      "maxItems": 2,
      "items": {
        "type": "object",
        "required": ["action", "title"],
        "properties": {
          "action": { "type": "string" },
          "title": { "type": "string" },
          "icon": { "type": "string" },
          "type": { "type": "string", "enum": ["button", "text"], "default": "button" },
          "placeholder": { "type": "string" }
        }
      }
    },
    "data": {
      "type": "object",
      "properties": {
        "notificationId": { "type": "string" },
        "category": { "type": "string" },
        "url": { "type": "string" }
      },
      "additionalProperties": true
    }
  },
  "additionalProperties": false
}
```

#### PHP Payload Contract for `PushNotificationService.php`

```php
namespace Src;

class PushNotificationService
{
    /**
     * Send rich PWA Web Push notification to user(s) with native Chromium and WebKit parity.
     *
     * @param string|int|array<int, string|int>|null $userId Target user ID or array of user IDs
     * @param string $message Main notification body text
     * @param string|null $url Navigation destination URL upon clicking notification
     * @param string $title Bold headline title
     * @param string $tag Coalescing identifier (max 32 chars URL-safe)
     * @param int|null $badgeCount Unread counter for Web Badging API (0 clears badge)
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
     * }|null $options Rich notification configuration
     * @return bool True if queued/dispatched to at least one valid subscription
     */
    public static function sendPush(
        string|int|array|null $userId,
        string $message,
        ?string $url = null,
        string $title = 'Platform Alert',
        string $tag = 'general',
        ?int $badgeCount = null,
        ?array $options = null
    ): bool;
}
```

#### Defensively Normalized Options in PHP
```php
// Normalize tag (max 32 chars, Base64/URL-safe for RFC 8030 Topic header)
$sanitizedTag = substr(preg_replace('/[^A-Za-z0-9_-]/', '', $tag) ?: 'general', 0, 32);

// Resolve silent vs vibrate conflict (WHATWG throw rule)
$isSilent = (bool)($options['silent'] ?? false);
$vibratePattern = ($isSilent || empty($options['vibrate'])) ? null : (array)$options['vibrate'];

// Resolve renotify requirement (WHATWG throw rule: renotify requires tag)
$renotify = (bool)($options['renotify'] ?? false) && ($sanitizedTag !== '');

// RFC 8030 Gateway Options
$ttl = isset($options['ttl']) && $options['ttl'] >= 0 ? (int)$options['ttl'] : 86400; // 24 hours
$urgency = in_array($options['urgency'] ?? '', ['very-low', 'low', 'normal', 'high'], true)
    ? $options['urgency']
    : 'high';

$webPushOptions = [
    'TTL'     => $ttl,
    'urgency' => $urgency,
    'topic'   => $sanitizedTag,
];
```
