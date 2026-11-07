<?php

declare(strict_types=1);

namespace Tests\Src\functionality;

use InvalidArgumentException;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Src\Db;
use Src\Exceptions\NotFoundException;
use Src\Exceptions\TooManyRequestsException;
use Src\functionality\LoginFunctionality;

/**
 * Unit tests for LoginFunctionality::login() guard paths.
 *
 * Uses the real Src\Db with a mocked PDO (via Db::setMockConnection) rather than
 * re-declaring Src\Db / Src\Select / Src\Update in this file. The previous version
 * of this test declared stub copies of those production classes, which replaced the
 * real ones for the entire PHPUnit run and broke ~200 unrelated tests.
 */
final class LoginFunctionalityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_POST = [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        Db::clearMockConnection();
        $_POST = [];
        $_SESSION = [];
        unset($_SERVER['CONTENT_TYPE']);
        parent::tearDown();
    }

    /** Mock PDO whose audit_logs failure count (used by the IP-ban check) is $failedAttempts. */
    private function useAuditLogCount(int $failedAttempts): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn($failedAttempts);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        Db::setMockConnection($pdo);
    }

    public function testThrowsWhenThereIsNoPostData(): void
    {
        $this->useAuditLogCount(0);

        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('There was no post data');

        LoginFunctionality::login(false, false, 'array');
    }

    public function testThrowsWhenNeitherEmailNorUsernameIsSupplied(): void
    {
        $this->useAuditLogCount(0);
        $_POST = ['password' => 'secret'];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Email or username is required');

        LoginFunctionality::login(false, false, 'array');
    }

    public function testBlocksAnIpWithTooManyFailedAttempts(): void
    {
        $this->useAuditLogCount(5);
        $_POST = ['email' => 'user@example.com', 'password' => 'secret'];

        $this->expectException(TooManyRequestsException::class);

        LoginFunctionality::login(false, false, 'array');
    }
}
