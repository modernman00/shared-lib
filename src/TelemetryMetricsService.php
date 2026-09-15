<?php

declare(strict_types=1);

namespace Src;

use PDO;
use PDOException;

/**
 * TelemetryMetricsService
 *
 * Aggregates UX friction metrics (rage clicks, dead clicks, funnel drops)
 * for the Admin Telemetry & RUM Friction Dashboard across portfolio applications.
 */
class TelemetryMetricsService
{
    /**
     * Fetches aggregated telemetry stats for executive/RUM dashboards.
     *
     * @param PDO $db
     * @return array{
     *     total_events: int,
     *     rage_clicks_count: int,
     *     dead_clicks_count: int,
     *     funnel_steps_count: int,
     *     top_rage_targets: array<int, array{target: string, count: int}>,
     *     top_dead_targets: array<int, array{target: string, count: int}>,
     *     recent_events: array<int, array<string, mixed>>
     * }
     */
    public static function getSummaryStats(PDO $db): array
    {
        try {
            return self::fetchMetrics($db);
        } catch (PDOException $e) {
            try {
                TelemetryIngestService::ensureSchema($db);
                return self::fetchMetrics($db);
            } catch (PDOException $ex) {
                return [
                    'total_events' => 0,
                    'rage_clicks_count' => 0,
                    'dead_clicks_count' => 0,
                    'funnel_steps_count' => 0,
                    'top_rage_targets' => [],
                    'top_dead_targets' => [],
                    'recent_events' => [],
                ];
            }
        }
    }

    /**
     * Internal SQL aggregator.
     *
     * @param PDO $db
     * @return array{
     *     total_events: int,
     *     rage_clicks_count: int,
     *     dead_clicks_count: int,
     *     funnel_steps_count: int,
     *     top_rage_targets: array<int, array{target: string, count: int}>,
     *     top_dead_targets: array<int, array{target: string, count: int}>,
     *     recent_events: array<int, array<string, mixed>>
     * }
     */
    private static function fetchMetrics(PDO $db): array
    {
        // 1. Total & Event Breakdown
        $stmt = $db->query(
            "SELECT 
                COUNT(*) as total_events,
                SUM(CASE WHEN event_name = 'rage_click' THEN 1 ELSE 0 END) as rage_clicks_count,
                SUM(CASE WHEN event_name = 'dead_click' THEN 1 ELSE 0 END) as dead_clicks_count,
                SUM(CASE WHEN event_name = 'funnel_step' THEN 1 ELSE 0 END) as funnel_steps_count
             FROM telemetry_events"
        );
        $counts = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC) ?: []) : [];

        $totalEvents = (int)($counts['total_events'] ?? 0);
        $rageClicksCount = (int)($counts['rage_clicks_count'] ?? 0);
        $deadClicksCount = (int)($counts['dead_clicks_count'] ?? 0);
        $funnelStepsCount = (int)($counts['funnel_steps_count'] ?? 0);

        // 2. Top Rage Targets
        $stmtRage = $db->query(
            "SELECT 
                JSON_UNQUOTE(JSON_EXTRACT(metadata_json, '$.target')) as target_el,
                COUNT(*) as hit_count
             FROM telemetry_events 
             WHERE event_name = 'rage_click'
             GROUP BY target_el
             ORDER BY hit_count DESC
             LIMIT 5"
        );
        $topRage = [];
        if ($stmtRage) {
            while ($row = $stmtRage->fetch(PDO::FETCH_ASSOC)) {
                $target = (string)($row['target_el'] ?? 'Unknown');
                if ($target !== 'null' && $target !== '') {
                    $topRage[] = ['target' => $target, 'count' => (int)($row['hit_count'] ?? 0)];
                }
            }
        }

        // 3. Top Dead Targets
        $stmtDead = $db->query(
            "SELECT 
                JSON_UNQUOTE(JSON_EXTRACT(metadata_json, '$.target')) as target_el,
                COUNT(*) as hit_count
             FROM telemetry_events 
             WHERE event_name = 'dead_click'
             GROUP BY target_el
             ORDER BY hit_count DESC
             LIMIT 5"
        );
        $topDead = [];
        if ($stmtDead) {
            while ($row = $stmtDead->fetch(PDO::FETCH_ASSOC)) {
                $target = (string)($row['target_el'] ?? 'Unknown');
                if ($target !== 'null' && $target !== '') {
                    $topDead[] = ['target' => $target, 'count' => (int)($row['hit_count'] ?? 0)];
                }
            }
        }

        // 4. Recent Timeline
        $stmtRecent = $db->query(
            "SELECT id, event_name, url_path, viewport_w, viewport_h, metadata_json, ip_anon, created_at
             FROM telemetry_events
             ORDER BY id DESC
             LIMIT 20"
        );
        $recent = [];
        if ($stmtRecent) {
            while ($row = $stmtRecent->fetch(PDO::FETCH_ASSOC)) {
                $meta = json_decode((string)($row['metadata_json'] ?? '{}'), true);
                $recent[] = [
                    'id' => (int)$row['id'],
                    'event' => (string)$row['event_name'],
                    'url' => (string)$row['url_path'],
                    'viewport' => $row['viewport_w'] . 'x' . $row['viewport_h'],
                    'metadata' => is_array($meta) ? $meta : [],
                    'ip_anon' => (string)$row['ip_anon'],
                    'created_at' => (string)$row['created_at'],
                ];
            }
        }

        return [
            'total_events' => $totalEvents,
            'rage_clicks_count' => $rageClicksCount,
            'dead_clicks_count' => $deadClicksCount,
            'funnel_steps_count' => $funnelStepsCount,
            'top_rage_targets' => $topRage,
            'top_dead_targets' => $topDead,
            'recent_events' => $recent,
        ];
    }
}
