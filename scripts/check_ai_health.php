<?php

declare(strict_types=1);

/**
 * CLI AI Subsystem Health & Diagnostics Utility
 *
 * Usage: php scripts/check_ai_health.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Bootstrap environment from active app or shared-lib
$envPaths = [
    __DIR__ . '/../.env',
    __DIR__ . '/../../partyplatform/.env',
    __DIR__ . '/../../familyPlatform/.env',
    __DIR__ . '/../../scoretenant/.env',
    __DIR__ . '/../../loaneasyfinance/.env',
];

foreach ($envPaths as $p) {
    if (file_exists($p)) {
        $lines = file($p, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines !== false) {
            foreach ($lines as $line) {
                if (str_starts_with(trim($line), '#')) continue;
                if (str_contains($line, '=')) {
                    list($k, $v) = explode('=', trim($line), 2);
                    $k = trim($k);
                    $v = trim($v, " \t\n\r\0\x0B\"'");
                    if (empty($_ENV[$k])) {
                        putenv("{$k}={$v}");
                        $_ENV[$k] = $v;
                    }
                }
            }
        }
    }
}

echo "=================================================================\n";
echo "🤖 LIVE AI SUBSYSTEM HEALTH & DIAGNOSTICS DASHBOARD\n";
echo "=================================================================\n\n";

$service = new \Src\Ai\AiHealthCheckService();
$diag = $service->runDiagnostics();

echo "🕒 Timestamp:             " . $diag['timestamp'] . "\n";
echo "🚦 Overall Status:        " . ($diag['overall_status'] === 'OPERATIONAL' ? '🟢 OPERATIONAL' : '🔴 DEGRADED') . "\n";
echo "💬 Active Chat Provider:  " . $diag['active_chat_provider'] . "\n";
echo "🛡️ Zero-PII Guard:       " . ($diag['privacy_guard_status'] === 'ENFORCED' ? '🟢 ENFORCED (100% PII Scrubbing Active)' : '🔴 FAILED') . "\n\n";

echo "--- Subsystem Breakdown ---\n";
foreach ($diag['subsystems'] as $name => $sub) {
    $icon = match($sub['status']) {
        'HEALTHY', 'CONFIGURED', 'ACTIVE' => '🟢',
        'DEGRADED', 'UNCONFIGURED' => '🟡',
        default => '🔴',
    };
    echo sprintf(
        " %s %-22s [%s] Provider: %-22s Latency: %4dms\n    └─ %s\n",
        $icon,
        strtoupper(str_replace('_', ' ', $name)),
        $sub['status'],
        $sub['provider'],
        $sub['latency_ms'],
        $sub['details']
    );
}

echo "\n=================================================================\n";
