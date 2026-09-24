## 2026-09-24T06:52:39Z
You are Worker 1 for Milestone M1 of the PWA Notification System hardening in shared-lib.

Your working directory is: /Users/waleolaogun/Sites/shared-lib/.agents/worker_m1/
The project root is: /Users/waleolaogun/Sites/shared-lib
The user's original request is at: /Users/waleolaogun/Sites/shared-lib/.agents/ORIGINAL_REQUEST.md
Master Governance Charter is at: /Users/waleolaogun/Sites/shared-lib/AGENTS.md
The project plan is at: /Users/waleolaogun/Sites/shared-lib/.agents/orchestrator_1/PROJECT.md
The TAT Board Review consensus is at: /Users/waleolaogun/Sites/shared-lib/.agents/orchestrator_1/tat_review.md
The Explorer Survey reports are at:
- /Users/waleolaogun/Sites/shared-lib/.agents/explorer_survey_codebase/handoff.md
- /Users/waleolaogun/Sites/shared-lib/.agents/spec_miner_pwa_webpush/handoff.md
- /Users/waleolaogun/Sites/shared-lib/.agents/explorer_survey_security/handoff.md

FILE OWNERSHIP: You EXCLUSIVELY own `src/PushNotificationService.php`. Do NOT modify any other files.

MANDATORY INTEGRITY WARNING:
DO NOT CHEAT. All implementations must be genuine. DO NOT hardcode test results, create dummy/facade implementations, or circumvent the intended task. A teamwork_preview_auditor will independently verify your work. Integrity violations WILL be detected and your work WILL be rejected.

YOUR OBJECTIVE:
Enhance `src/PushNotificationService.php` to achieve Best-In-Class (BIC) native-parity PWA capabilities and strict security compliance:

1. SSRF Push Endpoint Allowlist Hardening:
   - In `isAllowedPushEndpoint(string $endpoint): bool`:
     * Scheme MUST be 'https'.
     * Port MUST be empty/omitted or strictly 443. Any other port (e.g. 22, 6379, 8080) returns false.
     * User and pass MUST be empty (`empty($parsed['user']) && empty($parsed['pass'])`).
     * Exact hosts: `['fcm.googleapis.com', 'android.googleapis.com']`.
     * Exact domain suffixes: `['.push.apple.com', '.push.services.mozilla.com', '.notify.windows.com', '.push.amazon.com']`.
     * Disallow bare `googleapis.com` (to prevent arbitrary Google Cloud APIs).
     * Disallow IP addresses (IPv4, IPv6, localhost, 169.254.169.254).

2. Fix Gate 4 cURL Timeout Bug in Minishlink WebPush Constructor:
   - In `sendPush()`, instantiate `new WebPush($auth, $defaultOptions, $timeout, $clientOptions)`:
     * `$timeout = 3` (3-second execution timeout).
     * `$clientOptions = ['timeout' => 3.0, 'connect_timeout' => 2.0, 'allow_redirects' => false, 'http_errors' => false]`.
     * This fixes the 30-second timeout bug and prevents open-redirect SSRF.

3. VAPID RFC 8292 & RFC 8291 Content Encoding:
   - When calling `Subscription::create()`, explicitly supply `'contentEncoding' => 'aes128gcm'` alongside `endpoint`, `keys` (`p256dh`, `auth`).

4. Rich PWA Notification Payload & Native Capabilities:
   - Accept and process `$options` array:
     * `image`: URL for rich inline preview image (resolve relative paths if possible; ensure string).
     * `icon`: App/notification icon URL.
     * `badge`: Status bar monochrome icon URL (defaults to `/public/img/favicon/favicon-32x32.png`).
     * `actions`: Array of action buttons (up to 2 actions, each with `action`, `title`, optional `icon`, `type` ['button'|'text'], optional `placeholder`).
     * `vibrate`: Array of integers in milliseconds for haptic feedback.
     * `renotify`: Boolean flag.
     * `silent`: Boolean flag.
     * `requireInteraction`: Boolean flag.
     * `urgency`: `'very-low'|'low'|'normal'|'high'` (default `'high'`).
     * `ttl`: Integer seconds (default 86400, or 300 for silent sync).
     * `dir`: `'auto'|'ltr'|'rtl'` (default `'auto'`).
     * `lang`: String (default `'en-US'`).
     * `data`: Custom metadata array (must include `targetNotificationId`, `category`, `url`).
   - Normalization & WHATWG Throw Rule Defenses:
     * If `silent` is true, strip `vibrate` (WHATWG throws if both present).
     * If `renotify` is true, ensure `tag` is not empty (WHATWG throws if renotify is true with empty tag).
     * Sanitize `tag`: strip invalid characters (`preg_replace('/[^A-Za-z0-9_-]/', '', $tag)`), clamp to max 32 characters for RFC 8030 Topic header.
     * Web Badging API: Embed `badgeCount` in both root and `data.badgeCount`. If `$badgeCount === 0`, set `'clearBadge' => true`.
   - Dual-Level Payload Structure:
     * Top-level keys: `title`, `body`, `url`, `icon`, `badge`, `image`, `tag`, `badgeCount`, `clearBadge`, `vibrate`, `renotify`, `silent`, `requireInteraction`, `dir`, `lang`, `actions`, `data`, `timestamp`, `isSilent`, `syncAction`, `targetNotificationId`.
     * Nested `data` object containing `url`, `tag`, `badgeCount`, `notificationId`, `actions`, and any custom data.

5. RFC 8030 Gateway Headers:
   - Build `$webPushOptions = ['TTL' => $ttl, 'urgency' => $urgency, 'topic' => $sanitizedTag]`.
   - Pass `$webPushOptions` as the 3rd argument to `$webPush->queueNotification($subscriptionObject, $payload ?: null, $webPushOptions)`.

6. Strict Governance & Verification:
   - Run `php -l src/PushNotificationService.php` to verify 0 syntax errors.
   - Run `./vendor/bin/phpstan analyse src/PushNotificationService.php --level=8` to verify 0 PHPStan errors.
   - Document all changes and verification outputs in `/Users/waleolaogun/Sites/shared-lib/.agents/worker_m1/handoff.md`.
   - Send a completion message back when finished.
