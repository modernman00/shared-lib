<?php

declare(strict_types=1);

namespace Src\Admin;

use PDO;
use Src\Db;
use Src\AuditLogger;

class AdminService
{
    private PDO $db;

    public const APPS = [
        'execmind' => [
            'id' => 'execmind',
            'name' => 'ExecMind',
            'tagline' => 'Executive Career & Board Advisory Intelligence',
            'category' => 'Executive & Advisory',
            'color' => '#6366f1',
            'icon' => 'fa-brain-circuit',
            'badge' => 'Active',
            'domain' => 'execmindapp.com'
        ],
        'partyplatform' => [
            'id' => 'partyplatform',
            'name' => 'PartyPlatform',
            'tagline' => 'Next-Gen Social Event & Celebration Planning',
            'category' => 'Social & Lifestyle',
            'color' => '#ec4899',
            'icon' => 'fa-sparkles',
            'badge' => 'Active',
            'domain' => 'mypartyplatform.com'
        ],
        'familyplatform' => [
            'id' => 'familyplatform',
            'name' => 'FamilyPlatform',
            'tagline' => 'Connected Family Hub & Multi-Gen Coordination',
            'category' => 'Social & Lifestyle',
            'color' => '#10b981',
            'icon' => 'fa-house-heart',
            'badge' => 'Active',
            'domain' => 'myfamilyplatform.com'
        ],
        'idecide' => [
            'id' => 'idecide',
            'name' => 'iDecide',
            'tagline' => 'Collaborative Group Consensus & Decision Engine',
            'category' => 'Productivity & Decisions',
            'color' => '#f59e0b',
            'icon' => 'fa-scale-balanced',
            'badge' => 'Active',
            'domain' => 'idecideapp.com'
        ],
        'iaccount' => [
            'id' => 'iaccount',
            'name' => 'iAccount',
            'tagline' => 'Personal Wealth, Cashflow & Financial Intelligence',
            'category' => 'FinTech & Wealth',
            'color' => '#3b82f6',
            'icon' => 'fa-wallet',
            'badge' => 'Active',
            'domain' => 'iaccountapp.com'
        ],
        'scoretenant' => [
            'id' => 'scoretenant',
            'name' => 'TenantScore',
            'tagline' => 'Real-Time Tenant Credibility & Rental Scoring',
            'category' => 'PropTech & Trust',
            'color' => '#8b5cf6',
            'icon' => 'fa-building-shield',
            'badge' => 'Active',
            'domain' => 'scoretenant.com'
        ],
        'loaneasyfinance' => [
            'id' => 'loaneasyfinance',
            'name' => 'LoanEasyFinance',
            'tagline' => 'Ethical Personal Lending & Dynamic Affordability',
            'category' => 'FinTech & Lending',
            'color' => '#059669',
            'icon' => 'fa-hand-holding-dollar',
            'badge' => 'Active',
            'domain' => 'loaneasyfinance.com'
        ],
        'bcp' => [
            'id' => 'bcp',
            'name' => 'BestCarePro',
            'tagline' => 'Professional Care Management & Compliance Portal',
            'category' => 'HealthTech & Care',
            'color' => '#0284c7',
            'icon' => 'fa-user-nurse',
            'badge' => 'Active',
            'domain' => 'bestcarepro.co.uk'
        ],
    ];

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Db::connect2();
    }

    private function getUserTableName(): string
    {
        $configured = trim((string)($_ENV['DB_TABLE_LOGIN'] ?? (getenv('DB_TABLE_LOGIN') ?: '')));
        if (!empty($configured)) {
            return preg_replace('/[^a-zA-Z0-9_]/', '', $configured) ?: 'users';
        }

        try {
            $stmt = $this->db->query("SHOW TABLES LIKE 'users'");
            if ($stmt && $stmt->fetchColumn()) {
                return 'users';
            }
        } catch (\Throwable $e) {}

        return 'account';
    }

    /**
     * @return array<string, array<string, string>>
     */
    public function getSupportedApps(): array
    {
        return self::APPS;
    }

    /**
     * @param string|null $appFilter
     * @return array<string, mixed>
     */
    public function getOverviewMetrics(?string $appFilter = null): array
    {
        // 1. Total Registered Users (100% Empirical)
        $totalUsers = 0;
        try {
            $stmt = $this->db->query(sprintf("SELECT COUNT(*) FROM %s", $this->getUserTableName()));
            $totalUsers = (int)($stmt ? $stmt->fetchColumn() : 0);
        } catch (\Throwable $e) {
            $totalUsers = 0;
        }

        // 2. Push Subscribers (Real DB Count)
        $pushSubscribers = 0;
        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM push_subscriptions");
            $pushSubscribers = (int)($stmt ? $stmt->fetchColumn() : 0);
        } catch (\Throwable $e) {
            $pushSubscribers = 0;
        }

        // 3. Broadcasts & Delivered Volume (Real DB Count)
        $totalBroadcasts = 0;
        $totalDelivered = 0;
        $deliveryRate = 100.0;
        try {
            $stmt = $this->db->query("SELECT COUNT(*), COALESCE(SUM(recipient_count), 0), SUM(CASE WHEN status IN ('sent', 'delivered', 'completed') THEN 1 ELSE 0 END) FROM admin_broadcasts");
            if ($stmt) {
                $row = $stmt->fetch(PDO::FETCH_NUM);
                if (is_array($row)) {
                    $totalBroadcasts = (int)($row[0] ?? 0);
                    $totalDelivered = (int)($row[1] ?? 0);
                    $successfulBroadcasts = (int)($row[2] ?? 0);
                    if ($totalBroadcasts > 0) {
                        $deliveryRate = round(($successfulBroadcasts / $totalBroadcasts) * 100, 1);
                    }
                }
            }
        } catch (\Throwable $e) {
            $totalBroadcasts = 0;
            $totalDelivered = 0;
            $deliveryRate = 100.0;
        }

        // 4. Active Scheduled Campaigns
        $activeCampaigns = 0;
        try {
            $stmt = $this->db->query("SELECT COUNT(*) FROM admin_campaigns WHERE status IN ('scheduled', 'sending')");
            $activeCampaigns = (int)($stmt ? $stmt->fetchColumn() : 0);
        } catch (\Throwable $e) {
            $activeCampaigns = 0;
        }

        // 5. Empirical Active Users (Real DAU & MAU from presence and audit telemetry)
        $activeDau = 0;
        $activeMtu = 0;

        // Try presence table first (last 24 hours / last 30 days)
        try {
            AdminPresenceService::ensureSchema($this->db);
            $dauStmt = $this->db->query("SELECT COUNT(DISTINCT COALESCE(user_id, session_id)) FROM admin_presence WHERE last_seen_at >= (NOW() - INTERVAL 24 HOUR)");
            $activeDau = (int)($dauStmt ? $dauStmt->fetchColumn() : 0);

            $mtuStmt = $this->db->query("SELECT COUNT(DISTINCT COALESCE(user_id, session_id)) FROM admin_presence WHERE last_seen_at >= (NOW() - INTERVAL 30 DAY)");
            $activeMtu = (int)($mtuStmt ? $mtuStmt->fetchColumn() : 0);
        } catch (\Throwable) {}

        // Fallback to audit_logs / telemetry if presence is newly instantiated
        if ($activeDau === 0) {
            try {
                $stmt = $this->db->query("SELECT COUNT(DISTINCT user_id) FROM audit_logs WHERE created_at >= (NOW() - INTERVAL 24 HOUR) AND user_id IS NOT NULL AND user_id != '0'");
                $activeDau = (int)($stmt ? $stmt->fetchColumn() : 0);
            } catch (\Throwable) {}
        }
        if ($activeMtu === 0) {
            try {
                $stmt = $this->db->query("SELECT COUNT(DISTINCT user_id) FROM audit_logs WHERE created_at >= (NOW() - INTERVAL 30 DAY) AND user_id IS NOT NULL AND user_id != '0'");
                $activeMtu = (int)($stmt ? $stmt->fetchColumn() : 0);
            } catch (\Throwable) {}
        }

        // System operational status
        $systemUptime = 100.0;
        try {
            $this->db->query("SELECT 1");
        } catch (\Throwable) {
            $systemUptime = 0.0;
        }

        return [
            'total_users' => $totalUsers,
            'active_mtu' => $activeMtu,
            'active_dau' => $activeDau,
            'push_subscribers' => $pushSubscribers,
            'total_broadcasts' => $totalBroadcasts,
            'total_delivered_messages' => $totalDelivered,
            'active_campaigns' => $activeCampaigns,
            'delivery_rate' => $deliveryRate,
            'system_uptime' => $systemUptime,
            'selected_app' => $appFilter ?? 'all',
        ];
    }

    /**
     * @param string|null $appFilter
     * @return array<string, mixed>
     */
    public function getAppInsights(?string $appFilter = null): array
    {
        $apps = self::APPS;
        $insights = [];

        // 1. Calculate Real 7-day user signup growth rate
        $recentSignups = 0;
        $prevSignups = 0;
        try {
            $stmt = $this->db->query(
                "SELECT 
                    SUM(CASE WHEN created_at >= (NOW() - INTERVAL 7 DAY) THEN 1 ELSE 0 END) as recent_7d,
                    SUM(CASE WHEN created_at >= (NOW() - INTERVAL 14 DAY) AND created_at < (NOW() - INTERVAL 7 DAY) THEN 1 ELSE 0 END) as prev_7d
                 FROM " . $this->getUserTableName()
            );
            if ($stmt) {
                $growthRow = $stmt->fetch(PDO::FETCH_ASSOC);
                $recentSignups = (int)($growthRow['recent_7d'] ?? 0);
                $prevSignups = (int)($growthRow['prev_7d'] ?? 0);
            }
        } catch (\Throwable) {}

        $weeklyGrowthFormatted = '0%';
        if ($prevSignups > 0) {
            $diffPct = round((($recentSignups - $prevSignups) / $prevSignups) * 100);
            $weeklyGrowthFormatted = ($diffPct >= 0 ? '+' : '') . $diffPct . '%';
        } elseif ($recentSignups > 0) {
            $weeklyGrowthFormatted = '+' . $recentSignups . ' new';
        }

        // 2. Real Error Rate from audit_logs
        $errorRateFormatted = '0.0%';
        try {
            $errStmt = $this->db->query(
                "SELECT 
                    COUNT(*) as total_logs,
                    SUM(CASE WHEN action LIKE '%error%' OR action LIKE '%fail%' OR status LIKE '%error%' OR status LIKE '%fail%' THEN 1 ELSE 0 END) as error_logs
                 FROM audit_logs
                 WHERE created_at >= (NOW() - INTERVAL 7 DAY)"
            );
            if ($errStmt) {
                $errRow = $errStmt->fetch(PDO::FETCH_ASSOC);
                $tot = (int)($errRow['total_logs'] ?? 0);
                $err = (int)($errRow['error_logs'] ?? 0);
                if ($tot > 0) {
                    $errorRateFormatted = round(($err / $tot) * 100, 2) . '%';
                }
            }
        } catch (\Throwable) {}

        // 3. Real Push Opt-in rate
        $totalUsers = 0;
        $pushSubs = 0;
        try {
            $uStmt = $this->db->query(sprintf("SELECT COUNT(*) FROM %s", $this->getUserTableName()));
            $totalUsers = (int)($uStmt ? $uStmt->fetchColumn() : 0);
            $pStmt = $this->db->query("SELECT COUNT(*) FROM push_subscriptions");
            $pushSubs = (int)($pStmt ? $pStmt->fetchColumn() : 0);
        } catch (\Throwable) {}

        $pushOptinFormatted = $totalUsers > 0 ? round(($pushSubs / $totalUsers) * 100, 1) . '%' : '0.0%';

        // 4. Real Active Users by App from presence
        $activeByApp = [];
        try {
            AdminPresenceService::ensureSchema($this->db);
            $presStmt = $this->db->query(
                "SELECT app_name, COUNT(DISTINCT COALESCE(user_id, session_id)) as active_cnt
                 FROM admin_presence
                 WHERE last_seen_at >= (NOW() - INTERVAL 24 HOUR)
                 GROUP BY app_name"
            );
            if ($presStmt) {
                while ($pRow = $presStmt->fetch(PDO::FETCH_ASSOC)) {
                    $activeByApp[$pRow['app_name']] = (int)$pRow['active_cnt'];
                }
            }
        } catch (\Throwable) {}

        foreach ($apps as $key => $meta) {
            if ($appFilter && $appFilter !== 'all' && $appFilter !== $key) {
                continue;
            }

            $appActiveCount = $activeByApp[$key] ?? 0;

            $insights[$key] = [
                'name' => $meta['name'],
                'category' => $meta['category'],
                'color' => $meta['color'],
                'icon' => $meta['icon'],
                'domain' => $meta['domain'],
                'status' => 'Operational',
                'active_users' => $appActiveCount,
                'weekly_growth' => $weeklyGrowthFormatted,
                'conversion_rate' => $totalUsers > 0 ? round(($recentSignups / $totalUsers) * 100, 1) . '%' : '0.0%',
                'push_optin_rate' => $pushOptinFormatted,
                'error_rate' => $errorRateFormatted,
            ];
        }

        // 5. Real 7-day rolling traffic series (Mon - Sun empirical event volume)
        $trafficLabels = [];
        $trafficValues = [];
        for ($i = 6; $i >= 0; $i--) {
            $targetDate = date('Y-m-d', strtotime("-$i days"));
            $trafficLabels[] = date('D', strtotime("-$i days"));
            $dayCount = 0;

            // Query telemetry first
            try {
                $tStmt = $this->db->prepare("SELECT COUNT(*) FROM telemetry_events WHERE DATE(created_at) = ?");
                $tStmt->execute([$targetDate]);
                $dayCount += (int)$tStmt->fetchColumn();
            } catch (\Throwable) {}

            // Add presence events
            try {
                $pStmt = $this->db->prepare("SELECT COUNT(*) FROM admin_presence WHERE DATE(last_seen_at) = ?");
                $pStmt->execute([$targetDate]);
                $dayCount += (int)$pStmt->fetchColumn();
            } catch (\Throwable) {}

            // Add audit logs
            try {
                $aStmt = $this->db->prepare("SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at) = ?");
                $aStmt->execute([$targetDate]);
                $dayCount += (int)$aStmt->fetchColumn();
            } catch (\Throwable) {}

            $trafficValues[] = $dayCount;
        }

        // 6. Real channel share computed from admin_broadcasts
        $channelShare = [
            'Web Push' => 0,
            'Email' => 0,
            'In-App' => 0,
            'SMS' => 0,
        ];
        try {
            $chStmt = $this->db->query("SELECT channel, COUNT(*) as cnt FROM admin_broadcasts GROUP BY channel");
            if ($chStmt) {
                $totCh = 0;
                $rawCh = [];
                while ($cRow = $chStmt->fetch(PDO::FETCH_ASSOC)) {
                    $chName = strtolower(trim((string)$cRow['channel']));
                    $cnt = (int)$cRow['cnt'];
                    $totCh += $cnt;
                    if (str_contains($chName, 'push')) $rawCh['Web Push'] = ($rawCh['Web Push'] ?? 0) + $cnt;
                    elseif (str_contains($chName, 'email')) $rawCh['Email'] = ($rawCh['Email'] ?? 0) + $cnt;
                    elseif (str_contains($chName, 'sms')) $rawCh['SMS'] = ($rawCh['SMS'] ?? 0) + $cnt;
                    else $rawCh['In-App'] = ($rawCh['In-App'] ?? 0) + $cnt;
                }
                if ($totCh > 0) {
                    foreach ($rawCh as $k => $c) {
                        $channelShare[$k] = (int)round(($c / $totCh) * 100);
                    }
                } else {
                    // Reflect real push subscribers vs default if 0 broadcasts sent
                    if ($pushSubs > 0) {
                        $channelShare['Web Push'] = 100;
                    }
                }
            }
        } catch (\Throwable) {}

        return [
            'apps' => $insights,
            'traffic_series' => [
                'labels' => $trafficLabels,
                'values' => $trafficValues,
            ],
            'channel_distribution' => $channelShare,
        ];
    }

    /**
     * Executes the World-Class Executive Decision & Action Algorithm.
     * 
     * @return array<string, mixed>
     */
    public function getDecisionInsights(?string $appFilter = null): array
    {
        $engine = new DecisionEngineService($this->db, $this->getUserTableName());
        return $engine->evaluate($appFilter);
    }

    /**
     * Retrieves real-time active visitors on the site ("Who is on the site and when").
     * 
     * @return array<int, array<string, mixed>>
     */
    public function getActiveVisitors(int $windowMinutes = 15, int $limit = 50, ?string $appFilter = null): array
    {
        return AdminPresenceService::getActiveVisitors($this->db, $windowMinutes, $limit, $appFilter);
    }

    /**
     * Retrieves aggregated presence vitals.
     * 
     * @return array<string, mixed>
     */
    public function getPresenceSummary(int $windowMinutes = 15, ?string $appFilter = null): array
    {
        return AdminPresenceService::getPresenceSummary($this->db, $windowMinutes, $appFilter);
    }

    /**
     * @param array<string, mixed> $filters
     * @return array<string, mixed>
     */
    public function getUsers(array $filters = []): array
    {
        $search = trim((string)($filters['search'] ?? ''));
        $limit = min(100, max(10, (int)($filters['limit'] ?? 25)));
        $offset = max(0, (int)($filters['offset'] ?? 0));

        $table = $this->getUserTableName();
        $sql = sprintf("SELECT id, email, created_at, status FROM %s", $table);
        $params = [];

        if (!empty($search)) {
            $sql .= " WHERE email LIKE ?";
            $params[] = '%' . $search . '%';
        }

        $sql .= " ORDER BY id DESC LIMIT " . (int)$limit . " OFFSET " . (int)$offset;

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch count
            $baseQuery = sprintf("SELECT COUNT(*) FROM %s", $table);
            $countSql = $baseQuery . (!empty($search) ? ' WHERE email LIKE ?' : '');
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($params);
            $totalCount = (int)$countStmt->fetchColumn();

            return [
                'users' => $users ?: [],
                'total' => $totalCount,
                'limit' => $limit,
                'offset' => $offset,
            ];
        } catch (\Throwable $e) {
            return [
                'users' => [],
                'total' => 0,
                'limit' => $limit,
                'offset' => $offset,
            ];
        }
    }

    /**
     * @param int $userId
     * @param string $status
     * @param int $adminUserId
     * @return bool
     */
    public function updateUserStatus(int $userId, string $status, int $adminUserId): bool
    {
        $allowed = ['active', 'suspended', 'pending'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }

        try {
            $table = $this->getUserTableName();
            $stmt = $this->db->prepare("UPDATE " . $table . " SET status = ? WHERE id = ?");
            $result = $stmt->execute([$status, $userId]);

            if ($result) {
                AuditLogger::log('admin_user_status_change', [
                    'admin_user_id' => $adminUserId,
                    'target_user_id' => $userId,
                    'new_status' => $status
                ]);
            }
            return $result;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function getSystemHealth(): array
    {
        $dbStatus = 'Connected';
        $dbLatencyMs = 1.2;
        $t0 = microtime(true);
        try {
            $this->db->query("SELECT 1");
            $dbLatencyMs = round((microtime(true) - $t0) * 1000, 2);
        } catch (\Throwable $e) {
            $dbStatus = 'Error: ' . $e->getMessage();
        }

        return [
            'php_version' => PHP_VERSION,
            'database_status' => $dbStatus,
            'database_latency_ms' => $dbLatencyMs,
            'memory_usage_mb' => round(memory_get_usage(true) / 1024 / 1024, 2),
            'server_time' => date('Y-m-d H:i:s T'),
            'app_environment' => $_ENV['APP_ENV'] ?? 'production',
        ];
    }

    /**
     * Purges OPcache & APCu if available
     */
    public function purgeCache(int $adminUserId): bool
    {
        if (function_exists('opcache_reset')) {
            @opcache_reset();
        }
        if (function_exists('apcu_clear_cache')) {
            @apcu_clear_cache();
        }

        AuditLogger::log('admin_cache_purge', [
            'admin_user_id' => $adminUserId,
            'status' => 'success'
        ]);
        return true;
    }
}
