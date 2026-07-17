<?php

declare(strict_types=1);

namespace Src;

/**
 * Immutable External Audit Logger
 *
 * Designed to comply with ISO 27001 (A.12.4). Pushes logs to a secure, remote, 
 * append-only store (e.g. AWS CloudWatch). If the connection fails or times out
 * (hard 1.5s timeout as mandated by Gatewatcher structural safety), it degrades 
 * gracefully by writing to a local fallback queue without breaking the main thread.
 */
class AuditLogger
{
    private static string $fallbackLogPath = __DIR__ . '/../../../bootstrap/log/fallback_audit.log';

    /**
     * Log an event immutably.
     *
     * @param string $eventType e.g., 'login_success', 'key_rotation'
     * @param array $payload The event data to log
     */
    public static function log(string $eventType, array $payload): void
    {
        $payload['event_type'] = $eventType;
        $payload['timestamp'] = gmdate('Y-m-d\TH:i:s\Z');
        $payload['app_env'] = $_ENV['APP_ENV'] ?? 'unknown';

        $jsonData = json_encode($payload, JSON_UNESCAPED_SLASHES);

        // Define the remote immutable log endpoint (e.g., AWS API Gateway / CloudWatch)
        // If not set in .env, default to a simulated endpoint
        $remoteEndpoint = $_ENV['AUDIT_LOG_ENDPOINT'] ?? 'https://audit.mycompany.internal/log';
        $apiKey = $_ENV['AUDIT_LOG_API_KEY'] ?? '';

        try {
            $ch = curl_init($remoteEndpoint);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            
            // ⚡ Gatewatcher Mandate: Strict 1.5-second timeout (1500ms)
            curl_setopt($ch, CURLOPT_TIMEOUT_MS, 1500);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            // curl_close is deprecated and unnecessary in PHP 8.0+

            // If it failed or timed out, gracefully degrade to local fallback
            if ($response === false || $httpCode >= 400) {
                self::fallbackToLocalQueue($jsonData, "HTTP $httpCode - $error");
            }
        } catch (\Throwable $e) {
            // Failsafe: Never crash the main execution thread due to a logging failure
            self::fallbackToLocalQueue($jsonData, $e->getMessage());
        }
    }

    /**
     * Writes the failed log push to a local append-only queue for later processing.
     */
    private static function fallbackToLocalQueue(string $jsonData, string $reason): void
    {
        $logEntry = json_encode([
            'failed_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'reason' => $reason,
            'payload' => json_decode($jsonData, true)
        ]) . PHP_EOL;

        // Ensure directory exists
        $dir = dirname(self::$fallbackLogPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        // Append to local fallback log
        @file_put_contents(self::$fallbackLogPath, $logEntry, FILE_APPEND | LOCK_EX);
    }
}
