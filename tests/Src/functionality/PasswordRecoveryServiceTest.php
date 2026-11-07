<?php

declare(strict_types=1);

namespace Tests\Unit\Functionality;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Src\Db;
use Src\functionality\PasswordRecoveryService;

/**
 * PasswordRecoveryService::process() (formerly processRecovery()).
 * process() never throws: every failure is reported to the client via Utility::showError(),
 * so these tests assert on the JSON the endpoint emits. Runs in its own process because it
 * defines TESTING_ENV (which turns the rate limiter and CAPTCHA into no-ops).
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class PasswordRecoveryServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!defined('TESTING_ENV')) {
            define('TESTING_ENV', true);
        }
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_POST = [];
        $_SESSION = [];
        $_ENV['DB_TABLE_LOGIN'] = 'users';
    }

    protected function tearDown(): void
    {
        Db::clearMockConnection();
        parent::tearDown();
    }

    private function noUsers(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        foreach (['execute', 'bindValue', 'bindParam'] as $m) {
            $stmt->method($m)->willReturn(true);
        }
        $stmt->method('fetch')->willReturn(false);
        $stmt->method('fetchAll')->willReturn([]);
        $stmt->method('fetchColumn')->willReturn(0);
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        $pdo->method('lastInsertId')->willReturn('1');
        Db::setMockConnection($pdo);
    }

    private function run_(): string
    {
        ob_start();
        PasswordRecoveryService::process(false);
        return (string) ob_get_clean();
    }

    public function testRejectsAnEmptyRequest(): void
    {
        $this->assertStringContainsString('There was no post data', $this->run_());
    }

    public function testMissingEmailIsABadRequestNotAServerError(): void
    {
        $_POST = ['name' => 'someone'];
        $out = $this->run_();
        $this->assertStringContainsString('Please enter your email address', $out);
        $this->assertSame(400, http_response_code());
    }

    public function testUnknownEmailIsRejected(): void
    {
        $this->noUsers();
        $_POST = ['email' => 'nobody@example.com'];
        $this->assertStringContainsString('We do not recognise your account', $this->run_());
    }

    public function testShowAndProcessArePublicStatics(): void
    {
        foreach (['show', 'process'] as $m) {
            $r = new \ReflectionMethod(PasswordRecoveryService::class, $m);
            $this->assertTrue($r->isPublic() && $r->isStatic(), $m);
        }
    }
}
