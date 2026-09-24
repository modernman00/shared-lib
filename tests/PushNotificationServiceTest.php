<?php

declare(strict_types=1);

namespace Tests;

use Mockery as m;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Src\Db;
use Src\PushNotificationService;

/**
 * PushNotificationServiceTest
 *
 * Comprehensive offline, zero-network unit test suite for PushNotificationService
 * covering Tier 1 Feature Coverage, Tier 2 Boundary & SecOps Neutralization, and
 * Tier 3 Gateway & RFC 8291 / RFC 8292 Encryption Checks.
 */
class PushNotificationServiceTest extends TestCase
{
    private PDO $sqliteConnection;

    protected function setUp(): void
    {
        parent::setUp();

        // Configure deterministic VAPID test credentials in environment
        $_ENV['VAPID_PUBLIC_KEY'] = 'BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjDCWJ_BJMg4WCeTO20HG95IWKWGs';
        $_ENV['VAPID_PRIVATE_KEY'] = '1111111111111111111111111111111111111111111';
        $_ENV['VAPID_SUBJECT'] = 'mailto:secops@example.com';
        $_ENV['APP_LOGO'] = 'https://example.com/logo.png';
        $_ENV['APP_URL'] = 'https://example.com';

        // Provide an isolated in-memory SQLite connection for offline testing
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->sqliteConnection = $pdo;
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
            $_ENV['APP_URL']
        );

        parent::tearDown();
    }

    private function createDatabaseTables(PDO $pdo): void
    {
        $pdo->exec('
            CREATE TABLE IF NOT EXISTS user_push_subscriptions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id VARCHAR(64) NOT NULL,
                endpoint TEXT NOT NULL,
                p256dh TEXT NOT NULL,
                auth_token TEXT NOT NULL,
                created_at DATETIME NULL
            );
            CREATE TABLE IF NOT EXISTS pushNotification (
                id VARCHAR(64) NOT NULL,
                endpoint TEXT NOT NULL,
                p256dhKey TEXT NOT NULL,
                authKey TEXT NOT NULL
            );
        ');
    }

    private function insertSubscription(string $userId, string $endpoint, string $p256dh = 'test_p256dh_key', string $auth = 'test_auth_key'): void
    {
        $stmt = $this->sqliteConnection->prepare('
            INSERT INTO user_push_subscriptions (user_id, endpoint, p256dh, auth_token, created_at)
            VALUES (:uid, :endpoint, :p256dh, :auth, datetime("now"))
        ');
        $this->assertInstanceOf(PDOStatement::class, $stmt);
        $stmt->execute([
            ':uid'      => $userId,
            ':endpoint' => $endpoint,
            ':p256dh'   => $p256dh,
            ':auth'     => $auth,
        ]);
    }

    // =========================================================================
    // TIER 1: FEATURE COVERAGE
    // =========================================================================

    /**
     * @dataProvider provideValidPushGateways
     */
    public function testIsAllowedPushEndpointWithAllValidGateways(string $endpoint, string $gatewayName): void
    {
        $this->assertTrue(
            PushNotificationService::isAllowedPushEndpoint($endpoint),
            "Failed asserting that gateway '{$gatewayName}' endpoint '{$endpoint}' is allowed."
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function provideValidPushGateways(): array
    {
        return [
            'Google FCM primary' => [
                'https://fcm.googleapis.com/fcm/send/sample_fcm_token_123',
                'Google FCM',
            ],
            'Google FCM web push' => [
                'https://fcm.googleapis.com/wp/sample_wp_token_456',
                'Google FCM WebPush',
            ],
            'Google Android legacy GCM' => [
                'https://android.googleapis.com/gcm/send/sample_android_token_789',
                'Android GCM',
            ],
            'Apple APNs web push' => [
                'https://web.push.apple.com/QN/sample_apple_token_abc',
                'Apple APNs web.push',
            ],
            'Apple APNs api push' => [
                'https://api.push.apple.com/3/device/sample_apple_device_token',
                'Apple APNs api.push',
            ],
            'Apple APNs partition node' => [
                'https://1-web.push.apple.com/QN/sample_partition_token',
                'Apple APNs sub-domain partition',
            ],
            'Mozilla Autopush' => [
                'https://updates.push.services.mozilla.com/wpush/v2/sample_moz_token_def',
                'Mozilla Autopush',
            ],
            'Microsoft WNS db5' => [
                'https://db5.notify.windows.com/w/?token=sample_wns_token_ghi',
                'Microsoft WNS db5',
            ],
            'Microsoft WNS bn1' => [
                'https://bn1.notify.windows.com/w/?token=sample_wns_token_jkl',
                'Microsoft WNS bn1',
            ],
            'Amazon ADM push' => [
                'https://push.amazon.com/sample_amazon_token_mno',
                'Amazon ADM',
            ],
            'Allowed with explicit port 443' => [
                'https://fcm.googleapis.com:443/fcm/send/token_with_port_443',
                'Google FCM with port 443',
            ],
        ];
    }

    public function testPayloadAssemblyStructureIncludesAllRichFields(): void
    {
        $userId = 'user_rich_payload_test';
        $endpoint = 'https://fcm.googleapis.com/fcm/send/token_rich_payload';
        $this->insertSubscription($userId, $endpoint);

        $capturedPayload = null;
        $capturedOptions = null;

        $webPushMock = m::mock('overload:Minishlink\WebPush\WebPush');
        $webPushMock->shouldReceive('setReuseVAPIDHeaders')->andReturnNull();
        $webPushMock->shouldReceive('queueNotification')
            ->once()
            ->andReturnUsing(function ($sub, $payload, $options = []) use (&$capturedPayload, &$capturedOptions) {
                $capturedPayload = $payload;
                $capturedOptions = $options;
                return true;
            });
        $webPushMock->shouldReceive('flush')->once()->andReturn([]);

        $richOptions = [
            'image'              => 'https://example.com/banner.jpg',
            'icon'               => 'https://example.com/custom-icon.png',
            'badge'              => 'https://example.com/custom-badge.png',
            'vibrate'            => [100, 50, 100, 50, 200],
            'renotify'           => true,
            'silent'             => false,
            'requireInteraction' => true,
            'dir'                => 'ltr',
            'lang'               => 'en-GB',
            'actions'            => [
                [
                    'action' => 'confirm_payment',
                    'title'  => 'Approve Pledge',
                    'icon'   => 'https://example.com/icons/check.png',
                    'type'   => 'button',
                ],
                [
                    'action'      => 'reply_chat',
                    'title'       => 'Reply',
                    'type'        => 'text',
                    'placeholder' => 'Write your reply...',
                ],
            ],
            'data' => [
                'targetNotificationId' => 'notif_sec_999',
                'category'             => 'financial',
                'transaction_id'       => 'tx_88192',
            ],
        ];

        $dispatched = PushNotificationService::sendPush(
            userId: $userId,
            message: 'Your loan application has been approved!',
            url: '/loans/view/88192',
            title: 'Loan Easy Finance Approval',
            tag: 'loan_approval_88192',
            badgeCount: 4,
            options: $richOptions
        );

        $this->assertTrue($dispatched);
        $this->assertNotNull($capturedPayload, 'Payload must be passed to WebPush::queueNotification()');

        /** @var array<string, mixed> $payloadArray */
        $payloadArray = json_decode((string)$capturedPayload, true);
        $this->assertIsArray($payloadArray);

        // Verify root W3C fields
        $this->assertSame('Loan Easy Finance Approval', $payloadArray['title']);
        $this->assertSame('Your loan application has been approved!', $payloadArray['body']);
        $this->assertSame('/loans/view/88192', $payloadArray['url']);
        $this->assertSame('https://example.com/banner.jpg', $payloadArray['image']);
        $this->assertSame('https://example.com/custom-icon.png', $payloadArray['icon']);
        $this->assertSame('https://example.com/custom-badge.png', $payloadArray['badge']);
        $this->assertSame('loan_approval_88192', $payloadArray['tag']);
        $this->assertSame(4, $payloadArray['badgeCount']);
        $this->assertFalse($payloadArray['clearBadge']);
        $this->assertSame([100, 50, 100, 50, 200], $payloadArray['vibrate']);
        $this->assertTrue($payloadArray['renotify']);
        $this->assertFalse($payloadArray['silent']);
        $this->assertTrue($payloadArray['requireInteraction']);
        $this->assertSame('ltr', $payloadArray['dir']);
        $this->assertSame('en-GB', $payloadArray['lang']);
        $this->assertIsArray($payloadArray['actions']);
        $this->assertCount(2, $payloadArray['actions']);

        // Verify structured nested data dictionary
        $this->assertIsArray($payloadArray['data']);
        $this->assertSame('/loans/view/88192', $payloadArray['data']['url']);
        $this->assertSame('loan_approval_88192', $payloadArray['data']['tag']);
        $this->assertSame(4, $payloadArray['data']['badgeCount']);
        $this->assertFalse($payloadArray['data']['clearBadge']);
        $this->assertSame('financial', $payloadArray['data']['category']);
        $this->assertSame('notif_sec_999', $payloadArray['data']['targetNotificationId']);
        $this->assertSame('tx_88192', $payloadArray['data']['transaction_id']);
        $this->assertCount(2, $payloadArray['data']['actions']);
    }

    public function testWebBadgingIntegrationWithPositiveCount(): void
    {
        $userId = 'user_badging_positive';
        $endpoint = 'https://fcm.googleapis.com/fcm/send/token_badging_pos';
        $this->insertSubscription($userId, $endpoint);

        $capturedPayload = null;

        $webPushMock = m::mock('overload:Minishlink\WebPush\WebPush');
        $webPushMock->shouldReceive('setReuseVAPIDHeaders')->andReturnNull();
        $webPushMock->shouldReceive('queueNotification')
            ->once()
            ->andReturnUsing(function ($sub, $payload, $options = []) use (&$capturedPayload) {
                $capturedPayload = json_decode((string)$payload, true);
                return true;
            });
        $webPushMock->shouldReceive('flush')->once()->andReturn([]);

        $dispatched = PushNotificationService::sendPush(
            userId: $userId,
            message: 'You have new unread messages',
            title: 'Unread Messages',
            badgeCount: 7
        );

        $this->assertTrue($dispatched);
        $this->assertNotNull($capturedPayload);
        $this->assertSame(7, $capturedPayload['badgeCount']);
        $this->assertFalse($capturedPayload['clearBadge']);
        $this->assertSame(7, $capturedPayload['data']['badgeCount']);
        $this->assertFalse($capturedPayload['data']['clearBadge']);
    }

    public function testWebBadgingIntegrationWithZeroCountSetsClearBadge(): void
    {
        $userId = 'user_badging_zero';
        $endpoint = 'https://fcm.googleapis.com/fcm/send/token_badging_zero';
        $this->insertSubscription($userId, $endpoint);

        $capturedPayload = null;

        $webPushMock = m::mock('overload:Minishlink\WebPush\WebPush');
        $webPushMock->shouldReceive('setReuseVAPIDHeaders')->andReturnNull();
        $webPushMock->shouldReceive('queueNotification')
            ->once()
            ->andReturnUsing(function ($sub, $payload, $options = []) use (&$capturedPayload) {
                $capturedPayload = json_decode((string)$payload, true);
                return true;
            });
        $webPushMock->shouldReceive('flush')->once()->andReturn([]);

        $dispatched = PushNotificationService::sendPush(
            userId: $userId,
            message: 'All notifications caught up',
            title: 'Inbox Zero',
            badgeCount: 0
        );

        $this->assertTrue($dispatched);
        $this->assertNotNull($capturedPayload);
        $this->assertSame(0, $capturedPayload['badgeCount']);
        $this->assertTrue($capturedPayload['clearBadge']);
        $this->assertSame(0, $capturedPayload['data']['badgeCount']);
        $this->assertTrue($capturedPayload['data']['clearBadge']);
    }

    public function testBackwardCompatibilityWithLegacyPositionalArguments(): void
    {
        $userId = 'user_legacy_test';
        $endpoint = 'https://fcm.googleapis.com/fcm/send/token_legacy';
        $this->insertSubscription($userId, $endpoint);

        $capturedPayload = null;

        $webPushMock = m::mock('overload:Minishlink\WebPush\WebPush');
        $webPushMock->shouldReceive('setReuseVAPIDHeaders')->andReturnNull();
        $webPushMock->shouldReceive('queueNotification')
            ->once()
            ->andReturnUsing(function ($sub, $payload, $options = []) use (&$capturedPayload) {
                $capturedPayload = json_decode((string)$payload, true);
                return true;
            });
        $webPushMock->shouldReceive('flush')->once()->andReturn([]);

        // Call sendPush with legacy positional arguments:
        // ($userId, $message, $url, $title, $tag, $badgeCount, $isSilent, $syncAction, $targetNotificationId)
        $dispatched = PushNotificationService::sendPush(
            $userId,
            'Legacy Alert Body',
            '/legacy-url',
            'Legacy Alert Title',
            'sync-tag',
            2,
            true,                       // $isSilent
            'CLOSE_NOTIFICATION',       // $syncAction
            'notif_legacy_uuid_1234'    // $targetNotificationId
        );

        $this->assertTrue($dispatched);
        $this->assertNotNull($capturedPayload);
        $this->assertTrue($capturedPayload['isSilent']);
        $this->assertTrue($capturedPayload['silent']);
        $this->assertSame('CLOSE_NOTIFICATION', $capturedPayload['syncAction']);
        $this->assertSame('notif_legacy_uuid_1234', $capturedPayload['targetNotificationId']);
        $this->assertSame('notif_legacy_uuid_1234', $capturedPayload['data']['targetNotificationId']);
    }

    // =========================================================================
    // TIER 2: BOUNDARY & CORNER CASES (SECOPS & POC NEUTRALIZATION)
    // =========================================================================

    /**
     * @dataProvider provideSsrfCloudMetadataEndpoints
     */
    public function testSsrfRejectionOfCloudMetadata(string $endpoint, string $attackDesc): void
    {
        $this->assertFalse(
            PushNotificationService::isAllowedPushEndpoint($endpoint),
            "SecOps Violation: Cloud metadata endpoint was NOT rejected: {$attackDesc} ({$endpoint})"
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function provideSsrfCloudMetadataEndpoints(): array
    {
        return [
            'AWS IMDSv1 plaintext' => [
                'http://169.254.169.254/latest/meta-data/',
                'AWS IMDSv1 plaintext HTTP',
            ],
            'AWS IMDSv1 HTTPS bypass attempt' => [
                'https://169.254.169.254/latest/meta-data/iam/security-credentials/',
                'AWS IMDSv1 over HTTPS IP',
            ],
            'Google Cloud internal metadata' => [
                'http://metadata.google.internal/computeMetadata/v1/',
                'GCP Internal Metadata DNS',
            ],
            'Azure Instance Metadata Service' => [
                'http://169.254.169.254/metadata/instance?api-version=2021-02-01',
                'Azure IMDS',
            ],
            'Alibaba Cloud ECS metadata' => [
                'http://100.100.100.200/latest/meta-data/',
                'Alibaba Cloud Metadata',
            ],
        ];
    }

    /**
     * @dataProvider provideSsrfLoopbackAndPrivateIpEndpoints
     */
    public function testSsrfRejectionOfLoopbackAndPrivateIps(string $endpoint, string $targetDesc): void
    {
        $this->assertFalse(
            PushNotificationService::isAllowedPushEndpoint($endpoint),
            "SecOps Violation: Internal or loopback target was NOT rejected: {$targetDesc} ({$endpoint})"
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function provideSsrfLoopbackAndPrivateIpEndpoints(): array
    {
        return [
            'Loopback IPv4 with Redis port' => [
                'http://127.0.0.1:6379/',
                'Redis on loopback',
            ],
            'Loopback IPv4 HTTPS' => [
                'https://127.0.0.1:443/',
                'HTTPS on loopback IP',
            ],
            'Localhost hostname' => [
                'http://localhost/',
                'Localhost HTTP',
            ],
            'Localhost HTTPS' => [
                'https://localhost:443/',
                'Localhost HTTPS',
            ],
            'RFC 1918 10.0.0.0/8' => [
                'http://10.0.0.1/admin',
                'Private network 10.x',
            ],
            'RFC 1918 192.168.0.0/16' => [
                'http://192.168.1.1/api/push',
                'Home router / intranet',
            ],
            'RFC 1918 172.16.0.0/12' => [
                'http://172.16.0.1/',
                'Docker/corporate intranet',
            ],
            'IPv6 Loopback' => [
                'http://[::1]:8080/push',
                'IPv6 Loopback',
            ],
            'IPv4-mapped IPv6 loopback' => [
                'http://[::ffff:127.0.0.1]:6379/',
                'IPv4-mapped IPv6',
            ],
        ];
    }

    /**
     * @dataProvider provideSsrfNonHttpsSchemes
     */
    public function testSsrfRejectionOfNonHttpsSchemes(string $endpoint, string $scheme): void
    {
        $this->assertFalse(
            PushNotificationService::isAllowedPushEndpoint($endpoint),
            "SecOps Violation: Non-HTTPS scheme '{$scheme}' was NOT rejected on endpoint: {$endpoint}"
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function provideSsrfNonHttpsSchemes(): array
    {
        return [
            'Plaintext HTTP with FCM' => [
                'http://fcm.googleapis.com/fcm/send/plain_http_fcm',
                'http',
            ],
            'FTP protocol' => [
                'ftp://fcm.googleapis.com/upload',
                'ftp',
            ],
            'Gopher protocol' => [
                'gopher://fcm.googleapis.com:70/test',
                'gopher',
            ],
            'File protocol' => [
                'file:///etc/passwd',
                'file',
            ],
            'PHP stream wrapper' => [
                'php://filter/read=convert.base64-encode/resource=https://fcm.googleapis.com',
                'php',
            ],
            'Data URI' => [
                'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
                'data',
            ],
        ];
    }

    /**
     * @dataProvider provideSsrfPortProbingEndpoints
     */
    public function testSsrfRejectionOfPortProbing(string $endpoint, int $port, bool $shouldBeAllowed): void
    {
        $result = PushNotificationService::isAllowedPushEndpoint($endpoint);
        if ($shouldBeAllowed) {
            $this->assertTrue($result, "Expected port {$port} to be allowed on {$endpoint}");
        } else {
            $this->assertFalse($result, "SecOps Violation: Port {$port} probing was NOT blocked on {$endpoint}");
        }
    }

    /**
     * @return array<string, array{0: string, 1: int, 2: bool}>
     */
    public static function provideSsrfPortProbingEndpoints(): array
    {
        return [
            'SSH Port 22 on FCM' => [
                'https://fcm.googleapis.com:22/fcm/send',
                22,
                false,
            ],
            'Redis Port 6379 on Apple APNs' => [
                'https://push.apple.com:6379/QN/token',
                6379,
                false,
            ],
            'Alternative HTTP Port 8080 on Mozilla' => [
                'https://updates.push.services.mozilla.com:8080/wpush/v2',
                8080,
                false,
            ],
            'Plain HTTP Port 80 on WNS' => [
                'https://db5.notify.windows.com:80/w/',
                80,
                false,
            ],
            'Standard HTTPS Port 443 on FCM' => [
                'https://fcm.googleapis.com:443/fcm/send/token',
                443,
                true,
            ],
        ];
    }

    /**
     * @dataProvider provideSsrfUserinfoObfuscationEndpoints
     */
    public function testSsrfRejectionOfUserinfoObfuscation(string $endpoint, string $desc): void
    {
        $this->assertFalse(
            PushNotificationService::isAllowedPushEndpoint($endpoint),
            "SecOps Violation: Userinfo obfuscation was NOT blocked: {$desc} ({$endpoint})"
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function provideSsrfUserinfoObfuscationEndpoints(): array
    {
        return [
            'Username and password in FCM URL' => [
                'https://user:password@fcm.googleapis.com/fcm/send',
                'user:password credentials in authority',
            ],
            'Username only in Apple URL' => [
                'https://attacker@web.push.apple.com/QN/token',
                'username only in authority',
            ],
            'URL-encoded userinfo' => [
                'https://admin%3Asecret@updates.push.services.mozilla.com/token',
                'URL encoded credentials',
            ],
        ];
    }

    /**
     * @dataProvider provideSsrfGenericGoogleCloudApiEndpoints
     */
    public function testSsrfRejectionOfGenericGoogleCloudApis(string $endpoint, string $serviceName): void
    {
        $this->assertFalse(
            PushNotificationService::isAllowedPushEndpoint($endpoint),
            "SecOps Violation: Broad Google Cloud API '{$serviceName}' was permitted ({$endpoint})"
        );
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function provideSsrfGenericGoogleCloudApiEndpoints(): array
    {
        return [
            'Google Cloud Storage bucket' => [
                'https://storage.googleapis.com/my-private-bucket/keys.json',
                'Cloud Storage',
            ],
            'Google Cloud IAM' => [
                'https://iam.googleapis.com/v1/projects/my-project/serviceAccounts',
                'IAM Service',
            ],
            'Google Compute Engine API' => [
                'https://compute.googleapis.com/compute/v1/projects',
                'Compute Engine',
            ],
            'Generic googleapis.com apex domain' => [
                'https://googleapis.com/auth',
                'Apex googleapis.com',
            ],
            'Arbitrary Cloud Run subdomain' => [
                'https://my-internal-service-xyz.run.app.googleapis.com/',
                'Cloud Run',
            ],
        ];
    }

    public function testWhatwgConflictNormalizationSilentWithVibrateStripsVibrate(): void
    {
        $userId = 'user_whatwg_silent_vibrate';
        $endpoint = 'https://fcm.googleapis.com/fcm/send/token_whatwg_1';
        $this->insertSubscription($userId, $endpoint);

        $capturedPayload = null;

        $webPushMock = m::mock('overload:Minishlink\WebPush\WebPush');
        $webPushMock->shouldReceive('setReuseVAPIDHeaders')->andReturnNull();
        $webPushMock->shouldReceive('queueNotification')
            ->once()
            ->andReturnUsing(function ($sub, $payload, $options = []) use (&$capturedPayload) {
                $capturedPayload = json_decode((string)$payload, true);
                return true;
            });
        $webPushMock->shouldReceive('flush')->once()->andReturn([]);

        // WHATWG Rule: Browsers throw TypeError if silent: true AND vibrate is present.
        // The service must strip vibrate to prevent runtime browser exception.
        PushNotificationService::sendPush(
            userId: $userId,
            message: 'Silent sync message',
            title: 'Quiet Update',
            options: [
                'silent'  => true,
                'vibrate' => [200, 100, 200], // Conflicting option
            ]
        );

        $this->assertNotNull($capturedPayload);
        $this->assertTrue($capturedPayload['silent']);
        $this->assertNull(
            $capturedPayload['vibrate'],
            'Vibrate pattern must be stripped when silent is true to prevent WHATWG TypeError.'
        );
    }

    public function testWhatwgConflictNormalizationRenotifyWithEmptyTagNormalizesSafely(): void
    {
        $userId = 'user_whatwg_renotify_tag';
        $endpoint = 'https://fcm.googleapis.com/fcm/send/token_whatwg_2';
        $this->insertSubscription($userId, $endpoint);

        $capturedPayload = null;

        $webPushMock = m::mock('overload:Minishlink\WebPush\WebPush');
        $webPushMock->shouldReceive('setReuseVAPIDHeaders')->andReturnNull();
        $webPushMock->shouldReceive('queueNotification')
            ->once()
            ->andReturnUsing(function ($sub, $payload, $options = []) use (&$capturedPayload) {
                $capturedPayload = json_decode((string)$payload, true);
                return true;
            });
        $webPushMock->shouldReceive('flush')->once()->andReturn([]);

        // WHATWG Rule: Browsers throw TypeError if renotify: true AND tag is empty.
        // The service must ensure that tag is fallback-normalized or non-empty.
        PushNotificationService::sendPush(
            userId: $userId,
            message: 'Renotify with empty tag attempt',
            tag: '', // Empty tag
            options: [
                'renotify' => true,
                'tag'      => '',
            ]
        );

        $this->assertNotNull($capturedPayload);
        $this->assertNotEmpty(
            $capturedPayload['tag'],
            'Tag must not be empty when renotify is true to prevent WHATWG TypeError.'
        );
    }

    public function testTagSanitizationAndTruncation(): void
    {
        $userId = 'user_tag_sanitization';
        $endpoint = 'https://fcm.googleapis.com/fcm/send/token_tag_sanitized';
        $this->insertSubscription($userId, $endpoint);

        $capturedPayload = null;
        $capturedOptions = null;

        $webPushMock = m::mock('overload:Minishlink\WebPush\WebPush');
        $webPushMock->shouldReceive('setReuseVAPIDHeaders')->andReturnNull();
        $webPushMock->shouldReceive('queueNotification')
            ->once()
            ->andReturnUsing(function ($sub, $payload, $options = []) use (&$capturedPayload, &$capturedOptions) {
                $capturedPayload = json_decode((string)$payload, true);
                $capturedOptions = $options;
                return true;
            });
        $webPushMock->shouldReceive('flush')->once()->andReturn([]);

        // RFC 8030 Topic header: max 32 chars, Base64URL set only ([A-Za-z0-9_-]).
        // Pass a 60-character tag with forbidden characters like spaces, punctuation, symbols.
        $unsafeTag = 'order#9812-item@details:!special_and_extremely_long_identifier_beyond_limit';

        PushNotificationService::sendPush(
            userId: $userId,
            message: 'Your order has shipped',
            tag: $unsafeTag
        );

        $this->assertNotNull($capturedPayload);
        $sanitizedTag = $capturedPayload['tag'];

        // Assert no special characters remain
        $this->assertMatchesRegularExpression(
            '/^[A-Za-z0-9_-]+$/',
            $sanitizedTag,
            'Sanitized tag must only contain URL-safe alphanumeric, underscore, or hyphen characters.'
        );

        // Assert clamped to at most 32 characters
        $this->assertLessThanOrEqual(
            32,
            strlen($sanitizedTag),
            'Sanitized tag must be clamped to at most 32 characters for RFC 8030 Topic header compliance.'
        );

        // Assert topic header in gateway options matches sanitized tag
        $this->assertSame($sanitizedTag, $capturedOptions['topic']);
    }

    // =========================================================================
    // TIER 3: GATEWAY & ENCRYPTION CHECKS
    // =========================================================================

    public function testWebPushOptionsRfc8030GatewayHeaders(): void
    {
        $userId = 'user_rfc8030_options';
        $endpoint = 'https://fcm.googleapis.com/fcm/send/token_rfc8030';
        $this->insertSubscription($userId, $endpoint);

        $capturedOptions = null;

        $webPushMock = m::mock('overload:Minishlink\WebPush\WebPush');
        $webPushMock->shouldReceive('setReuseVAPIDHeaders')->andReturnNull();
        $webPushMock->shouldReceive('queueNotification')
            ->once()
            ->andReturnUsing(function ($sub, $payload, $options = []) use (&$capturedOptions) {
                $capturedOptions = $options;
                return true;
            });
        $webPushMock->shouldReceive('flush')->once()->andReturn([]);

        PushNotificationService::sendPush(
            userId: $userId,
            message: 'Urgent security alert',
            tag: 'security_alert_01',
            options: [
                'ttl'     => 7200,
                'urgency' => 'high',
            ]
        );

        $this->assertIsArray($capturedOptions);
        $this->assertSame(7200, $capturedOptions['TTL']);
        $this->assertSame('high', $capturedOptions['urgency']);
        $this->assertSame('security_alert_01', $capturedOptions['topic']);
    }

    public function testVapidContentEncodingConfiguresAes128Gcm(): void
    {
        $userId = 'user_vapid_encoding';
        $endpoint = 'https://fcm.googleapis.com/fcm/send/token_vapid_encoding';
        $this->insertSubscription($userId, $endpoint, 'p256dh_key_sample', 'auth_key_sample');

        /** @var \Minishlink\WebPush\SubscriptionInterface|null $capturedSub */
        $capturedSub = null;

        $webPushMock = m::mock('overload:Minishlink\WebPush\WebPush');
        $webPushMock->shouldReceive('setReuseVAPIDHeaders')->andReturnNull();
        $webPushMock->shouldReceive('queueNotification')
            ->once()
            ->andReturnUsing(function ($sub, $payload, $options = []) use (&$capturedSub) {
                $capturedSub = $sub;
                return true;
            });
        $webPushMock->shouldReceive('flush')->once()->andReturn([]);

        PushNotificationService::sendPush(
            userId: $userId,
            message: 'Testing RFC 8291 aes128gcm encoding'
        );

        $this->assertNotNull($capturedSub, 'Subscription object must be passed to WebPush::queueNotification()');
        $this->assertInstanceOf(\Minishlink\WebPush\Subscription::class, $capturedSub);

        // Verify modern RFC 8291 content encoding is set to aes128gcm
        $this->assertSame(
            'aes128gcm',
            $capturedSub->getContentEncoding(),
            'Subscription must specify aes128gcm content encoding for RFC 8291 / RFC 8292 compliance.'
        );
    }

    public function testSendPushReturnsFalseWhenVapidKeysMissing(): void
    {
        unset($_ENV['VAPID_PUBLIC_KEY'], $_ENV['VAPID_PRIVATE_KEY']);
        putenv('VAPID_PUBLIC_KEY');
        putenv('VAPID_PRIVATE_KEY');

        $result = PushNotificationService::sendPush('user1', 'Test message without VAPID keys');
        $this->assertFalse($result, 'sendPush must return false when VAPID keys are missing.');
    }

    public function testSendPushReturnsFalseWhenUserIdEmpty(): void
    {
        $this->assertFalse(PushNotificationService::sendPush('', 'Message'));
        $this->assertFalse(PushNotificationService::sendPush(null, 'Message'));
        $this->assertFalse(PushNotificationService::sendPush([], 'Message'));
    }

    public function testSendPushReturnsFalseWhenUserHasNoSubscriptions(): void
    {
        $webPushMock = m::mock('overload:Minishlink\WebPush\WebPush');
        $webPushMock->shouldReceive('setReuseVAPIDHeaders')->andReturnNull();

        // User exists with no subscriptions in database
        $result = PushNotificationService::sendPush('user_with_no_subs_at_all', 'Hello World');
        $this->assertFalse($result, 'sendPush must return false when user has no registered push subscriptions.');
    }
}
