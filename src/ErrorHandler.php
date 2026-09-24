<?php

declare(strict_types=1);

namespace Src;

use Monolog\Level;
use PDOException;

/**
 * Registers PHP's global exception and error handlers, routing everything
 * through LoggerFactory (Monolog: file + email) and returning a clean
 * JSON or HTML error response — never a raw stack trace.
 *
 * Bootstrap in every app's entry point (index.php / bootstrap.php):
 *
 *   \Src\ErrorHandler::register();
 *
 * That single line replaces the need for try/catch in every controller
 * for truly fatal/uncaught errors. Controllers should still catch
 * PDOException locally when they need partial-failure resilience (e.g.
 * dashboard widgets failing independently without taking the page down).
 */
final class ErrorHandler
{
    private static bool $registered = false;

    /**
     * Register the global exception + error handlers.
     * Safe to call multiple times — only registers once.
     */
    public static function register(): void
    {
        if (self::$registered) {
            return;
        }

        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);

        self::$registered = true;
    }

    /**
     * Handles any uncaught \Throwable (including PDOException propagated from
     * Select::selectFn2() and related query methods after the v2.0.0 fix).
     */
    public static function handleException(\Throwable $th): void
    {
        $statusCode = self::resolveStatusCode($th);

        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
        }

        // Log via Monolog (file + optional email alert for critical levels).
        try {
            $level = $statusCode >= 500 ? Level::Critical : Level::Error;
            LoggerFactory::getLogger()->log($level, '🚨 Uncaught Exception', [
                'class'   => get_class($th),
                'message' => $th->getMessage(),
                'code'    => $statusCode,
                'file'    => $th->getFile(),
                'line'    => $th->getLine(),
                'trace'   => $th->getTraceAsString(),
            ]);
        } catch (\Throwable $loggingFailure) {
            // Never let a logger failure replace the real error response.
            error_log('ErrorHandler: logger itself failed: ' . $loggingFailure->getMessage());
        }

        $isLocal = Utility::isLocalEnv();

        $message = match (true) {
            $th instanceof \Src\Exceptions\HttpException  => $th->getMessage(),
            $isLocal => "Error on line {$th->getLine()} in {$th->getFile()}: {$th->getMessage()}",
            default  => 'An unexpected error occurred. Our team has been notified.',
        };

        echo (string) json_encode(
            ['status' => 'error', 'message' => $message, 'code' => $statusCode],
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }

    /**
     * Converts PHP native errors (E_WARNING, E_NOTICE, etc.) into ErrorException
     * so they are caught by handleException above.
     *
     * @throws \ErrorException always — converts the error to an exception.
     */
    public static function handleError(
        int $errno,
        string $errstr,
        string $errfile = '',
        int $errline = 0
    ): bool {
        if (!(error_reporting() & $errno)) {
            return false; // error suppressed with @
        }

        throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    /**
     * Catches fatal errors (E_ERROR, E_PARSE) that set_error_handler cannot catch.
     */
    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            self::handleException(new \ErrorException(
                $error['message'],
                0,
                $error['type'],
                $error['file'],
                $error['line']
            ));
        }
    }

    // -----------------------------------------------------------------------

    private static function resolveStatusCode(\Throwable $th): int
    {
        if ($th instanceof \Src\Exceptions\HttpException) {
            return $th->getStatusCode();
        }

        $code = (int) $th->getCode();

        return ($code >= 100 && $code <= 599) ? $code : 500;
    }
}
