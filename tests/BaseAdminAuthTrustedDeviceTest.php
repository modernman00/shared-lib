<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\Auth\BaseAdminAuthController;

/**
 * Concrete test stub for abstract BaseAdminAuthController.
 */
class TestAdminAuthController extends BaseAdminAuthController
{
    protected function getAppName(): string
    {
        return 'TestApp';
    }

    protected function getDashboardUrl(): string
    {
        return '/admin/dashboard';
    }

    protected function getClientIp(): string
    {
        return '127.0.0.1';
    }

    protected function generateFingerprint(string $ip, string $userAgent): string
    {
        return hash('sha256', $ip . $userAgent);
    }

    protected function getAdminTableName(): string
    {
        return 'account';
    }

    protected function getAdminColumnMap(): array
    {
        return [
            'id'                  => 'id',
            'email'               => 'email',
            'password'            => 'password',
            'firstName'           => 'firstName',
            'lastName'            => 'lastName',
            'type'                => 'type',
            'status'              => 'status',
            'totp_enabled'        => 'totp_enabled',
            'totp_secret'         => 'totp_secret',
            'totp_last_used_step' => 'totp_last_used_step',
        ];
    }
}

final class BaseAdminAuthTrustedDeviceTest extends TestCase
{
    private string $originalUa;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalUa = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)';
        $_ENV['APP_KEY'] = 'test-secret-salt-key-32bytes-len!';
        $_ENV['APP_ENV'] = 'testing';
        $_SERVER['HTTP_X_CYPRESS_TEST'] = '1';
        unset($_COOKIE[TestAdminAuthController::get2faTrustCookieName()]);
    }

    protected function tearDown(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = $this->originalUa;
        unset($_COOKIE[TestAdminAuthController::get2faTrustCookieName()]);
        parent::tearDown();
    }

    public function testIssueAndVerifyTrustedDeviceCookieSuccess(): void
    {
        $adminId = 'admin_42';
        $email = 'admin@example.com';

        // Issue token
        TestAdminAuthController::issue2faTrustCookie($adminId, $email);

        $cookieName = TestAdminAuthController::get2faTrustCookieName();
        $this->assertArrayHasKey($cookieName, $_COOKIE);
        $this->assertNotEmpty($_COOKIE[$cookieName]);

        // Verify valid token
        $isValid = TestAdminAuthController::checkTrustedDevice($adminId, $email);
        $this->assertTrue($isValid, 'Valid 7-day trusted device token must be verified.');
    }

    public function testVerifyFailsOnEmailMismatch(): void
    {
        $adminId = 'admin_42';
        $email = 'admin@example.com';

        TestAdminAuthController::issue2faTrustCookie($adminId, $email);

        $isValid = TestAdminAuthController::checkTrustedDevice($adminId, 'other@example.com');
        $this->assertFalse($isValid, 'Token must not validate for a different email.');
    }

    public function testVerifyFailsOnAdminIdMismatch(): void
    {
        $adminId = 'admin_42';
        $email = 'admin@example.com';

        TestAdminAuthController::issue2faTrustCookie($adminId, $email);

        $isValid = TestAdminAuthController::checkTrustedDevice('admin_99', $email);
        $this->assertFalse($isValid, 'Token must not validate for a different admin ID.');
    }

    public function testVerifyFailsOnTamperedSignature(): void
    {
        $adminId = 'admin_42';
        $email = 'admin@example.com';

        TestAdminAuthController::issue2faTrustCookie($adminId, $email);
        $cookieName = TestAdminAuthController::get2faTrustCookieName();

        // Tamper with signature
        $_COOKIE[$cookieName] = $_COOKIE[$cookieName] . 'corrupted';

        $isValid = TestAdminAuthController::checkTrustedDevice($adminId, $email);
        $this->assertFalse($isValid, 'Tampered token signature must fail verification.');
    }

    public function testVerifyFailsOnTamperedPayload(): void
    {
        $adminId = 'admin_42';
        $email = 'admin@example.com';

        TestAdminAuthController::issue2faTrustCookie($adminId, $email);
        $cookieName = TestAdminAuthController::get2faTrustCookieName();

        [$payload, $sig] = explode('.', (string) $_COOKIE[$cookieName], 2);
        // Modify payload to elevate adminId to '1'
        $fakePayload = rtrim(strtr(base64_encode(json_encode([
            'id' => '1',
            'email' => $email,
            'exp' => time() + 604800,
            'device_hash' => hash('sha256', (string) $_SERVER['HTTP_USER_AGENT']),
            'nonce' => 'fake',
        ])), '+/', '-_'), '=');

        $_COOKIE[$cookieName] = $fakePayload . '.' . $sig;

        $isValid = TestAdminAuthController::checkTrustedDevice('1', $email);
        $this->assertFalse($isValid, 'Tampered payload with invalid signature must be rejected.');
    }

    public function testVerifyFailsOnDeviceUserAgentMismatch(): void
    {
        $adminId = 'admin_42';
        $email = 'admin@example.com';

        // Issue token on Mac Chrome
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)';
        TestAdminAuthController::issue2faTrustCookie($adminId, $email);

        // Attacker attempts to replay token from Linux Firefox
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (X11; Linux x86_64; rv:109.0) Gecko/20100101 Firefox/119.0';
        $isValid = TestAdminAuthController::checkTrustedDevice($adminId, $email);
        $this->assertFalse($isValid, 'Token replayed from different User-Agent must be rejected.');
    }

    public function testClear2faTrustCookieRemovesToken(): void
    {
        $adminId = 'admin_42';
        $email = 'admin@example.com';

        TestAdminAuthController::issue2faTrustCookie($adminId, $email);
        $cookieName = TestAdminAuthController::get2faTrustCookieName();
        $this->assertArrayHasKey($cookieName, $_COOKIE);

        TestAdminAuthController::clear2faTrustCookie();
        $this->assertArrayNotHasKey($cookieName, $_COOKIE);
    }

    public function testAdminGuardAllowsNetworkRoamingWithValid2faTrustToken(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        \Src\Auth\AdminGuardMiddleware::$testingMode = true;
        $adminId = 'admin_42';
        $email = 'admin@example.com';

        $_SESSION['auth'] = [
            'id' => $adminId,
            'email' => $email,
            'type' => 'super_admin',
        ];

        // Bind initial session on IP 192.168.1.50
        \Src\Auth\AdminGuardMiddleware::bindSession('192.168.1.50', (string) $_SERVER['HTTP_USER_AGENT']);
        $oldFingerprint = $_SESSION['admin_fingerprint'] ?? '';

        // Admin switches network to 10.0.0.100 (different subnet) with valid 2fa trust token
        TestAdminAuthController::issue2faTrustCookie($adminId, $email);
        $_SERVER['REMOTE_ADDR'] = '10.0.0.100';

        // Enforce guard
        \Src\Auth\AdminGuardMiddleware::enforce();

        // Session must NOT be destroyed; it should be gracefully updated to the new network
        $this->assertNotEmpty($_SESSION['auth']);
        $this->assertNotEquals($oldFingerprint, $_SESSION['admin_fingerprint']);
    }
}
