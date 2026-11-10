<?php

declare(strict_types=1);

namespace Src\Media;

use Throwable;

/**
 * PythonSidecarBridge
 *
 * Non-blocking UNIX Domain Socket / TCP IPC client connecting PHP to the persistent
 * ATG Python Enterprise Media & Threat Daemon (pyvips + YARA + PyMuPDF).
 *
 * Enforces strict circuit-breaker timeouts (50ms connect, 1500ms operation) and zero
 * shell execution (`exec` / `proc_open` strictly prohibited). If the Python sidecar
 * is offline, callers transparently fall back to the pure-PHP sovereign engines.
 */
class PythonSidecarBridge
{
    /** Default local UNIX socket path. */
    public const DEFAULT_SOCKET_PATH = '/tmp/atg-sidecar.sock';

    /** Connect timeout in seconds (50ms). */
    private const CONNECT_TIMEOUT_SEC = 0.05;

    /** Read/Write operation timeout in seconds (1.5s). */
    public const OPERATION_TIMEOUT_SEC = 1.5;

    /**
     * Dispatch a command to the warm Python sidecar daemon.
     *
     * @param string $action 'scan' or 'optimise'
     * @param array<string, mixed> $payload Task parameters
     * @return array<string, mixed>|null Decoded response or null if sidecar is offline
     */
    public static function dispatch(string $action, array $payload): ?array
    {
        $socketPath = (string) ($_ENV['ATG_PYTHON_SIDECAR_SOCKET'] ?? getenv('ATG_PYTHON_SIDECAR_SOCKET') ?: self::DEFAULT_SOCKET_PATH);
        $host = (string) ($_ENV['ATG_PYTHON_SIDECAR_HOST'] ?? getenv('ATG_PYTHON_SIDECAR_HOST') ?: '');
        $port = (int) ($_ENV['ATG_PYTHON_SIDECAR_PORT'] ?? getenv('ATG_PYTHON_SIDECAR_PORT') ?: 8484);

        $fp = null;

        try {
            if (file_exists($socketPath) && is_readable($socketPath)) {
                $fp = @stream_socket_client(
                    'unix://' . $socketPath,
                    $errno,
                    $errstr,
                    self::CONNECT_TIMEOUT_SEC
                );
            } elseif ($host !== '') {
                $fp = @stream_socket_client(
                    "tcp://{$host}:{$port}",
                    $errno,
                    $errstr,
                    self::CONNECT_TIMEOUT_SEC
                );
            }

            if (!is_resource($fp)) {
                return null;
            }

            stream_set_timeout($fp, 1, 500_000); // 1.5 seconds

            $request = json_encode([
                'action'    => $action,
                'payload'   => $payload,
                'timestamp' => microtime(true),
            ], JSON_THROW_ON_ERROR) . "\n";

            fwrite($fp, $request);

            $rawResponse = fgets($fp, 65536);
            fclose($fp);

            if (!is_string($rawResponse) || trim($rawResponse) === '') {
                return null;
            }

            $decoded = json_decode(trim($rawResponse), true, 16, JSON_THROW_ON_ERROR);
            if (!is_array($decoded) || !isset($decoded['status'])) {
                return null;
            }

            return $decoded;
        } catch (Throwable) {
            if (is_resource($fp)) {
                @fclose($fp);
            }
            return null;
        }
    }

    /**
     * Checks whether the Python sidecar daemon is currently warm and reachable.
     */
    public static function isAvailable(): bool
    {
        $res = self::dispatch('ping', []);
        return is_array($res) && ($res['status'] ?? '') === 'ok';
    }
}
