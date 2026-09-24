<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Src\Select;

/**
 * Regression guard for Select::selectFn2() and related query methods.
 *
 * KEY CONTRACT: these methods must NEVER swallow a PDOException silently.
 * They must propagate exceptions so that controller-level try/catch blocks
 * can handle partial failures gracefully without taking the whole page down.
 */
class SelectFn2Test extends TestCase
{
    // -----------------------------------------------------------------------
    // Happy path
    // -----------------------------------------------------------------------

    public function testSelectFn2ReturnsRowsOnSuccess(): void
    {
        $rows = [['id' => 1, 'name' => 'Alice'], ['id' => 2, 'name' => 'Bob']];

        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn($rows);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        \Src\Db::setMockConnection($pdo);

        $result = Select::selectFn2('SELECT * FROM users');

        $this->assertSame($rows, $result);
        $this->assertCount(2, $result);

        \Src\Db::clearMockConnection();
    }

    public function testSelectFn2ReturnsEmptyArrayWhenNoRows(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchAll')->willReturn([]);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        \Src\Db::setMockConnection($pdo);

        $result = Select::selectFn2('SELECT * FROM users WHERE id = ?', [999]);

        $this->assertSame([], $result);

        \Src\Db::clearMockConnection();
    }

    // -----------------------------------------------------------------------
    // Sad path — the critical regression guard
    // -----------------------------------------------------------------------

    /**
     * @see https://github.com/modernman00/shared-lib — bug: selectFn2 used to
     *      catch PDOException internally, echo JSON to the output buffer, and
     *      return [] — making every caller's try/catch dead code and corrupting
     *      HTML page renders.
     */
    public function testSelectFn2PropagatesPdoException(): void
    {
        $this->expectException(PDOException::class);
        $this->expectExceptionMessage('Table does not exist');

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException(
            new PDOException('Table does not exist')
        );

        \Src\Db::setMockConnection($pdo);

        try {
            Select::selectFn2('SELECT * FROM nonexistent_table');
        } finally {
            \Src\Db::clearMockConnection();
        }
    }

    /**
     * Verifies that NO bytes are echoed to the output buffer when a PDOException
     * is thrown. Previously Utility::showError() was called inside the catch block,
     * injecting a JSON error string mid-HTML-response.
     */
    public function testSelectFn2DoesNotEchoOnException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException(
            new PDOException('SQLSTATE[42S02]: Base table or view not found')
        );

        \Src\Db::setMockConnection($pdo);

        ob_start();
        try {
            Select::selectFn2('SELECT * FROM nonexistent_table');
        } catch (PDOException) {
            // expected — we are only checking that nothing was echoed
        } finally {
            \Src\Db::clearMockConnection();
        }
        $output = ob_get_clean();

        $this->assertSame(
            '',
            $output,
            'selectFn2() must not echo any output when a PDOException occurs. ' .
            'Utility::showError() must not be called inside the library method.'
        );
    }

    // -----------------------------------------------------------------------
    // selectCountFn2 — same contract
    // -----------------------------------------------------------------------

    public function testSelectCountFn2PropagatesPdoException(): void
    {
        $this->expectException(PDOException::class);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException(
            new PDOException('Connection refused')
        );

        \Src\Db::setMockConnection($pdo);

        try {
            Select::selectCountFn2('SELECT COUNT(*) FROM nonexistent_table');
        } finally {
            \Src\Db::clearMockConnection();
        }
    }

    public function testSelectCountFn2DoesNotEchoOnException(): void
    {
        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willThrowException(
            new PDOException('Connection refused')
        );

        \Src\Db::setMockConnection($pdo);

        ob_start();
        try {
            Select::selectCountFn2('SELECT COUNT(*) FROM nonexistent_table');
        } catch (PDOException) {
            // expected
        } finally {
            \Src\Db::clearMockConnection();
        }
        $output = ob_get_clean();

        $this->assertSame('', $output, 'selectCountFn2() must not echo any output on PDOException.');
    }

    // -----------------------------------------------------------------------
    // selectCountFn2 happy path
    // -----------------------------------------------------------------------

    public function testSelectCountFn2ReturnsRowCount(): void
    {
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('rowCount')->willReturn(42);

        $pdo = $this->createMock(PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        \Src\Db::setMockConnection($pdo);

        $result = Select::selectCountFn2('SELECT * FROM users WHERE active = ?', [1]);

        $this->assertSame(42, $result);

        \Src\Db::clearMockConnection();
    }
}
