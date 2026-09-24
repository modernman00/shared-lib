<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Src\ErrorHandler;

/**
 * Tests for ErrorHandler — the global PHP exception/error handler
 * that wraps LoggerFactory and ensures uncaught exceptions (including
 * PDOException propagated from Select::selectFn2() post v2.0.0) never
 * produce raw stack traces in the browser.
 */
class ErrorHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        // LoggerFactory is aliased by Mockery in bootstrap.php and handles getLogger().
        // We only need to ensure the env vars ErrorHandler uses for Utility::isLocalEnv() are set.
        $_ENV['APP_ENV'] = 'production'; // suppress debug output in handleException responses
    }

    protected function tearDown(): void
    {
        unset($_ENV['APP_ENV']);
    }

    // -----------------------------------------------------------------------
    // handleException — output & response code
    // -----------------------------------------------------------------------

    public function testHandleExceptionOutputsJsonWithoutStackTrace(): void
    {
        ob_start();
        ErrorHandler::handleException(new \PDOException('SQLSTATE[42S02]: Table not found'));
        $output = ob_get_clean();

        $this->assertNotEmpty($output, 'ErrorHandler must echo a response body.');
        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded, 'Response must be valid JSON.');
        $this->assertArrayHasKey('status', $decoded);
        $this->assertSame('error', $decoded['status']);
        $this->assertArrayHasKey('message', $decoded);

        // Must NOT contain raw PHP file paths or stack trace in production mode.
        $this->assertStringNotContainsString('ErrorHandler.php', $decoded['message']);
        $this->assertStringNotContainsString('#0 ', $decoded['message']);
    }

    public function testHandleExceptionOutputsJsonForGenericThrowable(): void
    {
        ob_start();
        ErrorHandler::handleException(new \RuntimeException('Something broke', 500));
        $output = ob_get_clean();

        $decoded = json_decode($output, true);
        $this->assertIsArray($decoded);
        $this->assertSame('error', $decoded['status']);
        $this->assertSame(500, $decoded['code']);
    }

    // -----------------------------------------------------------------------
    // handleError — converts PHP native errors to ErrorException
    // -----------------------------------------------------------------------

    public function testHandleErrorThrowsErrorException(): void
    {
        $previousLevel = error_reporting(E_ALL);
        try {
            $this->expectException(\ErrorException::class);
            $this->expectExceptionMessage('Division by zero');

            ErrorHandler::handleError(E_WARNING, 'Division by zero', __FILE__, __LINE__);
        } finally {
            error_reporting($previousLevel);
        }
    }

    public function testHandleErrorReturnsFalseWhenErrorSuppressed(): void
    {
        // Simulate error_reporting() == 0 (error suppressed with @).
        $previousLevel = error_reporting(0);
        try {
            $result = ErrorHandler::handleError(E_WARNING, 'Suppressed error', __FILE__, __LINE__);
            $this->assertFalse($result, 'Suppressed errors must return false, not throw.');
        } finally {
            error_reporting($previousLevel);
        }
    }

    // -----------------------------------------------------------------------
    // register() — idempotent
    // -----------------------------------------------------------------------

    public function testRegisterIsIdempotent(): void
    {
        // Should not throw even when called multiple times.
        ErrorHandler::register();
        ErrorHandler::register();
        ErrorHandler::register();
        $this->assertTrue(true); // reached without error
    }
}
