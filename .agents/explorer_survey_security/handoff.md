# Handoff Report: Security, SSRF & Test Infrastructure Analysis for PWA Notification System

## 1. Observation

### 1.1 Existing Push Implementation in `PushNotificationService.php`
- **Location**: `src/PushNotificationService.php` (lines 27–51, 85–107, 151–177, 183–211)
- **SSRF Validation Logic (Lines 27–51)**:
  ```php
  public static function isAllowedPushEndpoint(string $endpoint): bool
  {
      $parsed = parse_url($endpoint);
      if (!$parsed || empty($parsed['host']) || empty($parsed['scheme']) || $parsed['scheme'] !== 'https') {
          return false;
      }

      $host = strtolower($parsed['host']);
      $allowedSuffixes = [
          'push.apple.com',
          'fcm.googleapis.com',
          'googleapis.com',
          'push.services.mozilla.com',
          'notify.windows.com',
          'push.amazon.com',
      ];

      foreach ($allowedSuffixes as $suffix) {
          if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
              return true;
          }
      }

      return false;
  }
  ```
  - **Defect 1.1A (Overly Broad Domain Matching)**: `'googleapis.com'` allows *any* subdomain of `googleapis.com` (e.g. `storage.googleapis.com`, `compute.googleapis.com`, `iam.googleapis.com`). Google's WebPush push service is strictly `fcm.googleapis.com` and `android.googleapis.com`.
  - **Defect 1.1B (Unchecked Port Manipulation)**: `$parsed['port']` is not inspected. An endpoint like `https://fcm.googleapis.com:22/` or `https://fcm.googleapis.com:6379/` passes validation, allowing port scanning or TCP hang exploits.
  - **Defect 1.1C (Unchecked UserInfo)**: `$parsed['user']` and `$parsed['pass']` are not verified, exposing potential URL parser differentials between PHP `parse_url` and cURL.

- **David Gate 4 Timeout Bug (Lines 104–107)**:
  ```php
  $defaultOptions = [
      'timeout' => 3, // Bounded 3-second timeout (Gate 4)
  ];
  $webPush = new WebPush($auth, $defaultOptions);
  ```
  - **Direct Verification in Vendor**: `vendor/minishlink/web-push/src/WebPush.php` line 64:
    ```php
    public function __construct(
        array $auth = [],
        array $defaultOptions = [],
        ?int $timeout = 30,
        array $clientOptions = []
    )
    ```
    - Parameter 2 (`$defaultOptions`) only parses keys: `TTL`, `urgency`, `topic`, `batchSize`, `requestConcurrency`, `contentType` (`WebPush.php:382-387`). It completely ignores `'timeout' => 3`!
    - Parameter 3 (`$timeout`) defaults to `30` seconds.
    - Parameter 4 (`$clientOptions`) is empty `[]`.
    - Lines 76–79:
      ```php
      if (!array_key_exists('timeout', $clientOptions) && isset($timeout)) {
          $clientOptions['timeout'] = $timeout;
      }
      $this->client = new Client($clientOptions);
      ```
    - **Consequence**: The Guzzle client's actual execution timeout is **30 seconds** (not 3 seconds).
    - **Consequence**: `connect_timeout` is completely unset, defaulting to the OS TCP connect timeout (60–120 seconds).
    - **Consequence**: `allow_redirects` defaults to Guzzle's default (`true`), creating an open-redirect SSRF vulnerability if an allowed service issues a 301/302 redirect to `http://169.254.169.254/`.

- **VAPID & Content Encoding (Lines 151–177)**:
  - `Subscription::create` is called with:
    ```php
    $subscriptionObject = Subscription::create([
        'endpoint' => $endpoint,
        'keys' => [
            'p256dh' => $p256dh,
            'auth' => $auth,
        ],
    ]);
    ```
  - Direct inspection of `vendor/minishlink/web-push/src/Subscription.php:48` shows:
    `$associativeArray['contentEncoding'] ?? "aesgcm"`
  - Because `contentEncoding` is omitted, it defaults to `"aesgcm"` (draft-ietf-webpush-encryption-04), generating `Authorization: WebPush ...` and `Crypto-Key: p256ecdsa=...` headers. Modern WebPush (RFC 8291 / RFC 8292) requires `aes128gcm` with `Authorization: vapid t=..., k=...`.

### 1.2 Multi-Channel Notification Orchestrator
- **Location**: `src/NotificationOrchestrator.php`
- Uses `Db::connect2()` with parameterised PDO statements (`:id`, `:uid`, `:cat`, `:pri`, `:title`, `:body`, `:url`, `:tag`, `:meta`).
- Sanitises inputs: `strip_tags($title)`, `strip_tags($body)`, `preg_replace('/[^A-Za-z0-9_-]/', '', $tag)`.
- Pusher broadcast timeout set to `2` seconds (`timeout => 2` in `broadcastPusher()`).
- Unread badge counter calculation handles fallbacks safely.

### 1.3 Static Analysis & Quality Gate Baseline
- **Tool Execution**: `./vendor/bin/phpstan analyse src/PushNotificationService.php src/NotificationOrchestrator.php --level=8`
  - Result: `[OK] No errors` (0 syntax errors, 0 PHPStan L8 errors).
- **Tool Execution**: `php -l src/PushNotificationService.php && php -l src/NotificationOrchestrator.php`
  - Result: `No syntax errors detected`.
- **Database Mocking Capability**: `src/Db.php:90-98` provides `Db::setMockConnection(PDO $pdo)` and `Db::clearMockConnection()`. An in-memory SQLite PDO (`new PDO('sqlite::memory:')`) can be injected for 100% network-isolated unit testing.

---

## 2. Logic Chain

### 2.1 Push Service Endpoint SSRF Vulnerability Analysis
1. **Attack Vector Mechanics**:
   - Clients register WebPush subscriptions via client-side JavaScript (`pushManager.subscribe(...)`) and POST their `PushSubscription` payload to the backend application server.
   - If the backend blindly POSTs to arbitrary URLs in `endpoint`, an adversary can submit internal network targets:
     - **Cloud Metadata Services**: `http://169.254.169.254/latest/meta-data/` (AWS IMDSv1), `http://metadata.google.internal/computeMetadata/v1/` (GCP), or `http://169.254.169.254/metadata/instance` (Azure). An attacker stealing IAM/OAuth tokens gains direct control over cloud infrastructure.
     - **Loopback & Local Services**: `http://127.0.0.1:6379/` (Redis), `http://127.0.0.1:9200/` (Elasticsearch), `http://127.0.0.1:3306/` (MySQL), `http://localhost:8080/` (internal microservices).
     - **RFC 1918 Private Ranges**: `10.0.0.0/8`, `172.16.0.0/12`, `192.168.0.0/16`.
     - **IPv6 Evasions**: `[::1]`, `[fe80::...]`, or IPv4-mapped IPv6 `[::ffff:127.0.0.1]`.
     - **Non-Standard Ports**: E.g. `https://fcm.googleapis.com:22/` or `https://fcm.googleapis.com:25/` (SMTP relay probing / TCP hang).
     - **Protocol Smuggling**: `gopher://`, `file:///etc/passwd`, `dict://`.

2. **Analysis of Legitimate Push Service Providers**:
   - Web Push specifications mandate that Push Services MUST accept notifications over HTTPS.
   - The global ecosystem consists of a finite, well-defined set of major Push Service Gateways:
     - **Google FCM / Chrome / Android**:
       - `fcm.googleapis.com` (`https://fcm.googleapis.com/fcm/send/...` or `https://fcm.googleapis.com/wp/...`)
       - `android.googleapis.com` (`https://android.googleapis.com/gcm/send/...`)
       - *Note*: Permitting bare `*.googleapis.com` is unsafe because Google Cloud APIs (Storage, IAM, Compute) share that apex domain.
     - **Apple APNs WebPush (iOS 16.4+ Safari PWA & macOS Safari)**:
       - `*.push.apple.com` (e.g. `web.push.apple.com`, `api.push.apple.com`, `1-web.push.apple.com`).
     - **Mozilla Autopush (Firefox)**:
       - `*.push.services.mozilla.com` (e.g. `updates.push.services.mozilla.com`).
     - **Microsoft Windows Notification Service (Edge / Windows WNS)**:
       - `*.notify.windows.com` (e.g. `db5.notify.windows.com`, `bn1.notify.windows.com`).
     - **Amazon Device Messaging (Fire OS / Silk)**:
       - `*.push.amazon.com`.

3. **Safe URL Parsing & DNS Rebinding Defense**:
   - Step 1: Reject unparseable URLs (`parse_url($endpoint) === false`).
   - Step 2: Strict scheme check: `$parsed['scheme'] === 'https'` (case-insensitive).
   - Step 3: Strict port check: Port MUST be empty/omitted OR strictly `443`. Any other port (e.g. 80, 8080, 22, 6379) must be rejected.
   - Step 4: Strict user/pass check: `empty($parsed['user']) && empty($parsed['pass'])` to prevent credential/parser obfuscation.
   - Step 5: Host Allowlist:
     - Exact hosts: `['fcm.googleapis.com', 'android.googleapis.com']`.
     - Suffix hosts: `['.push.apple.com', '.push.services.mozilla.com', '.notify.windows.com', '.push.amazon.com']`.
   - Step 6: Guzzle Configuration: Set `'allow_redirects' => false` to eliminate open-redirect SSRF bypasses.

---

### 2.2 VAPID RFC 8292 and RFC 8291 Payload Encryption
1. **RFC 8292: Voluntary Application Server Identification (VAPID)**:
   - Operates on NIST P-256 (secp256r1) elliptic curve.
   - Application server holds a public key (65 bytes uncompressed, Base64URL-encoded) and a private key (32 bytes scalar, Base64URL-encoded).
   - VAPID JWT:
     - Header: `{"typ": "JWT", "alg": "ES256"}`
     - Claims: `aud` (Push service origin), `exp` (Expiry, $\le 24$h, typically 12h), `sub` (Contact URI: `mailto:` or `https:`).
   - Header Standards:
     - **Modern RFC 8292 (Section 2)**:
       `Authorization: vapid t=<JWT>, k=<Base64URL(public_key)>`
     - **Draft Format (draft-01)**:
       `Authorization: WebPush <JWT>` with `Crypto-Key: p256ecdsa=<Base64URL(public_key)>`.
     - In `minishlink/web-push`, when `contentEncoding` is `'aes128gcm'`, it outputs RFC 8292 `Authorization: vapid t=..., k=...`.

2. **RFC 8291: Message Encryption for Web Push**:
   - Subscriber provides `p256dh` (65-byte P-256 public key) and `auth` (16-byte authentication token).
   - Server generates an ephemeral P-256 key pair (`localPublicKey`, `localPrivateKey`).
   - Computes shared secret via ECDH: $S = \text{ECDH}(\text{localPrivateKey}, \text{userPublicKey})$.
   - Key derivation via HKDF (RFC 5869) with SHA-256:
     - $IKM = \text{HKDF-Extract}(salt=\text{userAuthToken}, IKM=S)$.
     - Derives CEK (Content Encryption Key, 16 bytes) and 12-byte nonce.
   - Payload encrypted via AEAD AES-128-GCM (`aes128gcm`).
   - Content-Coding Header: Prepends 16-byte salt, 4-byte record size (4096), 1-byte public key length (65), and 65-byte sender ephemeral public key.
   - Max payload size: 4078 octets (4096 including headers).

3. **Key Management & Secrets Hygiene**:
   - VAPID keys loaded via `$_ENV['VAPID_PUBLIC_KEY']` and `$_ENV['VAPID_PRIVATE_KEY']`.
   - Private key must NEVER be dumped into logs, stack traces, or exception messages.

---

### 2.3 Bounded cURL and Network Timeout Constraints (David Gate 4)
1. **Root Cause of Gate 4 Violation in Existing Code**:
   - `WebPush::__construct` signature places `$timeout` as parameter 3 and `$clientOptions` as parameter 4.
   - Passing `['timeout' => 3]` in parameter 2 was silently ignored by WebPush's default options parser.
   - Result: Timeout was 30s, connect timeout was unbound, and redirects were enabled.
2. **Required Architecture for Bounded Network Requests**:
   ```php
   $timeout = 3; // Total execution timeout: 3 seconds
   $clientOptions = [
       'timeout'         => 3.0,
       'connect_timeout' => 2.0, // Strict 2-second connection timeout
       'allow_redirects' => false, // Zero-trust SSRF defense
       'http_errors'     => false,
   ];
   $webPush = new WebPush($auth, $defaultOptions, $timeout, $clientOptions);
   ```
3. **Response Handling & Self-Healing Pruning**:
   - `200/201`: Success.
   - `404 Not Found` / `410 Gone`: Expired/unregistered subscription. Prune immediately from database (`user_push_subscriptions` and `pushNotification`).
   - `429 Too Many Requests`: Push service rate limit. Back off; fail over to Pusher or email.
   - `5xx / Timeouts`: Transient network error. Handled without blocking; cascade to email queue for high-priority alerts.

---

### 2.4 Static Analysis and Test Infrastructure
1. **PHPStan Level 8**:
   - Enforce `declare(strict_types=1);`.
   - Fully annotated parameter types and return types.
   - Strict array types: e.g. `array<string, mixed>`, `array<int, string>`.
   - Defensive null checks (`?? ''`, `isset()`, `empty()`).
2. **Zero-Network PHPUnit Test Framework**:
   - Tests must run in CI/CD and local environments with zero external network connectivity.
   - `Src\Db::setMockConnection()` allows injecting an in-memory SQLite PDO instance (`new PDO('sqlite::memory:')`) with preloaded schema tables (`user_push_subscriptions`, `notification_orchestration`, `notification_delivery_logs`, `user_socket_presence`).
   - Guzzle requests inside WebPush can be mocked using `GuzzleHttp\Handler\MockHandler` or via mock subscriptions.

---

### 2.5 Threat Model & PoC Attack Scenarios (Marcus & Ghost Gauntlet)
1. **Threat 1: Cloud Metadata & Private IP SSRF**:
   - Attacker attempts: `http://169.254.169.254/latest/meta-data/`, `http://127.0.0.1:6379/`, `http://10.0.0.1/`.
   - Neutralization: `isAllowedPushEndpoint()` checks scheme (`https`), port (`443`), and strict domain allowlist. Returns `false`.
2. **Threat 2: Open Redirect SSRF**:
   - Attacker attempts: `https://fcm.googleapis.com/redirect?url=http://169.254.169.254/`.
   - Neutralization: Guzzle `$clientOptions['allow_redirects'] = false`. Redirect is not followed.
3. **Threat 3: Non-Standard Port Probing & Resource Starvation**:
   - Attacker attempts: `https://fcm.googleapis.com:22/` or `https://fcm.googleapis.com:8443/`.
   - Neutralization: `isAllowedPushEndpoint()` verifies port is null or 443; bounded `connect_timeout` (2.0s) prevents thread hangs.
4. **Threat 4: Stored XSS in PWA Toast Notifications**:
   - Attacker attempts: `<script>alert(1)</script>` or `<img src=x onerror=...>` in title/body.
   - Neutralization: Server-side `strip_tags()` in `NotificationOrchestrator` + client-side `escapeHtml()` in `helpers/pwa-notifications.js`.
5. **Threat 5: ReDoS in Tag/Host Sanitisation**:
   - Attacker attempts: 10,000-character payload with repeated backslashes.
   - Neutralization: Character class `preg_replace('/[^A-Za-z0-9_-]/', '', $tag)` runs in linear $O(n)$ time.
6. **Threat 6: Credential / VAPID Key Leak in Logs**:
   - Attacker attempts: Inducing exceptions to trigger verbose stack dumps.
   - Neutralization: PHP 8.2 `#[\SensitiveParameter]` on private keys; logs strictly record endpoint and error reason without tokens or secrets.

---

## 3. Caveats
1. **External Network Isolation**: In offline development or restricted CI environments, real push delivery to Apple or Google servers cannot be verified with live devices. Mocks and unit tests must verify payload formatting, encryption derivation, and URL validation.
2. **Legacy Table Compatibility**: The codebase supports both `user_push_subscriptions` (modern schema) and `pushNotification` (legacy schema). Both must be maintained to prevent regressions across existing apps.
3. **Browser Push Service Diversity**: While Google FCM, Apple APNs, Mozilla Autopush, and Microsoft WNS cover >99.9% of all devices worldwide, regional browsers (e.g. UC Browser in Asia) route through FCM or Mozilla infrastructure, which are fully supported by the allowlist.

---

## 4. Conclusion
1. **SSRF Hardening**:
   - Remove generic `'googleapis.com'` from allowlist; restrict strictly to `'fcm.googleapis.com'` and `'android.googleapis.com'`.
   - Validate that the URL port is either empty/null or strictly `443`.
   - Reject any user/password components in endpoint URLs.
   - Enforce `'allow_redirects' => false` in Guzzle client options.
2. **David Gate 4 Resolution**:
   - Fix the `WebPush` instantiation in `PushNotificationService.php` to pass `$timeout = 3` as the 3rd argument and `$clientOptions` (`['timeout' => 3.0, 'connect_timeout' => 2.0, 'allow_redirects' => false, 'http_errors' => false]`) as the 4th argument.
3. **VAPID & Modern Encryption**:
   - Explicitly supply `'contentEncoding' => 'aes128gcm'` in `Subscription::create()` to ensure RFC 8291 / RFC 8292 compliance with `Authorization: vapid t=..., k=...`.
4. **Test Infrastructure**:
   - Create automated PHPUnit test suites (`tests/PushNotificationServiceTest.php` and `tests/NotificationOrchestratorTest.php`) using `Db::setMockConnection(new PDO('sqlite::memory:'))` for fast, zero-network verification.

---

## 5. Verification Method

### 5.1 Independent Reproduction & Verification Commands
1. **Syntax Check**:
   ```bash
   php -l src/PushNotificationService.php
   php -l src/NotificationOrchestrator.php
   ```
   *Expected Result*: `No syntax errors detected`.

2. **PHPStan Level 8 Static Analysis**:
   ```bash
   ./vendor/bin/phpstan analyse src/PushNotificationService.php src/NotificationOrchestrator.php --level=8
   ```
   *Expected Result*: `[OK] No errors`.

3. **PHPUnit Automated Test Suite**:
   ```bash
   ./vendor/bin/phpunit tests/PushNotificationServiceTest.php
   ./vendor/bin/phpunit tests/NotificationOrchestratorTest.php
   ```
   *Expected Result*: 100% passing tests with zero network calls and execution time <1.0s.

4. **Red Team PoC Neutralization Tests (Marcus & Ghost)**:
   Verify that all invalid endpoints return `false` from `isAllowedPushEndpoint`:
   - `http://169.254.169.254/latest/meta-data/` $\rightarrow$ `false`
   - `http://127.0.0.1:6379` $\rightarrow$ `false`
   - `http://10.0.0.1/admin` $\rightarrow$ `false`
   - `https://fcm.googleapis.com:22/` $\rightarrow$ `false`
   - `https://fcm.googleapis.com@evil.com/` $\rightarrow$ `false`
   - `https://storage.googleapis.com/bucket` $\rightarrow$ `false`
   - `https://fcm.googleapis.com/fcm/send/token` $\rightarrow$ `true`
   - `https://web.push.apple.com/token` $\rightarrow$ `true`
   - `https://updates.push.services.mozilla.com/token` $\rightarrow$ `true`
   - `https://db5.notify.windows.com/token` $\rightarrow$ `true`
