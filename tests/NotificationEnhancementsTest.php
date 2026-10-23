<?php

declare(strict_types=1);

namespace Tests;

use Mockery as m;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Src\Db;
use Src\NotificationOrchestrator;
use Src\PushNotificationService;
use Src\AgentDocHelper;

/**
 * NotificationEnhancementsTest
 *
 * Verifies the 4 architectural upgrades inspired by Notifiable:
 * - Scheduled notifications (scheduled_at dispatch suppression & release)
 * - Atomic queue processing (processScheduled)
 * - Device telemetry registration & diagnostics (PushNotificationService)
 * - Client telemetry pingbacks (delivered, clicked, dismissed)
 * - Machine-readable documentation generation (AgentDocHelper)
 */
class NotificationEnhancementsTest extends TestCase
{
    private PDO $pdo;

    protected function setUp(): void
    {
        parent::setUp();

        $_ENV['VAPID_PUBLIC_KEY'] = 'BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjDCWJ_BJMg4WCeTO20HG95IWKWGs';
        $_ENV['VAPID_PRIVATE_KEY'] = '1111111111111111111111111111111111111111111';
        $_ENV['VAPID_SUBJECT'] = 'mailto:secops@example.com';
        $_ENV['APP_LOGO'] = 'https://example.com/logo.png';
        $_ENV['APP_URL'] = 'https://example.com';

        $_ENV['PUSHER_APP_KEY'] = 'mock_pusher_key';
        $_ENV['PUSHER_APP_SECRET'] = 'mock_pusher_secret';
        $_ENV['PUSHER_APP_ID'] = 'mock_pusher_app_id';
        $_ENV['PUSHER_APP_CLUSTER'] = 'eu';

        $webPushMock = m::mock('overload:Minishlink\WebPush\WebPush');
        $webPushMock->shouldReceive('setReuseVAPIDHeaders')->byDefault();
        $webPushMock->shouldReceive('queueNotification')->byDefault()->andReturn(true);
        $webPushMock->shouldReceive('flush')->byDefault()->andReturn([]);

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
        $pdo->exec("
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
                scheduled_at DATETIME NULL,
                read_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
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
                device_platform VARCHAR(32) NULL,
                browser VARCHAR(32) NULL,
                app_version VARCHAR(32) NULL,
                user_agent TEXT NULL,
                failure_count INTEGER DEFAULT 0,
                last_seen DATETIME NULL,
                created_at DATETIME NULL
            );
        ");
    }

    public function testScheduledNotificationSuppressesImmediateCascade(): void
    {
        $userId = 'user_future_sched_1';
        $futureTime = date('Y-m-d H:i:s', time() + 3600); // 1 hour in future

        $notifId = NotificationOrchestrator::dispatch(
            userId: $userId,
            category: 'social',
            priority: 'high',
            title: 'Party Starts in 1 Hour!',
            body: 'Get ready for the main stage event.',
            actionUrl: '/party/main',
            tag: 'party_reminder',
            metadata: ['theme' => 'retro'],
            scheduledAt: $futureTime
        );

        $this->assertNotEmpty($notifId);
        $this->assertStringStartsWith('notif_', $notifId);

        // Verify status in DB is 'scheduled'
        $stmt = $this->pdo->prepare('SELECT status, scheduled_at FROM notification_orchestration WHERE id = ?');
        $stmt->execute([$notifId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertSame('scheduled', $row['status']);
        $this->assertSame($futureTime, $row['scheduled_at']);

        // Verify delivery log has scheduler entry and NO web_push/socket
        $logs = NotificationOrchestrator::getDeliveryLogs($notifId);
        $this->assertCount(1, $logs);
        $this->assertSame('scheduler', $logs[0]['channel']);
        $this->assertSame('scheduled', $logs[0]['status']);
    }

    public function testProcessScheduledDispatchesDueNotifications(): void
    {
        $userId = 'user_due_sched_2';
        $pastTime = date('Y-m-d H:i:s', time() - 60); // 1 minute in past

        // Insert directly into table with status = 'scheduled' and scheduled_at in the past
        $notifId = 'notif_due_' . bin2hex(random_bytes(6));
        $stmt = $this->pdo->prepare("
            INSERT INTO notification_orchestration 
            (id, user_id, category, priority, title, body, action_url, tag, status, scheduled_at)
            VALUES (?, ?, 'financial', 'critical', 'Payment Due', 'Your monthly statement is ready.', '/statement', 'fin_statement', 'scheduled', ?)
        ");
        $stmt->execute([$notifId, $userId, $pastTime]);

        // Process scheduled notifications
        $processedCount = NotificationOrchestrator::processScheduled(10);
        $this->assertGreaterThanOrEqual(1, $processedCount);

        // Verify notification status updated to 'sent'
        $stmt2 = $this->pdo->prepare('SELECT status FROM notification_orchestration WHERE id = ?');
        $stmt2->execute([$notifId]);
        $status = $stmt2->fetchColumn();
        $this->assertSame('sent', $status);

        // Verify delivery logs recorded
        $logs = NotificationOrchestrator::getDeliveryLogs($notifId);
        $this->assertNotEmpty($logs);
    }

    public function testRecordTelemetryDeliveredAndClicked(): void
    {
        $userId = 'telemetry_user_3';
        $notifId = NotificationOrchestrator::dispatch(
            userId: $userId,
            category: 'social',
            priority: 'medium',
            title: 'Welcome',
            body: 'Welcome to the platform!'
        );

        // 1. Record delivery confirmation
        $deliveredOk = NotificationOrchestrator::recordTelemetry($notifId, 'delivered', [
            'client_os' => 'iOS 18',
            'screen'    => 'lock_screen',
        ]);
        $this->assertTrue($deliveredOk);

        // 2. Record click confirmation
        $clickedOk = NotificationOrchestrator::recordTelemetry($notifId, 'clicked', [
            'user_id' => $userId,
            'source'  => 'banner_tap',
        ]);
        $this->assertTrue($clickedOk);

        // Verify status marked as read
        $stmt = $this->pdo->prepare('SELECT status, read_at FROM notification_orchestration WHERE id = ?');
        $stmt->execute([$notifId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $this->assertIsArray($row);
        $this->assertSame('read', $row['status']);
        $this->assertNotNull($row['read_at']);
    }

    public function testPushNotificationServiceRegisterSubscriptionWithTelemetry(): void
    {
        $userId = 'sub_user_4';
        $endpoint = 'https://fcm.googleapis.com/fcm/send/device_tok_abc_123';
        $p256dh = 'BMxR7...';
        $auth = 'auth_secret_xyz';

        $registered = PushNotificationService::registerSubscription($userId, $endpoint, $p256dh, $auth, [
            'device_platform' => 'iOS',
            'browser'         => 'Safari',
            'app_version'     => '2.4.1',
            'user_agent'      => 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0)',
        ]);

        $this->assertTrue($registered);

        // Fetch telemetry stats
        $stats = PushNotificationService::getSubscriptionTelemetry($userId);
        $this->assertCount(1, $stats);
        $this->assertSame($endpoint, $stats[0]['endpoint']);
        $this->assertSame('ios', $stats[0]['device_platform']);
        $this->assertSame('safari', $stats[0]['browser']);
        $this->assertSame('2.4.1', $stats[0]['app_version']);
    }

    public function testPushNotificationServiceRejectsSSRFEndpoint(): void
    {
        $userId = 'attacker_user';
        $maliciousEndpoint = 'http://169.254.169.254/latest/meta-data';

        $registered = PushNotificationService::registerSubscription(
            $userId,
            $maliciousEndpoint,
            'key',
            'auth'
        );

        $this->assertFalse($registered);
    }

    public function testAgentDocHelperGeneratesValidLlmsTxtAndNegotiatesMarkdown(): void
    {
        $llmsTxt = AgentDocHelper::generateLlmsTxt(
            appName: 'Shared Notification Engine',
            description: 'Intelligent multi-channel cascade notification system.',
            baseUrl: 'https://core.internal.app',
            endpoints: [
                'Dispatch API' => '/docs/dispatch.md',
                'Presence API' => '/docs/presence.md',
            ]
        );

        $this->assertStringContainsString('# Shared Notification Engine', $llmsTxt);
        $this->assertStringContainsString('https://core.internal.app/docs/dispatch.md', $llmsTxt);
        $this->assertStringContainsString('Append `.md` to documentation routes', $llmsTxt);

        // Test markdown content negotiation
        $prefersMd = AgentDocHelper::prefersMarkdown([
            'REQUEST_URI' => '/docs/notifications.md',
            'HTTP_ACCEPT' => 'text/html',
        ]);
        $this->assertTrue($prefersMd);

        $prefersHtml = AgentDocHelper::prefersMarkdown([
            'REQUEST_URI' => '/docs/notifications',
            'HTTP_ACCEPT' => 'text/html,application/xhtml+xml',
        ]);
        $this->assertFalse($prefersHtml);
    }
}
