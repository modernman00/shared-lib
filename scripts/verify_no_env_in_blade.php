<?php
declare(strict_types=1);

$viewsDirs = glob('/Users/waleolaogun/Sites/*/resources/views');
$violations = [];

foreach ($viewsDirs as $vDir) {
    if (!is_dir($vDir)) continue;
    $appName = basename(dirname($vDir));
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($vDir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            $path = $file->getPathname();
            $lines = file($path);
            foreach ($lines as $lineNum => $line) {
                // Look for env('...') or env("...") - exclude CSS env(safe-area...
                if (preg_match('/(?<![a-zA-Z0-9_\-])env\s*\(\s*[\'"][a-zA-Z0-9_]+[\'"]/', $line)) {
                    $violations[] = "[$appName] " . basename($path) . ":" . ($lineNum + 1) . " -> " . trim($line);
                }
            }
        }
    }
}

if (!empty($violations)) {
    echo "❌ FATAL: Found " . count($violations) . " direct env() calls in Blade templates:\n";
    foreach ($violations as $v) {
        echo "   $v\n";
    }
    echo "\nFix: Use \$_ENV['KEY'] ?? getenv('KEY') or shared-lib helpers to prevent 500 runtime crashes.\n";
    exit(1);
}

echo "✅ GATE PASSED: Zero unsafe env() calls in Blade templates across portfolio.\n";
exit(0);
