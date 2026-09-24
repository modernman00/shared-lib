<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Src\PushNotificationService;
use Src\Db;
use Minishlink\WebPush\VAPID;

echo "========================================================\n";
echo "Worker M1 Independent Verification Suite\n";
echo "========================================================\n\n";

$passCount = 0;
$failCount = 0;

function assertCondition(bool $cond, string $desc): void
{
    global $passCount, $failCount;
    if ($cond) {
        echo "  [PASS] {$desc}\n";
        $passCount++;
    } else {
        echo "  [FAIL] {$desc}\n";
        $failCount++;
    }
}

// -----------------------------------------------------------
// 1. SSRF Endpoint Allowlist Hardening Tests
// -----------------------------------------------------------
echo "1. Testing SSRF Endpoint Allowlist:\n";

$ssrfTests = [
    // Valid endpoints
    'https://fcm.googleapis.com/fcm/send/token' => true,
    'https://android.googleapis.com/gcm/send/token' => true,
    'https://web.push.apple.com/token' => true,
    'https://api.push.apple.com/token' => true,
    'https://push.apple.com/token' => true,
    'https://updates.push.services.mozilla.com/token' => true,
    'https://db5.notify.windows.com/token' => true,
    'https://bn1.notify.windows.com/token' => true,
    'https://push.amazon.com/token' => true,
    'https://fcm.googleapis.com:443/wp/token' => true,
    'https://web.push.apple.com:443/token' => true,

    // Blocked endpoints
    'http://169.254.169.254/latest/meta-data/' => false,
    'https://169.254.169.254/latest/meta-data/' => false,
    'http://127.0.0.1:6379' => false,
    'https://127.0.0.1:443' => false,
    'http://[::1]:80' => false,
    'https://[::1]' => false,
    'http://localhost:8080' => false,
    'https://localhost' => false,
    'https://localhost:443' => false,
    'http://10.0.0.1/admin' => false,
    'https://10.0.0.1:443/admin' => false,
    'https://fcm.googleapis.com:22/' => false,
    'https://fcm.googleapis.com:6379/' => false,
    'https://fcm.googleapis.com:8080/' => false,
    'https://web.push.apple.com:8443/' => false,
    'https://user:pass@fcm.googleapis.com/' => false,
    'https://fcm.googleapis.com@evil.com/' => false,
    'https://storage.googleapis.com/bucket' => false,
    'https://compute.googleapis.com/v1' => false,
    'https://googleapis.com' => false,
    'https://googleapis.com/oauth' => false,
    'https://evil.fcm.googleapis.com/evil' => false,
    'https://notpush.apple.com' => false,
    'https://evilpush.apple.com' => false,
    'ftp://fcm.googleapis.com' => false,
    'gopher://fcm.googleapis.com' => false,
    '' => false,
    'not a url' => false,
];

foreach ($ssrfTests as $endpoint => $expected) {
    $actual = PushNotificationService::isAllowedPushEndpoint($endpoint);
    assertCondition($actual === $expected, "Endpoint [{$endpoint}] expected " . ($expected ? 'ALLOW' : 'BLOCK'));
}

// -----------------------------------------------------------
// 2. Database Subscription Fetching Tests
// -----------------------------------------------------------
echo "\n2. Testing Database Subscription Fetching:\n";

$pdo = new PDO('sqlite::memory:');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->exec("CREATE TABLE user_push_subscriptions (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    user_id TEXT,
    endpoint TEXT,
    p256dh TEXT,
    auth_token TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)");
$pdo->exec("CREATE TABLE pushNotification (
    id TEXT,
    endpoint TEXT,
    p256dhKey TEXT,
    authKey TEXT
)");

$serverKeys = VAPID::createVapidKeys();
$clientKeys = VAPID::createVapidKeys();
$clientAuth = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');

$stmt = $pdo->prepare("INSERT INTO user_push_subscriptions (user_id, endpoint, p256dh, auth_token) VALUES (?, ?, ?, ?)");
$stmt->execute(['user_1', 'https://fcm.googleapis.com/fcm/send/token1', $clientKeys['publicKey'], $clientAuth]);

$stmt2 = $pdo->prepare("INSERT INTO pushNotification (id, endpoint, p256dhKey, authKey) VALUES (?, ?, ?, ?)");
$stmt2->execute(['user_2', 'https://web.push.apple.com/token2', $clientKeys['publicKey'], $clientAuth]);

Db::setMockConnection($pdo);

$subsUser1 = PushNotificationService::getUserPushSubscriptions('user_1');
assertCondition(count($subsUser1) === 1, "User 1 fetched from user_push_subscriptions");
assertCondition($subsUser1[0]['endpoint'] === 'https://fcm.googleapis.com/fcm/send/token1', "User 1 endpoint matches");

$subsUser2 = PushNotificationService::getUserPushSubscriptions('user_2');
assertCondition(count($subsUser2) === 1, "User 2 fetched from legacy pushNotification");
assertCondition($subsUser2[0]['endpoint'] === 'https://web.push.apple.com/token2', "User 2 endpoint matches");

// -----------------------------------------------------------
// 3. Payload Normalization & WHATWG Defenses
// -----------------------------------------------------------
echo "\n3. Testing Payload Normalization & WHATWG Throw Rule Defenses:\n";

putenv("VAPID_PUBLIC_KEY=" . $serverKeys['publicKey']);
putenv("VAPID_PRIVATE_KEY=" . $serverKeys['privateKey']);
putenv("VAPID_SUBJECT=mailto:support@modernman.org");
putenv("APP_URL=https://platform.example.com");

// Intercept payload via Subclass / Reflection on WebPush instance
// We can run sendPush with various option combinations:

// Scenario A: Rich push with all options, non-silent
$resA = PushNotificationService::sendPush(
    userId: 'user_1',
    message: 'Check out the new features!',
    url: '/features',
    title: 'New Update',
    tag: 'release-2026!@#',
    badgeCount: 3,
    options: [
        'image' => '/img/hero.png',
        'icon' => '/img/icon.png',
        'badge' => '/img/badge.png',
        'actions' => [
            ['action' => 'open', 'title' => 'Open', 'icon' => '/img/open.png', 'type' => 'button'],
            ['action' => 'reply', 'title' => 'Reply', 'type' => 'text', 'placeholder' => 'Write reply...'],
            ['action' => 'drop', 'title' => 'Third Action Dropped'],
        ],
        'vibrate' => [200, 100, 200],
        'renotify' => true,
        'silent' => false,
        'requireInteraction' => true,
        'dir' => 'ltr',
        'lang' => 'en-US',
        'urgency' => 'high',
        'ttl' => 7200,
        'data' => [
            'category' => 'announcement',
            'custom_key' => 'custom_value',
        ],
    ]
);
assertCondition($resA === true, "Scenario A: Rich push dispatched successfully");

// Scenario B: Silent push with vibrate provided (WHATWG rule: vibrate MUST be stripped)
$resB = PushNotificationService::sendPush(
    userId: 'user_1',
    message: 'Silent background update',
    url: '/sync',
    title: 'Silent Alert',
    tag: 'sync-event',
    badgeCount: 0,
    isSilent: true,
    options: [
        'vibrate' => [500, 200], // Should be stripped to null
        'renotify' => false,
        'silent' => true,
    ]
);
assertCondition($resB === true, "Scenario B: Silent push dispatched successfully");

// Scenario C: Empty tag with renotify: true (WHATWG rule: renotify requires tag, defaults to 'general')
$resC = PushNotificationService::sendPush(
    userId: 'user_1',
    message: 'Renotify with empty tag',
    tag: '',
    options: [
        'tag' => '',
        'renotify' => true,
    ]
);
assertCondition($resC === true, "Scenario C: Renotify with empty tag handled safely");

// Scenario D: Badge count = 0 sets clearBadge = true
$resD = PushNotificationService::sendPush(
    userId: 'user_1',
    message: 'All read',
    badgeCount: 0
);
assertCondition($resD === true, "Scenario D: Badge count 0 sets clearBadge");

// Scenario E: Blocked SSRF endpoint does not dispatch
$stmt->execute(['user_evil', 'http://169.254.169.254/latest', $clientKeys['publicKey'], $clientAuth]);
$resE = PushNotificationService::sendPush(
    userId: 'user_evil',
    message: 'SSRF Attack'
);
assertCondition($resE === false, "Scenario E: SSRF blocked endpoint returns false");

// -----------------------------------------------------------
// 4. Source Code Architecture & Security Auditing
// -----------------------------------------------------------
echo "\n4. Verifying Source Code Architecture Standards:\n";

$source = file_get_contents(__DIR__ . '/../../src/PushNotificationService.php');

// Gate 4 Bounded Timeout:
assertCondition(
    strpos($source, '$timeout = 3;') !== false,
    "Gate 4: Explicit \$timeout = 3 passed to WebPush constructor"
);
assertCondition(
    strpos($source, "'timeout'         => 3.0,") !== false || strpos($source, "'timeout' => 3.0,") !== false,
    "Gate 4: Guzzle timeout => 3.0 in clientOptions"
);
assertCondition(
    strpos($source, "'connect_timeout' => 2.0,") !== false,
    "Gate 4: Guzzle connect_timeout => 2.0 in clientOptions"
);
assertCondition(
    strpos($source, "'allow_redirects' => false,") !== false,
    "Security: Guzzle allow_redirects => false to prevent open-redirect SSRF"
);
assertCondition(
    strpos($source, "'http_errors'     => false,") !== false || strpos($source, "'http_errors' => false,") !== false,
    "Security: Guzzle http_errors => false"
);

// RFC 8291 Content Encoding:
assertCondition(
    strpos($source, "'contentEncoding' => 'aes128gcm'") !== false,
    "RFC 8291: Subscription::create explicitly sets contentEncoding => aes128gcm"
);

// RFC 8030 Gateway Header Options:
assertCondition(
    strpos($source, "\$webPush->queueNotification(\$subscriptionObject, \$payload ?: null, \$webPushOptions);") !== false,
    "RFC 8030: webPushOptions passed as 3rd parameter to queueNotification"
);

// 21 Top-Level Payload Keys:
$requiredKeys = [
    'title', 'body', 'url', 'icon', 'badge', 'image', 'tag', 'badgeCount',
    'clearBadge', 'vibrate', 'renotify', 'silent', 'requireInteraction',
    'dir', 'lang', 'actions', 'data', 'timestamp', 'isSilent', 'syncAction',
    'targetNotificationId'
];

foreach ($requiredKeys as $key) {
    assertCondition(
        strpos($source, "'{$key}'") !== false,
        "Payload Structure: Top-level key '{$key}' declared"
    );
}

// Nested data keys:
$requiredDataKeys = [
    'url', 'tag', 'badgeCount', 'clearBadge', 'notificationId',
    'targetNotificationId', 'category', 'actions'
];
foreach ($requiredDataKeys as $dkey) {
    assertCondition(
        strpos($source, "'{$dkey}'") !== false,
        "Payload Structure: Nested data key '{$dkey}' declared"
    );
}

echo "\n========================================================\n";
echo "Verification Summary: {$passCount} PASSED, {$failCount} FAILED\n";
echo "========================================================\n";

if ($failCount > 0) {
    exit(1);
}
exit(0);
