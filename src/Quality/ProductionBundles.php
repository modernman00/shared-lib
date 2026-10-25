<?php

declare(strict_types=1);

namespace Src\Quality;

/**
 * Production-bundle gate: refuses saved (tracked) webpack output that is a dev/test build.
 *
 * A `mix watch` build saved to git (eval-source-map JS, an expanded main.css, an unversioned mix-manifest.json)
 * runs against the production vendor.js and breaks pages (PartyPlatform Guest Builder, commit 610113c).
 *
 * Driven by mix-manifest.json, so no per-app file list is needed — every bundle the manifest names is checked:
 *  - webpack.mix.js calls .version() (production only)  -> every manifest entry must carry ?id=
 *  - JS must not carry the webpack eval-source-map banner or sourceURL=webpack markers
 *  - a versioned app's CSS bundle must be minified (production), not expanded
 *
 * Consumers: `vendor/bin/verify-prod-bundles [--staged] [dir]` from preflight and the .githooks/pre-commit hook.
 */
final class ProductionBundles
{
    /** A minified production stylesheet has a handful of lines; an expanded dev one has hundreds. */
    private const MAX_PRODUCTION_CSS_LINES = 50;

    /**
     * @return array{problems:list<string>,checked:int,skipped:?string}
     */
    public static function scan(string $dir, bool $staged = false): array
    {
        $root = realpath($dir);
        if ($root === false || !is_dir($root)) {
            return ['problems' => ["$dir is not a directory"], 'checked' => 0, 'skipped' => null];
        }

        $stagedFiles = $staged ? self::git($root, ['diff', '--cached', '--name-only', '--diff-filter=ACMR']) : [];

        $mixConfig = is_file($root . '/webpack.mix.js') ? (string) file_get_contents($root . '/webpack.mix.js') : '';
        $versioned = (bool) preg_match('/\.version\s*\(/', $mixConfig);

        $manifestPath = null;
        foreach (['public/mix-manifest.json', 'mix-manifest.json'] as $candidate) {
            if (is_file($root . '/' . $candidate) && self::isTracked($root, $candidate)) {
                $manifestPath = $candidate;
                break;
            }
        }
        if ($manifestPath === null) {
            return ['problems' => [], 'checked' => 0, 'skipped' => 'No tracked mix-manifest.json, so no saved webpack bundles to check.'];
        }

        $manifestBody = self::read($root, $manifestPath, $staged);
        $entries = is_string($manifestBody) ? json_decode($manifestBody, true) : null;
        if (!is_array($entries)) {
            return ['problems' => ["$manifestPath is missing or not valid JSON"], 'checked' => 0, 'skipped' => null];
        }

        $problems = [];
        $checked = 0;
        $manifestDir = dirname($manifestPath) === '.' ? '' : dirname($manifestPath) . '/';

        if ($versioned) {
            foreach ($entries as $key => $value) {
                if (is_string($value) && !str_contains($value, '?id=')) {
                    $problems[] = "$manifestPath has an unversioned entry ($key), which is what a dev build writes";
                    break;
                }
            }
        }

        foreach (array_keys($entries) as $key) {
            $path = $manifestDir . ltrim((string) preg_replace('/\?.*$/', '', (string) $key), '/');
            if (!self::isTracked($root, $path) || ($staged && !in_array($path, $stagedFiles, true))) {
                continue;
            }
            $body = self::read($root, $path, $staged);
            if ($body === null) {
                continue;
            }
            $checked++;

            if (str_ends_with($path, '.js')
                && (str_contains($body, 'An "eval-source-map" devtool has been used') || str_contains($body, '//# sourceURL=webpack'))) {
                $problems[] = "$path is a dev build (eval-source-map)";
            }

            if ($versioned && str_ends_with($path, '.css') && substr_count($body, "\n") > self::MAX_PRODUCTION_CSS_LINES) {
                $problems[] = "$path is expanded (" . (substr_count($body, "\n") + 1) . ' lines), not the minified production build';
            }
        }

        return ['problems' => $problems, 'checked' => $checked, 'skipped' => null];
    }

    /**
     * Human-readable report. Returns the process exit code (0 = clean).
     *
     * @param array{problems:list<string>,checked:int,skipped:?string} $result
     * @return array{exit:int,text:string}
     */
    public static function report(array $result, bool $staged = false, bool $print = true): array
    {
        if ($result['problems'] === []) {
            $text = $result['skipped'] !== null
                ? "✅ {$result['skipped']}\n"
                : "✅ Production bundles OK ({$result['checked']} saved bundle files checked" . ($staged ? ', staged' : '') . ").\n";
            $exit = 0;
        } else {
            $text = "❌ Dev/test build artefacts are saved in git:\n";
            foreach ($result['problems'] as $problem) {
                $text .= "   - $problem\n";
            }
            $text .= "\n   Stop `mix watch`, run `npm run production`, then `git add` the rebuilt files.\n";
            $exit = 1;
        }
        if ($print && $exit === 0) {
            echo $text;
        } elseif ($print) {
            fwrite(STDERR, $text);
        }

        return ['exit' => $exit, 'text' => $text];
    }

    private static function isTracked(string $root, string $path): bool
    {
        return self::git($root, ['ls-files', '--', $path]) !== [];
    }

    /** @return string|null contents from the index (staged) or the working tree; null when absent */
    private static function read(string $root, string $path, bool $staged): ?string
    {
        if ($staged) {
            $out = self::git($root, ['show', ':' . $path], $code);
            return $code === 0 ? implode("\n", $out) : null;
        }
        return is_file($root . '/' . $path) ? (string) file_get_contents($root . '/' . $path) : null;
    }

    /**
     * @param list<string> $args
     * @return list<string>
     */
    private static function git(string $root, array $args, ?int &$code = null): array
    {
        $cmd = 'git -C ' . escapeshellarg($root) . ' ' . implode(' ', array_map('escapeshellarg', $args)) . ' 2>/dev/null';
        $out = [];
        exec($cmd, $out, $exit);
        $code = $exit;
        return $out;
    }
}
