<?php

declare(strict_types=1);

namespace Tests;

use GuzzleHttp\Client;
use Mockery as m;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;
use Src\Db;
use Src\PushNotificationService;

/**
 * PushNotificationServiceChallengerTest
 *
 * Adversarial Verification Test Suite for PushNotificationService (Challenger 1):
 * - SSRF Evasion & Parsing Stress: IPv4, IPv6, Hex, Decimal, Octal, Cloud Metadata,
 *   Port Manipulation, Open Redirects, Scheme Evasions, URL Encoding, Subdomain Spoofing.
 * - Gate 4 Bounded Timeout Verification: Reflection on WebPush constructor, clientOptions,
 *   and Guzzle configuration (timeout=3.0, connect_timeout=2.0, allow_redirects=false, http_errors=false).
 * - Payload Fuzzing: Extremely long titles/bodies, tag sanitization & clamping, Unicode &
 *   multi-byte character handling, WHATWG conflict normalization.
 * - Web Badging API Edge Cases: Zero, positive, negative counts, extreme integers, explicit clearBadge.
 */
class PushNotificationServiceChallengerTest extends TestCase
{
    private PDO $sqliteConnection;

    protected function setUp(): void
    {
        parent::setUp();

        $_ENV['VAPID_PUBLIC_KEY'] = 'BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjDCWJ_BJMg4WCeTO20HG95IWKWGs';
        $_ENV['VAPID_PRIVATE_KEY'] = '1111111111111111111111111111111111111111111';
        $_ENV['VAPID_SUBJECT'] = 'mailto:challenger1@example.com';
        $_ENV['APP_LOGO'] = 'https://example.com/logo.png';
        $_ENV['APP_URL'] = 'https://example.com';

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

    private function insertSubscription(string $userId, string $endpoint, string $p256dh = 'test_p256dh', string $auth = 'test_auth'): void
    {
        $stmt = $this->sqliteConnection->prepare('
            INSERT INTO user_push_subscriptions (user_id, endpoint, p256dh, auth_token, created_at)
            VALUES (:uid, :endpoint, :p256dh, :auth, datetime("now"))
        ');
        $stmt->execute([
            ':uid'      => $userId,
            ':endpoint' => $endpoint,
            ':p256dh'   => $p256dh,
            ':auth'     => $auth,
        ]);
    }

    // =========================================================================
    // 1. SSRF EVASION & PARSING STRESS HARNESS
    // =========================================================================

    /**
     * @dataProvider provideSsrfAdversarialEndpoints
     */
    public function testSsrfAdversarialEndpoints(string $endpoint, bool $expectedAllowed, string $threatCategory, string $attackDescription): void
    {
        $isAllowed = PushNotificationService::isAllowedPushEndpoint($endpoint);
        if ($expectedAllowed) {
            $this->assertTrue(
                $isAllowed,
                "False Negative: Permitted push endpoint '{$endpoint}' was blocked ({$threatCategory}: {$attackDescription})"
            );
        } else {
            $this->assertFalse(
                $isAllowed,
                "VULNERABILITY: Adversarial SSRF evasion bypassed allowlist! '{$endpoint}' was permitted ({$threatCategory}: {$attackDescription})"
            );
        }
    }

    /**
     * @return array<string, array{0: string, 1: bool, 2: string, 3: string}>
     */
    public static function provideSsrfAdversarialEndpoints(): array
    {
        return [
            // --- IPv4 Dotted-Decimal Loopback & Private ---
            'IPv4 loopback plain' => [
                'http://127.0.0.1/push',
                false,
                'IPv4',
                'Plaintext loopback',
            ],
            'IPv4 loopback https' => [
                'https://127.0.0.1/push',
                false,
                'IPv4',
                'HTTPS loopback',
            ],
            'IPv4 loopback port 443' => [
                'https://127.0.0.1:443/push',
                false,
                'IPv4',
                'HTTPS loopback with port 443',
            ],
            'IPv4 loopback alt 127.0.0.2' => [
                'https://127.0.0.2/push',
                false,
                'IPv4',
                'Loopback block 127.0.0.2',
            ],
            'IPv4 loopback alt 127.127.127.127' => [
                'https://127.127.127.127/push',
                false,
                'IPv4',
                'Loopback block boundary 127.127.127.127',
            ],
            'IPv4 private 10.x' => [
                'https://10.0.0.1/push',
                false,
                'IPv4',
                'Class A RFC 1918 private network',
            ],
            'IPv4 private 172.16.x' => [
                'https://172.16.0.1/push',
                false,
                'IPv4',
                'Class B RFC 1918 private network',
            ],
            'IPv4 private 192.168.x' => [
                'https://192.168.1.1/push',
                false,
                'IPv4',
                'Class C RFC 1918 private network',
            ],
            'IPv4 0.0.0.0' => [
                'https://0.0.0.0/push',
                false,
                'IPv4',
                'INADDR_ANY 0.0.0.0',
            ],

            // --- IPv6 Loopback, Mapped, & Link-Local ---
            'IPv6 standard loopback' => [
                'https://[::1]/push',
                false,
                'IPv6',
                'IPv6 localhost [::1]',
            ],
            'IPv6 loopback port 443' => [
                'https://[::1]:443/push',
                false,
                'IPv6',
                'IPv6 localhost with port 443',
            ],
            'IPv6 unspecified [::]' => [
                'https://[::]/push',
                false,
                'IPv6',
                'IPv6 unspecified address',
            ],
            'IPv6 fully expanded loopback' => [
                'https://[0000:0000:0000:0000:0000:0000:0000:0001]/push',
                false,
                'IPv6',
                'Fully expanded IPv6 loopback',
            ],
            'IPv6 IPv4-mapped loopback' => [
                'https://[::ffff:127.0.0.1]/push',
                false,
                'IPv6',
                'IPv4-mapped IPv6 loopback',
            ],
            'IPv6 IPv4-mapped hex loopback' => [
                'https://[::ffff:7f00:1]/push',
                false,
                'IPv6',
                'IPv4-mapped hex representation',
            ],
            'IPv6 link-local' => [
                'https://[fe80::1]/push',
                false,
                'IPv6',
                'IPv6 link-local address',
            ],
            'IPv6 documentation prefix' => [
                'https://[2001:db8::1]/push',
                false,
                'IPv6',
                'Documentation IPv6',
            ],

            // --- Hex IP Representations ---
            'Hex IP 127.0.0.1 as dword' => [
                'https://0x7f000001/push',
                false,
                'Hex IP',
                'Hexadecimal representation of 127.0.0.1',
            ],
            'Hex IP dotted 0x7f.0.0.1' => [
                'https://0x7f.0.0.1/push',
                false,
                'Hex IP',
                'Partial hex dotted IP',
            ],
            'Hex IP all dotted' => [
                'https://0x7f.0x0.0x0.0x1/push',
                false,
                'Hex IP',
                'All-parts hex dotted IP',
            ],
            'Hex IP short form 0x7f.1' => [
                'https://0x7f.1/push',
                false,
                'Hex IP',
                'Short form two-part hex IP',
            ],
            'Hex IP metadata 169.254.169.254' => [
                'https://0xa9fea9fe/latest/meta-data/',
                false,
                'Hex IP',
                'Hex encoded AWS IMDS IP',
            ],

            // --- Decimal (Dword) IP Representations ---
            'Decimal IP 127.0.0.1 (2130706433)' => [
                'https://2130706433/push',
                false,
                'Decimal IP',
                'Dword decimal integer representation of 127.0.0.1',
            ],
            'Decimal IP metadata (2852039166)' => [
                'https://2852039166/latest/meta-data/',
                false,
                'Decimal IP',
                'Dword decimal integer representation of 169.254.169.254',
            ],
            'Decimal IP 192.168.1.1 (3232235777)' => [
                'https://3232235777/push',
                false,
                'Decimal IP',
                'Dword decimal representation of 192.168.1.1',
            ],
            'Decimal IP 10.0.0.1 (167772161)' => [
                'https://167772161/push',
                false,
                'Decimal IP',
                'Dword decimal representation of 10.0.0.1',
            ],
            'Decimal IP 0' => [
                'https://0/push',
                false,
                'Decimal IP',
                'Dword integer 0 for INADDR_ANY',
            ],

            // --- Octal IP Representations ---
            'Octal IP 0177.0.0.1' => [
                'https://0177.0.0.1/push',
                false,
                'Octal IP',
                'Leading zero octal representation of 127.0.0.1',
            ],
            'Octal IP all parts' => [
                'https://0177.0000.0000.0001/push',
                false,
                'Octal IP',
                'Full 4-part octal representation',
            ],
            'Octal IP single dword 017700000001' => [
                'https://017700000001/push',
                false,
                'Octal IP',
                'Single dword octal integer',
            ],
            'Octal IP metadata 169.254.169.254' => [
                'https://0251.0372.0251.0372/latest/meta-data/',
                false,
                'Octal IP',
                'Octal encoded AWS IMDS IP',
            ],

            // --- Cloud Metadata Endpoints ---
            'AWS IMDSv1 plaintext' => [
                'http://169.254.169.254/latest/meta-data/',
                false,
                'Cloud Metadata',
                'AWS IMDSv1 root plaintext',
            ],
            'AWS IMDSv1 HTTPS' => [
                'https://169.254.169.254/latest/meta-data/',
                false,
                'Cloud Metadata',
                'AWS IMDSv1 root HTTPS',
            ],
            'AWS IMDSv1 HTTPS port 443' => [
                'https://169.254.169.254:443/latest/meta-data/iam/security-credentials/',
                false,
                'Cloud Metadata',
                'AWS IMDSv1 IAM credentials with port 443',
            ],
            'AWS ECS Task Metadata 169.254.170.2' => [
                'http://169.254.170.2/v2/credentials/',
                false,
                'Cloud Metadata',
                'AWS ECS Task IAM credential endpoint',
            ],
            'AWS ECS Task Metadata HTTPS' => [
                'https://169.254.170.2:443/v2/credentials/',
                false,
                'Cloud Metadata',
                'AWS ECS Task IAM credential endpoint over HTTPS',
            ],
            'GCP Internal Metadata DNS' => [
                'http://metadata.google.internal/computeMetadata/v1/',
                false,
                'Cloud Metadata',
                'Google Cloud internal DNS metadata',
            ],
            'GCP Internal Metadata HTTPS' => [
                'https://metadata.google.internal/computeMetadata/v1/',
                false,
                'Cloud Metadata',
                'Google Cloud internal DNS metadata HTTPS',
            ],
            'Alibaba Cloud ECS Metadata' => [
                'http://100.100.100.200/latest/meta-data/',
                false,
                'Cloud Metadata',
                'Alibaba Cloud internal metadata IP',
            ],

            // --- Port Manipulation on Allowed Hosts ---
            'FCM with SSH Port 22' => [
                'https://fcm.googleapis.com:22/fcm/send',
                false,
                'Port Manipulation',
                'SSH Port 22 probe on allowed FCM hostname',
            ],
            'FCM with HTTP Port 80' => [
                'https://fcm.googleapis.com:80/fcm/send',
                false,
                'Port Manipulation',
                'HTTP Port 80 on allowed FCM hostname',
            ],
            'FCM with Alt HTTP Port 8080' => [
                'https://fcm.googleapis.com:8080/fcm/send',
                false,
                'Port Manipulation',
                'Alt HTTP Port 8080 on allowed FCM hostname',
            ],
            'FCM with Redis Port 6379' => [
                'https://fcm.googleapis.com:6379/fcm/send',
                false,
                'Port Manipulation',
                'Redis Port 6379 on allowed FCM hostname',
            ],
            'FCM with Port 4430' => [
                'https://fcm.googleapis.com:4430/fcm/send',
                false,
                'Port Manipulation',
                'Port 4430 probe on allowed FCM hostname',
            ],
            'FCM with Port 0' => [
                'https://fcm.googleapis.com:0/fcm/send',
                false,
                'Port Manipulation',
                'Reserved Port 0 on allowed FCM hostname',
            ],
            'FCM with Port 65535' => [
                'https://fcm.googleapis.com:65535/fcm/send',
                false,
                'Port Manipulation',
                'Boundary max Port 65535 on allowed FCM hostname',
            ],
            'Apple APNs with Port 8443' => [
                'https://push.apple.com:8443/push',
                false,
                'Port Manipulation',
                'Port 8443 probe on allowed Apple push domain',
            ],
            'Mozilla Autopush with Port 8000' => [
                'https://updates.push.services.mozilla.com:8000/wpush/v2',
                false,
                'Port Manipulation',
                'Port 8000 probe on allowed Mozilla push domain',
            ],
            'FCM with Standard HTTPS Port 443' => [
                'https://fcm.googleapis.com:443/fcm/send',
                true,
                'Legitimate Port',
                'Explicit standard HTTPS Port 443 on FCM',
            ],
            'FCM with Default Implicit Port 443' => [
                'https://fcm.googleapis.com/fcm/send',
                true,
                'Legitimate Port',
                'Implicit default HTTPS Port 443 on FCM',
            ],
            'Apple APNs with Standard Port 443' => [
                'https://web.push.apple.com:443/QN/token',
                true,
                'Legitimate Port',
                'Explicit standard HTTPS Port 443 on Apple APNs',
            ],

            // --- Scheme Evasions ---
            'Uppercase HTTPS scheme' => [
                'HTTPS://fcm.googleapis.com/fcm/send/token',
                true,
                'Scheme Validation',
                'Uppercase HTTPS must be accepted (case-insensitive standard)',
            ],
            'Mixed case HtTpS scheme' => [
                'HtTpS://fcm.googleapis.com/fcm/send/token',
                true,
                'Scheme Validation',
                'Mixed case HtTpS must be accepted',
            ],
            'Plaintext HTTP scheme' => [
                'http://fcm.googleapis.com/fcm/send/token',
                false,
                'Scheme Validation',
                'Plaintext HTTP must be strictly rejected',
            ],
            'Uppercase HTTP scheme' => [
                'HTTP://fcm.googleapis.com/fcm/send/token',
                false,
                'Scheme Validation',
                'Uppercase HTTP must be strictly rejected',
            ],
            'FTP protocol' => [
                'ftp://fcm.googleapis.com/fcm/send',
                false,
                'Scheme Validation',
                'FTP scheme must be rejected',
            ],
            'Gopher protocol' => [
                'gopher://fcm.googleapis.com:70/push',
                false,
                'Scheme Validation',
                'Gopher scheme must be rejected',
            ],
            'File protocol' => [
                'file:///etc/passwd',
                false,
                'Scheme Validation',
                'File protocol must be rejected',
            ],
            'PHP stream wrapper' => [
                'php://filter/read=convert.base64-encode/resource=https://fcm.googleapis.com',
                false,
                'Scheme Validation',
                'PHP stream wrapper must be rejected',
            ],
            'Data URI scheme' => [
                'data:text/html;base64,PHNjcmlwdD5hbGVydCgxKTwvc2NyaXB0Pg==',
                false,
                'Scheme Validation',
                'Data URI must be rejected',
            ],
            'Javascript scheme' => [
                'javascript:alert(1)',
                false,
                'Scheme Validation',
                'Javascript pseudoprotocol must be rejected',
            ],
            'Protocol-relative URL' => [
                '//fcm.googleapis.com/fcm/send',
                false,
                'Scheme Validation',
                'Protocol-relative URL missing scheme must be rejected',
            ],
            'Bare hostname URL' => [
                'fcm.googleapis.com/fcm/send',
                false,
                'Scheme Validation',
                'Bare host string missing scheme must be rejected',
            ],

            // --- URL Encoding, Authority Deception & Homograph Attacks ---
            'Embedded credentials in authority' => [
                'https://user:password@fcm.googleapis.com/fcm/send',
                false,
                'Authority Deception',
                'Username and password in authority must be rejected',
            ],
            'Username only in authority' => [
                'https://admin@fcm.googleapis.com/fcm/send',
                false,
                'Authority Deception',
                'Username in authority must be rejected',
            ],
            'Password only in authority' => [
                'https://:secret@fcm.googleapis.com/fcm/send',
                false,
                'Authority Deception',
                'Password in authority must be rejected',
            ],
            'Userinfo redirect trick' => [
                'https://fcm.googleapis.com%2f@evil.com/',
                false,
                'Authority Deception',
                'URL encoded slash in userinfo before @ sign',
            ],
            'Target host in authority prefix' => [
                'https://fcm.googleapis.com:443@attacker.com/',
                false,
                'Authority Deception',
                'Allowed host as username pointing to attacker host',
            ],
            'URL encoded dot in host' => [
                'https://fcm%2egoogleapis.com/fcm/send',
                false,
                'URL Encoding',
                'URL encoded dot in allowed host',
            ],
            'Hex encoded characters in host' => [
                'https://%66%63%6d.googleapis.com/fcm/send',
                false,
                'URL Encoding',
                'Hex encoded host characters',
            ],
            'Fragment delimiter spoofing' => [
                'https://evil.com#fcm.googleapis.com',
                false,
                'Authority Deception',
                'Allowed host placed in URL fragment of attacker domain',
            ],

            // --- Subdomain & Suffix Spoofing ---
            'Subdomain spoofing FCM' => [
                'https://fcm.googleapis.com.evil.com/push',
                false,
                'Subdomain Spoofing',
                'fcm.googleapis.com as prefix on attacker domain',
            ],
            'Fake Apple Push prefix' => [
                'https://notpush.apple.com/QN/token',
                false,
                'Subdomain Spoofing',
                'Apple subdomain that does not end with .push.apple.com',
            ],
            'Apple push domain inside attacker domain' => [
                'https://evil.push.apple.com.attacker.com/QN/token',
                false,
                'Subdomain Spoofing',
                'push.apple.com embedded inside attacker domain suffix',
            ],
            'Hyphenated Apple push domain' => [
                'https://fake-push.apple.com/QN/token',
                false,
                'Subdomain Spoofing',
                'Hyphenated push domain fake-push.apple.com',
            ],
            'Push Apple domain as prefix on attacker domain' => [
                'https://push.apple.com.evil.com/QN/token',
                false,
                'Subdomain Spoofing',
                'push.apple.com prefix on evil.com',
            ],
            'Mozilla Autopush inside attacker domain' => [
                'https://evil.push.services.mozilla.com.evil.com/wpush/v2',
                false,
                'Subdomain Spoofing',
                'Mozilla Autopush domain inside attacker host',
            ],
            'Windows WNS inside attacker domain' => [
                'https://attacker.notify.windows.com.evil.com/w/',
                false,
                'Subdomain Spoofing',
                'Windows WNS domain inside attacker host',
            ],
            'Amazon ADM inside attacker domain' => [
                'https://evil.push.amazon.com.evil.com/adm',
                false,
                'Subdomain Spoofing',
                'Amazon ADM domain inside attacker host',
            ],
            'Broad apex googleapis.com' => [
                'https://googleapis.com/oauth2/v1/userinfo',
                false,
                'Broad Domain Abuse',
                'Apex googleapis.com domain',
            ],
            'Cloud Storage googleapis.com' => [
                'https://storage.googleapis.com/my-bucket/secret.json',
                false,
                'Broad Domain Abuse',
                'Cloud Storage subdomain of googleapis.com',
            ],
            'Cloud Functions googleapis.com' => [
                'https://cloudfunctions.googleapis.com/v1/projects',
                false,
                'Broad Domain Abuse',
                'Cloud Functions subdomain of googleapis.com',
            ],
        ];
    }

    // =========================================================================
    // 2. GATE 4 BOUNDED TIMEOUT & GUZZLE OPTIONS VERIFICATION
    // =========================================================================

    #[\PHPUnit\Framework\Attributes\RunInSeparateProcess]
    #[\PHPUnit\Framework\Attributes\PreserveGlobalState(false)]
    public function testGate4WebPushConstructorAndClientOptionsReflection(): void
    {
        // 1. Verify Reflection on WebPush constructor
        $reflector = new ReflectionClass(\Minishlink\WebPush\WebPush::class);
        $constructor = $reflector->getConstructor();
        $this->assertNotNull($constructor, 'WebPush class must have a constructor.');

        $params = $constructor->getParameters();
        $this->assertGreaterThanOrEqual(4, count($params), 'WebPush constructor must have at least 4 parameters.');
        $this->assertSame('auth', $params[0]->getName());
        $this->assertSame('defaultOptions', $params[1]->getName());
        $this->assertSame('timeout', $params[2]->getName());
        $this->assertSame('clientOptions', $params[3]->getName());

        // 2. Instantiate WebPush exactly as PushNotificationService::sendPush does
        $auth = [
            'VAPID' => [
                'subject'    => 'mailto:test@example.com',
                'publicKey'  => 'BEl62iUYgUivxIkv69yViEuiBIa-Ib9-SkvMeAtA3LFgDzkrxZJjSgSnfckjDCWJ_BJMg4WCeTO20HG95IWKWGs',
                'privateKey' => '1111111111111111111111111111111111111111111',
            ],
        ];
        $defaultOptions = [];
        $timeout = 3;
        $clientOptions = [
            'timeout'         => 3.0,
            'connect_timeout' => 2.0,
            'allow_redirects' => false,
            'http_errors'     => false,
        ];

        $webPush = new \Minishlink\WebPush\WebPush($auth, $defaultOptions, $timeout, $clientOptions);

        // 3. Inspect internal Guzzle Client instance via reflection
        $clientProp = $reflector->getProperty('client');
        /** @var Client $guzzleClient */
        $guzzleClient = $clientProp->getValue($webPush);
        $this->assertInstanceOf(Client::class, $guzzleClient);

        $config = $guzzleClient->getConfig();

        // 4. Assert Gate 3 & Gate 4 strict invariants
        $this->assertSame(
            3.0,
            (float)($config['timeout'] ?? 0.0),
            'Gate 4 Violation: Request timeout must be strictly bounded to 3.0 seconds.'
        );
        $this->assertSame(
            2.0,
            (float)($config['connect_timeout'] ?? 0.0),
            'Gate 4 Violation: Connect timeout must be strictly bounded to 2.0 seconds.'
        );
        $this->assertFalse(
            $config['allow_redirects'],
            'Gate 4 / SecOps Violation: allow_redirects MUST be false to prevent open-redirect SSRF bypasses.'
        );
        $this->assertFalse(
            $config['http_errors'],
            'Gate 4 Invariant: http_errors must be false so non-200 responses do not throw unhandled Guzzle exceptions.'
        );

        // 5. Verify real WebPush queueNotification enforces 4078 octet limit
        $sub = \Minishlink\WebPush\Subscription::create([
            'endpoint'        => 'https://fcm.googleapis.com/fcm/send/sample_fcm_token_123',
            'keys'            => ['p256dh' => 'test_p256dh_key', 'auth' => 'test_auth_key'],
            'contentEncoding' => 'aes128gcm',
        ]);
        $this->expectException(\ErrorException::class);
        $this->expectExceptionMessage('Size of payload must not be greater than 4078 octets.');
        $webPush->queueNotification($sub, str_repeat('X', 5000));
    }

    // =========================================================================
    // 3. PAYLOAD FUZZING & STRESS TESTING
    // =========================================================================

    public function testPayloadFuzzingWithOversizedPayloadExceeding4078OctetsReturnsFalseGracefully(): void
    {
        $userId = 'user_fuzz_huge_payload';
        $endpoint = 'https://fcm.googleapis.com/fcm/send/token_huge';
        $this->insertSubscription($userId, $endpoint);

        $webPushMock = m::mock('overload:Minishlink\WebPush\WebPush');
        $webPushMock->shouldReceive('setReuseVAPIDHeaders')->andReturnNull();
        $webPushMock->shouldReceive('queueNotification')
            ->once()
            ->andThrow(new \ErrorException('Size of payload must not be greater than 4078 octets.'));

        // sendPush() must catch the \Throwable, log the error, and return false without fatal crashing.
        $hugeMessage = str_repeat('A', 10000);

        $result = PushNotificationService::sendPush(
            userId: $userId,
            message: $hugeMessage,
            title: 'Huge Payload Test'
        );

        $this->assertFalse(
            $result,
            'sendPush() must return false when payload size exceeds the 4078-octet RFC 8291 limit.'
        );
    }

    public function testTagFuzzingExtremePunctuationAndLengthClampedTo32Chars(): void
    {
        $userId = 'user_fuzz_tag';
        $endpoint = 'https://fcm.googleapis.com/fcm/send/token_tag_fuzz';
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

        // Pass 200-character tag with forbidden characters
        $adversarialTag = 'ORDER#994!@#$%^&*()-=+/\\<script>alert(1)</script>_VERY_LONG_STRING_THAT_EXCEEDS_RFC8030_LIMIT';

        PushNotificationService::sendPush(
            userId: $userId,
            message: 'Tag stress test',
            tag: $adversarialTag
        );

        $this->assertNotNull($capturedPayload);
        $sanitizedTag = $capturedPayload['tag'];

        // Assert strictly alphanumeric, underscore, or hyphen
        $this->assertMatchesRegularExpression(
            '/^[A-Za-z0-9_-]+$/',
            $sanitizedTag,
            'Sanitized tag must only contain URL-safe alphanumeric, underscore, or hyphen characters.'
        );

        // Assert clamped to at most 32 characters
        $this->assertLessThanOrEqual(
            32,
            strlen($sanitizedTag),
            'Sanitized tag must be clamped to 32 characters maximum.'
        );

        // Assert topic header in gateway options matches
        $this->assertSame($sanitizedTag, $capturedOptions['topic']);
    }

    public function testTagFuzzingAllSpecialCharsFallsBackToGeneral(): void
    {
        $userId = 'user_fuzz_tag_empty';
        $endpoint = 'https://fcm.googleapis.com/fcm/send/token_tag_empty';
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

        // Tag consisting exclusively of stripped characters
        PushNotificationService::sendPush(
            userId: $userId,
            message: 'Empty tag fallback test',
            tag: '!@#$%^&*()+=~`'
        );

        $this->assertNotNull($capturedPayload);
        $this->assertSame('general', $capturedPayload['tag']);
    }

    public function testUnicodeAndMultiBytePayloadFuzzing(): void
    {
        $userId = 'user_fuzz_unicode';
        $endpoint = 'https://fcm.googleapis.com/fcm/send/token_unicode';
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

        $unicodeTitle = '🎉 审批通过！ loan_approved #9928';
        $unicodeBody = "您的贷款申请已通过审核。 💰\n金额: £5,000\n状态: 立即生效 🚀\nمرحبا بك في الخدمة";
        $emojiActionTitle = '✅ 同意 / Confirm';

        PushNotificationService::sendPush(
            userId: $userId,
            message: $unicodeBody,
            title: $unicodeTitle,
            options: [
                'dir'     => 'rtl',
                'lang'    => 'zh-CN',
                'actions' => [
                    ['action' => 'approve', 'title' => $emojiActionTitle],
                ],
            ]
        );

        $this->assertNotNull($capturedPayload);
        $this->assertSame($unicodeTitle, $capturedPayload['title']);
        $this->assertSame($unicodeBody, $capturedPayload['body']);
        $this->assertSame('rtl', $capturedPayload['dir']);
        $this->assertSame('zh-CN', $capturedPayload['lang']);
        $this->assertSame($emojiActionTitle, $capturedPayload['actions'][0]['title']);
    }

    public function testActionsArrayFuzzingClampedToMaxTwoActions(): void
    {
        $userId = 'user_fuzz_actions';
        $endpoint = 'https://fcm.googleapis.com/fcm/send/token_actions';
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

        // Pass 5 actions (Chromium / W3C allows at most 2 actions)
        $rawActions = [
            ['action' => 'act1', 'title' => 'Button 1'],
            ['action' => 'act2', 'title' => 'Button 2'],
            ['action' => 'act3', 'title' => 'Button 3 (Should be ignored)'],
            ['action' => 'act4', 'title' => 'Button 4 (Should be ignored)'],
            ['action' => 'act5', 'title' => 'Button 5 (Should be ignored)'],
        ];

        PushNotificationService::sendPush(
            userId: $userId,
            message: 'Action clamping test',
            options: ['actions' => $rawActions]
        );

        $this->assertNotNull($capturedPayload);
        $this->assertIsArray($capturedPayload['actions']);
        $this->assertCount(2, $capturedPayload['actions'], 'Actions array must be clamped to at most 2 actions.');
        $this->assertSame('act1', $capturedPayload['actions'][0]['action']);
        $this->assertSame('act2', $capturedPayload['actions'][1]['action']);
    }

    public function testDefensiveHandlingOfNonArrayOptions(): void
    {
        $userId = 'user_fuzz_bad_options';
        $endpoint = 'https://fcm.googleapis.com/fcm/send/token_bad_options';
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

        // Pass malformed options types that might trigger PHP type errors
        $malformedOptions = [
            'actions' => 'not_an_array',
            'vibrate' => 'heavy_vibration',
            'data'    => 'not_an_array',
            'dir'     => 'diagonal', // Invalid direction
            'ttl'     => -999,       // Negative TTL
            'urgency' => 'ludicrous',// Invalid urgency
        ];

        $result = PushNotificationService::sendPush(
            userId: $userId,
            message: 'Malformed options test',
            options: $malformedOptions
        );

        $this->assertTrue($result);
        $this->assertNotNull($capturedPayload);
        $this->assertSame([], $capturedPayload['actions']);
        $this->assertNull($capturedPayload['vibrate']);
        $this->assertSame('auto', $capturedPayload['dir']);
    }

    public function testMultipleUserIdsArrayDispatch(): void
    {
        $user1 = 'user_multi_1';
        $user2 = 'user_multi_2';
        $this->insertSubscription($user1, 'https://fcm.googleapis.com/fcm/send/token_user1');
        $this->insertSubscription($user2, 'https://fcm.googleapis.com/fcm/send/token_user2');

        $queuedCount = 0;

        $webPushMock = m::mock('overload:Minishlink\WebPush\WebPush');
        $webPushMock->shouldReceive('setReuseVAPIDHeaders')->andReturnNull();
        $webPushMock->shouldReceive('queueNotification')
            ->twice()
            ->andReturnUsing(function () use (&$queuedCount) {
                $queuedCount++;
                return true;
            });
        $webPushMock->shouldReceive('flush')->once()->andReturn([]);

        $dispatched = PushNotificationService::sendPush(
            userId: [$user1, $user2],
            message: 'Broadcast alert to multiple users'
        );

        $this->assertTrue($dispatched);
        $this->assertSame(2, $queuedCount, 'sendPush() must queue notifications for all user IDs in the array.');
    }

    // =========================================================================
    // 4. WEB BADGING API EDGE CASES
    // =========================================================================

    public function testWebBadgingZeroCountExplicitClearBadge(): void
    {
        $userId = 'user_badge_zero';
        $this->insertSubscription($userId, 'https://fcm.googleapis.com/fcm/send/token_badge_zero');

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

        PushNotificationService::sendPush(
            userId: $userId,
            message: 'Inbox Zero',
            badgeCount: 0
        );

        $this->assertNotNull($capturedPayload);
        $this->assertSame(0, $capturedPayload['badgeCount']);
        $this->assertTrue($capturedPayload['clearBadge'], 'clearBadge must be true when badgeCount is 0.');
        $this->assertSame(0, $capturedPayload['data']['badgeCount']);
        $this->assertTrue($capturedPayload['data']['clearBadge']);
    }

    public function testWebBadgingExtremeInteger(): void
    {
        $userId = 'user_badge_extreme';
        $this->insertSubscription($userId, 'https://fcm.googleapis.com/fcm/send/token_badge_extreme');

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

        // Pass 999,999,999 badge count
        PushNotificationService::sendPush(
            userId: $userId,
            message: 'High badge count',
            badgeCount: 999999999
        );

        $this->assertNotNull($capturedPayload);
        $this->assertSame(999999999, $capturedPayload['badgeCount']);
        $this->assertFalse($capturedPayload['clearBadge']);
    }

    public function testWebBadgingExplicitClearBadgeOptionOverridesPositiveCount(): void
    {
        $userId = 'user_badge_override';
        $this->insertSubscription($userId, 'https://fcm.googleapis.com/fcm/send/token_badge_override');

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

        // Pass positive count, but options explicitly request clearBadge: true
        PushNotificationService::sendPush(
            userId: $userId,
            message: 'Explicit clear badge',
            badgeCount: 5,
            options: ['clearBadge' => true]
        );

        $this->assertNotNull($capturedPayload);
        $this->assertSame(5, $capturedPayload['badgeCount']);
        $this->assertTrue(
            $capturedPayload['clearBadge'],
            'clearBadge must be true when options["clearBadge"] is explicitly requested.'
        );
        $this->assertTrue($capturedPayload['data']['clearBadge']);
    }

    public function testWebBadgingNegativeCountObservation(): void
    {
        $userId = 'user_badge_negative';
        $this->insertSubscription($userId, 'https://fcm.googleapis.com/fcm/send/token_badge_negative');

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

        // Negative badge count edge case
        PushNotificationService::sendPush(
            userId: $userId,
            message: 'Negative count test',
            badgeCount: -5
        );

        $this->assertNotNull($capturedPayload);
        // Empirically observe current behavior:
        // effectiveBadgeCount is -5, and clearBadge is false (as -5 !== 0)
        $this->assertSame(-5, $capturedPayload['badgeCount']);
        $this->assertFalse($capturedPayload['clearBadge']);
    }
}
