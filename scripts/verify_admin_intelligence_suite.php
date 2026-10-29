<?php
/**
 * Master Verification Suite: Secret Paths, Authentication, World-Class Decision Engine & Live Presence
 * Tested across all 7 portfolio applications (strictly excluding loaneasyfinance)
 */

$apps = [
    'scoretenant' => [
        'entry' => 'public/index.php',
        'secret' => '/portal_3109982e5c14',
        'subpaths' => ['', '/campaigns', '/broadcast', '/system', '/users', '/telemetry'],
        'auth_session' => [
            'id' => 1517, 'user_id' => 1517, 'account_id' => 1517, 'role' => 'admin',
            'auth' => ['type' => 'super_admin', 'user_id' => 1517, '2fa_passed' => true, 'email' => 'superadmin_e2e@example.com']
        ]
    ],
    'partyplatform' => [
        'entry' => 'index.php',
        'secret' => '/manager_url',
        'subpaths' => ['/dashboard', '/campaigns', '/broadcast', '/system', '/users'],
        'auth_session' => [
            'id' => 1, 'user_id' => 1, 'account_id' => 1, 'role' => 'admin', 'APPROVED' => true, 'totp_verified' => true,
            'auth' => ['type' => 'super_admin', 'user_id' => 1, '2fa_passed' => true, 'email' => 'superadmin@partyplanner.live']
        ]
    ],
    'familyPlatform' => [
        'entry' => 'index.php',
        'secret' => '/manager_url',
        'subpaths' => ['/dashboard', '/campaigns', '/broadcast', '/system', '/users'],
        'auth_session' => [
            'id' => 1, 'user_id' => 1, 'account_id' => 1, 'famCode' => 'DEMO123', 'role' => 'admin', 'APPROVED' => true, 'totp_verified' => true,
            'auth' => ['type' => 'super_admin', 'user_id' => 1, '2fa_passed' => true, 'email' => 'admin@familytree.org.uk']
        ]
    ],
    'idecideapp' => [
        'entry' => 'index.php',
        'secret' => '/manager_url',
        'subpaths' => ['/dashboard', '/campaigns', '/broadcast-console', '/system', '/users'],
        'auth_session' => [
            'id' => 1, 'user_id' => 1, 'account_id' => 1, 'role' => 'admin', 'APPROVED' => true, 'totp_verified' => true,
            'auth' => ['type' => 'super_admin', 'user_id' => 1, '2fa_passed' => true, 'email' => 'admin@idecide.app']
        ]
    ],
    'bcp' => [
        'entry' => 'index.php',
        'secret' => '/manager_url',
        'subpaths' => ['/hub', '/campaigns', '/broadcast', '/system'],
        'auth_session' => [
            'id' => 1, 'user_id' => 1, 'account_id' => 1, 'role' => 'admin', 'APPROVED' => true, 'totp_verified' => true,
            'auth' => ['type' => 'super_admin', 'user_id' => 1, '2fa_passed' => true, 'email' => 'admin@bestcarepro.co.uk']
        ]
    ],
    'execmindapp' => [
        'entry' => 'index.php',
        'secret' => '/portal_c9e50b7def38',
        'subpaths' => ['', '/campaigns', '/broadcast', '/system', '/users'],
        'auth_session' => [
            'id' => 1, 'user_id' => 1, 'account_id' => 1, 'role' => 'admin',
            'auth' => ['type' => 'super_admin', 'user_id' => 1, '2fa_passed' => true, 'email' => 'general@execmindapp.com']
        ]
    ],
    'iaccountapp' => [
        'entry' => 'index.php',
        'secret' => '/portal_1d771d3b1033',
        'subpaths' => ['', '/campaigns', '/broadcast', '/system', '/users'],
        'auth_session' => [
            'id' => 13, 'user_id' => 13, 'account_id' => 13, 'role' => 'admin',
            'auth' => ['type' => 'super_admin', 'user_id' => 13, '2fa_passed' => true, 'email' => 'retailwally@gmail.com']
        ]
    ]
];

function testAppRoute(string $appName, string $entry, string $uri, ?array $sessionData) {
    $sessionCode = '';
    if (!empty($sessionData)) {
        $exported = var_export($sessionData, true);
        $sessionCode = "\$_SESSION = array_merge(\$_SESSION ?? [], {$exported});";
    }

    $appRoot = "/Users/waleolaogun/Sites/{$appName}";
    $entryFile = "{$appRoot}/{$entry}";
    $workingDir = dirname($entryFile);

    $runnerCode = <<<PHP
<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
\$_SERVER['REQUEST_URI'] = '{$uri}';
\$_SERVER['REQUEST_METHOD'] = 'GET';
\$_SERVER['HTTP_HOST'] = 'localhost';
\$_SERVER['SERVER_NAME'] = 'localhost';
\$_SERVER['SERVER_PORT'] = '80';
\$_SERVER['SCRIPT_NAME'] = '/index.php';
\$_ENV['APP_ENV'] = 'local';
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}
{$sessionCode}
chdir('{$workingDir}');
ob_start();
register_shutdown_function(function() {
    \$body = ob_get_contents();
    ob_end_clean();
    \$code = http_response_code() ?: 200;
    \$loc = '';
    foreach (headers_list() as \$h) {
        if (stripos(\$h, 'location:') === 0) {
            \$loc = trim(substr(\$h, 9));
        }
    }
    \$hasUmac = stripos(\$body, 'UMAC') !== false || stripos(\$body, 'Command Centre') !== false || stripos(\$body, 'Command Hub') !== false || stripos(\$body, 'modernHub') !== false;
    \$hasDecision = stripos(\$body, 'Autonomous Executive Decision') !== false;
    \$hasPresence = stripos(\$body, 'Live Presence') !== false;
    \$hasLogin = stripos(\$body, 'login') !== false || stripos(\$body, 'Password') !== false;
    \$is404 = stripos(\$body, '404 Not Found') !== false || \$code === 404;
    echo json_encode([
        'code' => \$code,
        'bytes' => strlen(\$body),
        'location' => \$loc,
        'has_umac' => \$hasUmac,
        'has_decision' => \$hasDecision,
        'has_presence' => \$hasPresence,
        'has_login' => \$hasLogin,
        'is_404' => \$is404
    ]);
});
try {
    require '{$entryFile}';
} catch (\Throwable \$e) {
    if (ob_get_level() > 0) ob_end_clean();
    echo json_encode([
        'code' => 500,
        'error' => \$e->getMessage(),
        'file' => basename(\$e->getFile()) . ':' . \$e->getLine()
    ]);
    exit;
}
PHP;

    $tmp = tempnam(sys_get_temp_dir(), 'test_run_');
    file_put_contents($tmp, $runnerCode);
    $out = shell_exec("php " . escapeshellarg($tmp) . " 2>&1");
    @unlink($tmp);
    return json_decode(trim($out), true) ?: ['raw' => $out];
}

$summary = [];

foreach ($apps as $name => $conf) {
    echo "=================================================================\n";
    echo "TESTING APP: {$name}\n";
    echo "Secret Path: {$conf['secret']}\n";
    echo "=================================================================\n";

    // 1. Direct /admin unauthenticated attack (Must be 404 disguised)
    $resDirect = testAppRoute($name, $conf['entry'], '/admin', null);
    $direct404 = ($resDirect['code'] ?? 0) === 404 || ($resDirect['is_404'] ?? false);
    $umacLeaked = ($resDirect['has_umac'] ?? false);

    echo "[SECURITY ATTACK] GET /admin (Unauthenticated) -> Code: " . ($resDirect['code'] ?? 'ERR') . 
         " | 404 Disguised: " . ($direct404 ? 'YES (PASSED)' : 'NO (FAILED)') . 
         " | UMAC Leaked: " . ($umacLeaked ? 'YES (LEAK!)' : 'NO (SECURE)') . "\n";

    // 2. Unauthenticated secret path access (Must redirect/block)
    $resUnauthSecret = testAppRoute($name, $conf['entry'], $conf['secret'], null);
    $unauthBlocked = ($resUnauthSecret['code'] ?? 0) === 302 || 
                     ($resUnauthSecret['has_login'] ?? false) || 
                     (!($resUnauthSecret['has_umac'] ?? false));

    echo "[AUTH GATE] GET {$conf['secret']} (Unauthenticated) -> Code: " . ($resUnauthSecret['code'] ?? 'ERR') .
         " | Redirect: " . ($resUnauthSecret['location'] ?? 'none') .
         " | UMAC Blocked: " . ($unauthBlocked ? 'YES (PASSED)' : 'NO (FAILED)') . "\n";

    // 3. Authenticated SuperAdmin Happy Path
    $allAuthPassed = true;
    foreach ($conf['subpaths'] as $sub) {
        $testUri = rtrim($conf['secret'] . $sub, '/');
        if (empty($testUri)) $testUri = $conf['secret'];
        $resAuth = testAppRoute($name, $conf['entry'], $testUri, $conf['auth_session']);
        $pass = (($resAuth['code'] ?? 0) === 200) && ($resAuth['bytes'] ?? 0) > 1000;
        if (!$pass) $allAuthPassed = false;

        $decStr = ($resAuth['has_decision'] ?? false) ? ' [Decision Engine OK]' : '';
        $presStr = ($resAuth['has_presence'] ?? false) ? ' [Live Presence OK]' : '';

        echo "  [AUTHENTICATED] GET {$testUri} -> Code: " . ($resAuth['code'] ?? 'ERR') .
             " | Bytes: " . ($resAuth['bytes'] ?? 0) . 
             " | UMAC: " . (($resAuth['has_umac'] ?? false) ? 'YES' : 'NO') .
             $decStr . $presStr .
             " -> " . ($pass ? "PASS" : "FAIL") . "\n";
    }

    $summary[$name] = [
        'direct_404_passed' => $direct404 && !$umacLeaked,
        'unauth_blocked' => $unauthBlocked,
        'auth_happy_path_passed' => $allAuthPassed
    ];
    echo "\n";
}

echo "=================================================================\n";
echo "MASTER SUMMARY:\n";
echo json_encode($summary, JSON_PRETTY_PRINT) . "\n";
echo "=================================================================\n";
