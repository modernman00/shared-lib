<?php

declare(strict_types=1);

namespace Src\Quality;

use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Engineering Must-Haves scanner — the single source of truth for every portfolio app.
 *
 * Checks (AGENTS.md Golden Invariants):
 *  1. CSRF token inside every state-changing (non-GET) <form>
 *  2. Prepared statements only — bans raw variable interpolation inside SQL strings
 *  3. No md5()/sha1() on passwords or tokens
 *
 * Consumers: `vendor/bin/verify-must-haves [dir]` and the AssertsEngineeringMustHaves trait,
 * which each app mixes into a PHPUnit test so the scan runs on every test run.
 */
final class EngineeringMustHaves
{
    /** Directories never scanned. Pruned during traversal, so large trees cost nothing. */
    public const EXCLUDED_DIRS = [
        'vendor', '.git', '.phpunit.cache', 'cache', 'storage', 'node_modules', 'tests', 'scratch', 'scripts',
        'bootstrap', 'public', 'build', 'dist', 'test-results', 'playwright-report', '.php_tmp', 'gen_class', 'php-uk',
    ];

    private const SQL_INTERPOLATION = '/\b(SELECT\s+.*?\s+FROM|INSERT\s+INTO\s+.*?\s+VALUES|UPDATE\s+.*?\s+SET|DELETE\s+FROM)\b.*?(?:"[^"]*\$[a-zA-Z_\x7f-\xff][^"]*"|[\'"]\s*\.\s*\$)/i';

    private const CSRF_MARKERS = '/(name=[\'"]_token[\'"]|name=[\'"]token[\'"]|@csrf|\bcsrf_token\b|\bcsrf_field\b|\bcsrfField\b|\bcsrfToken\b|@include\([\'"][^\'"]*csrf[^\'"]*[\'"]\)|LaravelHelper::csrfField|LaravelHelper::csrfToken|BuildFormBStrap|BuildFormBulma|FormBuilder)/i';

    /**
     * @return list<array{severity:string,rule:string,file:string,line:int,remediation:string}>
     */
    public static function scan(string $targetDir): array
    {
        $root = realpath($targetDir);
        if ($root === false || !is_dir($root)) {
            return [];
        }

        $excluded = array_flip(self::EXCLUDED_DIRS);
        $filter = new RecursiveCallbackFilterIterator(
            new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS),
            static function (SplFileInfo $current) use ($excluded): bool {
                if ($current->isDir()) {
                    return !isset($excluded[$current->getFilename()]) && !$current->isLink();
                }
                return true;
            }
        );

        $violations = [];
        foreach (new RecursiveIteratorIterator($filter) as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }
            $path = $file->getPathname();
            $ext = $file->getExtension();
            if (!in_array($ext, ['php', 'html', 'phtml'], true)) {
                continue;
            }
            $content = file_get_contents($path);
            if ($content === false) {
                continue;
            }
            array_push($violations, ...self::scanContent($path, $ext, $content));
        }

        return $violations;
    }

    /**
     * @return list<array{severity:string,rule:string,file:string,line:int,remediation:string}>
     */
    public static function scanContent(string $path, string $ext, string $content): array
    {
        $out = [];
        $isBlade = str_ends_with($path, '.blade.php');

        // CHECK 1: CSRF in non-GET forms
        if (preg_match_all('#<form\b([^>]*)>(.*?)</form>#is', $content, $m, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($m[0] as $i => $formMatch) {
                $attrs = $m[1][$i][0];
                $body = $m[2][$i][0];
                if (preg_match('/method=[\'"]get[\'"]/i', $attrs) === 1) {
                    continue;
                }
                if (preg_match(self::CSRF_MARKERS, $body) !== 1) {
                    $out[] = [
                        'severity' => 'CRITICAL',
                        'rule' => 'MUST-HAVE #1: Missing CSRF Token in Form',
                        'file' => $path,
                        'line' => substr_count(substr($content, 0, $formMatch[1]), "\n") + 1,
                        'remediation' => 'Add `@csrf` or `LaravelHelper::csrfField()` or `<input type="hidden" name="_token" value="...">` inside the `<form>`.',
                    ];
                }
            }
        }

        if ($ext !== 'php') {
            return $out;
        }

        $lines = explode("\n", $content);

        // CHECK 2: raw SQL interpolation (PHP, not Blade)
        if (!$isBlade) {
            foreach ($lines as $n => $line) {
                if (preg_match(self::SQL_INTERPOLATION, $line) === 1 && preg_match('~^\s*(//|\*|#)~', $line) !== 1) {
                    $out[] = [
                        'severity' => 'CRITICAL',
                        'rule' => 'MUST-HAVE #2: Raw SQL Interpolation Detected',
                        'file' => $path,
                        'line' => $n + 1,
                        'remediation' => 'Use prepared statements with `?` or `:name` placeholders. Never interpolate variables directly into SQL queries.',
                    ];
                }
            }
        }

        // CHECK 3: weak hashes on credentials
        foreach ($lines as $n => $line) {
            if (preg_match('/\b(md5|sha1)\s*\(\s*\$[a-zA-Z0-9_]*(pass|pwd|token)/i', $line) === 1) {
                $out[] = [
                    'severity' => 'CRITICAL',
                    'rule' => 'MUST-HAVE #3: Insecure Hash Function on Password/Token',
                    'file' => $path,
                    'line' => $n + 1,
                    'remediation' => 'Use `password_hash($pass, PASSWORD_ARGON2ID)` or `hash_hmac()` with SHA-256 for tokens.',
                ];
            }
        }

        return $out;
    }

    /**
     * Human-readable report. Returns the process exit code (0 = clean).
     *
     * @param list<array{severity:string,rule:string,file:string,line:int,remediation:string}> $violations
     */
    public static function report(array $violations, string $dir, bool $print = true): array
    {
        $text = "ENGINEERING MUST-HAVES SCANNER\nScanning Directory: " . (realpath($dir) ?: $dir) . "\n\n";
        if ($violations === []) {
            $text .= "ALL MUST-HAVE CHECKS PASSED (0 CSRF, 0 SQL interpolation, 0 weak hashes).\n";
        } else {
            $text .= 'MUST-HAVE VIOLATIONS DETECTED (' . count($violations) . " issues):\n\n";
            foreach ($violations as $v) {
                $text .= "  [{$v['severity']}] {$v['rule']}\n  Location: {$v['file']}:{$v['line']}\n  Fix: {$v['remediation']}\n\n";
            }
        }
        if ($print) {
            echo $text;
        }

        return ['exit' => $violations === [] ? 0 : 1, 'text' => $text];
    }
}
