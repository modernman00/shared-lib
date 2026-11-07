<?php

declare(strict_types=1);

namespace Tests\Src\functionality;

use PDO;
use PDOStatement;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Src\Db;
use Src\functionality\SubmitPostData;

/**
 * SubmitPostData::submitToOneTablenImage() / submitToMultipleTable() against the real
 * sanitiser, BotGuard and SubmitForm, with a recording PDO in place of MySQL.
 *
 * Replaces two obsolete files that targeted removed methods (submit(), submitImgDataSingle(),
 * submitImgDataMultiple()) through process-wide Mockery alias mocks of core classes.
 * Separate process: defines TESTING_ENV (rate limiter + CAPTCHA become no-ops).
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class SubmitPostDataTest extends TestCase
{
    /** @var list<array{sql: string, params: array<string, mixed>}> */
    public array $queries = [];
    public array $tx = ['begin' => 0, 'commit' => 0, 'rollback' => 0, 'active' => false];

    protected function setUp(): void
    {
        if (!defined('TESTING_ENV')) {
            define('TESTING_ENV', true);
        }
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['CONTENT_TYPE'] = 'application/x-www-form-urlencoded';
        $_POST = [];
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        Db::clearMockConnection();
    }

    private function db(bool $failInsert = false): void
    {
        $t = $this;
        $pdo = $this->createMock(PDO::class);
        $pdo->method('inTransaction')->willReturnCallback(fn () => $t->tx['active']);
        $pdo->method('beginTransaction')->willReturnCallback(function () use ($t) { $t->tx['begin']++; $t->tx['active'] = true; return true; });
        $pdo->method('commit')->willReturnCallback(function () use ($t) { $t->tx['commit']++; $t->tx['active'] = false; return true; });
        $pdo->method('rollBack')->willReturnCallback(function () use ($t) { $t->tx['rollback']++; $t->tx['active'] = false; return true; });
        $pdo->method('lastInsertId')->willReturn('42');
        $pdo->method('prepare')->willReturnCallback(function (string $sql) use ($t, $failInsert) {
            $i = count($t->queries);
            $t->queries[$i] = ['sql' => $sql, 'params' => []];
            $stmt = $t->createMock(PDOStatement::class);
            $stmt->method('bindValue')->willReturnCallback(function ($k, $v) use ($t, $i) { $t->queries[$i]['params'][$k] = $v; return true; });
            $stmt->method('execute')->willReturnCallback(function () use ($failInsert) {
                if ($failInsert) {
                    throw new \PDOException('SQLSTATE[23000]: Integrity constraint violation');
                }
                return true;
            });
            return $stmt;
        });
        Db::setMockConnection($pdo);
    }

    /** @return array{0: mixed, 1: string} */
    private function call(callable $fn): array
    {
        ob_start();
        $r = $fn();
        return [$r, (string) ob_get_clean()];
    }

    private function inserts(): array
    {
        return array_values(array_filter($this->queries, static fn ($q) => str_starts_with($q['sql'], 'INSERT')));
    }

    public function testInsertsSanitisedDataAndReturnsTheNewId(): void
    {
        $this->db();
        $_POST = ['name' => '  Ada <b>Lovelace</b> ', 'email' => 'ada@example.com', 'submit' => 'Send', 'website_hp' => ''];

        [$id, $out] = $this->call(fn () => SubmitPostData::submitToOneTablenImage('contacts'));

        $this->assertSame('42', $id, $out);
        $insert = $this->inserts()[0];
        $this->assertSame('INSERT INTO contacts (name, email) VALUES (:name, :email)', $insert['sql'], 'control + honeypot keys stripped');
        $this->assertStringNotContainsString('<b>', $insert['params'][':name']);
        $this->assertSame('ada@example.com', $insert['params'][':email']);
        $this->assertSame(['begin' => 1, 'commit' => 1, 'rollback' => 0, 'active' => false], $this->tx);
    }

    public function testPasswordsAreHashedBeforeStorage(): void
    {
        $this->db();
        $_POST = ['email' => 'ada@example.com', 'password' => 'CorrectHorse9', 'confirm_password' => 'CorrectHorse9'];

        [$id, $out] = $this->call(fn () => SubmitPostData::submitToOneTablenImage('account', null, ['confirm_password', 'submit', 'token']));

        $this->assertSame('42', $id, $out);
        $params = $this->inserts()[0]['params'];
        $this->assertArrayNotHasKey(':confirm_password', $params);
        $this->assertNotSame('CorrectHorse9', $params[':password']);
        $this->assertTrue(password_verify('CorrectHorse9', $params[':password']));
    }

    public function testMismatchedPasswordConfirmationIsRejectedWithoutWriting(): void
    {
        $this->db();
        $_POST = ['email' => 'ada@example.com', 'password' => 'CorrectHorse9', 'confirm_password' => 'Different9'];

        [$r, $out] = $this->call(fn () => SubmitPostData::submitToOneTablenImage('account'));

        $this->assertFalse($r);
        // In test mode the global showError() helper captures the error instead of printing it.
        $this->assertStringContainsString('Passwords do not match', $GLOBALS['__testError']->getMessage(), $out);
        $this->assertSame([], $this->inserts());
    }

    public function testInvalidEmailIsRejectedWithoutWriting(): void
    {
        $this->db();
        $_POST = ['name' => 'Ada', 'email' => 'not-an-email'];

        [$r, $out] = $this->call(fn () => SubmitPostData::submitToOneTablenImage('contacts'));

        $this->assertFalse($r);
        // In test mode the global showError() helper captures the error instead of printing it.
        $this->assertStringContainsString('valid permanent email', $GLOBALS['__testError']->getMessage(), $out);
        $this->assertSame([], $this->inserts());
    }

    public function testDatabaseFailureRollsBack(): void
    {
        $this->db(failInsert: true);
        $_POST = ['name' => 'Ada', 'email' => 'ada@example.com'];

        [$r] = $this->call(fn () => SubmitPostData::submitToOneTablenImage('contacts'));

        $this->assertFalse($r);
        $this->assertSame(1, $this->tx['rollback']);
        $this->assertSame(0, $this->tx['commit']);
    }

    public function testMultipleTablesOnlyWritesAllowedTables(): void
    {
        $this->db();
        $post = [
            'profile' => ['first_name' => 'Ada', 'last_name' => 'Lovelace'],
            'address' => ['city' => 'London'],
            'admin_flags' => ['is_admin' => '1'], // not allow-listed: must be ignored
        ];

        [$r, $out] = $this->call(fn () => SubmitPostData::submitToMultipleTable(['profile', 'address'], null, null, null, null, null, $post, false, false, 'SUBMIT', 'images', 'array'));

        $this->assertSame(['message' => 'Records created successfully'], $r, $out);
        $tables = array_map(static fn ($q) => preg_replace('/^INSERT INTO (\w+).*/', '$1', $q['sql']), $this->inserts());
        $this->assertSame(['profile', 'address'], $tables);
    }
}
