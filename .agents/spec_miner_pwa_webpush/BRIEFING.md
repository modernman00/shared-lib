# BRIEFING — 2026-09-24T06:51:30Z

## Mission
Probe and document authoritative specifications, RFCs, and standards for Best-In-Class (BIC) PWA Web Push notifications across Chromium and Apple iOS Safari WebKit to define exact contracts for PushNotificationService and NotificationOrchestrator.

## 🔒 My Identity
- Archetype: Specification Miner
- Roles: Web Push & PWA Standards Researcher, Protocol Auditor
- Working directory: /Users/waleolaogun/Sites/shared-lib/.agents/spec_miner_pwa_webpush/
- Original parent: 9e0d0884-1682-4415-9101-92ecd90b15a1
- Milestone: PWA Web Push Specifications & Standards Mining

## 🔒 Key Constraints
- Discover and document features by probing authoritative specifications (W3C Push API, WHATWG Notifications API, W3C Badging API, Apple WebKit Web Push documentation / APNs Web Push RFC 8030 / RFC 8291 / RFC 8292).
- Do NOT implement code — read-only specification mining and protocol contract definition.
- Output required tables: `## Features Discovered` and `## Edge Cases`.
- Comprehensive 5-component handoff report in `handoff.md`.
- No skipping obscure features; prioritize authoritative sources.

## Current Parent
- Conversation ID: 9e0d0884-1682-4415-9101-92ecd90b15a1
- Updated: 2026-09-24T06:51:30Z

## Task Summary
- **What to build**: Comprehensive authoritative specification analysis & contract synthesis for PWA WebPush (Chromium + iOS WebKit parity, Badging API, APNs headers, silent push/dismissal mechanics, Pusher/WebPush cascade, JSON schema).
- **Success criteria**: Exhaustive tables of features, edge cases, APNs headers, WebKit requirements, JSON schema contracts, and architectural handoff.
- **Interface contracts**: W3C Push API, WHATWG Notifications API, W3C Badging API, Apple Developer Web Push documentation, RFC 8030 / RFC 8291 / RFC 8292.
- **Code layout**: Document findings in `.agents/spec_miner_pwa_webpush/handoff.md`.

## Key Decisions Made
- Confirmed Apple Web Push on iOS 16.4+ / 17+ uses standard RFC 8030 headers (`TTL`, `Urgency`, `Topic`, `Authorization`, `Content-Encoding`) rather than native APNs binary headers.
- Identified strict Safari rule: Invisible/silent push is strictly prohibited; failure to show notification immediately revokes push permission.
- Identified Chromium rule: Push without `showNotification` consumes push budget and triggers "This site has been updated in the background" fallback.
- Identified WHATWG throwing rules: `silent: true` + `vibrate` throws `TypeError`; `renotify: true` + empty `tag` throws `TypeError`; `new Notification()` in `ServiceWorkerGlobalScope` throws `TypeError`.
- Designed exact JSON schema and PHP method signatures for `PushNotificationService.php`.

## Artifact Index
- DISPATCH.md — Initial dispatch prompt
- BRIEFING.md — Working memory and identity
- progress.md — Liveness heartbeat and progress tracking
- handoff.md — Comprehensive authoritative specification findings and contracts
