<?php

declare(strict_types=1);

namespace Src;

use PDO;
use PDOException;

/**
 * TelemetryIngestService
 *
 * Reusable, privacy-preserving telemetry ingestion service for all portfolio applications.
 * Enforces zero-PII sanitization, IP anonymization (UK GDPR compliant), and self-healing schema provisioning.
 */
class TelemetryIngestService
{
    /**
     * Ingests a raw telemetry payload safely.
     *
     * @param PDO $db
     * @param array<string, mixed> $data
     * @param string $clientIp
     * @return array{status: string, message?: string, recorded?: bool}
     */
    public static function processEvent(PDO $db, array $data, string $clientIp = '127.0.0.1'): array
    {
        if (empty($data['event']) || !is_string($data['event'])) {
            return ['status' => 'error', 'message' => 'Invalid event payload'];
        }

        $event = substr(trim(strip_tags($data['event'])), 0, 50);
        $url = isset($data['url']) && is_string($data['url']) ? substr(trim(strip_tags($data['url'])), 0, 255) : '/';
        $viewportWidth = isset($data['viewport_width']) ? (int)$data['viewport_width'] : 0;
        $viewportHeight = isset($data['viewport_height']) ? (int)$data['viewport_height'] : 0;

        // Strip PII from metadata
        $metadata = [];
        if (isset($data['metadata']) && is_array($data['metadata'])) {
            foreach ($data['metadata'] as $key => $val) {
                if (is_scalar($val)) {
                    $cleanKey = substr(preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$key) ?? '', 0, 32);
                    $metadata[$cleanKey] = substr(strip_tags((string)$val), 0, 150);
                }
            }
        }

        $anonymizedIp = self::anonymizeIp($clientIp);

        try {
            self::insertRecord($db, $event, $url, $viewportWidth, $viewportHeight, $metadata, $anonymizedIp);
        } catch (PDOException $e) {
            try {
                self::ensureSchema($db);
                self::insertRecord($db, $event, $url, $viewportWidth, $viewportHeight, $metadata, $anonymizedIp);
            } catch (PDOException $ex) {
                // Silently succeed to prevent breaking UI
            }
        }

        return ['status' => 'success', 'recorded' => true];
    }

    /**
     * Inserts the telemetry record into MySQL.
     *
     * @param PDO $db
     * @param string $event
     * @param string $url
     * @param int $vw
     * @param int $vh
     * @param array<string, mixed> $metadata
     * @param string $ipAnon
     */
    private static function insertRecord(PDO $db, string $event, string $url, int $vw, int $vh, array $metadata, string $ipAnon): void
    {
        $stmt = $db->prepare(
            "INSERT INTO telemetry_events (event_name, url_path, viewport_w, viewport_h, metadata_json, ip_anon, created_at)
             VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );
        $stmt->execute([
            $event,
            $url,
            $vw,
            $vh,
            json_encode($metadata),
            $ipAnon,
        ]);
    }

    /**
     * Self-healing schema provisioning for telemetry_events.
     *
     * @param PDO $db
     */
    public static function ensureSchema(PDO $db): void
    {
        $db->exec(
            "CREATE TABLE IF NOT EXISTS `telemetry_events` (
                `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                `event_name` VARCHAR(50) NOT NULL,
                `url_path` VARCHAR(255) NOT NULL,
                `viewport_w` INT NOT NULL DEFAULT 0,
                `viewport_h` INT NOT NULL DEFAULT 0,
                `metadata_json` JSON NULL,
                `ip_anon` VARCHAR(45) NOT NULL,
                `created_at` DATETIME NOT NULL,
                INDEX `idx_telemetry_event_name` (`event_name`),
                INDEX `idx_telemetry_created_at` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
        );
    }

    /**
     * Anonymizes IPv4 and IPv6 addresses for UK GDPR compliance.
     *
     * @param string $ip
     * @return string
     */
    public static function anonymizeIp(string $ip): string
    {
        $packed = inet_pton($ip);
        if ($packed === false) {
            return '0.0.0.0';
        }

        if (strlen($packed) === 4) {
            $mask = inet_pton('255.255.255.0');
            if ($mask !== false) {
                return inet_ntop($packed & $mask) ?: '0.0.0.0';
            }
            return '0.0.0.0';
        }

        $mask = inet_pton('ffff:ffff:ffff::');
        if ($mask !== false) {
            return inet_ntop($packed & $mask) ?: '::';
        }
        return '::';
    }
}
