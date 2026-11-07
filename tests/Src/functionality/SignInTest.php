<?php

declare(strict_types=1);

namespace Tests\Unit\Functionality;

use Firebase\JWT\JWT;
use PDO;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Src\Db;
use Src\Exceptions\UnauthorisedException;
use Src\functionality\SignIn;

/**
 * SignIn::verify() / isLoggedIn() exercised through the real (final) RoleMiddleware,
 * with real HS256 JWTs and a mocked PDO for the user lookup. The previous version tried
 * to Mockery-mock the final RoleMiddleware class, which is impossible.
 *
 * Contract: verify() THROWS UnauthorisedException when access is denied;
 * isLoggedIn() is the non-throwing wrapper that returns false.
 */
final class SignInTest extends TestCase
{
    private const KEY = 'test-signing-key-for-signin-tests-0123456789';
    private array $env;
    private array $cookie;
    private array $session;

    protected function setUp(): void
    {
        parent::setUp();
        $this->env = $_ENV;
        $this->cookie = $_COOKIE;
        $this->session = $_SESSION ?? [];
        $_ENV['JWT_KEY'] = self::KEY;
        unset($_ENV['JWT_KEY_PREVIOUS']);
        $_ENV['COOKIE_TOKEN_LOGIN'] = 'auth_token';
        $_ENV['DB_TABLE_LOGIN'] = 'users';
        $_COOKIE = [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        Db::clearMockConnection();
        $_ENV = $this->env;
        $_COOKIE = $this->cookie;
        $_SESSION = $this->session;
        parent::tearDown();
    }

    private function issue(array $data, string $key = self::KEY): void
    {
        $_COOKIE['auth_token'] = JWT::encode(['iat' => time(), 'exp' => time() + 600, 'data' => $data], $key, 'HS256');
    }

    /** User lookup returns $row (or false for "not found"). */
    private function userRow(array|false $row): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetch')->willReturn($row);
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);
        Db::setMockConnection($pdo);
    }

    public function testVerifyWithValidRoleReturnsUserData(): void
    {
        $this->issue(['id' => 'U123', 'email' => 'test@example.com', 'role' => 'users', 'token_version' => 1]);
        $this->userRow(['email' => 'test@example.com', 'token_version' => 1]);

        $result = SignIn::verify('users');

        $this->assertSame(['id' => 'U123', 'email' => 'test@example.com', 'role' => 'users'], $result);
        $this->assertSame('U123', $_SESSION['id']);
    }

    public function testVerifyThrowsWhenRoleIsNotAllowed(): void
    {
        $this->issue(['id' => 'U123', 'email' => 'test@example.com', 'role' => 'users']);
        $this->userRow(['email' => 'test@example.com', 'token_version' => 1]);

        $this->expectException(UnauthorisedException::class);
        $this->expectExceptionMessage('Access denied for role: users');
        SignIn::verify('admin');
    }

    public function testVerifyThrowsWithoutCookie(): void
    {
        $this->expectException(UnauthorisedException::class);
        SignIn::verify('users');
    }

    public function testVerifyRejectsATokenSignedWithAnotherKey(): void
    {
        $this->issue(['id' => 'U123', 'role' => 'users'], 'attacker-controlled-key-0123456789abcdef');

        $this->expectException(UnauthorisedException::class);
        $this->expectExceptionMessage('Invalid JWT signature');
        SignIn::verify('users');
    }

    public function testVerifyThrowsWhenUserNoLongerExists(): void
    {
        $this->issue(['id' => 'U123', 'role' => 'users']);
        $this->userRow(false);

        $this->expectException(UnauthorisedException::class);
        SignIn::verify('users');
    }

    public function testIsLoggedInReturnsFalseInsteadOfThrowing(): void
    {
        $this->assertFalse(SignIn::isLoggedIn('users'));
    }
}
