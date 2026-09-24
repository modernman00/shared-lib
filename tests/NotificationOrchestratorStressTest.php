<?php

declare(strict_types=1);

namespace Tests;

use Mockery as m;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Src\Db;
use Src\NotificationOrchestrator;

/**
 * NotificationOrchestratorStressTest
 *
 * Dedicated adversarial stress test harness for NotificationOrchestrator probing:
 * 1. Database failure & zero-loss error_log fallback (disconnects, locked tables, PDOExceptions).
 * 2. Rapid sequential and batch markAllAsRead under high pending notification volumes (500-1000+ items).
 * 3. Malformed/null notification IDs, user IDs, priority strings, and SQL injection/XSS fuzzing.
 * 4. Dual-schema compatibility testing (attempted_at vs created_at).
 */
class NotificationOrchestratorStressTest extends TestCase
{
    private PDO $pdo;
    private string $tempLogFile;
    private string|false $originalErrorLog;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Isolate error_log to temporary file for zero-loss fallback assertions
        $this->tempLogFile = (string) tempnam(sys_get_temp_dir(), 'orch_stress_log_');
        $this->originalErrorLog = ini_get('error_log');
        ini_set('error_log', $this->tempLogFile);

        // 2. Configure mock environment
        $_ENV['VAPID_PUBLIC_KEY'] = 'BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjDCWJ_BJMg4WCeTO20HG95IWKWGs';
        $_ENV['VAPID_PRIVATE_KEY'] = '1111111111111111111111111111111111111111111';
        $_ENV['VAPID_SUBJECT'] = 'mailto:secops@example.com';
        $_ENV['APP_LOGO'] = 'https://example.com/logo.png';
        $_ENV['APP_URL'] = 'https://example.com';

        // Keep Pusher disabled during stress tests so no external HTTP calls are attempted
        $_ENV['PUSHER_APP_KEY'] = '';
        $_ENV['PUSHER_APP_SECRET'] = '';
        $_ENV['PUSHER_APP_ID'] = '';
        $_ENV['PUSHER_APP_CLUSTER'] = 'eu';

        // 3. Mock WebPush offline
        $webPushMock = m::mock('overload:Minishlink\WebPush\WebPush');
        $webPushMock->shouldReceive('setReuseVAPIDHeaders')->byDefault();
        $webPushMock->shouldReceive('queueNotification')->byDefault()->andReturn(true);
        $webPushMock->shouldReceive('flush')->byDefault()->andReturn([]);

        // 4. Default SQLite database setup
        $this->pdo = $this->createSqliteConnection();
        $this->createStandardTables($this->pdo);
        Db::setMockConnection($this->pdo);
    }

    protected function tearDown(): void
    {
        Db::clearMockConnection();
        m::close();

        // Restore original error_log
        if ($this->originalErrorLog !== false) {
            ini_set('error_log', $this->originalErrorLog);
        }
        if (file_exists($this->tempLogFile)) {
            @unlink($this->tempLogFile);
        }

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

    private function getCapturedErrorLog(): string
    {
        return file_exists($this->tempLogFile) ? (string) file_get_contents($this->tempLogFile) : '';
    }

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

    private function createStandardTables(PDO $pdo): void
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

            CREATE INDEX IF NOT EXISTS idx_orch_user_status ON notification_orchestration(user_id, status);

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

            CREATE INDEX IF NOT EXISTS idx_notif_recv_status ON notification(receiver_id, notification_status);

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

            CREATE INDEX IF NOT EXISTS idx_logs_notif_id ON notification_delivery_logs(notification_id);

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
    // 1. DATABASE FAILURE SIMULATION & ZERO-LOSS FALLBACK
    // =========================================================================

    public function testLogDeliveryZeroLossFallbackOnDatabaseDisconnect(): void
    {
        // Simulate a broken/disconnected database connection
        $disconnectedPdo = new class('sqlite::memory:') extends PDO {
            public function __construct()
            {
                parent::__construct('sqlite::memory:');
            }

            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                throw new PDOException('MySQL server has gone away');
            }
        };

        Db::setMockConnection($disconnectedPdo);

        $notifId = 'notif_disconnect_test_99';
        $channel = 'web_push';
        $status = 'failed';
        $details = 'Endpoint handshake connection timeout';

        // Must NOT leak PDOException to caller
        NotificationOrchestrator::logDelivery($notifId, $channel, $status, $details);

        // Verify fallback log was written to error_log
        $logContent = $this->getCapturedErrorLog();
        $this->assertStringContainsString('[NOTIFICATION_DELIVERY_LOG_FAILURE]', $logContent);

        // Parse logged JSON payload
        preg_match('/\[NOTIFICATION_DELIVERY_LOG_FAILURE\]\s+(\{.*\})/', $logContent, $matches);
        $this->assertNotEmpty($matches, 'Zero-loss JSON payload must be present in error_log');

        /** @var array<string, mixed> $payload */
        $payload = json_decode($matches[1], true);
        $this->assertSame($notifId, $payload['notification_id']);
        $this->assertSame($channel, $payload['channel']);
        $this->assertSame($status, $payload['status']);
        $this->assertSame($details, $payload['details']);
        $this->assertStringContainsString('MySQL server has gone away', (string) $payload['error']);
    }

    public function testLogDeliveryZeroLossFallbackOnLockedTable(): void
    {
        // Simulate a locked database table where both attempted_at and created_at writes throw
        $lockedPdo = new class('sqlite::memory:') extends PDO {
            public function __construct()
            {
                parent::__construct('sqlite::memory:');
            }

            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                throw new PDOException('General error: 5 database is locked');
            }
        };

        Db::setMockConnection($lockedPdo);

        $notifId = 'notif_locked_test_42';
        NotificationOrchestrator::logDelivery($notifId, 'email', 'failed', 'Lock timeout exceeded');

        $logContent = $this->getCapturedErrorLog();
        $this->assertStringContainsString('[NOTIFICATION_DELIVERY_LOG_FAILURE]', $logContent);
        $this->assertStringContainsString('database is locked', $logContent);
        $this->assertStringContainsString($notifId, $logContent);
    }

    public function testDispatchGracefulDegradationOnCompleteDatabaseFailure(): void
    {
        // Simulate database failing completely when dispatch() is called
        $deadPdo = new class('sqlite::memory:') extends PDO {
            public function __construct()
            {
                parent::__construct('sqlite::memory:');
            }

            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                throw new PDOException('SQLSTATE[HY000] [2002] Connection refused');
            }
        };

        Db::setMockConnection($deadPdo);

        // Dispatch must NOT throw; it must return a valid notification ID
        $notificationId = NotificationOrchestrator::dispatch(
            userId: 'user_dead_db',
            category: 'social',
            priority: 'high',
            title: 'Critical Alert',
            body: 'Database is down',
            actionUrl: '/status'
        );

        $this->assertNotEmpty($notificationId);
        $this->assertStringStartsWith('notif_', $notificationId);

        // Verify that failure was recorded in error_log
        $logContent = $this->getCapturedErrorLog();
        $this->assertStringContainsString('[NotificationOrchestrator]', $logContent);
    }

    public function testLogDeliveryHandlesMalformedUtf8InDetailsGracefully(): void
    {
        $failingPdo = new class('sqlite::memory:') extends PDO {
            public function __construct()
            {
                parent::__construct('sqlite::memory:');
            }

            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                throw new PDOException('Simulated DB failure for UTF8 test');
            }
        };

        Db::setMockConnection($failingPdo);

        // Invalid UTF-8 sequence
        $malformedDetails = "Corrupt packet: \xB1\x31\xFF";

        // Must execute safely without fatal error or uncaught exception
        NotificationOrchestrator::logDelivery('notif_utf8_corrupt', 'web_push', 'failed', $malformedDetails);

        $logContent = $this->getCapturedErrorLog();
        $this->assertStringContainsString('[NOTIFICATION_DELIVERY_LOG_FAILURE]', $logContent);
    }

    // =========================================================================
    // 2. RAPID SEQUENTIAL & BATCH markAllAsRead UNDER HIGH VOLUME
    // =========================================================================

    public function testMarkAllAsReadHighVolumePendingNotifications(): void
    {
        $targetUser = 'user_high_vol_target';
        $otherUser = 'user_high_vol_isolated';

        $totalTargetItems = 1000;
        $totalOtherItems = 100;

        // Populate 1,000 pending notifications for target user in transaction
        $this->pdo->beginTransaction();
        $stmt = $this->pdo->prepare('
            INSERT INTO notification_orchestration 
            (id, user_id, family_code, category, priority, title, body, action_url, tag, data_payload, status)
            VALUES (?, ?, NULL, "social", "medium", "Alert Title", "Alert Body", "/home", "tag_high", NULL, "pending")
        ');
        $legStmt = $this->pdo->prepare('
            INSERT INTO notification 
            (sender_id, receiver_id, notification_name, notification_type, notification_content, notification_status, notification_date)
            VALUES ("sys", ?, "Alert", "social", "Body", "new", datetime("now"))
        ');

        for ($i = 1; $i <= $totalTargetItems; $i++) {
            $nid = "notif_target_{$i}";
            $stmt->execute([$nid, $targetUser]);
            $legStmt->execute([$targetUser]);
        }

        // Also populate 100 pending notifications for other user
        for ($j = 1; $j <= $totalOtherItems; $j++) {
            $nid = "notif_other_{$j}";
            $stmt->execute([$nid, $otherUser]);
            $legStmt->execute([$otherUser]);
        }
        $this->pdo->commit();

        // Seed pending email logs for a subset of target user notifications
        $this->pdo->exec("
            INSERT INTO notification_delivery_logs (notification_id, channel, status, details, attempted_at)
            VALUES ('notif_target_1', 'email', 'queued', 'Queued 15min fallback', datetime('now')),
                   ('notif_target_2', 'email', 'queued_immediate', 'Queued immediate fallback', datetime('now')),
                   ('notif_other_1', 'email', 'queued', 'Other user queued email', datetime('now'))
        ");

        // Verify initial counts
        $this->assertSame($totalTargetItems, NotificationOrchestrator::getUnreadCount($targetUser));
        $this->assertSame($totalOtherItems, NotificationOrchestrator::getUnreadCount($otherUser));

        // Benchmark and execute markAllAsRead
        $startTime = microtime(true);
        $result = NotificationOrchestrator::markAllAsRead($targetUser);
        $elapsed = microtime(true) - $startTime;

        $this->assertTrue($result, 'markAllAsRead must return true');
        $this->assertLessThan(2.0, $elapsed, 'Batch markAllAsRead for 1,000 items must execute in under 2 seconds');

        // Target user must now have 0 unread
        $this->assertSame(0, NotificationOrchestrator::getUnreadCount($targetUser));

        // Isolated other user must STILL have exactly 100 unread
        $this->assertSame($totalOtherItems, NotificationOrchestrator::getUnreadCount($otherUser), 'Other user notifications must remain unmutated');

        // Verify all 1,000 orchestration records updated to 'read' with valid read_at
        $checkStmt = $this->pdo->prepare('
            SELECT COUNT(*) FROM notification_orchestration 
            WHERE user_id = ? AND status = "read" AND read_at IS NOT NULL
        ');
        $checkStmt->execute([$targetUser]);
        $this->assertSame($totalTargetItems, (int) $checkStmt->fetchColumn());

        // Verify legacy table updated to 'deleted'
        $legCheck = $this->pdo->prepare('
            SELECT COUNT(*) FROM notification 
            WHERE receiver_id = ? AND notification_status = "deleted"
        ');
        $legCheck->execute([$targetUser]);
        $this->assertSame($totalTargetItems, (int) $legCheck->fetchColumn());

        // Verify target user's email logs were cancelled
        $emailLogCheck = $this->pdo->query('
            SELECT notification_id, status FROM notification_delivery_logs WHERE channel = "email"
        ')->fetchAll(PDO::FETCH_KEY_PAIR);

        $this->assertSame('cancelled_already_read', $emailLogCheck['notif_target_1']);
        $this->assertSame('cancelled_already_read', $emailLogCheck['notif_target_2']);
        $this->assertSame('queued', $emailLogCheck['notif_other_1'], 'Other user email log must not be cancelled');
    }

    public function testRapidSequentialMarkAllAsReadCallsAreIdempotent(): void
    {
        $userId = 'user_idempotent_test';

        // Dispatch 5 items
        for ($i = 1; $i <= 5; $i++) {
            NotificationOrchestrator::dispatch(
                userId: $userId,
                category: 'system',
                priority: 'low',
                title: "Notice {$i}",
                body: "Body {$i}"
            );
        }

        $this->assertSame(5, NotificationOrchestrator::getUnreadCount($userId));

        // Execute markAllAsRead 50 times in rapid succession
        for ($k = 0; $k < 50; $k++) {
            $res = NotificationOrchestrator::markAllAsRead($userId);
            $this->assertTrue($res, "Call {$k} to markAllAsRead must return true");
        }

        $this->assertSame(0, NotificationOrchestrator::getUnreadCount($userId));
    }

    public function testInterleavedDispatchAndMarkAllAsReadStress(): void
    {
        $userId = 'user_interleaved_stress';

        // Concurrently interleave dispatches and batch reads
        for ($round = 1; $round <= 20; $round++) {
            // Dispatch 5 notifications
            for ($d = 1; $d <= 5; $d++) {
                NotificationOrchestrator::dispatch(
                    userId: $userId,
                    category: 'financial',
                    priority: 'medium',
                    title: "Round {$round} Item {$d}",
                    body: "Content",
                    actionUrl: "/item/{$round}/{$d}",
                    tag: "round_{$round}"
                );
            }

            // Every 4th round, mark all as read
            if ($round % 4 === 0) {
                $res = NotificationOrchestrator::markAllAsRead($userId);
                $this->assertTrue($res);
                $this->assertSame(0, NotificationOrchestrator::getUnreadCount($userId));
            }
        }

        // Remaining rounds 17, 18, 19, 20 (4 rounds * 5 = 20 items, but round 20 marked all as read)
        $this->assertSame(0, NotificationOrchestrator::getUnreadCount($userId));
    }

    // =========================================================================
    // 3. MALFORMED / NULL INPUTS, EXTREME TYPES & BOUNDARY FUZZING
    // =========================================================================

    public function testDispatchRejectsEmptyAndWhitespaceUserIds(): void
    {
        $invalidUserIds = ['', '   ', "\t", "\n", "\r\n", "  \t \n  "];

        foreach ($invalidUserIds as $invUid) {
            $result = NotificationOrchestrator::dispatch(
                userId: $invUid,
                category: 'social',
                priority: 'medium',
                title: 'Test Title',
                body: 'Test Body'
            );

            $this->assertSame('', $result, "Dispatch with empty/whitespace userId '{$invUid}' must return empty string");
        }

        // Ensure 0 rows inserted
        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM notification_orchestration')->fetchColumn();
        $this->assertSame(0, $count);
    }

    public function testMarkAsReadRejectsEmptyAndWhitespaceIds(): void
    {
        $this->assertFalse(NotificationOrchestrator::markAsRead('', 'user_1'));
        $this->assertFalse(NotificationOrchestrator::markAsRead('   ', 'user_1'));
        $this->assertFalse(NotificationOrchestrator::markAsRead('notif_1', ''));
        $this->assertFalse(NotificationOrchestrator::markAsRead('notif_1', '   '));
        $this->assertFalse(NotificationOrchestrator::markAsRead('', ''));
        $this->assertFalse(NotificationOrchestrator::markAsRead(" \t ", " \n "));
    }

    public function testMarkAllAsReadRejectsEmptyAndWhitespaceUserIds(): void
    {
        $this->assertFalse(NotificationOrchestrator::markAllAsRead(''));
        $this->assertFalse(NotificationOrchestrator::markAllAsRead('   '));
        $this->assertFalse(NotificationOrchestrator::markAllAsRead("\t\r\n"));
    }

    public function testGetUnreadCountWithMalformedUserIdsReturnsZero(): void
    {
        $this->assertSame(0, NotificationOrchestrator::getUnreadCount(''));
        $this->assertSame(0, NotificationOrchestrator::getUnreadCount('   '));
        $this->assertSame(0, NotificationOrchestrator::getUnreadCount('non_existent_user_9999'));
    }

    public function testDispatchFuzzesCategoryAndPriorityDefaultsSafely(): void
    {
        $userId = 'user_fuzz_categories';

        // Unrecognized categories must default to 'social'
        $fuzzCategories = ['admin', 'root', 'unknown', 'SYSTEM_UPPER', '', '!!!', 'SELECT * FROM users'];
        foreach ($fuzzCategories as $idx => $badCat) {
            $nid = NotificationOrchestrator::dispatch(
                userId: $userId,
                category: $badCat,
                priority: 'medium',
                title: "Cat Test {$idx}",
                body: 'Body'
            );

            $stmt = $this->pdo->prepare('SELECT category FROM notification_orchestration WHERE id = ?');
            $stmt->execute([$nid]);
            $this->assertSame('social', $stmt->fetchColumn(), "Unrecognized category '{$badCat}' must default to 'social'");
        }

        // Unrecognized priorities must default to 'medium'
        $fuzzPriorities = ['urgent', 'P0', 'CRITICAL_UPPER', 'emergency', '', '999', 'undefined'];
        foreach ($fuzzPriorities as $idx => $badPri) {
            $nid = NotificationOrchestrator::dispatch(
                userId: $userId,
                category: 'system',
                priority: $badPri,
                title: "Pri Test {$idx}",
                body: 'Body'
            );

            $stmt = $this->pdo->prepare('SELECT priority FROM notification_orchestration WHERE id = ?');
            $stmt->execute([$nid]);
            $this->assertSame('medium', $stmt->fetchColumn(), "Unrecognized priority '{$badPri}' must default to 'medium'");

            // Unrecognized priority must NOT queue email fallback
            $logStmt = $this->pdo->prepare('SELECT COUNT(*) FROM notification_delivery_logs WHERE notification_id = ? AND channel = "email"');
            $logStmt->execute([$nid]);
            $this->assertSame(0, (int) $logStmt->fetchColumn(), "Non-high/critical priority must not queue email fallback");
        }
    }

    public function testDispatchSanitizesMaliciousInputsAndSqlInjection(): void
    {
        $userId = 'user_sqli_test';

        $maliciousTitle = "<script>alert('XSS')</script><b>Critical Alert</b>";
        $maliciousBody = "<img src=x onerror=evil()>Your pledge was approved.<a href='javascript:evil()'>Click</a>";
        $maliciousTag = "../../etc/passwd#tag!@$123";
        $maliciousUrl = '';

        $nid = NotificationOrchestrator::dispatch(
            userId: $userId,
            category: 'financial',
            priority: 'medium',
            title: $maliciousTitle,
            body: $maliciousBody,
            actionUrl: $maliciousUrl,
            tag: $maliciousTag
        );

        $stmt = $this->pdo->prepare('SELECT title, body, action_url, tag FROM notification_orchestration WHERE id = ?');
        $stmt->execute([$nid]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        // HTML tags must be stripped
        $this->assertSame("alert('XSS')Critical Alert", $row['title']);
        $this->assertSame('Your pledge was approved.Click', $row['body']);
        // Empty URL defaults to '/'
        $this->assertSame('/', $row['action_url']);
        // Tag must be stripped of special characters
        $this->assertSame('etcpasswdtag123', $row['tag']);
    }

    public function testDispatchWithMassivePayloadsSurvivesWithoutMemoryCrash(): void
    {
        $userId = 'user_massive_payload';

        // 10,000 char title, 100,000 char body, large metadata array
        $hugeTitle = str_repeat('A', 10000);
        $hugeBody = str_repeat('B', 100000);
        $hugeMetadata = [
            'nested' => array_fill(0, 100, ['key' => str_repeat('C', 100)]),
        ];

        $nid = NotificationOrchestrator::dispatch(
            userId: $userId,
            category: 'system',
            priority: 'low',
            title: $hugeTitle,
            body: $hugeBody,
            actionUrl: '/status',
            tag: 'huge_tag',
            scopeCode: 'scope_massive',
            metadata: $hugeMetadata
        );

        $this->assertNotEmpty($nid);
        $stmt = $this->pdo->prepare('SELECT LENGTH(title), LENGTH(body) FROM notification_orchestration WHERE id = ?');
        $stmt->execute([$nid]);
        $row = $stmt->fetch(PDO::FETCH_NUM);

        $this->assertIsArray($row);
        $this->assertSame(10000, (int) $row[0]);
        $this->assertSame(100000, (int) $row[1]);
    }

    public function testGetDeliveryLogsHandlesMalformedAndNonExistentIds(): void
    {
        $this->assertSame([], NotificationOrchestrator::getDeliveryLogs(''));
        $this->assertSame([], NotificationOrchestrator::getDeliveryLogs('   '));
        $this->assertSame([], NotificationOrchestrator::getDeliveryLogs("\t\n"));
        $this->assertSame([], NotificationOrchestrator::getDeliveryLogs('non_existent_notif_99999'));
    }

    // =========================================================================
    // 4. DUAL-SCHEMA COMPATIBILITY TESTING (attempted_at vs created_at)
    // =========================================================================

    public function testDualSchemaWithOnlyAttemptedAtColumn(): void
    {
        // Setup isolated in-memory PDO with ONLY attempted_at in notification_delivery_logs
        $isolatedPdo = $this->createSqliteConnection();
        $isolatedPdo->exec('
            CREATE TABLE notification_delivery_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                notification_id VARCHAR(64) NOT NULL,
                channel VARCHAR(32) NOT NULL,
                status VARCHAR(64) NOT NULL,
                details TEXT NULL,
                attempted_at DATETIME NOT NULL
            );
        ');
        Db::setMockConnection($isolatedPdo);

        $notifId = 'notif_schema_attempted_at_only';

        // 1. logDelivery must succeed on first query
        NotificationOrchestrator::logDelivery($notifId, 'web_push', 'sent', 'Delivered via attempted_at');

        $count = (int) $isolatedPdo->query("SELECT COUNT(*) FROM notification_delivery_logs WHERE notification_id = '{$notifId}'")->fetchColumn();
        $this->assertSame(1, $count);

        // 2. getDeliveryLogs must return rows with both attempted_at AND created_at alias
        $logs = NotificationOrchestrator::getDeliveryLogs($notifId);
        $this->assertCount(1, $logs);
        $this->assertSame('web_push', $logs[0]['channel']);
        $this->assertSame('sent', $logs[0]['status']);
        $this->assertSame('Delivered via attempted_at', $logs[0]['details']);
        $this->assertArrayHasKey('attempted_at', $logs[0]);
        $this->assertArrayHasKey('created_at', $logs[0]);
        $this->assertSame($logs[0]['attempted_at'], $logs[0]['created_at']);
    }

    public function testDualSchemaWithOnlyCreatedAtColumn(): void
    {
        // Setup isolated in-memory PDO with ONLY created_at in notification_delivery_logs (legacy schema)
        $isolatedPdo = $this->createSqliteConnection();
        $isolatedPdo->exec('
            CREATE TABLE notification_delivery_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                notification_id VARCHAR(64) NOT NULL,
                channel VARCHAR(32) NOT NULL,
                status VARCHAR(64) NOT NULL,
                details TEXT NULL,
                created_at DATETIME NOT NULL
            );
        ');
        Db::setMockConnection($isolatedPdo);

        $notifId = 'notif_schema_created_at_legacy';

        // 1. logDelivery must catch primary insert exception (no attempted_at) and succeed on created_at fallback
        NotificationOrchestrator::logDelivery($notifId, 'email', 'sent', 'Delivered via legacy created_at fallback');

        $count = (int) $isolatedPdo->query("SELECT COUNT(*) FROM notification_delivery_logs WHERE notification_id = '{$notifId}'")->fetchColumn();
        $this->assertSame(1, $count);

        // 2. getDeliveryLogs must catch primary select exception (no attempted_at) and succeed on created_at fallback
        $logs = NotificationOrchestrator::getDeliveryLogs($notifId);
        $this->assertCount(1, $logs);
        $this->assertSame('email', $logs[0]['channel']);
        $this->assertSame('sent', $logs[0]['status']);
        $this->assertSame('Delivered via legacy created_at fallback', $logs[0]['details']);
        $this->assertArrayHasKey('created_at', $logs[0]);
        $this->assertArrayHasKey('attempted_at', $logs[0]);
        $this->assertSame($logs[0]['created_at'], $logs[0]['attempted_at']);
    }

    public function testDualSchemaWithBothColumnsPresent(): void
    {
        // Setup isolated in-memory PDO with BOTH columns present
        $isolatedPdo = $this->createSqliteConnection();
        $isolatedPdo->exec('
            CREATE TABLE notification_delivery_logs (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                notification_id VARCHAR(64) NOT NULL,
                channel VARCHAR(32) NOT NULL,
                status VARCHAR(64) NOT NULL,
                details TEXT NULL,
                attempted_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            );
        ');
        Db::setMockConnection($isolatedPdo);

        $notifId = 'notif_schema_both_columns';

        NotificationOrchestrator::logDelivery($notifId, 'in_app_socket', 'sent', 'Both columns present');

        $logs = NotificationOrchestrator::getDeliveryLogs($notifId);
        $this->assertCount(1, $logs);
        $this->assertSame('in_app_socket', $logs[0]['channel']);
        $this->assertArrayHasKey('attempted_at', $logs[0]);
        $this->assertArrayHasKey('created_at', $logs[0]);
    }

    public function testDualSchemaWithMissingTableEntirely(): void
    {
        // Setup isolated in-memory PDO with NO notification_delivery_logs table
        $isolatedPdo = $this->createSqliteConnection();
        Db::setMockConnection($isolatedPdo);

        $notifId = 'notif_no_table';

        // 1. logDelivery must catch both failures and log zero-loss payload to error_log without throwing
        NotificationOrchestrator::logDelivery($notifId, 'email', 'failed', 'Missing table test');

        $logContent = $this->getCapturedErrorLog();
        $this->assertStringContainsString('[NOTIFICATION_DELIVERY_LOG_FAILURE]', $logContent);
        $this->assertStringContainsString($notifId, $logContent);

        // 2. getDeliveryLogs must catch both select failures, log to error_log, and return empty array []
        $logs = NotificationOrchestrator::getDeliveryLogs($notifId);
        $this->assertSame([], $logs);
        $logContentAfter = $this->getCapturedErrorLog();
        $this->assertStringContainsString('[NotificationOrchestrator] getDeliveryLogs failed:', $logContentAfter);
    }

    public function testMarkAsReadEnforcesUserIsolationAgainstIdor(): void
    {
        $victimUser = 'user_victim';
        $attackerUser = 'user_attacker';

        // Dispatch notification for victim user
        $victimNotifId = NotificationOrchestrator::dispatch(
            userId: $victimUser,
            category: 'financial',
            priority: 'high',
            title: 'Victim Confidential Alert',
            body: 'Private financial transfer details'
        );

        $this->assertSame(1, NotificationOrchestrator::getUnreadCount($victimUser));

        // Attacker attempts to mark victim's notification as read
        $attackerResult = NotificationOrchestrator::markAsRead($victimNotifId, $attackerUser);

        // markAsRead returns true because the UPDATE query executes safely,
        // but 0 rows are affected for victim due to WHERE user_id = :uid
        $this->assertTrue($attackerResult);

        // Crucial security invariant: Victim unread count MUST still be 1!
        $this->assertSame(1, NotificationOrchestrator::getUnreadCount($victimUser));

        $stmt = $this->pdo->prepare('SELECT status, read_at FROM notification_orchestration WHERE id = ?');
        $stmt->execute([$victimNotifId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertSame('pending', $row['status'], 'Victim notification status must remain pending');
        $this->assertNull($row['read_at'], 'Victim notification read_at must remain null');
    }

    public function testMarkAsReadHandlesNumericLegacyIdsAndStringOrchestratorIds(): void
    {
        $userId = 'user_legacy_num_test';

        // Dispatch modern notification
        $notifId = NotificationOrchestrator::dispatch(
            userId: $userId,
            category: 'social',
            priority: 'low',
            title: 'Modern Notice',
            body: 'Hello world'
        );

        // Seed a legacy notification with numeric primary key '5042'
        $this->pdo->exec("
            INSERT INTO notification 
            (no, sender_id, receiver_id, notification_name, notification_type, notification_content, notification_status, notification_date)
            VALUES (5042, 'sender_1', '{$userId}', 'legacy_notif', 'system', 'Legacy content', 'new', datetime('now'))
        ");

        // 1. Mark as read using numeric legacy ID
        $resNum = NotificationOrchestrator::markAsRead('5042', $userId);
        $this->assertTrue($resNum);

        $legCheck1 = $this->pdo->query("SELECT notification_status FROM notification WHERE no = 5042")->fetchColumn();
        $this->assertSame('deleted', $legCheck1);

        // 2. Mark as read using alphanumeric modern ID
        $resStr = NotificationOrchestrator::markAsRead($notifId, $userId);
        $this->assertTrue($resStr);

        $orchCheck = $this->pdo->query("SELECT status FROM notification_orchestration WHERE id = '{$notifId}'")->fetchColumn();
        $this->assertSame('read', $orchCheck);
    }

    public function testMarkAsReadGracefulFailureOnInitialDatabaseConnectFailure(): void
    {
        Db::clearMockConnection();
        $oldDb = [
            'host' => $_ENV['DB_HOST'] ?? null,
            'name' => $_ENV['DB_NAME'] ?? null,
            'user' => $_ENV['DB_USERNAME'] ?? null,
            'pass' => $_ENV['DB_PASSWORD'] ?? null,
        ];

        $_ENV['DB_HOST'] = '127.0.0.1:99999';
        $_ENV['DB_NAME'] = 'dummy_db';
        $_ENV['DB_USERNAME'] = 'dummy_user';
        $_ENV['DB_PASSWORD'] = 'dummy_pass';

        try {
            // Must catch PDOException from connect2(), log to error_log, and return false
            $result = NotificationOrchestrator::markAsRead('notif_any', 'user_any');
            $this->assertFalse($result);

            $log = $this->getCapturedErrorLog();
            $this->assertStringContainsString('[NotificationOrchestrator] markAsRead failed:', $log);
        } finally {
            foreach ($oldDb as $k => $v) {
                $envKey = 'DB_' . strtoupper($k);
                if ($v !== null) {
                    $_ENV[$envKey] = $v;
                } else {
                    unset($_ENV[$envKey]);
                }
            }
        }
    }

    public function testMarkAllAsReadGracefulFailureOnInitialDatabaseConnectFailure(): void
    {
        Db::clearMockConnection();
        $oldDb = [
            'host' => $_ENV['DB_HOST'] ?? null,
            'name' => $_ENV['DB_NAME'] ?? null,
            'user' => $_ENV['DB_USERNAME'] ?? null,
            'pass' => $_ENV['DB_PASSWORD'] ?? null,
        ];

        $_ENV['DB_HOST'] = '127.0.0.1:99999';
        $_ENV['DB_NAME'] = 'dummy_db';
        $_ENV['DB_USERNAME'] = 'dummy_user';
        $_ENV['DB_PASSWORD'] = 'dummy_pass';

        try {
            // Must catch PDOException from connect2(), log to error_log, and return false
            $result = NotificationOrchestrator::markAllAsRead('user_any');
            $this->assertFalse($result);

            $log = $this->getCapturedErrorLog();
            $this->assertStringContainsString('[NotificationOrchestrator] markAllAsRead failed:', $log);
        } finally {
            foreach ($oldDb as $k => $v) {
                $envKey = 'DB_' . strtoupper($k);
                if ($v !== null) {
                    $_ENV[$envKey] = $v;
                } else {
                    unset($_ENV[$envKey]);
                }
            }
        }
    }

    public function testMarkAsReadSwallowsQueryExecutionExceptionsInInnerCatch(): void
    {
        // When connection exists but statement prepare/execute throws (e.g. table lock or lost connection)
        $lockedPdo = new class('sqlite::memory:') extends PDO {
            public function __construct()
            {
                parent::__construct('sqlite::memory:');
            }

            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                throw new PDOException('Simulated table lock during update');
            }
        };

        Db::setMockConnection($lockedPdo);

        // Due to inner catch (\Throwable $e) {} on line 249, markAsRead swallows the query exception and returns true!
        $result = NotificationOrchestrator::markAsRead('notif_swallowed', 'user_swallowed');
        $this->assertTrue($result, 'Empirical Proof: markAsRead swallows query execution exceptions and returns true');
    }

    public function testMarkAllAsReadSwallowsOrchestrationUpdateExceptionInInnerCatch(): void
    {
        // When connection exists but batch update statement throws
        $lockedPdo = new class('sqlite::memory:') extends PDO {
            public function __construct()
            {
                parent::__construct('sqlite::memory:');
            }

            public function prepare(string $query, array $options = []): PDOStatement|false
            {
                throw new PDOException('Deadlock detected on batch update');
            }
        };

        Db::setMockConnection($lockedPdo);

        // Due to inner catch (\Throwable $e) on line 322, markAllAsRead logs the error but continues and returns true!
        $result = NotificationOrchestrator::markAllAsRead('user_swallowed');
        $this->assertTrue($result, 'Empirical Proof: markAllAsRead logs orchestration error but proceeds to return true');

        $log = $this->getCapturedErrorLog();
        $this->assertStringContainsString('[NotificationOrchestrator] markAllAsRead orchestration error: Deadlock detected on batch update', $log);
    }

    public function testPresenceAndUnreadCountGracefulDegradationOnMissingTables(): void
    {
        // Isolated connection with NO tables created
        $emptyPdo = $this->createSqliteConnection();
        Db::setMockConnection($emptyPdo);

        // 1. getUnreadCount must degrade gracefully to 0
        $this->assertSame(0, NotificationOrchestrator::getUnreadCount('user_missing_tables'));

        // 2. isUserOnline must degrade gracefully to false
        $this->assertFalse(NotificationOrchestrator::isUserOnline('user_missing_tables'));

        // 3. updatePresence must log to error_log and not crash
        NotificationOrchestrator::updatePresence('user_missing_tables', 'channel_test', true);
        $log = $this->getCapturedErrorLog();
        $this->assertStringContainsString('[NotificationOrchestrator] updatePresence error:', $log);
    }
}

