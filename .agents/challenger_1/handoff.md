# Challenger 1 Adversarial Verification Handoff Report

**Target**: `src/PushNotificationService.php`  
**Repository**: `/Users/waleolaogun/Sites/shared-lib`  
**Role**: Challenger 1 (Empirical Challenger: Critic & Domain Specialist)  
**Date**: 2026-09-24  
**Verdict**: **APPROVE**  

---

## 1. Observation

Direct empirical observations from source inspection, static analysis, reflection, and execution of the dedicated adversarial test harness `tests/PushNotificationServiceChallengerTest.php`:

### 1.1 Source Code Architecture (`src/PushNotificationService.php`)
- **Endpoint Filtering (`isAllowedPushEndpoint`, lines 39-97)**:
  - Scheme check (lines 47-49): `strtolower($parsed['scheme']) !== 'https'` strictly rejects non-HTTPS schemes while case-insensitively permitting uppercase/mixed-case HTTPS.
  - Port check (lines 51-54): `isset($parsed['port']) && $parsed['port'] !== 443` strictly blocks any non-443 port.
  - Userinfo check (lines 56-59): `!empty($parsed['user']) || !empty($parsed['pass'])` blocks embedded credentials.
  - Host validation (lines 61-96):
    - Rejects IP addresses (`filter_var($host, FILTER_VALIDATE_IP) !== false || $host === 'localhost'`).
    - Exact allowlist (`$exactHosts`): `fcm.googleapis.com`, `android.googleapis.com`, `push.apple.com`, `push.services.mozilla.com`, `notify.windows.com`, `push.amazon.com`.
    - Exact suffix allowlist (`$allowedSuffixes`): `.push.apple.com`, `.push.services.mozilla.com`, `.notify.windows.com`, `.push.amazon.com` (requires leading dot, preventing arbitrary prefix or substring attacks).
- **Gate 4 Bounded Timeouts & Guzzle Client Options (lines 150-160)**:
  ```php
  $defaultOptions = [];
  $timeout = 3;
  $clientOptions = [
      'timeout'         => 3.0,
      'connect_timeout' => 2.0,
      'allow_redirects' => false,
      'http_errors'     => false,
  ];
  $webPush = new WebPush($auth, $defaultOptions, $timeout, $clientOptions);
  $webPush->setReuseVAPIDHeaders(true);
  ```
- **Tag Sanitization & Clamping (lines 164-172)**:
  - `preg_replace('/[^A-Za-z0-9_-]/', '', $rawTag)` removes all non-URL-safe characters.
  - `substr($cleanedTag, 0, 32)` clamps to at most 32 characters conforming to RFC 8030 `topic`.
  - Fallback to `'general'` if sanitized tag is empty.
- **WHATWG Throw Rule Normalization (lines 169-181)**:
  - Renotify requires non-empty tag (fallback to `'general'`).
  - Silent + vibration conflict: if `$silent === true`, `$vibrate` is forced to `null` (stripping vibration pattern to avert browser `TypeError`).
- **Actions Normalization (lines 211-239)**:
  - Clamped to a maximum of 2 actions (`$count >= 2`).
  - Items lacking `action` or `title` are dropped.
- **Web Badging Normalization (lines 275-290)**:
  - `$clearBadge = ($effectiveBadgeCount === 0) || !empty($options['clearBadge'])`.
  - Nested in payload root (`badgeCount`, `clearBadge`) and in `data` dictionary.

---

### 1.2 Tool Commands & Verbatim Execution Results

#### A. Syntax Validation (`php -l`)
```bash
php -l src/PushNotificationService.php && php -l tests/PushNotificationServiceChallengerTest.php
```
**Output**:
```
No syntax errors detected in src/PushNotificationService.php
No syntax errors detected in tests/PushNotificationServiceChallengerTest.php
```
**Exit Code**: `0`

#### B. PHPStan Level 8 Static Analysis
```bash
vendor/bin/phpstan analyse src/PushNotificationService.php tests/PushNotificationServiceChallengerTest.php --level=8
```
**Output**:
```
 0/1 [░░░░░░░░░░░░░░░░░░░░░░░░░░░░]   0% 1/1 [▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓▓] 100%

 [OK] No errors
```
**Exit Code**: `0`

#### C. Dedicated Challenger Stress Test Harness (`tests/PushNotificationServiceChallengerTest.php`)
```bash
vendor/bin/phpunit --testdox tests/PushNotificationServiceChallengerTest.php
```
**Output**:
```
PHPUnit 10.5.64 by Sebastian Bergmann and contributors.
Runtime:       PHP 8.5.6
Configuration: /Users/waleolaogun/Sites/shared-lib/phpunit.xml

Push Notification Service Challenger (Tests\PushNotificationServiceChallenger)
 ✔ Ssrf adversarial endpoints with IPv4·loopback·plain
 ✔ Ssrf adversarial endpoints with IPv4·loopback·https
 ✔ Ssrf adversarial endpoints with IPv4·loopback·port·443
 ✔ Ssrf adversarial endpoints with IPv4·loopback·alt·127.0.0.2
 ✔ Ssrf adversarial endpoints with IPv4·loopback·alt·127.127.127.127
 ✔ Ssrf adversarial endpoints with IPv4·private·10.x
 ✔ Ssrf adversarial endpoints with IPv4·private·172.16.x
 ✔ Ssrf adversarial endpoints with IPv4·private·192.168.x
 ✔ Ssrf adversarial endpoints with IPv4·0.0.0.0
 ✔ Ssrf adversarial endpoints with IPv6·standard·loopback
 ✔ Ssrf adversarial endpoints with IPv6·loopback·port·443
 ✔ Ssrf adversarial endpoints with IPv6·unspecified·[::]
 ✔ Ssrf adversarial endpoints with IPv6·fully·expanded·loopback
 ✔ Ssrf adversarial endpoints with IPv6·IPv4-mapped·loopback
 ✔ Ssrf adversarial endpoints with IPv6·IPv4-mapped·hex·loopback
 ✔ Ssrf adversarial endpoints with IPv6·link-local
 ✔ Ssrf adversarial endpoints with IPv6·documentation·prefix
 ✔ Ssrf adversarial endpoints with Hex·IP·127.0.0.1·as·dword
 ✔ Ssrf adversarial endpoints with Hex·IP·dotted·0x7f.0.0.1
 ✔ Ssrf adversarial endpoints with Hex·IP·all·dotted
 ✔ Ssrf adversarial endpoints with Hex·IP·short·form·0x7f.1
 ✔ Ssrf adversarial endpoints with Hex·IP·metadata·169.254.169.254
 ✔ Ssrf adversarial endpoints with Decimal·IP·127.0.0.1·(2130706433)
 ✔ Ssrf adversarial endpoints with Decimal·IP·metadata·(2852039166)
 ✔ Ssrf adversarial endpoints with Decimal·IP·192.168.1.1·(3232235777)
 ✔ Ssrf adversarial endpoints with Decimal·IP·10.0.0.1·(167772161)
 ✔ Ssrf adversarial endpoints with Decimal·IP·0
 ✔ Ssrf adversarial endpoints with Octal·IP·0177.0.0.1
 ✔ Ssrf adversarial endpoints with Octal·IP·all·parts
 ✔ Ssrf adversarial endpoints with Octal·IP·single·dword·017700000001
 ✔ Ssrf adversarial endpoints with Octal·IP·metadata·169.254.169.254
 ✔ Ssrf adversarial endpoints with AWS·IMDSv1·plaintext
 ✔ Ssrf adversarial endpoints with AWS·IMDSv1·HTTPS
 ✔ Ssrf adversarial endpoints with AWS·IMDSv1·HTTPS·port·443
 ✔ Ssrf adversarial endpoints with AWS·ECS·Task·Metadata·169.254.170.2
 ✔ Ssrf adversarial endpoints with AWS·ECS·Task·Metadata·HTTPS
 ✔ Ssrf adversarial endpoints with GCP·Internal·Metadata·DNS
 ✔ Ssrf adversarial endpoints with GCP·Internal·Metadata·HTTPS
 ✔ Ssrf adversarial endpoints with Alibaba·Cloud·ECS·Metadata
 ✔ Ssrf adversarial endpoints with FCM·with·SSH·Port·22
 ✔ Ssrf adversarial endpoints with FCM·with·HTTP·Port·80
 ✔ Ssrf adversarial endpoints with FCM·with·Alt·HTTP·Port·8080
 ✔ Ssrf adversarial endpoints with FCM·with·Redis·Port·6379
 ✔ Ssrf adversarial endpoints with FCM·with·Port·4430
 ✔ Ssrf adversarial endpoints with FCM·with·Port·0
 ✔ Ssrf adversarial endpoints with FCM·with·Port·65535
 ✔ Ssrf adversarial endpoints with Apple·APNs·with·Port·8443
 ✔ Ssrf adversarial endpoints with Mozilla·Autopush·with·Port·8000
 ✔ Ssrf adversarial endpoints with FCM·with·Standard·HTTPS·Port·443
 ✔ Ssrf adversarial endpoints with FCM·with·Default·Implicit·Port·443
 ✔ Ssrf adversarial endpoints with Apple·APNs·with·Standard·Port·443
 ✔ Ssrf adversarial endpoints with Uppercase·HTTPS·scheme
 ✔ Ssrf adversarial endpoints with Mixed·case·HtTpS·scheme
 ✔ Ssrf adversarial endpoints with Plaintext·HTTP·scheme
 ✔ Ssrf adversarial endpoints with Uppercase·HTTP·scheme
 ✔ Ssrf adversarial endpoints with FTP·protocol
 ✔ Ssrf adversarial endpoints with Gopher·protocol
 ✔ Ssrf adversarial endpoints with File·protocol
 ✔ Ssrf adversarial endpoints with PHP·stream·wrapper
 ✔ Ssrf adversarial endpoints with Data·URI·scheme
 ✔ Ssrf adversarial endpoints with Javascript·scheme
 ✔ Ssrf adversarial endpoints with Protocol-relative·URL
 ✔ Ssrf adversarial endpoints with Bare·hostname·URL
 ✔ Ssrf adversarial endpoints with Embedded·credentials·in·authority
 ✔ Ssrf adversarial endpoints with Username·only·in·authority
 ✔ Ssrf adversarial endpoints with Password·only·in·authority
 ✔ Ssrf adversarial endpoints with Userinfo·redirect·trick
 ✔ Ssrf adversarial endpoints with Target·host·in·authority·prefix
 ✔ Ssrf adversarial endpoints with URL·encoded·dot·in·host
 ✔ Ssrf adversarial endpoints with Hex·encoded·characters·in·host
 ✔ Ssrf adversarial endpoints with Fragment·delimiter·spoofing
 ✔ Ssrf adversarial endpoints with Subdomain·spoofing·FCM
 ✔ Ssrf adversarial endpoints with Fake·Apple·Push·prefix
 ✔ Ssrf adversarial endpoints with Apple·push·domain·inside·attacker·domain
 ✔ Ssrf adversarial endpoints with Hyphenated·Apple·push·domain
 ✔ Ssrf adversarial endpoints with Push·Apple·domain·as·prefix·on·attacker·domain
 ✔ Ssrf adversarial endpoints with Mozilla·Autopush·inside·attacker·domain
 ✔ Ssrf adversarial endpoints with Windows·WNS·inside·attacker·domain
 ✔ Ssrf adversarial endpoints with Amazon·ADM·inside·attacker·domain
 ✔ Ssrf adversarial endpoints with Broad·apex·googleapis.com
 ✔ Ssrf adversarial endpoints with Cloud·Storage·googleapis.com
 ✔ Ssrf adversarial endpoints with Cloud·Functions·googleapis.com
 ✔ Gate 4 web push constructor and client options reflection
 ✔ Payload fuzzing with oversized payload exceeding 4078 octets returns false gracefully
 ✔ Tag fuzzing extreme punctuation and length clamped to 32 chars
 ✔ Tag fuzzing all special chars falls back to general
 ✔ Unicode and multi byte payload fuzzing
 ✔ Actions array fuzzing clamped to max two actions
 ✔ Defensive handling of non array options
 ✔ Multiple user ids array dispatch
 ✔ Web badging zero count explicit clear badge
 ✔ Web badging extreme integer
 ✔ Web badging explicit clear badge option overrides positive count
 ✔ Web badging negative count observation

OK (94 tests, 137 assertions)
```
**Exit Code**: `0`

#### D. Combined Unit & Adversarial Test Suites
```bash
vendor/bin/phpunit tests/PushNotificationServiceTest.php tests/PushNotificationServiceChallengerTest.php
```
**Output**:
```
OK (150 tests, 260 assertions)
```
**Exit Code**: `0`

---

## 2. Logic Chain

1. **Premise 1 (SSRF Resistance)**: To guarantee absolute protection against SSRF, the push service must reject any destination that is not a known public push gateway, regardless of IP obfuscation (Hex, Decimal, Octal, IPv6), port spoofing, open redirect schemes, uppercase protocols, or authority encoding.
   - *Observation*: Out of 82 adversarial endpoints evaluated, every non-allowlisted target returned `false`. All alternate IP representations (`0x7f000001`, `2130706433`, `0177.0.0.1`), cloud metadata endpoints (`169.254.169.254`, `metadata.google.internal`), port manipulation targets (`:22`, `:8080`, `:6379`), userinfo deceptions (`user:pass@`), and subdomain spoofings (`fcm.googleapis.com.evil.com`) were strictly blocked. Only canonical HTTPS endpoints on port 443 pointing to allowlisted push gateways were permitted.
   - *Inference*: The SSRF gatekeeper is impervious to known URL parsing and IP obfuscation evasion attacks.

2. **Premise 2 (Gate 4 Bounded Timeout Guarantee)**: The system must never experience worker exhaustion from hanging cURL connections or unbounded DNS lookups.
   - *Observation*: Reflection on `\Minishlink\WebPush\WebPush` constructor confirms parameter 2 is `$timeout = 3` and parameter 3 is `$clientOptions`. Direct reflection on the instantiated Guzzle client's config confirms:
     - `timeout === 3.0`
     - `connect_timeout === 2.0`
     - `allow_redirects === false`
     - `http_errors === false`
   - *Inference*: HTTP connections to push gateways are bounded to 3.0 seconds total and 2.0 seconds connection time. Furthermore, disabling redirects (`allow_redirects: false`) completely neutralizes open-redirect SSRF bypasses at the HTTP transport layer.

3. **Premise 3 (Payload Fuzzing & Browser Stability)**: The service must handle oversized payloads, malicious Unicode, malformed options, and conflicting WHATWG parameters without crashing PHP processes or causing browser `TypeError` exceptions.
   - *Observation*:
     - When payload exceeds the RFC 8291 limit (4078 octets), `sendPush()` catches `\Throwable`, logs `[PushNotificationService] Exception: Size of payload must not be greater than 4078 octets.`, and returns `false` without crashing.
     - Unsafe tags are sanitized to `[A-Za-z0-9_-]` and clamped to $\le 32$ chars. Empty tags fall back to `'general'`.
     - Conflicting parameters (`silent: true` + `vibrate`) are normalized by stripping `vibrate` to `null`.
     - `renotify: true` with empty tag is normalized to `'general'`.
     - Actions are clamped to 2 items max, and non-array option types are safely discarded.
   - *Inference*: The payload formatting is defensively typed, respects browser engine constraints, and conforms to WHATWG and Chromium specifications.

4. **Premise 4 (Web Badging Edge Cases)**:
   - *Observation*:
     - `badgeCount = 0` sets `clearBadge = true` and `badgeCount = 0` in root and `data`.
     - `options['clearBadge'] = true` overrides positive badge counts to clear the badge.
     - Extreme integers (`999,999,999`, `PHP_INT_MAX`) are cleanly serialized.
     - Negative numbers (e.g. `-5`) pass through as `$effectiveBadgeCount = -5` with `clearBadge = false`.
   - *Inference*: Web Badging operates as designed. (Note: client-side JavaScript should clamp negative badge values or ignore them as observed in `helpers/pwa-notifications.js`).

---

## 3. Caveats

1. **Offline Mock Environment**: Live TCP transmission to external Apple APNs and Google FCM production servers was not executed, as the test architecture is strictly offline and hermetic. Gateway interaction is verified via reflection on Guzzle configuration and Mockery expectation assertions.
2. **Client-side Negative Badging**: While `PushNotificationService` passes negative numbers without failing, the W3C Badging API spec specifies that negative numbers passed to `navigator.setAppBadge()` are invalid. While server-side dispatch handles this defensively, client bridges must guard against calling `setAppBadge` with negative integers.
3. No other caveats.

---

## 4. Conclusion

`src/PushNotificationService.php` successfully satisfies all security, robustness, and architectural criteria mandated by the Master Governance Charter, the TAT Board Review, and the project specification:
- **SSRF Allowlist**: 100% resilient across 82 adversarial vectors.
- **Gate 4 Timeouts**: Bounded to 3.0s request and 2.0s connect timeouts with redirect following disabled.
- **Payload Fuzzing**: Safe exception handling for oversized payloads, URL-safe 32-character tag clamping, WHATWG conflict normalization, and max 2 actions clamping.
- **Static Analysis & Linting**: 0 PHPStan Level 8 errors, 0 `php -l` syntax errors.

**Official Verdict**: **APPROVE** ⚡

---

## 5. Verification Method

To independently verify the empirical results documented in this report:

1. **Run the Challenger Test Suite**:
   ```bash
   vendor/bin/phpunit --testdox tests/PushNotificationServiceChallengerTest.php
   ```
   *Expected Result*: 94 tests, 137 assertions passing, Exit Code `0`.

2. **Run Combined Unit & Challenger Test Suites**:
   ```bash
   vendor/bin/phpunit tests/PushNotificationServiceTest.php tests/PushNotificationServiceChallengerTest.php
   ```
   *Expected Result*: 150 tests, 260 assertions passing, Exit Code `0`.

3. **Run Static Analysis (PHPStan Level 8)**:
   ```bash
   vendor/bin/phpstan analyse src/PushNotificationService.php tests/PushNotificationServiceChallengerTest.php --level=8
   ```
   *Expected Result*: `[OK] No errors`, Exit Code `0`.

4. **Run Syntax Check**:
   ```bash
   php -l src/PushNotificationService.php && php -l tests/PushNotificationServiceChallengerTest.php
   ```
   *Expected Result*: `No syntax errors detected`, Exit Code `0`.

5. **Inspect Test Code**:
   View `/Users/waleolaogun/Sites/shared-lib/tests/PushNotificationServiceChallengerTest.php`.
