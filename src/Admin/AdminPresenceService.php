<?php

declare(strict_types=1);

namespace Src\Admin;

use PDO;
use PDOException;

/**
 * Real-Time Presence & Visitor Intelligence Service
 * 
 * Tracks "Who is on the site and when" with zero performance penalty.
 * Throttles heartbeats to reduce DB write contention and auto-provisions schema.
 */
class AdminPresenceService
{
    private const PRESENCE_TABLE = 'admin_presence';
    private const THROTTLE_SECONDS = 15;

    /**
     * Auto-provision the presence table if not present.
     */
    public static function ensureSchema(PDO $db): void
    {
        $sql = "CREATE TABLE IF NOT EXISTS `" . self::PRESENCE_TABLE . "` (
            `session_id` VARCHAR(64) NOT NULL,
            `app_name` VARCHAR(64) NOT NULL DEFAULT 'app',
            `user_id` INT NULL,
            `user_email` VARCHAR(255) NULL,
            `user_role` VARCHAR(64) NULL,
            `ip_address` VARCHAR(45) NOT NULL,
            `user_agent` VARCHAR(255) NULL,
            `device` VARCHAR(64) NOT NULL DEFAULT 'Desktop',
            `current_url` VARCHAR(255) NOT NULL,
            `first_seen_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `last_seen_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`session_id`),
            INDEX `idx_last_seen` (`last_seen_at`),
            INDEX `idx_app_seen` (`app_name`, `last_seen_at`),
            INDEX `idx_user_seen` (`user_id`, `last_seen_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";

        try {
            $db->exec($sql);
        } catch (PDOException $e) {
            // Fail safe - ignore if already exists or concurrent creation
        }
    }

    /**
     * Record or update the active visitor's presence pulse.
     */
    public static function recordPulse(PDO $db, string $appName = 'app'): void
    {
        // Don't record CLI executions unless explicitly testing
        if ((php_sapi_name() === 'cli' && empty($_SERVER['TESTING_CLI_PULSE'])) || !isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        $uri = (string)($_SERVER['REQUEST_URI'] ?? '/');

        // Skip static asset queries
        $ext = strtolower(pathinfo(parse_url($uri, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
        if (in_array($ext, ['css', 'js', 'png', 'jpg', 'jpeg', 'svg', 'webp', 'ico', 'woff', 'woff2', 'map', 'json'], true)) {
            return;
        }

        // Establish or retrieve persistent visitor session id
        if (session_status() === PHP_SESSION_ACTIVE && session_id() !== '') {
            $sessionId = session_id();
        } else {
            $sessionId = (string)($_COOKIE['_umac_presence_id'] ?? '');
            if (empty($sessionId) || strlen($sessionId) < 16) {
                $sessionId = bin2hex(random_bytes(16));
                @setcookie('_umac_presence_id', $sessionId, [
                    'expires' => time() + 86400 * 30,
                    'path' => '/',
                    'httponly' => true,
                    'samesite' => 'Lax',
                    'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                ]);
            }
        }

        // Throttle check: only update DB if 15+ seconds passed or URL changed
        $now = time();
        $lastPulse = (int)($_SESSION['_last_presence_pulse'] ?? 0);
        $lastUrl = (string)($_SESSION['_last_presence_url'] ?? '');

        if (($now - $lastPulse < self::THROTTLE_SECONDS) && ($lastUrl === $uri)) {
            return;
        }

        $_SESSION['_last_presence_pulse'] = $now;
        $_SESSION['_last_presence_url'] = $uri;

        // Resolve authenticated user details
        $userId = null;
        $userEmail = null;
        $userRole = null;

        if (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
            $userId = (int)$_SESSION['user_id'];
        } elseif (isset($_SESSION['id']) && is_numeric($_SESSION['id'])) {
            $userId = (int)$_SESSION['id'];
        }

        if (isset($_SESSION['auth']['email']) && is_string($_SESSION['auth']['email'])) {
            $userEmail = trim($_SESSION['auth']['email']);
        } elseif (isset($_SESSION['email']) && is_string($_SESSION['email'])) {
            $userEmail = trim($_SESSION['email']);
        }

        if (isset($_SESSION['role']) && is_string($_SESSION['role'])) {
            $userRole = trim($_SESSION['role']);
        } elseif (isset($_SESSION['auth']['type']) && is_string($_SESSION['auth']['type'])) {
            $userRole = trim($_SESSION['auth']['type']);
        }

        // Resolve client IP and device
        $ip = self::resolveClientIp();
        $userAgent = substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'), 0, 255);
        $device = self::detectDeviceType($userAgent);
        $cleanUrl = substr($uri, 0, 255);

        $sql = "INSERT INTO `" . self::PRESENCE_TABLE . "`
            (`session_id`, `app_name`, `user_id`, `user_email`, `user_role`, `ip_address`, `user_agent`, `device`, `current_url`, `first_seen_at`, `last_seen_at`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ON DUPLICATE KEY UPDATE
                `app_name` = VALUES(`app_name`),
                `user_id` = COALESCE(VALUES(`user_id`), `user_id`),
                `user_email` = COALESCE(VALUES(`user_email`), `user_email`),
                `user_role` = COALESCE(VALUES(`user_role`), `user_role`),
                `ip_address` = VALUES(`ip_address`),
                `user_agent` = VALUES(`user_agent`),
                `device` = VALUES(`device`),
                `current_url` = VALUES(`current_url`),
                `last_seen_at` = NOW()";

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $sessionId,
                $appName,
                $userId,
                $userEmail,
                $userRole,
                $ip,
                $userAgent,
                $device,
                $cleanUrl
            ]);
        } catch (PDOException $e) {
            // Self-heal table schema if missing, then retry once
            try {
                self::ensureSchema($db);
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $sessionId,
                    $appName,
                    $userId,
                    $userEmail,
                    $userRole,
                    $ip,
                    $userAgent,
                    $device,
                    $cleanUrl
                ]);
            } catch (\Throwable) {
                // Non-blocking fail-silent to protect user journey
            }
        }
    }

    /**
     * Retrieve list of active visitors currently on the site.
     * 
     * @return array<int, array<string, mixed>>
     */
    public static function getActiveVisitors(PDO $db, int $windowMinutes = 15, int $limit = 50, ?string $appFilter = null): array
    {
        self::ensureSchema($db);

        $sql = "SELECT 
                    `session_id`,
                    `app_name`,
                    `user_id`,
                    `user_email`,
                    `user_role`,
                    `ip_address`,
                    `user_agent`,
                    `device`,
                    `current_url`,
                    `first_seen_at`,
                    `last_seen_at`,
                    TIMESTAMPDIFF(SECOND, `last_seen_at`, NOW()) as seconds_ago,
                    TIMESTAMPDIFF(MINUTE, `first_seen_at`, `last_seen_at`) as duration_minutes
                FROM `" . self::PRESENCE_TABLE . "`
                WHERE `last_seen_at` >= (NOW() - INTERVAL ? MINUTE)";

        $params = [$windowMinutes];

        if (!empty($appFilter) && $appFilter !== 'all') {
            $sql .= " AND `app_name` = ?";
            $params[] = $appFilter;
        }

        $sql .= " ORDER BY `last_seen_at` DESC LIMIT " . (int)$limit;

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(static function (array $row): array {
                $secs = (int)($row['seconds_ago'] ?? 0);
                $status = $secs <= 120 ? 'Active Now' : ($secs <= 600 ? 'Idle' : 'Away');
                $isAuth = !empty($row['user_email']) || !empty($row['user_id']);

                $displayName = $isAuth 
                    ? ($row['user_email'] ?: 'User #' . $row['user_id'])
                    : 'Guest #' . substr(md5((string)$row['session_id']), 0, 6);

                return [
                    'session_id' => $row['session_id'],
                    'app_name' => $row['app_name'],
                    'display_name' => $displayName,
                    'user_id' => $row['user_id'],
                    'user_email' => $row['user_email'],
                    'user_role' => $row['user_role'] ?: ($isAuth ? 'Member' : 'Anonymous Visitor'),
                    'is_authenticated' => $isAuth,
                    'device' => $row['device'] ?? 'Desktop',
                    'current_url' => $row['current_url'],
                    'ip_address' => $row['ip_address'],
                    'last_seen_at' => $row['last_seen_at'],
                    'first_seen_at' => $row['first_seen_at'],
                    'seconds_ago' => $secs,
                    'time_ago_human' => self::formatTimeAgo($secs),
                    'duration_minutes' => max(1, (int)($row['duration_minutes'] ?? 1)),
                    'status' => $status
                ];
            }, $rows ?: []);
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * Compute aggregated presence statistics for executive dashboard vitals.
     * 
     * @return array<string, mixed>
     */
    public static function getPresenceSummary(PDO $db, int $windowMinutes = 15, ?string $appFilter = null): array
    {
        self::ensureSchema($db);

        $sql = "SELECT 
                    COUNT(*) as total_active,
                    SUM(CASE WHEN `user_id` IS NOT NULL OR (`user_email` IS NOT NULL AND `user_email` != '') THEN 1 ELSE 0 END) as authenticated_users,
                    SUM(CASE WHEN `user_id` IS NULL AND (`user_email` IS NULL OR `user_email` = '') THEN 1 ELSE 0 END) as guest_visitors,
                    SUM(CASE WHEN `device` = 'Mobile' THEN 1 ELSE 0 END) as mobile_count,
                    SUM(CASE WHEN `device` = 'Desktop' THEN 1 ELSE 0 END) as desktop_count,
                    SUM(CASE WHEN `device` = 'Tablet' THEN 1 ELSE 0 END) as tablet_count
                FROM `" . self::PRESENCE_TABLE . "`
                WHERE `last_seen_at` >= (NOW() - INTERVAL ? MINUTE)";

        $params = [$windowMinutes];
        if (!empty($appFilter) && $appFilter !== 'all') {
            $sql .= " AND `app_name` = ?";
            $params[] = $appFilter;
        }

        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $stats = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            // Top active pages
            $pageSql = "SELECT `current_url`, COUNT(*) as visitor_count 
                        FROM `" . self::PRESENCE_TABLE . "`
                        WHERE `last_seen_at` >= (NOW() - INTERVAL ? MINUTE)";
            $pageParams = [$windowMinutes];
            if (!empty($appFilter) && $appFilter !== 'all') {
                $pageSql .= " AND `app_name` = ?";
                $pageParams[] = $appFilter;
            }
            $pageSql .= " GROUP BY `current_url` ORDER BY visitor_count DESC LIMIT 5";
            $pageStmt = $db->prepare($pageSql);
            $pageStmt->execute($pageParams);
            $topPages = $pageStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            return [
                'total_active_now' => (int)($stats['total_active'] ?? 0),
                'authenticated_count' => (int)($stats['authenticated_users'] ?? 0),
                'guest_count' => (int)($stats['guest_visitors'] ?? 0),
                'mobile_count' => (int)($stats['mobile_count'] ?? 0),
                'desktop_count' => (int)($stats['desktop_count'] ?? 0),
                'tablet_count' => (int)($stats['tablet_count'] ?? 0),
                'top_active_pages' => $topPages,
                'window_minutes' => $windowMinutes
            ];
        } catch (\Throwable) {
            return [
                'total_active_now' => 0,
                'authenticated_count' => 0,
                'guest_count' => 0,
                'mobile_count' => 0,
                'desktop_count' => 0,
                'tablet_count' => 0,
                'top_active_pages' => [],
                'window_minutes' => $windowMinutes
            ];
        }
    }

    /**
     * Purge presence records older than 24 hours to keep the table compact and sub-millisecond fast.
     */
    public static function pruneExpired(PDO $db): int
    {
        try {
            return (int)$db->exec("DELETE FROM `" . self::PRESENCE_TABLE . "` WHERE `last_seen_at` < (NOW() - INTERVAL 24 HOUR)");
        } catch (\Throwable) {
            return 0;
        }
    }

    private static function resolveClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $h) {
            if (!empty($_SERVER[$h])) {
                $raw = (string)$_SERVER[$h];
                $parts = explode(',', $raw);
                $ip = trim($parts[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '127.0.0.1';
    }

    private static function detectDeviceType(string $ua): string
    {
        $uaLower = strtolower($ua);
        if (str_contains($uaLower, 'tablet') || str_contains($uaLower, 'ipad')) {
            return 'Tablet';
        }
        if (str_contains($uaLower, 'mobile') || str_contains($uaLower, 'android') || str_contains($uaLower, 'iphone')) {
            return 'Mobile';
        }
        return 'Desktop';
    }

    private static function formatTimeAgo(int $seconds): string
    {
        if ($seconds < 10) {
            return 'Just now';
        }
        if ($seconds < 60) {
            return $seconds . 's ago';
        }
        $mins = (int)floor($seconds / 60);
        if ($mins < 60) {
            return $mins . 'm ago';
        }
        $hours = (int)floor($mins / 60);
        return $hours . 'h ago';
    }
}
