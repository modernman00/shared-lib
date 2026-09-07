<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Src\SecureSession;

class SecureSessionTest extends TestCase
{
    protected function setUp(): void
    {
        $_SESSION = [];
        unset($_ENV['SESSION_LIFETIME'], $_ENV['COOKIE_EXPIRE'], $_ENV['SESSION_STRICT_IP']);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($_ENV['SESSION_LIFETIME'], $_ENV['COOKIE_EXPIRE'], $_ENV['SESSION_STRICT_IP']);
    }

    public function testGetLifetimeDefaultReturns30Days(): void
    {
        $lifetime = SecureSession::getLifetime();
        $this->assertSame(2592000, $lifetime);
    }

    public function testGetLifetimeFromEnvironment(): void
    {
        $_ENV['SESSION_LIFETIME'] = '86400';
        $this->assertSame(86400, SecureSession::getLifetime());

        unset($_ENV['SESSION_LIFETIME']);
        $_ENV['COOKIE_EXPIRE'] = '604800';
        $this->assertSame(604800, SecureSession::getLifetime());
    }

    public function testValidateAllowsMobileIpChangeWhenStrictIpDisabled(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0)';
        $_SERVER['REMOTE_ADDR'] = '198.51.100.2'; // New IP (e.g. cellular hop)

        $_SESSION['UA'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0)';
        $_SESSION['IP'] = '203.0.113.1'; // Initial IP (e.g. home Wi-Fi)
        $_SESSION['CREATED'] = time() - 3600; // Created 1 hour ago
        $_SESSION['LAST_ACTIVITY'] = time() - 60; // Active 1 minute ago

        $this->assertTrue(SecureSession::validate());
    }

    public function testValidateKillsSessionWhenStrictIpEnabledAndIpDiffers(): void
    {
        $_ENV['SESSION_STRICT_IP'] = '1';

        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0)';
        $_SERVER['REMOTE_ADDR'] = '198.51.100.2';

        $_SESSION['UA'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0)';
        $_SESSION['IP'] = '203.0.113.1';
        $_SESSION['CREATED'] = time() - 3600;
        $_SESSION['LAST_ACTIVITY'] = time() - 60;

        $this->assertFalse(SecureSession::validate());
    }

    public function testValidateRejectsUserAgentMismatch(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)';
        $_SERVER['REMOTE_ADDR'] = '203.0.113.1';

        $_SESSION['UA'] = 'Mozilla/5.0 (iPhone; CPU iPhone OS 18_0)';
        $_SESSION['IP'] = '203.0.113.1';
        $_SESSION['CREATED'] = time();
        $_SESSION['LAST_ACTIVITY'] = time();

        $this->assertFalse(SecureSession::validate());
    }

    public function testValidateInactivityTimeoutUsesSlidingWindow(): void
    {
        $_SERVER['HTTP_USER_AGENT'] = 'TestAgent/1.0';
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';

        $_SESSION['UA'] = 'TestAgent/1.0';
        $_SESSION['IP'] = '127.0.0.1';
        $_SESSION['CREATED'] = time() - 7200; // Session was created 2 hours ago
        $_SESSION['LAST_ACTIVITY'] = time() - 100; // Last request was 100 seconds ago

        // With default 30-day lifetime, it should be valid even though created > 1 hour ago
        $this->assertTrue(SecureSession::validate());

        // Now test with short 60-second lifetime
        $_ENV['SESSION_LIFETIME'] = '60';
        $_SESSION['LAST_ACTIVITY'] = time() - 120; // 2 minutes inactive
        $this->assertFalse(SecureSession::validate());
    }
}
