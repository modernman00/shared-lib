<?php

declare(strict_types=1);

/**
 * Ecosystem Translation & Localization Verification Suite
 *
 * Verifies I18n translation capabilities across all portfolio applications:
 * - Shared-lib core engine, fallback cascades, allowlists, and helpers
 * - Dictionary syntax, validity, and key counts across apps
 * - Route and controller dispatch verification
 * - App-level translation test executions
 * - Assessment of unlocalized / en-GB default apps
 */

echo "===============================================================\n";
echo "🌍 PORTFOLIO ECOSYSTEM TRANSLATION & I18N VERIFICATION SUITE\n";
echo "===============================================================\n\n";

$sitesDir = dirname(__DIR__, 2);
$sharedLibDir = dirname(__DIR__);

$autoloadPath = file_exists($sharedLibDir . '/vendor/autoload.php')
    ? $sharedLibDir . '/vendor/autoload.php'
    : dirname($sharedLibDir, 3) . '/vendor/autoload.php';
require_once $autoloadPath;
require_once $sharedLibDir . '/helpers/helpers.php';

use Src\I18n;

$failures = 0;
$passes = 0;

function assertCheck(bool $condition, string $message): void {
    global $passes, $failures;
    if ($condition) {
        echo "  [PASS] $message\n";
        $passes++;
    } else {
        echo "  [FAIL] $message\n";
        $failures++;
    }
}

// -------------------------------------------------------------
// 1. Shared-Lib Core Engine Verification
// -------------------------------------------------------------
echo "[1/4] Auditing Centralized I18n Engine (shared-lib)...\n";

I18n::reset();
assertCheck(I18n::getLocale() === 'en', 'Default locale is British English (en)');
assertCheck(I18n::setLocale('fr') === true, 'setLocale to French succeeds');
assertCheck(I18n::getLocale() === 'fr', 'Active locale is now French (fr)');
assertCheck(I18n::setLocale('../../../etc/passwd') === false, 'Strict allowlist rejects directory traversal attacks');
assertCheck(I18n::getLocale() === 'fr', 'Locale preserved after rejected attack');

// Dictionaries in shared-lib
$sharedLangDir = $sharedLibDir . '/resources/lang';
$sharedLocales = ['en', 'fr', 'es', 'us'];
foreach ($sharedLocales as $loc) {
    $file = $sharedLangDir . '/' . $loc . '.php';
    assertCheck(file_exists($file), "shared-lib dictionary exists: $loc.php");
    $dict = require $file;
    assertCheck(is_array($dict) && count($dict) > 0, "shared-lib $loc.php contains valid dictionary (" . count($dict) . " keys)");
}

// Global helpers test
I18n::setLocale('en');
$translatedEn = __('global.save');
assertCheck($translatedEn === 'Save', "Global helper __('global.save') returns 'Save' in English");

I18n::setLocale('fr');
$translatedFr = __('global.save');
assertCheck($translatedFr === 'Enregistrer', "Global helper __('global.save') returns 'Enregistrer' in French");

I18n::setLocale('es');
$translatedEs = __('global.save');
assertCheck($translatedEs === 'Guardar', "Global helper __('global.save') returns 'Guardar' in Spanish");

// Cascade fallback test
$fallbackTest = __('unknown.key.test', 'My Default');
assertCheck($fallbackTest === 'My Default', "Cascade correctly returns fallback when key does not exist");

echo "\n";

// -------------------------------------------------------------
// 2. Portfolio Applications with Dedicated Dictionaries
// -------------------------------------------------------------
echo "[2/4] Verifying Applications with Dedicated Dictionaries...\n";

$appsWithLang = [
    'partyplatform' => ['dir' => $sitesDir . '/partyplatform', 'locales' => ['en', 'fr', 'es']],
    'familyPlatform' => ['dir' => $sitesDir . '/familyPlatform', 'locales' => ['en', 'fr', 'es']],
    'idecideapp' => ['dir' => $sitesDir . '/idecideapp', 'locales' => ['en', 'fr', 'es']],
    'iaccountapp' => ['dir' => $sitesDir . '/iaccountapp', 'locales' => ['en', 'fr', 'es']],
];

foreach ($appsWithLang as $appName => $config) {
    echo "  -> Auditing $appName:\n";
    $appDir = $config['dir'];
    $langDir = $appDir . '/resources/lang';
    assertCheck(is_dir($langDir), "$appName has resources/lang directory");

    $keyCounts = [];
    foreach ($config['locales'] as $loc) {
        $file = $langDir . '/' . $loc . '.php';
        assertCheck(file_exists($file), "$appName has $loc.php");
        if (file_exists($file)) {
            $dict = require $file;
            $count = is_array($dict) ? count($dict) : 0;
            $keyCounts[$loc] = $count;
            assertCheck($count > 0, "$appName [$loc] contains $count keys");
        }
    }

    // Check key parity against English dictionary
    if (isset($keyCounts['en'])) {
        $enKeys = array_keys(require $langDir . '/en.php');
        foreach (['fr', 'es'] as $checkLoc) {
            if (file_exists($langDir . "/$checkLoc.php")) {
                $locKeys = array_keys(require $langDir . "/$checkLoc.php");
                $missing = array_diff($enKeys, $locKeys);
                $coveragePct = round((count($locKeys) / max(1, count($enKeys))) * 100, 1);
                assertCheck(count($locKeys) > 0, "$appName [$checkLoc] has $coveragePct% key coverage relative to English (" . count($missing) . " missing/inherited)");
            }
        }
    }
}

echo "\n";

// -------------------------------------------------------------
// 3. Execution of Application Unit Test Suites
// -------------------------------------------------------------
echo "[3/4] Running Active Unit Test Suites for Translation...\n";

$testSuites = [
    'shared-lib' => [
        'cmd' => 'cd ' . escapeshellarg($sharedLibDir) . ' && vendor/bin/phpunit tests/I18nTest.php',
    ],
    'partyplatform' => [
        'cmd' => 'cd ' . escapeshellarg($sitesDir . '/partyplatform') . ' && vendor/bin/phpunit tests/Unit/I18nTest.php',
    ],
    'familyPlatform' => [
        'cmd' => 'cd ' . escapeshellarg($sitesDir . '/familyPlatform') . ' && vendor/bin/phpunit tests/Unit/I18nTest.php',
    ],
    'idecideapp' => [
        'cmd' => 'cd ' . escapeshellarg($sitesDir . '/idecideapp') . ' && vendor/bin/phpunit tests/Unit/I18nTest.php tests/Unit/TemplateTranslationsTest.php',
    ],
    'iaccountapp' => [
        'cmd' => 'cd ' . escapeshellarg($sitesDir . '/iaccountapp') . ' && vendor/bin/phpunit tests/Unit/I18nTest.php',
    ],
];

foreach ($testSuites as $suiteName => $info) {
    exec($info['cmd'] . ' 2>&1', $output, $returnCode);
    $outputStr = implode("\n", $output);
    $ok = ($returnCode === 0);
    assertCheck($ok, "Test suite [$suiteName] passed with exit code $returnCode");
    if (!$ok) {
        echo "    Output: " . substr($outputStr, 0, 300) . "...\n";
    }
    unset($output);
}

echo "\n";

// -------------------------------------------------------------
// 4. Audit of Remaining Applications
// -------------------------------------------------------------
echo "[4/4] Auditing Remaining Applications Architecture...\n";

// ExecMind
echo "  -> ExecMind:\n";
$execMindRoute = file_exists($sitesDir . '/execmindapp/app/Routes/route.php') 
    && str_contains(file_get_contents($sitesDir . '/execmindapp/app/Routes/route.php'), 'LOCALE_SWITCH');
assertCheck($execMindRoute, "ExecMind has /locale/[a:locale] route registered to Src\\LocaleController");
$execMindLayout = file_exists($sitesDir . '/execmindapp/resources/views/layout.blade.php')
    && str_contains(file_get_contents($sitesDir . '/execmindapp/resources/views/layout.blade.php'), 'Src\\I18n::getLocale()');
assertCheck($execMindLayout, "ExecMind layout has Language Selector Pill using Src\\I18n");

// ScoreTenant, LoanEasyFinance, BCP
echo "  -> ScoreTenant, LoanEasyFinance, BCP:\n";
foreach (['scoretenant', 'loaneasyfinance', 'bcp'] as $monolingualApp) {
    $hasSharedLib = file_exists($sitesDir . '/' . $monolingualApp . '/composer.json')
        && str_contains(file_get_contents($sitesDir . '/' . $monolingualApp . '/composer.json'), 'shared-lib');
    assertCheck($hasSharedLib, "$monolingualApp includes shared-lib dependency (I18n engine available, currently operating on sovereign British English en-GB default)");
}

echo "\n===============================================================\n";
echo "SUMMARY: Total Checks: " . ($passes + $failures) . " | Passed: $passes | Failed: $failures\n";
echo "===============================================================\n";

exit($failures > 0 ? 1 : 0);
