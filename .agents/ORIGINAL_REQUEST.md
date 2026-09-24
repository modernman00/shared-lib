# Original User Request

## 2026-09-24T06:30:31Z

Make the PWA notification system (`src/PushNotificationService.php` and `src/NotificationOrchestrator.php` in `/Users/waleolaogun/Sites/shared-lib`) world-class, matching Best-In-Class (BIC) native app capabilities across all portfolio apps. Segun (PWA/Mobile Lead) and the full governance team must thoroughly oversee design and verification.

Working directory: /Users/waleolaogun/Sites/shared-lib
Integrity mode: demo

## Requirements

### R1. Native-Parity PWA Push Payload & Capabilities
Enhance `PushNotificationService.php` to support rich action buttons, inline images/media attachments, custom vibration patterns, coalescing tags, silent background sync, and Web Badging API integration. Ensure strict WebKit (iOS Safari PWA) and Chromium parity.

### R2. Orchestration, Cross-Device Sync & Multi-Channel Cascade
Refine `NotificationOrchestrator.php` to handle multi-device cross-device badge sync, automatic banner dismissal on read (`CLOSE_NOTIFICATION`), intelligent Pusher in-app socket vs OS WebPush cascade, and zero-loss delivery logging.

### R3. Security & Governance Compliance
Maintain strict compliance with Master Engineering Governance:
- SSRF endpoint allowlist validation.
- VAPID RFC 8292 / RFC 8291 payload encryption standards.
- PHPStan Level 8 with 0 errors.
- Bounded cURL timeouts (Gate 4).
- Automated PHPUnit tests for dispatch logic and badge counter calculations.

## Acceptance Criteria

### Security & Quality Standards
- [ ] `php -l` on modified files returns 0 syntax errors.
- [ ] PHPStan analysis (`vendor/bin/phpstan analyse src/PushNotificationService.php src/NotificationOrchestrator.php --level=8`) passes with 0 errors.
- [ ] Automated PHPUnit test suite added/updated and passing for `PushNotificationService` and `NotificationOrchestrator`.
- [ ] Segun's PWA guidelines (touch targets, iOS WebKit PWA compatibility, offline background sync, badge API) strictly verified.
- [ ] SSRF security allowlist validated for push service endpoints.

## 2026-09-24T06:40:05Z

Please continue executing the PWA notification system task. Follow Segun's PWA guidelines and governance criteria.
