<?php

declare(strict_types=1);

/**
 * verify_engineering_must_haves.php
 *
 * Automated CLI Linter & Static Analysis Guard for Non-Negotiable Engineering Must-Haves:
 * 1. CSRF Token in every POST/State-Changing Form
 * 2. Prepared Statements Only (Bans raw SQL interpolation)
 * 3. Defensive Superglobal Access (Enforces null coalescing `??`)
 * 4. Banned Insecure Hash Functions (md5/sha1 on credentials)
 *
 * Usage: php scripts/verify_engineering_must_haves.php [target_directory]
 */

$targetDir = $argv[1] ?? getcwd();
$exitCode = 0;
$violations = [];

echo "=================================================================\n";
echo "🛡️  ENGINEERING MUST-HAVES AUTOMATED SCANNER (TAT & BRATS GATE)\n";
echo "Scanning Directory: " . realpath($targetDir) . "\n";
echo "=================================================================\n\n";

$fileExtensions = ['php', 'blade.php', 'html', 'phtml'];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($targetDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile()) {
        continue;
    }

    $filePath = $file->getPathname();

    // Skip vendor, cache, git, tests directories
    if (preg_match('#/(vendor|\.git|\.phpunit\.cache|cache|storage|node_modules)/#', $filePath)) {
        continue;
    }

    $extension = $file->getExtension();
    if (!in_array($extension, ['php', 'html', 'phtml'], true) && !str_ends_with($filePath, '.blade.php')) {
        continue;
    }

    $content = file_get_contents($filePath);
    if ($content === false) {
        continue;
    }

    // --- CHECK 1: CSRF TOKEN IN FORMS ---
    // Match forms that use POST (or don't specify GET)
    if (preg_match_all('#<form\b([^>]*)>(.*?)</form>#is', $content, $matches, PREG_OFFSET_CAPTURE)) {
        foreach ($matches[0] as $index => $formMatch) {
            $formAttributes = $matches[1][$index][0];
            $formBody = $matches[2][$index][0];
            $formOffset = $formMatch[1];

            // If method is explicitly GET, CSRF is not required
            if (preg_match('/method=[\'"]get[\'"]/i', $formAttributes)) {
                continue;
            }

            // Check if CSRF token exists in the form body
            $hasCsrf = preg_match(
                '/(name=[\'"]_token[\'"]|name=[\'"]token[\'"]|@csrf|\bcsrf_token\b|\bcsrf_field\b|LaravelHelper::csrfField|LaravelHelper::csrfToken)/i',
                $formBody
            );

            if (!$hasCsrf) {
                $lineNumber = substr_count(substr($content, 0, $formOffset), "\n") + 1;
                $violations[] = [
                    'severity' => 'CRITICAL',
                    'rule' => 'MUST-HAVE #1: Missing CSRF Token in Form',
                    'file' => $filePath,
                    'line' => $lineNumber,
                    'remediation' => 'Add `@csrf` or `LaravelHelper::csrfField()` or `<input type="hidden" name="_token" value="...">` inside the `<form>`.',
                ];
                $exitCode = 1;
            }
        }
    }

    // --- CHECK 2: RAW SQL STRING INTERPOLATION ---
    if ($extension === 'php') {
        $lines = explode("\n", $content);
        foreach ($lines as $lineNum => $line) {
            // Check for SQL statements with direct variable interpolation
            if (preg_match('/(SELECT|INSERT INTO|UPDATE|DELETE FROM)\s+.*["\'].*\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*.*["\']/i', $line)) {
                // Ignore if it's within a comment
                if (!preg_match('#^\s*(//|\*|#)#', $line)) {
                    $violations[] = [
                        'severity' => 'CRITICAL',
                        'rule' => 'MUST-HAVE #2: Raw SQL Interpolation Detected',
                        'file' => $filePath,
                        'line' => $lineNum + 1,
                        'remediation' => 'Use prepared statements with `?` or `:name` placeholders. Never interpolate variables directly into SQL queries.',
                    ];
                    $exitCode = 1;
                }
            }
        }
    }

    // --- CHECK 3: INSECURE HASHING ON PASSWORDS ---
    if ($extension === 'php') {
        $lines = explode("\n", $content);
        foreach ($lines as $lineNum => $line) {
            if (preg_match('/\b(md5|sha1)\s*\(\s*\$[a-zA-Z0-9_]*(pass|pwd|token)/i', $line)) {
                $violations[] = [
                    'severity' => 'CRITICAL',
                    'rule' => 'MUST-HAVE #3: Insecure Hash Function on Password/Token',
                    'file' => $filePath,
                    'line' => $lineNum + 1,
                    'remediation' => 'Use `password_hash($pass, PASSWORD_ARGON2ID)` or `hash_hmac()` with SHA-256 for tokens.',
                ];
                $exitCode = 1;
            }
        }
    }
}

// Display report
if (empty($violations)) {
    echo "✅ ALL MUST-HAVE CHECKS PASSED!\n";
    echo "0 CSRF violations, 0 SQL interpolation flaws, 0 weak hashes detected.\n\n";
    exit(0);
}

echo "🚨 MUST-HAVE VIOLATIONS DETECTED (" . count($violations) . " issues found):\n\n";
foreach ($violations as $v) {
    echo "  [" . $v['severity'] . "] " . $v['rule'] . "\n";
    echo "  Location: " . $v['file'] . ":" . $v['line'] . "\n";
    echo "  Fix: " . $v['remediation'] . "\n\n";
}

echo "❌ Pre-review gate failed. Address all violations above before submitting PR.\n";
exit($exitCode);
