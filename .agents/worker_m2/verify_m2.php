<?php

declare(strict_types=1);

require_once __DIR__ . '/../../vendor/autoload.php';

use Src\Db;
use Src\NotificationOrchestrator;
use Src\PushNotificationService;

$passed = 0;
$failed = 0;

function assertCondition(bool $cond, string $message): void {
    global $passed, $failed;
    if ($cond) {
        $passed++;
        echo "  [PASS] {$message}\n";
    } else {
        $failed++;
        echo "  [FAIL] {$message}\n";
    }
}

echo "=== Milestone M2 Verification Suite ===\n\n";

// 1. Setup in-memory SQLite database
$pdo = new class('sqlite::memory:') extends PDO {
    public function __construct(string $dsn) {
        parent::__construct($dsn);
        $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        if (method_exists($this, 'sqliteCreateFunction')) {
            @$this->sqliteCreateFunction('NOW', static fn(): string => date('Y-m-d H:i:s'));
        }
    }

    public function prepare(string $query, array $options = []): PDOStatement|false {
        $rewritten = str_replace(
            [
                'DATE_SUB(NOW(), INTERVAL 60 SECOND)',
                'ON DUPLICATE KEY UPDATE is_active = :active_update, last_heartbeat = NOW()',
            ],
            [
                'datetime("now", "-60 seconds")',
                'ON CONFLICT(user_id, channel_name) DO UPDATE SET is_active = :active_update, last_heartbeat = datetime("now")',
            ],
            $query
        );
        return parent::prepare($rewritten, $options);
    }
};

$pdo->exec('
    CREATE TABLE IF NOT EXISTS notification_orchestration (
        id VARCHAR(64) PRIMARY KEY,
        user_id VARCHAR(64) NOT NULL,
        family_code VARCHAR(64) NULL,
        category VARCHAR(32) NOT NULL,
        priority VARCHAR(32) NOT NULL,
        title VARCHAR(255) NOT NULL,
        body TEXT NOT NULL,
        action_url VARCHAR(255) NOT NULL,
        tag VARCHAR(64) NOT NULL,
        data_payload TEXT NULL,
        status VARCHAR(32) NOT NULL,
        read_at DATETIME NULL,
        created_at DATETIME NULL
    );

    CREATE TABLE IF NOT EXISTS notification (
        no INTEGER PRIMARY KEY AUTOINCREMENT,
        sender_id VARCHAR(64) NOT NULL,
        receiver_id VARCHAR(64) NOT NULL,
        notification_name VARCHAR(255) NOT NULL,
        notification_type VARCHAR(64) NOT NULL,
        notification_content TEXT NOT NULL,
        notification_status VARCHAR(32) NOT NULL,
        notification_date DATETIME NOT NULL
    );

    CREATE TABLE IF NOT EXISTS user_socket_presence (
        user_id VARCHAR(64) NOT NULL,
        channel_name VARCHAR(255) NOT NULL,
        is_active INTEGER NOT NULL,
        last_heartbeat DATETIME NOT NULL,
        PRIMARY KEY (user_id, channel_name)
    );

    CREATE TABLE IF NOT EXISTS notification_delivery_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        notification_id VARCHAR(64) NOT NULL,
        channel VARCHAR(32) NOT NULL,
        status VARCHAR(64) NOT NULL,
        details TEXT NULL,
        attempted_at DATETIME NOT NULL
    );

    CREATE TABLE IF NOT EXISTS user_push_subscriptions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        user_id VARCHAR(64) NOT NULL,
        endpoint TEXT NOT NULL,
        p256dh TEXT NOT NULL,
        auth_token TEXT NOT NULL,
        created_at DATETIME NULL
    );
');

Db::setMockConnection($pdo);

// Test 1: Empty input guards
echo "Section 1: Defensive Input Validation\n";
assertCondition(NotificationOrchestrator::dispatch('', 'social', 'low', 't', 'b') === '', 'dispatch returns empty string for empty userId');
assertCondition(!NotificationOrchestrator::markAsRead('', 'user_1'), 'markAsRead returns false for empty notificationId');
assertCondition(!NotificationOrchestrator::markAsRead('notif_1', ''), 'markAsRead returns false for empty userId');
assertCondition(!NotificationOrchestrator::markAllAsRead(''), 'markAllAsRead returns false for empty userId');
assertCondition(NotificationOrchestrator::getDeliveryLogs('') === [], 'getDeliveryLogs returns empty array for empty notificationId');

// Test 2: Rich Metadata Cascade in dispatch
echo "\nSection 2: Rich Metadata Cascade in dispatch()\n";
$userId = 'user_rich_test';
$richMeta = [
    'image'              => '/img/preview.png',
    'vibrate'            => [100, 50, 100],
    'actions'            => [
        ['action' => 'view_deal', 'title' => 'View Deal'],
    ],
    'renotify'           => true,
    'requireInteraction' => true,
    'urgency'            => 'high',
    'ttl'                => 3600,
    'dir'                => 'ltr',
    'lang'               => 'en-GB',
    'data'               => ['custom_item' => 'val_99'],
];

$notifId = NotificationOrchestrator::dispatch(
    userId: $userId,
    category: 'financial',
    priority: 'high',
    title: 'Rich Alert Title',
    body: 'Rich Alert Body with Details',
    actionUrl: '/deals/99',
    tag: 'deal_99',
    metadata: $richMeta
);

assertCondition(!empty($notifId), 'Dispatch generated valid notification ID');
assertCondition(str_starts_with($notifId, 'notif_'), 'Notification ID has notif_ prefix');

// Verify orchestration table storage
$stmt = $pdo->prepare('SELECT * FROM notification_orchestration WHERE id = ?');
$stmt->execute([$notifId]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
assertCondition(is_array($row), 'Orchestration record created');
assertCondition($row['tag'] === 'deal_99', 'Tag correctly stored');
assertCondition($row['priority'] === 'high', 'Priority correctly stored');
assertCondition($row['category'] === 'financial', 'Category correctly stored');

// Test 3: Safe Cross-Device Read Synchronization (markAsRead)
echo "\nSection 3: Safe Cross-Device Read Sync (markAsRead)\n";
// Seed email delivery log
$pdo->exec("
    INSERT INTO notification_delivery_logs (notification_id, channel, status, details, attempted_at)
    VALUES ('{$notifId}', 'email', 'queued', 'Queued for 15-minute fallback', datetime('now'))
");

$readSuccess = NotificationOrchestrator::markAsRead($notifId, $userId);
assertCondition($readSuccess, 'markAsRead returns true on success');

// Verify orchestration record is read
$stmt = $pdo->prepare('SELECT status, read_at FROM notification_orchestration WHERE id = ?');
$stmt->execute([$notifId]);
$updatedRow = $stmt->fetch(PDO::FETCH_ASSOC);
assertCondition($updatedRow['status'] === 'read', 'Status updated to read in orchestration table');
assertCondition(!empty($updatedRow['read_at']), 'read_at timestamp is populated');

// Verify pending email log is cancelled
$logStmt = $pdo->prepare('SELECT status FROM notification_delivery_logs WHERE notification_id = ? AND channel = "email"');
$logStmt->execute([$notifId]);
$emailLogStatus = $logStmt->fetchColumn();
assertCondition($emailLogStatus === 'cancelled_already_read', 'Pending email fallback cancelled on markAsRead');

// Verify unread count is 0
assertCondition(NotificationOrchestrator::getUnreadCount($userId) === 0, 'Unread count is 0 after markAsRead');

// Test 4: Batch Read Dismissal (markAllAsRead)
echo "\nSection 4: Batch Read Dismissal (markAllAsRead)\n";
$batchUser = 'user_batch_m2';
$batchIds = [];
for ($i = 1; $i <= 4; $i++) {
    $batchIds[] = NotificationOrchestrator::dispatch(
        userId: $batchUser,
        category: 'social',
        priority: 'low',
        title: "Batch Item #{$i}",
        body: "Body #{$i}",
        actionUrl: "/batch/{$i}",
        tag: "tag_{$i}"
    );
}

assertCondition(NotificationOrchestrator::getUnreadCount($batchUser) === 4, 'User has 4 pending notifications before markAllAsRead');

// Seed an email log for one of them
$pdo->exec("
    INSERT INTO notification_delivery_logs (notification_id, channel, status, details, attempted_at)
    VALUES ('{$batchIds[0]}', 'email', 'queued_immediate', 'Immediate queued', datetime('now'))
");

$allReadResult = NotificationOrchestrator::markAllAsRead($batchUser);
assertCondition($allReadResult, 'markAllAsRead returns true');
assertCondition(NotificationOrchestrator::getUnreadCount($batchUser) === 0, 'Unread count is 0 after markAllAsRead');

// Check that all batch records are read
$stmt = $pdo->prepare('SELECT COUNT(*) FROM notification_orchestration WHERE user_id = ? AND status = "pending"');
$stmt->execute([$batchUser]);
assertCondition(((int)$stmt->fetchColumn()) === 0, 'Zero pending records remaining in notification_orchestration');

// Check that email log for user was cancelled
$stmt = $pdo->prepare('SELECT status FROM notification_delivery_logs WHERE notification_id = ? AND channel = "email"');
$stmt->execute([$batchIds[0]]);
assertCondition($stmt->fetchColumn() === 'cancelled_already_read', 'Batch email log cancelled on markAllAsRead');

// Test 5: Zero-Loss Delivery Logging & getDeliveryLogs
echo "\nSection 5: Delivery Logs Audit Trail (getDeliveryLogs)\n";
$logs = NotificationOrchestrator::getDeliveryLogs($notifId);
assertCondition(is_array($logs), 'getDeliveryLogs returns array');
assertCondition(count($logs) >= 2, 'Delivery logs contain at least 2 entries (web_push and email)');

foreach ($logs as $entry) {
    assertCondition(isset($entry['channel']), 'Log entry contains channel key');
    assertCondition(isset($entry['status']), 'Log entry contains status key');
    assertCondition(array_key_exists('details', $entry), 'Log entry contains details key');
    assertCondition(isset($entry['attempted_at']) || isset($entry['created_at']), 'Log entry contains timestamp key');
}

// Test 6: Zero-Loss Logging Fallback on DB Failure
echo "\nSection 6: Zero-Loss Logging Fallback\n";
// Temporarily simulate DB table error by pointing to closed connection
Db::clearMockConnection();
// Set up invalid PDO that throws on prepare
$failingPdo = new class('sqlite::memory:') extends PDO {
    public function __construct(string $dsn) {
        parent::__construct($dsn);
    }
    public function prepare(string $query, array $options = []): PDOStatement|false {
        throw new PDOException('Simulated table disk I/O failure');
    }
};
Db::setMockConnection($failingPdo);

// Capture error_log output
$logFile = (string) tempnam(sys_get_temp_dir(), 'notif_test_log_');
ini_set('error_log', $logFile);

NotificationOrchestrator::logDelivery('notif_fail_test', 'web_push', 'failed', 'Network timeout');

$logContent = (string) file_get_contents($logFile);
assertCondition(
    str_contains($logContent, '[NOTIFICATION_DELIVERY_LOG_FAILURE]'),
    'Zero-loss logger wrote to error_log when database write failed'
);
assertCondition(
    str_contains($logContent, 'notif_fail_test'),
    'Fallback error_log payload contains notification_id'
);
assertCondition(
    str_contains($logContent, 'Simulated table disk I/O failure'),
    'Fallback error_log payload contains original error message'
);

@unlink($logFile);
Db::clearMockConnection();

echo "\n=========================================\n";
echo "Verification Summary: {$passed} PASSED, {$failed} FAILED\n";
echo "=========================================\n";

if ($failed > 0) {
    exit(1);
}
