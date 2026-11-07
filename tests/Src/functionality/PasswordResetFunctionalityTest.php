<?php

declare(strict_types=1);

use Firebase\JWT\JWT;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Src\Db;
use Src\functionality\PasswordResetFunctionality;

/**
 * PasswordResetFunctionality::process() (replaces the removed processRequest()).
 * The user is identified by the signed `auth_forgot` JWT cookie issued during recovery;
 * the new password is hashed and written with a parameterised UPDATE, and existing
 * sessions are revoked by bumping token_version.
 *
 * Separate process: defines TESTING_ENV (rate limiter no-op) and calls session/cookie APIs.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class PasswordResetFunctionalityTest extends TestCase
{
    private const KEY = 'test-signing-key-for-password-reset-0123456789';
    /** @var list<array{sql: string, params: array}> */
    private array $queries = [];

    protected function setUp(): void
    {
        if (!defined('TESTING_ENV')) {
            define('TESTING_ENV', true);
        }
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_ENV['JWT_KEY'] = self::KEY;
        $_ENV['DB_TABLE_LOGIN'] = 'users';
        unset($_ENV['COOKIE_NAME_GENERAL'], $_ENV['PATH_TO_PASSWORD_CHANGE_NOTIFICATION']);
        $_POST = [];
        $_COOKIE = [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        Db::clearMockConnection();
    }

    /** Records every prepared statement and the parameters it was executed/bound with. */
    private function recordingDb(): void
    {
        $test = $this;
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($test) {
            $i = count($test->queries);
            $test->queries[$i] = ['sql' => $sql, 'params' => []];
            $stmt = $test->createMock(PDOStatement::class);
            $stmt->method('bindParam')->willReturnCallback(function ($k, &$v) use ($test, $i) { $test->queries[$i]['params'][$k] = $v; return true; });
            $stmt->method('execute')->willReturnCallback(function ($p = null) use ($test, $i) { if ($p) { $test->queries[$i]['params'] += $p; } return true; });
            // JwtHandler::fetchUser() confirms the reset token's user still exists (token_version 1).
            $stmt->method('fetch')->willReturn(['email' => 'user@example.com', 'token_version' => 1]);
            return $stmt;
        });
        Db::setMockConnection($pdo);
    }

    private function resetCookieFor(string $email, string $id = 'U42'): void
    {
        $_COOKIE['auth_forgot'] = JWT::encode(['iat' => time(), 'exp' => time() + 600, 'data' => ['email' => $email, 'id' => $id]], self::KEY, 'HS256');
    }

    private function process(): array
    {
        ob_start();
        $ok = @PasswordResetFunctionality::process(); // @: session_destroy() without an active session in CLI
        return [$ok, (string) ob_get_clean()];
    }

    public function testRejectsAnEmptyRequest(): void
    {
        [$ok, $out] = $this->process();
        $this->assertFalse($ok);
        $this->assertStringContainsString('There was no post data', $out);
    }

    public function testRejectsMismatchedPasswords(): void
    {
        $this->resetCookieFor('user@example.com');
        $_POST = ['password' => 'NewPassword123', 'confirm_password' => 'Different123'];
        [$ok, $out] = $this->process();
        $this->assertFalse($ok);
        $this->assertStringContainsStringIgnoringCase('do not match', $out);
    }

    public function testRequiresTheSignedResetCookie(): void
    {
        $_POST = ['password' => 'NewPassword123', 'confirm_password' => 'NewPassword123'];
        [$ok, $out] = $this->process();
        $this->assertFalse($ok);
        $this->assertStringContainsString('Missing authentication cookie', $out);
    }

    public function testRejectsAResetCookieSignedWithAnotherKey(): void
    {
        $_COOKIE['auth_forgot'] = JWT::encode(['exp' => time() + 600, 'data' => ['email' => 'victim@example.com']], 'attacker-key-0123456789abcdef0123456789', 'HS256');
        $_POST = ['password' => 'NewPassword123', 'confirm_password' => 'NewPassword123'];
        $this->recordingDb();
        [$ok] = $this->process();
        $this->assertFalse($ok);
        $this->assertSame([], $this->queries, 'No database write may happen for a forged token');
    }

    public function testChangesThePasswordAndRevokesSessions(): void
    {
        $this->resetCookieFor('user@example.com', 'U42');
        $_POST = ['password' => 'NewPassword123', 'confirm_password' => 'NewPassword123'];
        $this->recordingDb();

        [$ok, $out] = $this->process();

        $this->assertTrue($ok, $out);
        $this->assertStringContainsString('Password was successfully changed', $out);

        $writes = array_values(array_filter($this->queries, static fn ($q) => str_starts_with($q['sql'], 'UPDATE')));
        $this->assertCount(2, $writes, 'Exactly two writes: new password, then session revocation');
        $update = $writes[0];
        $this->assertSame('UPDATE users SET password = :columnValue WHERE email = :identifierValue', $update['sql']);
        $this->assertSame('user@example.com', $update['params'][':identifierValue']);
        $this->assertTrue(password_verify('NewPassword123', $update['params'][':columnValue']), 'Password must be stored hashed');

        $this->assertStringContainsString('SET token_version = token_version + 1 WHERE id = ?', $writes[1]['sql']);
        $this->assertSame(['U42'], $writes[1]['params']);
    }
}
