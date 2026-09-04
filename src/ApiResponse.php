<?php

declare(strict_types=1);

namespace Src;

/**
 * Standardized API Response Contract
 *
 * Provides an immutable, uniform envelope across all 7 portfolio applications:
 * Success: { ok: true, status: 'success', data: mixed, message: string|null, error: null }
 * Error:   { ok: false, status: 'error', data: mixed, error: string, errors: array, message: string, code: string|null }
 */
class ApiResponse
{
    /**
     * Send a standardized JSON success response and terminate execution.
     *
     * @param mixed $data Payload to return
     * @param string|null $message Optional success message
     * @param int $statusCode HTTP status code (default: 200)
     * @param array<string, string> $headers Optional HTTP headers
     */
    public static function success(
        mixed $data = null,
        ?string $message = null,
        int $statusCode = 200,
        array $headers = []
    ): void {
        self::send(self::toSuccessEnvelope($data, $message), $statusCode, $headers);
    }

    /**
     * Send a standardized JSON error response and terminate execution.
     *
     * @param string|array<string, mixed> $error Error message or validation map
     * @param int $statusCode HTTP status code (default: 400)
     * @param string|null $code Machine-readable application error code
     * @param mixed $data Optional context data
     * @param array<string, string> $headers Optional HTTP headers
     */
    public static function error(
        string|array $error,
        int $statusCode = 400,
        ?string $code = null,
        mixed $data = null,
        array $headers = []
    ): void {
        self::send(self::toErrorEnvelope($error, $code, $data), $statusCode, $headers);
    }

    /**
     * Build the standardized success envelope array.
     *
     * @param mixed $data
     * @param string|null $message
     * @return array{ok: bool, status: string, data: mixed, message: ?string, error: null}
     */
    public static function toSuccessEnvelope(mixed $data = null, ?string $message = null): array
    {
        return [
            'ok' => true,
            'status' => 'success',
            'data' => $data,
            'message' => $message,
            'error' => null,
        ];
    }

    /**
     * Build the standardized error envelope array.
     *
     * Guarantees that `error` and `message` are strictly formatted strings (never array),
     * preventing callers from rendering [object Object].
     *
     * @param string|array<string, mixed> $error
     * @param string|null $code
     * @param mixed $data
     * @return array{ok: bool, status: string, data: mixed, error: string, errors: array<string, mixed>, message: string, code: ?string}
     */
    public static function toErrorEnvelope(
        string|array $error,
        ?string $code = null,
        mixed $data = null
    ): array {
        if (is_array($error)) {
            $errorsMap = $error;
            // Extract the first human-readable message from the validation array
            $firstVal = reset($error);
            if (is_array($firstVal)) {
                $errorString = (string) (reset($firstVal) ?: 'Validation error');
            } elseif (is_string($firstVal) && trim($firstVal) !== '') {
                $errorString = trim($firstVal);
            } else {
                $errorString = 'Validation error occurred';
            }
        } else {
            $errorString = trim($error) !== '' ? trim($error) : 'An error occurred';
            $errorsMap = [];
        }

        return [
            'ok' => false,
            'status' => 'error',
            'data' => $data,
            'error' => $errorString,
            'errors' => $errorsMap,
            'message' => $errorString,
            'code' => $code,
        ];
    }

    /**
     * Send HTTP status code, headers, JSON body, and exit.
     *
     * @param array<string, mixed> $payload
     * @param int $statusCode
     * @param array<string, string> $headers
     */
    private static function send(array $payload, int $statusCode, array $headers = []): void
    {
        if (!headers_sent()) {
            http_response_code($statusCode);
            header('Content-Type: application/json; charset=utf-8');
            header('X-Content-Type-Options: nosniff');
            foreach ($headers as $key => $value) {
                header("{$key}: {$value}");
            }
        }

        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
