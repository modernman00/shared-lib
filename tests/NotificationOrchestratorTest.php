<?php

declare(strict_types=1);

namespace Tests;

use Mockery as m;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Src\Db;
use Src\NotificationOrchestrator;

/**
 * NotificationOrchestratorTest
 *
 * Comprehensive offline, zero-network unit test suite for NotificationOrchestrator.
 * Uses Db::setMockConnection() with an in-memory SQLite database providing
 * seamless translation for MySQL-specific functions (NOW(), DATE_SUB, ON DUPLICATE KEY).
 */
class NotificationOrchestratorTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure test credentials in environment
        $_ENV['VAPID_PUBLIC_KEY'] = 'BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjDCWJ_BJMg4WCeTO20HG95IWKWGs';
        $_ENV['VAPID_PRIVATE_KEY'] = '1111111111111111111111111111111111111111111';
        $_ENV['VAPID_SUBJECT'] = 'mailto:secops@example.com';
        $_ENV['APP_LOGO'] = 'https://example.com/logo.png';
        $_ENV['APP_URL'] = 'https://example.com';

        // Pusher test credentials
        $_ENV['PUSHER_APP_KEY'] = 'mock_pusher_key';
        $_ENV['PUSHER_APP_SECRET'] = 'mock_pusher_secret';
        $_ENV['PUSHER_APP_ID'] = 'mock_pusher_app_id';
        $_ENV['PUSHER_APP_CLUSTER'] = 'eu';

        // Provide a default WebPush mock to ensure no network calls are ever made
        $webPushMock = m::mock('overload:Minishlink\WebPush\WebPush');
        $webPushMock->shouldReceive('setReuseVAPIDHeaders')->byDefault();
        $webPushMock->shouldReceive('queueNotification')->byDefault()->andReturn(true);
        $webPushMock->shouldReceive('flush')->byDefault()->andReturn([]);

        // Set up in-memory SQLite connection with custom MySQL compatibility layer
        $pdo = $this->createSqliteConnection();
        $this->pdo = $pdo;
        $this->createDatabaseTables($pdo);
        Db::setMockConnection($pdo);
    }

    protected function tearDown(): void
    {
        Db::clearMockConnection();
        m::close();

        unset(
            $_ENV['VAPID_PUBLIC_KEY'],
            $_ENV['VAPID_PRIVATE_KEY'],
            $_ENV['VAPID_SUBJECT'],
            $_ENV['APP_LOGO'],
            $_ENV['APP_URL'],
            $_ENV['PUSHER_APP_KEY'],
            $_ENV['PUSHER_APP_SECRET'],
            $_ENV['PUSHER_APP_ID'],
            $_ENV['PUSHER_APP_CLUSTER']
        );

        parent::tearDown();
    }

    /**
     * Creates an in-memory SQLite PDO instance with custom adapters for MySQL syntax
     * (NOW(), DATE_SUB(..., INTERVAL), and ON DUPLICATE KEY UPDATE).
     */
    private function createSqliteConnection(): PDO
    {
        return new class('sqlite::memory:') extends PDO {
            public function __construct(string $dsn)
            {
                parent::__construct($dsn);
                $this->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                if (method_exists($this, 'sqliteCreateFunction')) {
                    @$this->sqliteCreateFunction('NOW', static fn(): string => date('Y-m-d H:i:s'));
                }
            }

            /**
             * @param string $query
             * @param array<int|string, mixed> $options
             * @return PDOStatement|false
             */
            public function prepare(string $query, array $options = []): PDOStatement|false
            {
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
    }

    private function createDatabaseTables(PDO $pdo): void
    {
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
    }

    // =========================================================================
    // DISPATCH TESTS
    // =========================================================================

    public function testDispatchInAppSocketDeliveryWhenUserIsOnline(): void
    {
        $userId = 'user_online_socket_test';

        // Seed user presence as online (active within 60s window)
        NotificationOrchestrator::updatePresence($userId, 'private-user-' . $userId, true);
        $this->assertTrue(NotificationOrchestrator::isUserOnline($userId));

        // Dispatch notification
        $notificationId = NotificationOrchestrator::dispatch(
            userId: $userId,
            category: 'social',
            priority: 'medium',
            title: 'New Comment',
            body: 'Alice commented on your photo',
            actionUrl: '/posts/123',
            tag: 'comment_123'
        );

        $this->assertNotEmpty($notificationId);
        $this->assertStringStartsWith('notif_', $notificationId);

        // Verify orchestration table record
        $stmt = $this->pdo->prepare('SELECT * FROM notification_orchestration WHERE id = ?');
        $this->assertInstanceOf(PDOStatement::class, $stmt);
        $stmt->execute([$notificationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertSame($userId, $row['user_id']);
        $this->assertSame('social', $row['category']);
        $this->assertSame('medium', $row['priority']);
        $this->assertSame('New Comment', $row['title']);
        $this->assertSame('Alice commented on your photo', $row['body']);
        $this->assertSame('/posts/123', $row['action_url']);
        $this->assertSame('comment_123', $row['tag']);
        $this->assertSame('pending', $row['status']);

        // Verify socket delivery logged
        $logStmt = $this->pdo->prepare('
            SELECT * FROM notification_delivery_logs 
            WHERE notification_id = ? AND channel = "in_app_socket"
        ');
        $this->assertInstanceOf(PDOStatement::class, $logStmt);
        $logStmt->execute([$notificationId]);
        $logRow = $logStmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($logRow);
        $this->assertSame('sent', $logRow['status']);
    }

    public function testDispatchOsWebPushDeliveryWhenUserHasPushSubscriptions(): void
    {
        $userId = 'user_push_delivery_test';

        // Seed push subscription
        $subStmt = $this->pdo->prepare('
            INSERT INTO user_push_subscriptions (user_id, endpoint, p256dh, auth_token, created_at)
            VALUES (?, "https://fcm.googleapis.com/fcm/send/token_dispatch_push", "p256dh_val", "auth_val", datetime("now"))
        ');
        $this->assertInstanceOf(PDOStatement::class, $subStmt);
        $subStmt->execute([$userId]);

        // Dispatch notification
        $notificationId = NotificationOrchestrator::dispatch(
            userId: $userId,
            category: 'financial',
            priority: 'medium',
            title: 'Payment Received',
            body: 'You received £25.00',
            actionUrl: '/wallet',
            tag: 'payment_99'
        );

        $this->assertNotEmpty($notificationId);

        // Verify web_push delivery logged as sent
        $logStmt = $this->pdo->prepare('
            SELECT * FROM notification_delivery_logs 
            WHERE notification_id = ? AND channel = "web_push"
        ');
        $this->assertInstanceOf(PDOStatement::class, $logStmt);
        $logStmt->execute([$notificationId]);
        $logRow = $logStmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($logRow);
        $this->assertSame('sent', $logRow['status']);
    }

    public function testDispatchEmailFallbackQueuedWhenUserIsOfflineWithHighPriority(): void
    {
        $userId = 'user_offline_email_test';

        // Ensure user is offline (no socket presence) and has no push devices
        $this->assertFalse(NotificationOrchestrator::isUserOnline($userId));

        // Dispatch high priority notification
        $notificationId = NotificationOrchestrator::dispatch(
            userId: $userId,
            category: 'security',
            priority: 'high',
            title: 'New Login Detected',
            body: 'Login from an unrecognized device',
            actionUrl: '/security/sessions',
            tag: 'sec_login'
        );

        $this->assertNotEmpty($notificationId);

        // Verify email delivery logged as queued_immediate
        $logStmt = $this->pdo->prepare('
            SELECT * FROM notification_delivery_logs 
            WHERE notification_id = ? AND channel = "email"
        ');
        $this->assertInstanceOf(PDOStatement::class, $logStmt);
        $logStmt->execute([$notificationId]);
        $logRow = $logStmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($logRow);
        $this->assertSame('queued_immediate', $logRow['status']);
    }

    public function testDispatchSanitizesHtmlFromTitleBodyAndTag(): void
    {
        $userId = 'user_xss_sanitization_test';

        $rawTitle = '<b>Security Alert</b><script>alert("xss")</script>';
        $rawBody = '<p>Your account has been accessed.</p><img src="x" onerror="evil()"/>';
        $rawTag = 'tag#special!@value$123';

        $notificationId = NotificationOrchestrator::dispatch(
            userId: $userId,
            category: 'security',
            priority: 'critical',
            title: $rawTitle,
            body: $rawBody,
            actionUrl: '/sec',
            tag: $rawTag
        );

        $stmt = $this->pdo->prepare('SELECT title, body, tag FROM notification_orchestration WHERE id = ?');
        $this->assertInstanceOf(PDOStatement::class, $stmt);
        $stmt->execute([$notificationId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        // HTML tags must be stripped
        $this->assertSame('Security Alertalert("xss")', $row['title']);
        $this->assertSame('Your account has been accessed.', $row['body']);
        // Non-alphanumeric chars stripped from tag
        $this->assertSame('tagspecialvalue123', $row['tag']);
    }

    // =========================================================================
    // MARK AS READ TESTS
    // =========================================================================

    public function testMarkAsReadUpdatesOrchestrationAndLegacyTableAndDecrementsUnread(): void
    {
        $userId = 'user_mark_read_test';

        // 1. Dispatch an initial notification
        $notificationId = NotificationOrchestrator::dispatch(
            userId: $userId,
            category: 'financial',
            priority: 'medium',
            title: 'Invoice Sent',
            body: 'Invoice #104 has been sent.',
            actionUrl: '/invoices/104',
            tag: 'inv_104'
        );

        // Ensure legacy record matches notification_name for legacy update test
        $this->pdo->exec("UPDATE notification SET notification_name = '{$notificationId}' WHERE receiver_id = '{$userId}'");

        $unreadBefore = NotificationOrchestrator::getUnreadCount($userId);
        $this->assertSame(1, $unreadBefore);

        // 2. Mark as read
        $result = NotificationOrchestrator::markAsRead($notificationId, $userId);
        $this->assertTrue($result);

        // 3. Verify status in notification_orchestration
        $stmt = $this->pdo->prepare('SELECT status, read_at FROM notification_orchestration WHERE id = ?');
        $this->assertInstanceOf(PDOStatement::class, $stmt);
        $stmt->execute([$notificationId]);
        $orchRow = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($orchRow);
        $this->assertSame('read', $orchRow['status']);
        $this->assertNotNull($orchRow['read_at']);

        // 4. Verify status in legacy notification table
        $legStmt = $this->pdo->prepare('SELECT notification_status FROM notification WHERE receiver_id = ?');
        $this->assertInstanceOf(PDOStatement::class, $legStmt);
        $legStmt->execute([$userId]);
        $legRow = $legStmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($legRow);
        $this->assertSame('deleted', $legRow['notification_status']);

        // 5. Verify unread count decremented
        $unreadAfter = NotificationOrchestrator::getUnreadCount($userId);
        $this->assertSame(0, $unreadAfter);
    }

    public function testMarkAsReadCancelsPendingEmailFallback(): void
    {
        $userId = 'user_cancel_email_test';

        $notificationId = NotificationOrchestrator::dispatch(
            userId: $userId,
            category: 'financial',
            priority: 'high',
            title: 'Payment Pending',
            body: 'Please authorize transfer',
            actionUrl: '/pay',
            tag: 'pay_1'
        );

        // Seed a queued email delivery log
        $this->pdo->exec("
            INSERT INTO notification_delivery_logs (notification_id, channel, status, details, attempted_at)
            VALUES ('{$notificationId}', 'email', 'queued', 'Queued for 15-minute fallback', datetime('now'))
        ");

        // Mark notification as read
        NotificationOrchestrator::markAsRead($notificationId, $userId);

        // Verify status updated to cancelled_already_read
        $stmt = $this->pdo->prepare('
            SELECT status FROM notification_delivery_logs 
            WHERE notification_id = ? AND channel = "email" AND details LIKE "%15-minute%"
        ');
        $this->assertInstanceOf(PDOStatement::class, $stmt);
        $stmt->execute([$notificationId]);
        $status = $stmt->fetchColumn();

        $this->assertSame('cancelled_already_read', $status);
    }

    // =========================================================================
    // MARK ALL AS READ (BATCH) TESTS (Milestone M2 Contract)
    // =========================================================================

    public function testMarkAllAsReadBatchUpdatesAllPendingNotifications(): void
    {
        $ref = new ReflectionClass(NotificationOrchestrator::class);
        $this->assertTrue(
            $ref->hasMethod('markAllAsRead'),
            'NotificationOrchestrator::markAllAsRead(string $userId): bool must be implemented in Milestone M2.'
        );

        $userId = 'user_batch_read_test';

        // Dispatch 3 notifications
        for ($i = 1; $i <= 3; $i++) {
            NotificationOrchestrator::dispatch(
                userId: $userId,
                category: 'social',
                priority: 'low',
                title: "Alert #{$i}",
                body: "Message body #{$i}",
                actionUrl: "/item/{$i}",
                tag: "batch_{$i}"
            );
        }

        $this->assertSame(3, NotificationOrchestrator::getUnreadCount($userId));

        // Execute batch mark all as read
        $method = $ref->getMethod('markAllAsRead');
        /** @var bool $result */
        $result = $method->invoke(null, $userId);
        $this->assertTrue($result);

        // Assert all notifications for this user are now marked read
        $stmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM notification_orchestration 
            WHERE user_id = ? AND status = "pending"
        ');
        $this->assertInstanceOf(PDOStatement::class, $stmt);
        $stmt->execute([$userId]);
        $pendingRemaining = (int)$stmt->fetchColumn();

        $this->assertSame(0, $pendingRemaining);
        $this->assertSame(0, NotificationOrchestrator::getUnreadCount($userId));
    }

    // =========================================================================
    // DELIVERY LOGS AUDIT API TESTS (Milestone M2 Contract)
    // =========================================================================

    public function testGetDeliveryLogsRetrievesAllLogsForNotification(): void
    {
        $ref = new ReflectionClass(NotificationOrchestrator::class);
        $this->assertTrue(
            $ref->hasMethod('getDeliveryLogs'),
            'NotificationOrchestrator::getDeliveryLogs(string $notificationId): array must be implemented in Milestone M2.'
        );

        $userId = 'user_delivery_logs_test';

        $notificationId = NotificationOrchestrator::dispatch(
            userId: $userId,
            category: 'system',
            priority: 'low',
            title: 'System Notice',
            body: 'Scheduled maintenance tonight',
            actionUrl: '/status',
            tag: 'maint_01'
        );

        // Call public audit API
        $method = $ref->getMethod('getDeliveryLogs');
        /** @var array<int, array<string, mixed>> $logs */
        $logs = $method->invoke(null, $notificationId);

        $this->assertIsArray($logs);
        $this->assertNotEmpty($logs, 'Delivery logs must not be empty after dispatch');

        foreach ($logs as $log) {
            $this->assertArrayHasKey('channel', $log);
            $this->assertArrayHasKey('status', $log);
        }
    }

    // =========================================================================
    // PRESENCE TESTS
    // =========================================================================

    public function testUpdatePresenceAndIsUserOnlineWithin60SecondsHeartbeatWindow(): void
    {
        $userId = 'user_presence_window_test';
        $channel = 'private-user-' . $userId;

        // 1. Initial state: user is offline
        $this->assertFalse(NotificationOrchestrator::isUserOnline($userId));

        // 2. User registers active presence heartbeat
        NotificationOrchestrator::updatePresence($userId, $channel, true);
        $this->assertTrue(NotificationOrchestrator::isUserOnline($userId));

        // 3. User registers inactive presence (e.g. tab closed or backgrounded)
        NotificationOrchestrator::updatePresence($userId, $channel, false);
        $this->assertFalse(NotificationOrchestrator::isUserOnline($userId));
    }

    public function testIsUserOnlineReturnsFalseWhenHeartbeatIsOlderThan60Seconds(): void
    {
        $userId = 'user_stale_heartbeat_test';
        $channel = 'private-user-' . $userId;

        // Insert a heartbeat recorded 120 seconds ago
        $stmt = $this->pdo->prepare('
            INSERT INTO user_socket_presence (user_id, channel_name, is_active, last_heartbeat)
            VALUES (?, ?, 1, datetime("now", "-120 seconds"))
        ');
        $this->assertInstanceOf(PDOStatement::class, $stmt);
        $stmt->execute([$userId, $channel]);

        // Heartbeat is > 60s old; user must be considered offline
        $this->assertFalse(NotificationOrchestrator::isUserOnline($userId));
    }
}
