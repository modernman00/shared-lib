<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Src\Admin\AdminPresenceService;

class AdminPresenceServiceTest extends TestCase
{
    private array $originalServer = [];
    private array $originalCookie = [];
    private mixed $originalSession = null;

    protected function setUp(): void
    {
        $this->originalServer = $_SERVER;
        $this->originalCookie = $_COOKIE;
        $this->originalSession = $_SESSION ?? null;

        $_SERVER['TESTING_CLI_PULSE'] = '1';
        $_SERVER['REQUEST_URI'] = '/dashboard';
        $_COOKIE['_umac_presence_id'] = str_repeat('a', 32);
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        $_SERVER = $this->originalServer;
        $_COOKIE = $this->originalCookie;
        if ($this->originalSession === null) {
            unset($_SESSION);
        } else {
            $_SESSION = $this->originalSession;
        }
    }

    public function testFactoryNotInvokedForStaticAssets(): void
    {
        $staticPaths = [
            '/assets/app.css',
            '/js/bundle.js?v=123',
            '/images/hero.png',
            '/images/anim.gif',
            '/robots.txt',
            '/sitemap.xml',
            '/favicon.ico',
        ];

        foreach ($staticPaths as $uri) {
            $_SERVER['REQUEST_URI'] = $uri;
            $invocations = 0;

            AdminPresenceService::recordPulse(function () use (&$invocations): \PDO {
                $invocations++;
                return $this->createMockPdo();
            }, 'testapp');

            $this->assertSame(0, $invocations, "Factory must not be invoked for static URI: {$uri}");
        }
    }

    public function testFactoryNotInvokedForHealthcheckAndBotPaths(): void
    {
        foreach (['/health', '/healthz', '/ping', '/wp-login.php'] as $uri) {
            $_SERVER['REQUEST_URI'] = $uri;
            $invocations = 0;

            AdminPresenceService::recordPulse(function () use (&$invocations): \PDO {
                $invocations++;
                return $this->createMockPdo();
            }, 'testapp');

            $this->assertSame(0, $invocations, "Factory must not be invoked for non-page URI: {$uri}");
        }
    }

    public function testFactoryNotInvokedForStatelessHitWithoutSessionOrCookie(): void
    {
        unset($_COOKIE['_umac_presence_id']);
        $invocations = 0;

        AdminPresenceService::recordPulse(function () use (&$invocations): \PDO {
            $invocations++;
            return $this->createMockPdo();
        }, 'testapp');

        $this->assertSame(0, $invocations);
    }

    public function testFactoryNotInvokedWhenThrottledOnSameUrl(): void
    {
        $_SESSION['_last_presence_pulse'] = time();
        $_SESSION['_last_presence_url'] = '/dashboard';
        $invocations = 0;

        AdminPresenceService::recordPulse(function () use (&$invocations): \PDO {
            $invocations++;
            return $this->createMockPdo();
        }, 'testapp');

        $this->assertSame(0, $invocations);
    }

    public function testFactoryInvokedOnceWhenPulseIsDueThenThrottled(): void
    {
        $invocations = 0;
        $pdo = $this->createMockPdo();

        $factory = static function () use (&$invocations, $pdo): \PDO {
            $invocations++;
            return $pdo;
        };

        // First hit: pulse is due -> factory invoked once
        AdminPresenceService::recordPulse($factory, 'testapp');
        $this->assertSame(1, $invocations);

        // Second immediate hit on same URL: throttled -> factory NOT invoked again
        AdminPresenceService::recordPulse($factory, 'testapp');
        $this->assertSame(1, $invocations);

        // Navigation to a new URL: pulse is due -> factory invoked again
        $_SERVER['REQUEST_URI'] = '/settings';
        AdminPresenceService::recordPulseLazy($factory, 'testapp');
        $this->assertSame(2, $invocations);
    }

    public function testDirectPdoRemainsBackwardsCompatible(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->expects($this->once())->method('execute')->willReturn(true);

        $pdo = $this->createMock(\PDO::class);
        $pdo->expects($this->once())->method('prepare')->willReturn($stmt);

        AdminPresenceService::recordPulse($pdo, 'legacy_app');
    }

    public function testFactoryExceptionFailsSafe(): void
    {
        AdminPresenceService::recordPulse(static function (): \PDO {
            throw new \PDOException('SQLSTATE[08004] [1040] Too many connections');
        }, 'testapp');

        $this->assertTrue(true);
    }

    private function createMockPdo(): \PDO
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        return $pdo;
    }
}
