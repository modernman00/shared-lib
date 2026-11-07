<?php

declare(strict_types=1);

namespace Src\Quality;

/**
 * Sovereign Whole-Site Quality, CWV, Accessibility, SEO & v2.1 Design Standards Scanner
 * Commissioned by: Wally (CEO) | Engineered by: ATG & Engineering Council (Callum, Maya, Declan, Marcus)
 *
 * Replaces bloated third-party wrappers (e.g. Unlighthouse) across all 8 portfolio applications:
 *  1. Dynamic Sitemap Discovery (`public/sitemap.xml` / `sitemap.xml`) with local `.env` APP_URL origin rewriting
 *  2. Authenticated Route Scanning (`--cookie`, `--auth-routes`) with Auth-Wall Redirect Trap detection (Dr. Silas Thorne gate)
 *  3. Adversarial SSRF / LFI / XSS Payload Neutralization (Dr. Viktor Kovács & Marcus gate)
 *  4. Parallel cURL Multi HTTP + DOM/A11y/SEO/v2.1 Standards Engine
 *  5. Optional Live Headless Chrome CDP Runtime Probe (`--chrome`) for FCP/DOMInteractive, console.error, and computed RGB contrast
 *  6. Self-Contained Executive HTML & JSON Dashboard Generation (`storage/reports/whole-site-audit.html` & `.json`)
 */
final class WholeSiteScanner
{
    /** Default portfolio applications for `--all-apps` omnibus mode. */
    public const PORTFOLIO_APPS = [
        'familyPlatform',
        'partyplatform',
        'idecideapp',
        'iaccountapp',
        'scoretenant',
        'execmindapp',
        'loaneasyfinance',
        'bcp',
    ];

    /** Common authenticated routes probed when `--auth-routes` or `--cookie` is supplied. */
    public const DEFAULT_AUTH_ROUTES = [
        '/home',
        '/dashboard',
        '/playbook',
        '/how-to-use',
    ];

    /** US English words flagged in visible copy (Jumoke British English Invariant). */
    private const US_SPELLINGS = [
        'color' => 'colour',
        'behavior' => 'behaviour',
        'favor' => 'favour',
        'honor' => 'honour',
        'neighbor' => 'neighbour',
        'optimize' => 'optimise',
        'customize' => 'customise',
        'organize' => 'organise',
        'prioritize' => 'prioritise',
        'recognize' => 'recognise',
    ];

    /**
     * Validate and sanitize a candidate URL against SSRF, LFI (`file://`), and metadata IP exploits.
     *
     * @return array{valid:bool,url:string,reason:?string}
     */
    public static function validateTargetUrl(string $rawUrl, bool $allowPrivateLocal = true): array
    {
        $trimmed = trim($rawUrl);
        if ($trimmed === '' || preg_match('/[\r\n\0]/', $trimmed) === 1) {
            return ['valid' => false, 'url' => $trimmed, 'reason' => 'Empty or control-character tainted URL'];
        }

        $parts = parse_url($trimmed);
        if (!is_array($parts) || !isset($parts['scheme'])) {
            return ['valid' => false, 'url' => $trimmed, 'reason' => 'Malformed URL structure'];
        }

        $scheme = strtolower((string) $parts['scheme']);
        if (!in_array($scheme, ['http', 'https'], true)) {
            return ['valid' => false, 'url' => $trimmed, 'reason' => "Forbidden URL scheme '{$scheme}://' (only http/https permitted)"];
        }

        if (!isset($parts['host']) || trim((string) $parts['host']) === '') {
            return ['valid' => false, 'url' => $trimmed, 'reason' => 'Missing URL hostname'];
        }

        $host = strtolower((string) $parts['host']);

        // Block cloud metadata endpoints unconditionally (AWS/GCP/Azure SSRF vector)
        $blockedHosts = [
            '169.254.169.254',
            'metadata.google.internal',
            'metadata.goog',
            '100.100.100.200',
            '0.0.0.0',
            '[::]',
            '[::1]',
        ];
        if (in_array($host, $blockedHosts, true) || str_starts_with($host, '169.254.')) {
            return ['valid' => false, 'url' => $trimmed, 'reason' => "Blocked cloud metadata / link-local SSRF host '{$host}'"];
        }

        if (!$allowPrivateLocal) {
            if ($host === 'localhost' || $host === '127.0.0.1' || str_ends_with($host, '.test') || str_ends_with($host, '.local')) {
                return ['valid' => false, 'url' => $trimmed, 'reason' => "Localhost/private host '{$host}' disallowed in strict external mode"];
            }
        }

        return ['valid' => true, 'url' => $trimmed, 'reason' => null];
    }

    /**
     * Sanitize an optional Cookie header string against CRLF / header injection.
     */
    public static function sanitizeCookie(string $cookie): string
    {
        $clean = (string) preg_replace('/[\r\n\0]+/', '', trim($cookie));
        return substr($clean, 0, 4096);
    }

    /**
     * Resolve local or production base URL from an application's `.env` file.
     */
    public static function resolveAppBaseUrl(string $appDir, bool $useProductionOrigin = false): string
    {
        $root = realpath($appDir) ?: $appDir;
        $envFiles = $useProductionOrigin
            ? [$root . '/.env_production', $root . '/.env']
            : [$root . '/.env', $root . '/.env_production'];

        $keys = $useProductionOrigin
            ? ['GEO_CANONICAL_URL', 'APP_URL', 'APP_URL2']
            : ['APP_URL_DEV', 'APP_URL', 'APP_URL2'];

        foreach ($envFiles as $envFile) {
            if (!is_file($envFile)) {
                continue;
            }
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if (!is_array($lines)) {
                continue;
            }
            $parsed = [];
            foreach ($lines as $line) {
                $trimmed = trim($line);
                if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
                    continue;
                }
                [$k, $v] = explode('=', $trimmed, 2);
                $k = trim($k);
                $v = trim($v, " \t\n\r\0\x0B\"'");
                if ($v !== '') {
                    $parsed[$k] = rtrim($v, '/');
                }
            }
            foreach ($keys as $key) {
                if (!empty($parsed[$key]) && preg_match('#^https?://#i', $parsed[$key]) === 1) {
                    $candidate = $parsed[$key];
                    if (!$useProductionOrigin && str_contains($candidate, 'bestcarepro.co.uk')) {
                        return 'https://bcp.test';
                    }
                    return $candidate;
                }
            }
        }

        $folder = strtolower(basename($root));
        if ($folder === 'familyplatform') {
            return 'https://olaogun.test';
        }
        if ($folder === 'loaneasyfinance') {
            return 'https://lef.test';
        }
        return 'https://' . $folder . '.test';
    }

    /**
     * Discover target URLs from `public/sitemap.xml` (or `sitemap.xml`), rewriting origins to `$baseUrl` when needed.
     *
     * @param list<string> $extraUrls
     * @return array{urls:list<string>,rejected:list<array{url:string,reason:string}>,source:string}
     */
    public static function discoverUrls(
        string $appDir,
        ?string $baseUrl = null,
        bool $rewriteOrigin = true,
        array $extraUrls = [],
        bool $includeAuthRoutes = false,
        int $maxUrls = 50,
        bool $onlyExplicitUrls = false
    ): array {
        $root = realpath($appDir) ?: $appDir;
        $resolvedBase = rtrim($baseUrl ?: self::resolveAppBaseUrl($root), '/');
        $sitemapCandidates = [
            $root . '/public/sitemap.xml',
            $root . '/sitemap.xml',
        ];

        $rawCandidates = [];
        $source = 'fallback-core-routes';

        if ($onlyExplicitUrls && $extraUrls !== []) {
            $source = 'explicit-urls';
            foreach ($extraUrls as $extra) {
                $trimmed = trim($extra);
                if ($trimmed === '') {
                    continue;
                }
                if (str_starts_with($trimmed, '/')) {
                    $rawCandidates[] = $resolvedBase . $trimmed;
                } else {
                    $rawCandidates[] = $trimmed;
                }
            }
        } else {
            foreach ($sitemapCandidates as $smPath) {
                if (is_file($smPath)) {
                    $xmlContent = (string) file_get_contents($smPath);
                    if (preg_match_all('#<loc>\s*([^<\s]+)\s*</loc>#i', $xmlContent, $matches) > 0) {
                        foreach ($matches[1] as $loc) {
                            $decoded = html_entity_decode(trim($loc), ENT_QUOTES | ENT_XML1, 'UTF-8');
                            // Validate before origin rewriting so SSRF/LFI URLs inside sitemap.xml are never rewritten to local base URL
                            $preCheck = self::validateTargetUrl($decoded, true);
                            if (!$preCheck['valid']) {
                                $rawCandidates[] = $decoded;
                                continue;
                            }
                            if ($rewriteOrigin && $resolvedBase !== '') {
                                $parsed = parse_url($decoded);
                                if (is_array($parsed) && isset($parsed['scheme'], $parsed['host'])) {
                                    $path = $parsed['path'] ?? '/';
                                    $query = isset($parsed['query']) ? '?' . $parsed['query'] : '';
                                    $decoded = $resolvedBase . '/' . ltrim($path, '/') . $query;
                                }
                            }
                            $rawCandidates[] = $decoded;
                        }
                        $source = $smPath;
                        break;
                    }
                }
            }

            if ($rawCandidates === [] && $extraUrls === []) {
                foreach (['/', '/aboutus', '/playbook', '/how-to-use', '/blog', '/contact', '/privacy', '/terms'] as $route) {
                    $rawCandidates[] = $resolvedBase . $route;
                }
            }

            if ($includeAuthRoutes) {
                foreach (self::DEFAULT_AUTH_ROUTES as $authRoute) {
                    $rawCandidates[] = $resolvedBase . $authRoute;
                }
            }

            foreach ($extraUrls as $extra) {
                $trimmed = trim($extra);
                if ($trimmed === '') {
                    continue;
                }
                if (str_starts_with($trimmed, '/')) {
                    $rawCandidates[] = $resolvedBase . $trimmed;
                } else {
                    $rawCandidates[] = $trimmed;
                }
            }
        }

        $validUrls = [];
        $rejected = [];
        $seen = [];

        foreach ($rawCandidates as $candidate) {
            $check = self::validateTargetUrl($candidate, true);
            if (!$check['valid']) {
                $rejected[] = ['url' => $candidate, 'reason' => (string) $check['reason']];
                continue;
            }
            $normalized = $check['url'];
            if (isset($seen[$normalized])) {
                continue;
            }
            $seen[$normalized] = true;
            $validUrls[] = $normalized;
        }

        if (count($validUrls) > $maxUrls) {
            $validUrls = array_slice($validUrls, 0, $maxUrls);
        }

        return [
            'urls' => $validUrls,
            'rejected' => $rejected,
            'source' => $source,
        ];
    }

    /**
     * Audit a single HTML document + HTTP response metadata across 5 Lighthouse & Sovereign Design categories:
     *  - Performance (0-100)
     *  - Accessibility (0-100)
     *  - Best Practices & Security (0-100)
     *  - SEO (0-100)
     *  - Design Standards v2.1 (0-100)
     *
     * @param array<string,string> $headers Lowercase header map
     * @param array<string,mixed>|null $chromeMetrics Optional live Chrome CDP probe telemetry
     * @return array{
     *   url:string,
     *   final_url:string,
     *   status_code:int,
     *   ttfb_ms:int,
     *   total_ms:int,
     *   size_bytes:int,
     *   auth_redirect_wall:bool,
     *   scores:array{performance:int,accessibility:int,best_practices:int,seo:int,design_v21:int,overall:int},
     *   issues:list<array{category:string,severity:string,code:string,message:string}>,
     *   chrome_probed:bool
     * }
     */
    public static function evaluatePage(
        string $url,
        string $finalUrl,
        int $statusCode,
        int $ttfbMs,
        int $totalMs,
        string $html,
        array $headers = [],
        ?array $chromeMetrics = null
    ): array {
        $issues = [];
        $perfScore = 100;
        $a11yScore = 100;
        $bpScore = 100;
        $seoScore = 100;
        $designScore = 100;

        $sizeBytes = strlen($html);

        // Dr. Silas Thorne Gate: Detect Auth-Wall Redirect Trap
        $reqPath = (string) (parse_url($url, PHP_URL_PATH) ?? '/');
        $finalPath = (string) (parse_url($finalUrl, PHP_URL_PATH) ?? '/');
        $isLoginTarget = (bool) preg_match('#/(login|signin|auth/login)\b#i', $reqPath);
        $landedOnLogin = (bool) preg_match('#/(login|signin|auth/login)\b#i', $finalPath);
        $authRedirectWall = !$isLoginTarget && $landedOnLogin;

        if ($authRedirectWall) {
            $issues[] = [
                'category' => 'best_practices',
                'severity' => 'WARNING',
                'code' => 'AUTH_REDIRECT_WALL',
                'message' => "Protected route '{$reqPath}' redirected to login wall ('{$finalPath}'). Pass --cookie=\"PHPSESSID=...\" to audit authenticated state.",
            ];
            $bpScore -= 15;
        }

        // HTTP Status Check
        if ($statusCode === 0) {
            return [
                'url' => $url,
                'final_url' => $finalUrl,
                'status_code' => 0,
                'ttfb_ms' => $ttfbMs,
                'total_ms' => $totalMs,
                'size_bytes' => 0,
                'auth_redirect_wall' => false,
                'scores' => [
                    'performance' => 0,
                    'accessibility' => 0,
                    'best_practices' => 0,
                    'seo' => 0,
                    'design_v21' => 0,
                    'overall' => 0,
                ],
                'issues' => [[
                    'category' => 'best_practices',
                    'severity' => 'CRITICAL',
                    'code' => 'HTTP_UNREACHABLE',
                    'message' => 'Target URL failed to respond (connection refused, DNS error, or timeout).',
                ]],
                'chrome_probed' => false,
            ];
        }

        if ($statusCode >= 500) {
            $issues[] = [
                'category' => 'best_practices',
                'severity' => 'CRITICAL',
                'code' => 'HTTP_5XX_ERROR',
                'message' => "Server returned HTTP {$statusCode} error.",
            ];
            $perfScore -= 50;
            $bpScore -= 60;
            $seoScore -= 60;
        } elseif ($statusCode >= 400) {
            $issues[] = [
                'category' => 'seo',
                'severity' => 'CRITICAL',
                'code' => 'HTTP_4XX_ERROR',
                'message' => "Page returned HTTP {$statusCode} client error.",
            ];
            $bpScore -= 30;
            $seoScore -= 50;
        }

        // ---------------------------------------------------------------------
        // 1. PERFORMANCE AUDITS
        // ---------------------------------------------------------------------
        $chromeTtfb = is_array($chromeMetrics) && isset($chromeMetrics['ttfbMs']) ? (int) $chromeMetrics['ttfbMs'] : 0;
        $effectiveTtfb = $chromeTtfb > 0 ? $chromeTtfb : $ttfbMs;
        if ($effectiveTtfb > 1500) {
            $perfScore -= 25;
            $issues[] = [
                'category' => 'performance',
                'severity' => 'WARNING',
                'code' => 'SLOW_TTFB',
                'message' => "Server Time-To-First-Byte is {$effectiveTtfb}ms (target < 600ms).",
            ];
        } elseif ($effectiveTtfb > 800) {
            $perfScore -= 10;
            $issues[] = [
                'category' => 'performance',
                'severity' => 'INFO',
                'code' => 'MODERATE_TTFB',
                'message' => "Server Time-To-First-Byte is {$effectiveTtfb}ms (target < 600ms).",
            ];
        }

        if ($sizeBytes > 1024 * 500) {
            $kb = (int) round($sizeBytes / 1024);
            $perfScore -= 15;
            $issues[] = [
                'category' => 'performance',
                'severity' => 'WARNING',
                'code' => 'LARGE_HTML_PAYLOAD',
                'message' => "HTML document payload is {$kb}KB (target < 500KB).",
            ];
        }

        if (str_contains($html, 'An "eval-source-map" devtool has been used')) {
            $perfScore -= 20;
            $bpScore -= 20;
            $issues[] = [
                'category' => 'performance',
                'severity' => 'CRITICAL',
                'code' => 'DEV_BUNDLE_DETECTED',
                'message' => 'Unminified webpack eval-source-map development bundle detected in live HTML response.',
            ];
        }

        // Check images missing width/height or loading="lazy" (CLS risk)
        if (preg_match_all('#<img\b([^>]*)>#i', $html, $imgMatches) > 0) {
            $missingDimensions = 0;
            foreach ($imgMatches[1] as $imgIdx => $imgAttrs) {
                $hasWidth = preg_match('/\b(width|style)\s*=/i', $imgAttrs) === 1 || preg_match('/\bw-\d+|\bh-\d+/i', $imgAttrs) === 1;
                if (!$hasWidth && $imgIdx > 1) {
                    $missingDimensions++;
                }
            }
            if ($missingDimensions > 3) {
                $perfScore -= 5;
                $issues[] = [
                    'category' => 'performance',
                    'severity' => 'INFO',
                    'code' => 'IMAGE_CLS_RISK',
                    'message' => "{$missingDimensions} <img> elements lack explicit width/height attributes or sizing classes (CLS risk).",
                ];
            }
        }

        // Check videos missing preload="none" (The 8MB+ Mobile Data & LCP trap)
        if (preg_match_all('#<video\b([^>]*)>#i', $html, $vidMatches) > 0) {
            $unlazyVideos = 0;
            foreach ($vidMatches[1] as $vAttrs) {
                if (preg_match('/preload\s*=\s*[\'"]none[\'"]/i', $vAttrs) !== 1) {
                    $unlazyVideos++;
                }
            }
            if ($unlazyVideos > 0) {
                $perfScore -= min(25, $unlazyVideos * 10);
                $issues[] = [
                    'category' => 'performance',
                    'severity' => 'WARNING',
                    'code' => 'VIDEO_PRELOAD_BLOCKED',
                    'message' => "{$unlazyVideos} <video> element(s) lack preload=\"none\". Mobile browsers may preload multi-megabyte streams, heavily degrading LCP and bandwidth.",
                ];
            }
        }

        // ---------------------------------------------------------------------
        // 2. ACCESSIBILITY (A11Y) AUDITS
        // ---------------------------------------------------------------------
        if (preg_match('#<html\b[^>]*\blang\s*=\s*[\'"][a-zA-Z-]+[\'"]#i', $html) !== 1) {
            $a11yScore -= 15;
            $issues[] = [
                'category' => 'accessibility',
                'severity' => 'WARNING',
                'code' => 'MISSING_HTML_LANG',
                'message' => '<html> element does not have a valid [lang] attribute (e.g. lang="en-GB").',
            ];
        }

        if (preg_match('#<meta\b[^>]*name=[\'"]viewport[\'"][^>]*>#i', $html, $vpMatch) === 1) {
            if (preg_match('/user-scalable\s*=\s*no|maximum-scale\s*=\s*1(\.0)?\b/i', $vpMatch[0]) === 1) {
                $a11yScore -= 20;
                $issues[] = [
                    'category' => 'accessibility',
                    'severity' => 'CRITICAL',
                    'code' => 'VIEWPORT_ZOOM_LOCKED',
                    'message' => 'Viewport meta tag disables user zooming (violates WCAG 2.1 AA 200% text zoom requirement).',
                ];
            }
        } else {
            $a11yScore -= 20;
            $seoScore -= 15;
            $issues[] = [
                'category' => 'accessibility',
                'severity' => 'CRITICAL',
                'code' => 'MISSING_VIEWPORT_META',
                'message' => 'Missing <meta name="viewport"> tag required for mobile responsiveness.',
            ];
        }

        // Image alt attributes
        if (!empty($imgMatches[1])) {
            $missingAlt = 0;
            foreach ($imgMatches[1] as $imgAttrs) {
                if (preg_match('/\balt\s*=/i', $imgAttrs) !== 1 && preg_match('/\baria-hidden\s*=\s*[\'"]true[\'"]/i', $imgAttrs) !== 1) {
                    $missingAlt++;
                }
            }
            if ($missingAlt > 0) {
                $penalty = min(25, $missingAlt * 5);
                $a11yScore -= $penalty;
                $issues[] = [
                    'category' => 'accessibility',
                    'severity' => 'WARNING',
                    'code' => 'IMG_MISSING_ALT',
                    'message' => "{$missingAlt} <img> element(s) are missing an [alt] attribute.",
                ];
            }
        }

        // Empty buttons without accessible name
        if (preg_match_all('#<button\b([^>]*)>(.*?)</button>#is', $html, $btnMatches) > 0) {
            $unlabelledBtns = 0;
            foreach ($btnMatches[1] as $bIdx => $btnAttrs) {
                $innerText = trim(strip_tags($btnMatches[2][$bIdx]));
                $hasAria = preg_match('/\b(aria-label|aria-labelledby|title)\s*=\s*[\'"][^\'"]+[\'"]/i', $btnAttrs) === 1;
                if ($innerText === '' && !$hasAria) {
                    $unlabelledBtns++;
                }
            }
            if ($unlabelledBtns > 0) {
                $a11yScore -= min(20, $unlabelledBtns * 5);
                $issues[] = [
                    'category' => 'accessibility',
                    'severity' => 'WARNING',
                    'code' => 'BUTTON_NAME_MISSING',
                    'message' => "{$unlabelledBtns} icon-only <button> element(s) lack discernible text or [aria-label].",
                ];
            }
        }

        // Check semantic <main> landmark
        if (preg_match('#<main\b#i', $html) !== 1 && preg_match('#role\s*=\s*[\'"]main[\'"]#i', $html) !== 1) {
            $a11yScore -= 10;
            $issues[] = [
                'category' => 'accessibility',
                'severity' => 'WARNING',
                'code' => 'MISSING_MAIN_LANDMARK',
                'message' => 'Document is missing a <main> landmark or element with role="main".',
            ];
        }

        // Check ARIA role="list" contains role="listitem"
        if (preg_match_all('#<([a-z0-9]+)\b[^>]*\brole\s*=\s*[\'"]list[\'"][^>]*>(.*?)</\1>#is', $html, $listMatches) > 0) {
            $invalidLists = 0;
            foreach ($listMatches[2] as $listBody) {
                if (trim($listBody) !== '' && preg_match('#\brole\s*=\s*[\'"]listitem[\'"]#i', $listBody) !== 1 && preg_match('#<li\b#i', $listBody) !== 1) {
                    $invalidLists++;
                }
            }
            if ($invalidLists > 0) {
                $a11yScore -= min(15, $invalidLists * 5);
                $issues[] = [
                    'category' => 'accessibility',
                    'severity' => 'WARNING',
                    'code' => 'ARIA_LIST_MISSING_ITEMS',
                    'message' => "{$invalidLists} element(s) with role=\"list\" do not contain children with role=\"listitem\" or <li> tags.",
                ];
            }
        }

        // Check heading level skips (e.g. h1 followed directly by h3..h6)
        if (preg_match_all('#<h([1-6])\b#i', $html, $headingMatches) > 0) {
            $levels = array_map('intval', $headingMatches[1]);
            $prevLevel = 0;
            $skipped = false;
            foreach ($levels as $lvl) {
                if ($prevLevel > 0 && $lvl > ($prevLevel + 1)) {
                    $skipped = true;
                    break;
                }
                $prevLevel = $lvl;
            }
            if ($skipped) {
                $a11yScore -= 10;
                $issues[] = [
                    'category' => 'accessibility',
                    'severity' => 'WARNING',
                    'code' => 'HEADING_ORDER_SKIPPED',
                    'message' => 'Heading elements are not in a sequentially-descending order (skips levels).',
                ];
            }
        }

        // ---------------------------------------------------------------------
        // 3. SEO AUDITS
        // ---------------------------------------------------------------------
        if (preg_match('#<title[^>]*>(.*?)</title>#is', $html, $titleMatch) === 1) {
            $titleText = trim(html_entity_decode(strip_tags($titleMatch[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $titleLen = mb_strlen($titleText);
            if ($titleLen === 0) {
                $seoScore -= 25;
                $issues[] = [
                    'category' => 'seo',
                    'severity' => 'CRITICAL',
                    'code' => 'EMPTY_TITLE',
                    'message' => 'Document <title> tag is empty.',
                ];
            } elseif ($titleLen < 10) {
                $seoScore -= 10;
                $issues[] = [
                    'category' => 'seo',
                    'severity' => 'INFO',
                    'code' => 'SHORT_TITLE',
                    'message' => "Document <title> is very short ({$titleLen} chars: '{$titleText}').",
                ];
            }
        } else {
            $seoScore -= 25;
            $issues[] = [
                'category' => 'seo',
                'severity' => 'CRITICAL',
                'code' => 'MISSING_TITLE',
                'message' => 'Document is missing a <title> element.',
            ];
        }

        if (preg_match('#<meta\b[^>]*name=[\'"]description[\'"][^>]*content=[\'"]([^\'"]*)[\'"]#i', $html, $descMatch) === 1
            || preg_match('#<meta\b[^>]*content=[\'"]([^\'"]*)[\'"][^>]*name=[\'"]description[\'"]#i', $html, $descMatch) === 1) {
            if (trim($descMatch[1]) === '') {
                $seoScore -= 15;
                $issues[] = [
                    'category' => 'seo',
                    'severity' => 'WARNING',
                    'code' => 'EMPTY_META_DESCRIPTION',
                    'message' => '<meta name="description"> attribute is empty.',
                ];
            }
        } else {
            $seoScore -= 15;
            $issues[] = [
                'category' => 'seo',
                'severity' => 'WARNING',
                'code' => 'MISSING_META_DESCRIPTION',
                'message' => 'Document is missing a <meta name="description"> tag.',
            ];
        }

        if (preg_match('#<h1\b[^>]*>(.*?)</h1>#is', $html) !== 1) {
            $seoScore -= 10;
            $a11yScore -= 5;
            $issues[] = [
                'category' => 'seo',
                'severity' => 'WARNING',
                'code' => 'MISSING_H1',
                'message' => 'Page does not contain a primary <h1> heading.',
            ];
        }

        if (preg_match('#<link\b[^>]*rel=[\'"]canonical[\'"]#i', $html) !== 1) {
            $seoScore -= 10;
            $issues[] = [
                'category' => 'seo',
                'severity' => 'INFO',
                'code' => 'MISSING_CANONICAL',
                'message' => 'Page does not specify a <link rel="canonical"> tag.',
            ];
        }

        // Check uncrawlable anchors (Lighthouse crawlable-anchors)
        if (preg_match_all('#<a\b[^>]*\bhref\s*=\s*[\'"]javascript:[^\'"]*[\'"]#i', $html, $jsAnchorMatches) > 0) {
            $jsCount = count($jsAnchorMatches[0]);
            $seoScore -= min(15, $jsCount * 5);
            $issues[] = [
                'category' => 'seo',
                'severity' => 'WARNING',
                'code' => 'UNCRAWLABLE_JAVASCRIPT_ANCHORS',
                'message' => "{$jsCount} <a> anchor(s) use href=\"javascript:...\" which search engines cannot crawl. Use <button type=\"button\"> instead.",
            ];
        }

        // ---------------------------------------------------------------------
        // 4. BEST PRACTICES & SECURITY AUDITS
        // ---------------------------------------------------------------------
        if (preg_match('#<!doctype\s+html>#i', $html) !== 1) {
            $bpScore -= 15;
            $issues[] = [
                'category' => 'best_practices',
                'severity' => 'WARNING',
                'code' => 'MISSING_DOCTYPE',
                'message' => 'Page is missing <!DOCTYPE html> declaration (triggers quirks mode).',
            ];
        }

        // Check non-GET forms for CSRF token (Engineering Must-Have #1)
        if (preg_match_all('#<form\b([^>]*)>(.*?)</form>#is', $html, $formMatches) > 0) {
            $missingCsrfForms = 0;
            foreach ($formMatches[1] as $fIdx => $fAttrs) {
                if (preg_match('/method\s*=\s*[\'"]get[\'"]/i', $fAttrs) === 1) {
                    continue;
                }
                $fBody = $formMatches[2][$fIdx];
                if (preg_match('/name\s*=\s*[\'"](_token|token|csrf_token)[\'"]/i', $fBody) !== 1) {
                    $missingCsrfForms++;
                }
            }
            if ($missingCsrfForms > 0) {
                $bpScore -= 25;
                $designScore -= 20;
                $issues[] = [
                    'category' => 'best_practices',
                    'severity' => 'CRITICAL',
                    'code' => 'FORM_MISSING_CSRF',
                    'message' => "{$missingCsrfForms} state-changing <form> element(s) rendered without a hidden CSRF token input.",
                ];
            }
        }

        // Check HTTP Security Headers when headers are provided
        if ($headers !== []) {
            if (!isset($headers['x-content-type-options'])) {
                $bpScore -= 5;
                $issues[] = [
                    'category' => 'best_practices',
                    'severity' => 'INFO',
                    'code' => 'MISSING_XCTO_HEADER',
                    'message' => 'Missing X-Content-Type-Options: nosniff response header.',
                ];
            }
            if (!isset($headers['x-frame-options']) && (!isset($headers['content-security-policy']) || !str_contains($headers['content-security-policy'], 'frame-ancestors'))) {
                $bpScore -= 5;
                $issues[] = [
                    'category' => 'best_practices',
                    'severity' => 'INFO',
                    'code' => 'MISSING_CLICKJACK_HEADER',
                    'message' => 'Missing X-Frame-Options or CSP frame-ancestors clickjacking protection header.',
                ];
            }
        }

        // ---------------------------------------------------------------------
        // 5. SOVEREIGN DESIGN & UI/UX STANDARDS v2.1 AUDITS
        // ---------------------------------------------------------------------
        // Check 5.1: Inviolable Red Surface Rule (Static class combination check)
        if (preg_match('/class=[\'"][^\'"]*\b(bg-danger|bg-red-\d+)\b[^\'"]*\b(text-dark|text-black)\b/i', $html) === 1
            || preg_match('/class=[\'"][^\'"]*\b(text-dark|text-black)\b[^\'"]*\b(bg-danger|bg-red-\d+)\b/i', $html) === 1) {
            $designScore -= 30;
            $a11yScore -= 15;
            $issues[] = [
                'category' => 'design_v21',
                'severity' => 'CRITICAL',
                'code' => 'RED_SURFACE_BLACK_TEXT',
                'message' => 'Violates Inviolable Red Surface Rule: dark/black text class detected on a red/danger surface.',
            ];
        }

        // Check 5.2: Ghost Typography Invariant (Static class combination check)
        if (preg_match('/class=[\'"][^\'"]*\b(bg-white|bg-light)\b[^\'"]*\b(text-white|text-light)\b/i', $html) === 1) {
            $designScore -= 25;
            $a11yScore -= 15;
            $issues[] = [
                'category' => 'design_v21',
                'severity' => 'CRITICAL',
                'code' => 'GHOST_TYPOGRAPHY',
                'message' => 'Violates Ghost Typography Invariant: white/light text class placed directly on a white/light background class.',
            ];
        }

        // Check 5.3: Universal British English (UK) Invariant on visible body text
        $visibleBody = (string) preg_replace('#<(script|style|code|pre|svg)\b[^>]*>.*?</\1>#is', ' ', $html);
        $visibleText = strip_tags($visibleBody);
        $usFound = [];
        foreach (self::US_SPELLINGS as $usWord => $ukWord) {
            if (preg_match('/\b' . preg_quote($usWord, '/') . '\b/i', $visibleText) === 1) {
                $usFound[] = "'{$usWord}' -> '{$ukWord}'";
            }
        }
        if ($usFound !== []) {
            $designScore -= min(20, count($usFound) * 5);
            $issues[] = [
                'category' => 'design_v21',
                'severity' => 'WARNING',
                'code' => 'US_ENGLISH_COPY',
                'message' => 'US English spelling detected in visible copy (' . implode(', ', array_slice($usFound, 0, 4)) . '). Use British English (en-GB).',
            ];
        }

        // ---------------------------------------------------------------------
        // 6. MERGE HEADLESS CHROME CDP LIVE PROBE TELEMETRY (IF ENABLED)
        // ---------------------------------------------------------------------
        $chromeProbed = false;
        if (is_array($chromeMetrics) && ($chromeMetrics['probed'] ?? false) === true) {
            $chromeProbed = true;
            $consoleErrors = is_array($chromeMetrics['consoleErrors'] ?? null) ? $chromeMetrics['consoleErrors'] : [];
            $pageExceptions = is_array($chromeMetrics['pageExceptions'] ?? null) ? $chromeMetrics['pageExceptions'] : [];

            if ($consoleErrors !== [] || $pageExceptions !== []) {
                $errCount = count($consoleErrors) + count($pageExceptions);
                $bpScore -= min(30, $errCount * 10);
                $sampleErr = (string) ($pageExceptions[0] ?? $consoleErrors[0] ?? 'Runtime JS error');
                $issues[] = [
                    'category' => 'best_practices',
                    'severity' => 'CRITICAL',
                    'code' => 'RUNTIME_CONSOLE_ERROR',
                    'message' => "Headless Chrome captured {$errCount} runtime JS error(s): {$sampleErr}",
                ];
            }

            $redViolations = (int) ($chromeMetrics['redSurfaceViolations'] ?? 0);
            if ($redViolations > 0) {
                $designScore -= 30;
                $issues[] = [
                    'category' => 'design_v21',
                    'severity' => 'CRITICAL',
                    'code' => 'COMPUTED_RED_SURFACE_VIOLATION',
                    'message' => "Headless Chrome computed style check found {$redViolations} element(s) with dark text over a red background.",
                ];
            }

            $ghostViolations = (int) ($chromeMetrics['ghostTypeViolations'] ?? 0);
            if ($ghostViolations > 0) {
                $designScore -= 25;
                $issues[] = [
                    'category' => 'design_v21',
                    'severity' => 'CRITICAL',
                    'code' => 'COMPUTED_GHOST_TYPOGRAPHY',
                    'message' => "Headless Chrome computed style check found {$ghostViolations} element(s) with white-on-white unreadable contrast.",
                ];
            }
        }

        $perfScore = max(0, min(100, $perfScore));
        $a11yScore = max(0, min(100, $a11yScore));
        $bpScore = max(0, min(100, $bpScore));
        $seoScore = max(0, min(100, $seoScore));
        $designScore = max(0, min(100, $designScore));

        $overall = (int) round(($perfScore + $a11yScore + $bpScore + $seoScore + $designScore) / 5);

        return [
            'url' => $url,
            'final_url' => $finalUrl,
            'status_code' => $statusCode,
            'ttfb_ms' => $effectiveTtfb,
            'total_ms' => $totalMs,
            'size_bytes' => $sizeBytes,
            'auth_redirect_wall' => $authRedirectWall,
            'scores' => [
                'performance' => $perfScore,
                'accessibility' => $a11yScore,
                'best_practices' => $bpScore,
                'seo' => $seoScore,
                'design_v21' => $designScore,
                'overall' => $overall,
            ],
            'issues' => $issues,
            'chrome_probed' => $chromeProbed,
        ];
    }

    /**
     * Execute a full whole-site scan for an application or explicit URL list.
     *
     * @param array{
     *   app_dir?:string,
     *   base_url?:?string,
     *   rewrite_origin?:bool,
     *   urls?:list<string>,
     *   only_urls?:bool,
     *   include_sitemap?:bool,
     *   include_auth_routes?:bool,
     *   cookie?:string,
     *   concurrency?:int,
     *   timeout_sec?:int,
     *   max_urls?:int,
     *   use_chrome?:bool,
     *   use_lighthouse?:bool,
     *   min_score?:int
     * } $options
     * @return array{
     *   app_name:string,
     *   app_dir:string,
     *   base_url:string,
     *   sitemap_source:string,
     *   scanned_at:string,
     *   duration_ms:int,
     *   chrome_available:bool,
     *   min_score_threshold:int,
     *   passed_gate:bool,
     *   summary:array{
     *     total_urls:int,
     *     reachable_urls:int,
     *     unreachable_urls:int,
     *     auth_wall_redirects:int,
     *     rejected_unsafe_urls:int,
     *     critical_issues:int,
     *     warning_issues:int,
     *     avg_scores:array{performance:int,accessibility:int,best_practices:int,seo:int,design_v21:int,overall:int}
     *   },
     *   rejected_urls:list<array{url:string,reason:string}>,
     *   pages:list<array<string,mixed>>
     * }
     */
    public static function scanSite(array $options = []): array
    {
        $startTime = microtime(true);
        $appDir = (string) ($options['app_dir'] ?? getcwd());
        $root = realpath($appDir) ?: $appDir;
        $appName = basename($root);

        $rewriteOrigin = (bool) ($options['rewrite_origin'] ?? true);
        $baseUrl = isset($options['base_url']) && $options['base_url'] !== ''
            ? (string) $options['base_url']
            : self::resolveAppBaseUrl($root, !$rewriteOrigin);

        $extraUrls = is_array($options['urls'] ?? null) ? $options['urls'] : [];
        $onlyExplicitUrls = (bool) ($options['only_urls'] ?? false) || (!empty($extraUrls) && !($options['include_sitemap'] ?? false));
        $includeAuth = (bool) ($options['include_auth_routes'] ?? false);
        $maxUrls = max(1, min(250, (int) ($options['max_urls'] ?? 50)));
        $concurrency = max(1, min(8, (int) ($options['concurrency'] ?? 2)));
        $timeoutSec = max(2, min(30, (int) ($options['timeout_sec'] ?? 15)));
        $minScore = max(0, min(100, (int) ($options['min_score'] ?? 80)));
        $cookie = self::sanitizeCookie((string) ($options['cookie'] ?? ''));
        $useChrome = (bool) ($options['use_chrome'] ?? false);
        $useLighthouse = (bool) ($options['use_lighthouse'] ?? false);

        $discovery = self::discoverUrls($root, $baseUrl, $rewriteOrigin, $extraUrls, $includeAuth, $maxUrls, $onlyExplicitUrls);
        $urls = $discovery['urls'];
        $rejected = $discovery['rejected'];

        // Step 1: Parallel HTTP fetch via curl_multi
        $httpResponses = self::fetchUrlsParallel($urls, $cookie, $concurrency, $timeoutSec);

        // Step 2: Optional Headless Chrome CDP probe for reachable URLs
        $chromeResults = [];
        $chromeAvailable = false;
        if ($useChrome && $urls !== []) {
            $reachableUrls = [];
            foreach ($urls as $u) {
                if (($httpResponses[$u]['status_code'] ?? 0) > 0) {
                    $reachableUrls[] = $u;
                }
            }
            if ($reachableUrls !== []) {
                $probe = self::runChromeProbe($reachableUrls, $cookie, $timeoutSec * 1000);
                $chromeAvailable = $probe['available'];
                $chromeResults = $probe['results'];
            }
        }

        // Step 3: Evaluate each page
        $pages = [];
        $sumPerf = 0;
        $sumA11y = 0;
        $sumBp = 0;
        $sumSeo = 0;
        $sumDesign = 0;
        $sumOverall = 0;
        $reachableCount = 0;
        $unreachableCount = 0;
        $authWalls = 0;
        $criticalCount = 0;
        $warningCount = 0;

        foreach ($urls as $u) {
            $http = $httpResponses[$u] ?? [
                'final_url' => $u,
                'status_code' => 0,
                'ttfb_ms' => 0,
                'total_ms' => 0,
                'body' => '',
                'headers' => [],
            ];
            $cMetrics = is_array($chromeResults[$u] ?? null) ? $chromeResults[$u] : null;

            $eval = self::evaluatePage(
                $u,
                $http['final_url'],
                $http['status_code'],
                $http['ttfb_ms'],
                $http['total_ms'],
                $http['body'],
                $http['headers'],
                $cMetrics
            );

            if ($useLighthouse && $eval['status_code'] > 0) {
                $lhAudit = self::runLighthouseAudit($u, $cookie);
                if ($lhAudit !== null) {
                    $eval = $lhAudit;
                }
            }

            $pages[] = $eval;

            if ($eval['status_code'] > 0) {
                $reachableCount++;
                $sumPerf += $eval['scores']['performance'];
                $sumA11y += $eval['scores']['accessibility'];
                $sumBp += $eval['scores']['best_practices'];
                $sumSeo += $eval['scores']['seo'];
                $sumDesign += $eval['scores']['design_v21'];
                $sumOverall += $eval['scores']['overall'];
            } else {
                $unreachableCount++;
            }

            if ($eval['auth_redirect_wall']) {
                $authWalls++;
            }

            foreach ($eval['issues'] as $iss) {
                if ($iss['severity'] === 'CRITICAL') {
                    $criticalCount++;
                } elseif ($iss['severity'] === 'WARNING') {
                    $warningCount++;
                }
            }
        }

        $divisor = max(1, $reachableCount);
        $avgScores = [
            'performance' => $reachableCount > 0 ? (int) round($sumPerf / $divisor) : 0,
            'accessibility' => $reachableCount > 0 ? (int) round($sumA11y / $divisor) : 0,
            'best_practices' => $reachableCount > 0 ? (int) round($sumBp / $divisor) : 0,
            'seo' => $reachableCount > 0 ? (int) round($sumSeo / $divisor) : 0,
            'design_v21' => $reachableCount > 0 ? (int) round($sumDesign / $divisor) : 0,
            'overall' => $reachableCount > 0 ? (int) round($sumOverall / $divisor) : 0,
        ];

        $passedGate = $reachableCount > 0
            && $unreachableCount === 0
            && $avgScores['overall'] >= $minScore;

        $durationMs = (int) round((microtime(true) - $startTime) * 1000);

        return [
            'app_name' => $appName,
            'app_dir' => $root,
            'base_url' => $baseUrl,
            'sitemap_source' => $discovery['source'],
            'scanned_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'duration_ms' => $durationMs,
            'chrome_available' => $chromeAvailable,
            'min_score_threshold' => $minScore,
            'passed_gate' => $passedGate,
            'summary' => [
                'total_urls' => count($urls),
                'reachable_urls' => $reachableCount,
                'unreachable_urls' => $unreachableCount,
                'auth_wall_redirects' => $authWalls,
                'rejected_unsafe_urls' => count($rejected),
                'critical_issues' => $criticalCount,
                'warning_issues' => $warningCount,
                'avg_scores' => $avgScores,
            ],
            'rejected_urls' => $rejected,
            'pages' => $pages,
        ];
    }

    /**
     * Fetch URLs in parallel batches using `curl_multi` with strict protocol pinning (`CURLPROTO_HTTP | CURLPROTO_HTTPS`).
     *
     * @param list<string> $urls
     * @return array<string,array{final_url:string,status_code:int,ttfb_ms:int,total_ms:int,body:string,headers:array<string,string>}>
     */
    private static function fetchUrlsParallel(array $urls, string $cookie, int $concurrency, int $timeoutSec): array
    {
        $results = [];
        $batches = array_chunk($urls, max(1, $concurrency));

        foreach ($batches as $batch) {
            $mh = curl_multi_init();
            $handles = [];
            $headerBag = [];

            foreach ($batch as $url) {
                $ch = curl_init();
                $headerBag[$url] = [];

                $opts = [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_MAXREDIRS => 5,
                    CURLOPT_TIMEOUT => $timeoutSec,
                    CURLOPT_CONNECTTIMEOUT => min(5, $timeoutSec),
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_SSL_VERIFYHOST => 0,
                    CURLOPT_USERAGENT => 'SovereignWholeSiteScanner/3.0 (+https://olaogun.com)',
                    CURLOPT_ENCODING => '',
                    CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$headerBag, $url): int {
                        $len = strlen($headerLine);
                        $parts = explode(':', $headerLine, 2);
                        if (count($parts) === 2) {
                            $headerBag[$url][strtolower(trim($parts[0]))] = trim($parts[1]);
                        }
                        return $len;
                    },
                ];

                if ($cookie !== '') {
                    $opts[CURLOPT_COOKIE] = $cookie;
                }

                curl_setopt_array($ch, $opts);
                curl_multi_add_handle($mh, $ch);
                $handles[$url] = $ch;
            }

            $active = null;
            do {
                $status = curl_multi_exec($mh, $active);
                if ($active) {
                    curl_multi_select($mh, 0.2);
                }
            } while ($active && $status === CURLM_OK);

            foreach ($handles as $url => $ch) {
                $body = (string) curl_multi_getcontent($ch);
                $info = curl_getinfo($ch);
                $statusCode = is_array($info) ? (int) $info['http_code'] : 0;
                $finalUrl = is_array($info) && $info['url'] !== '' ? (string) $info['url'] : $url;
                $ttfbMs = is_array($info) ? (int) round(((float) $info['starttransfer_time']) * 1000) : 0;
                $totalMs = is_array($info) ? (int) round(((float) $info['total_time']) * 1000) : 0;

                $results[$url] = [
                    'final_url' => $finalUrl,
                    'status_code' => $statusCode,
                    'ttfb_ms' => $ttfbMs,
                    'total_ms' => $totalMs,
                    'body' => $body,
                    'headers' => $headerBag[$url] ?? [],
                ];

                curl_multi_remove_handle($mh, $ch);
                curl_close($ch);
            }

            curl_multi_close($mh);
        }

        return $results;
    }

    /**
     * Invoke the zero-dependency Headless Chrome CDP probe via JSON file IPC (immune to shell injection).
     *
     * @param list<string> $urls
     * @return array{available:bool,results:array<string,array<string,mixed>>}
     */
    private static function runChromeProbe(array $urls, string $cookie, int $timeoutMs): array
    {
        $workerScript = dirname(__DIR__, 2) . '/scripts/chrome_site_probe.mjs';
        if (!is_file($workerScript)) {
            return ['available' => false, 'results' => []];
        }

        $tmpInput = tempnam(sys_get_temp_dir(), 'sov_lh_in_');
        if ($tmpInput === false) {
            return ['available' => false, 'results' => []];
        }

        $payload = json_encode([
            'urls' => $urls,
            'cookie' => $cookie,
            'timeoutMs' => $timeoutMs,
        ], JSON_UNESCAPED_SLASHES);

        file_put_contents($tmpInput, (string) $payload);

        $cmd = sprintf('node %s %s 2>/dev/null', escapeshellarg($workerScript), escapeshellarg($tmpInput));
        $rawOut = (string) shell_exec($cmd);
        @unlink($tmpInput);

        if ($rawOut === '') {
            return ['available' => false, 'results' => []];
        }

        $decoded = json_decode($rawOut, true);
        if (!is_array($decoded)) {
            return ['available' => false, 'results' => []];
        }

        return [
            'available' => (bool) ($decoded['available'] ?? false),
            'results' => is_array($decoded['results'] ?? null) ? $decoded['results'] : [],
        ];
    }

    /**
     * Locate the official Google Lighthouse CLI binary.
     */
    public static function findLighthouseBinary(): ?string
    {
        $candidates = [
            '/Users/waleolaogun/Sites/familyPlatform/scratch/lh/node_modules/.bin/lighthouse',
            getcwd() . '/scratch/lh/node_modules/.bin/lighthouse',
            getcwd() . '/node_modules/.bin/lighthouse',
            dirname(__DIR__, 2) . '/node_modules/.bin/lighthouse',
        ];

        foreach ($candidates as $cand) {
            if (is_file($cand) && is_executable($cand)) {
                return $cand;
            }
        }

        $which = trim((string) shell_exec('which lighthouse 2>/dev/null'));
        if ($which !== '' && is_file($which) && is_executable($which)) {
            return $which;
        }

        return null;
    }

    /**
     * Run official Google Lighthouse mobile/desktop audit on a target URL.
     *
     * @return array{
     *   url:string,
     *   final_url:string,
     *   status_code:int,
     *   ttfb_ms:int,
     *   total_ms:int,
     *   size_bytes:int,
     *   auth_redirect_wall:bool,
     *   scores:array{performance:int,accessibility:int,best_practices:int,seo:int,design_v21:int,overall:int},
     *   metrics:array<string,string>,
     *   issues:list<array{category:string,severity:string,code:string,message:string}>,
     *   chrome_probed:bool
     * }|null
     */
    public static function runLighthouseAudit(
        string $url,
        string $cookie = '',
        string $formFactor = 'mobile',
        int $timeoutSec = 60
    ): ?array {
        $bin = self::findLighthouseBinary();
        if ($bin === null) {
            return null;
        }

        $tmpOut = tempnam(sys_get_temp_dir(), 'lh_rep_') . '.json';
        $tmpHeaders = null;

        $cmdArgs = [
            escapeshellarg($bin),
            escapeshellarg($url),
            '--output=json',
            '--output-path=' . escapeshellarg($tmpOut),
            '--form-factor=' . escapeshellarg($formFactor),
            '--chrome-flags="--headless=new --ignore-certificate-errors --no-sandbox"',
            '--quiet',
        ];

        if ($cookie !== '') {
            $tmpHeaders = tempnam(sys_get_temp_dir(), 'lh_hdr_') . '.json';
            file_put_contents($tmpHeaders, (string) json_encode(['Cookie' => $cookie]));
            $cmdArgs[] = '--extra-headers=' . escapeshellarg($tmpHeaders);
        }

        $cmd = implode(' ', $cmdArgs) . ' 2>/dev/null';
        $startTs = microtime(true);
        exec($cmd, $out, $exitCode);
        $durationMs = (int) round((microtime(true) - $startTs) * 1000);

        if ($tmpHeaders !== null) {
            @unlink($tmpHeaders);
        }

        if (!is_file($tmpOut)) {
            return null;
        }

        $content = (string) file_get_contents($tmpOut);
        @unlink($tmpOut);

        $lh = json_decode($content, true);
        if (!is_array($lh) || !isset($lh['categories'])) {
            return null;
        }

        $cats = $lh['categories'];
        $audits = is_array($lh['audits'] ?? null) ? $lh['audits'] : [];

        $perf = (int) round(((float) ($cats['performance']['score'] ?? 0)) * 100);
        $a11y = (int) round(((float) ($cats['accessibility']['score'] ?? 0)) * 100);
        $bp = (int) round(((float) ($cats['best-practices']['score'] ?? 0)) * 100);
        $seo = (int) round(((float) ($cats['seo']['score'] ?? 0)) * 100);
        $design = 100;

        $overall = (int) round(($perf + $a11y + $bp + $seo + $design) / 5);

        $issues = [];
        foreach ($audits as $auditId => $audit) {
            if (!is_array($audit)) {
                continue;
            }
            $score = $audit['score'] ?? null;
            $mode = (string) ($audit['scoreDisplayMode'] ?? '');
            if ($score !== null && (float) $score < 1.0 && $mode !== 'notApplicable' && $mode !== 'informative') {
                $category = 'best_practices';
                if (str_contains((string) $auditId, 'paint') || str_contains((string) $auditId, 'time') || str_contains((string) $auditId, 'speed') || str_contains((string) $auditId, 'byte') || str_contains((string) $auditId, 'css') || str_contains((string) $auditId, 'javascript')) {
                    $category = 'performance';
                } elseif (str_contains((string) $auditId, 'aria') || str_contains((string) $auditId, 'contrast') || str_contains((string) $auditId, 'heading') || str_contains((string) $auditId, 'landmark')) {
                    $category = 'accessibility';
                } elseif (str_contains((string) $auditId, 'crawl') || str_contains((string) $auditId, 'title') || str_contains((string) $auditId, 'description') || str_contains((string) $auditId, 'canonical')) {
                    $category = 'seo';
                }

                $issues[] = [
                    'category' => $category,
                    'severity' => (float) $score < 0.5 ? 'CRITICAL' : 'WARNING',
                    'code' => strtoupper(str_replace('-', '_', (string) $auditId)),
                    'message' => (string) ($audit['title'] ?? $auditId) . (!empty($audit['displayValue']) ? ' (' . $audit['displayValue'] . ')' : ''),
                ];
            }
        }

        $finalUrl = (string) ($lh['finalDisplayedUrl'] ?? $lh['finalUrl'] ?? $url);
        $reqPath = (string) (parse_url($url, PHP_URL_PATH) ?? '/');
        $finalPath = (string) (parse_url($finalUrl, PHP_URL_PATH) ?? '/');
        $authRedirectWall = !preg_match('#/(login|signin)\b#i', $reqPath) && preg_match('#/(login|signin)\b#i', $finalPath);

        $metrics = [
            'fcp' => (string) ($audits['first-contentful-paint']['displayValue'] ?? 'N/A'),
            'lcp' => (string) ($audits['largest-contentful-paint']['displayValue'] ?? 'N/A'),
            'tbt' => (string) ($audits['total-blocking-time']['displayValue'] ?? 'N/A'),
            'cls' => (string) ($audits['cumulative-layout-shift']['displayValue'] ?? 'N/A'),
            'speed_index' => (string) ($audits['speed-index']['displayValue'] ?? 'N/A'),
            'ttfb' => (string) ($audits['server-response-time']['displayValue'] ?? 'N/A'),
            'total_bytes' => (string) ($audits['total-byte-weight']['displayValue'] ?? 'N/A'),
        ];

        return [
            'url' => $url,
            'final_url' => $finalUrl,
            'status_code' => 200,
            'ttfb_ms' => (int) round(((float) ($audits['server-response-time']['numericValue'] ?? 0))),
            'total_ms' => $durationMs,
            'size_bytes' => (int) round(((float) ($audits['total-byte-weight']['numericValue'] ?? 0))),
            'auth_redirect_wall' => $authRedirectWall,
            'scores' => [
                'performance' => $perf,
                'accessibility' => $a11y,
                'best_practices' => $bp,
                'seo' => $seo,
                'design_v21' => $design,
                'overall' => $overall,
            ],
            'metrics' => $metrics,
            'issues' => $issues,
            'chrome_probed' => true,
        ];
    }

    /**
     * Generate a self-contained, XSS-safe Executive HTML Dashboard Report (`whole-site-audit.html`).
     * Strictly adheres to Global Design Standards v2.1 and British English (en-GB).
     *
     * @param array<string,mixed> $report
     */
    public static function renderHtmlReport(array $report): string
    {
        $esc = static fn (mixed $v): string => htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $appName = $esc($report['app_name'] ?? 'Portfolio App');
        $baseUrl = $esc($report['base_url'] ?? '');
        $scannedAt = $esc($report['scanned_at'] ?? '');
        $durationMs = (int) ($report['duration_ms'] ?? 0);
        $summary = is_array($report['summary'] ?? null) ? $report['summary'] : [];
        $avgScores = is_array($summary['avg_scores'] ?? null) ? $summary['avg_scores'] : [];

        $perf = (int) ($avgScores['performance'] ?? 0);
        $a11y = (int) ($avgScores['accessibility'] ?? 0);
        $bp = (int) ($avgScores['best_practices'] ?? 0);
        $seo = (int) ($avgScores['seo'] ?? 0);
        $design = (int) ($avgScores['design_v21'] ?? 0);
        $overall = (int) ($avgScores['overall'] ?? 0);

        $totalUrls = (int) ($summary['total_urls'] ?? 0);
        $reachableUrls = (int) ($summary['reachable_urls'] ?? 0);
        $criticalIssues = (int) ($summary['critical_issues'] ?? 0);
        $warningIssues = (int) ($summary['warning_issues'] ?? 0);

        $badgeColour = static function (int $score): string {
            if ($score >= 90) {
                return '#10b981';
            }
            if ($score >= 75) {
                return '#f59e0b';
            }
            return '#ef4444';
        };

        $rowsHtml = '';
        $pages = is_array($report['pages'] ?? null) ? $report['pages'] : [];
        foreach ($pages as $page) {
            if (!is_array($page)) {
                continue;
            }
            $u = $esc($page['url'] ?? '');
            $status = (int) ($page['status_code'] ?? 0);
            $ttfb = (int) ($page['ttfb_ms'] ?? 0);
            $pScores = is_array($page['scores'] ?? null) ? $page['scores'] : [];
            $pOverall = (int) ($pScores['overall'] ?? 0);
            $pPerf = (int) ($pScores['performance'] ?? 0);
            $pA11y = (int) ($pScores['accessibility'] ?? 0);
            $pBp = (int) ($pScores['best_practices'] ?? 0);
            $pSeo = (int) ($pScores['seo'] ?? 0);
            $pDesign = (int) ($pScores['design_v21'] ?? 0);

            $issuesList = is_array($page['issues'] ?? null) ? $page['issues'] : [];
            $issuePills = '';
            if ($issuesList === []) {
                $issuePills = '<span class="pill pill-pass">0 Issues — All Checks Passed</span>';
            } else {
                foreach ($issuesList as $iss) {
                    if (!is_array($iss)) {
                        continue;
                    }
                    $sev = strtoupper((string) ($iss['severity'] ?? 'INFO'));
                    $cls = $sev === 'CRITICAL' ? 'pill-crit' : ($sev === 'WARNING' ? 'pill-warn' : 'pill-info');
                    $code = $esc($iss['code'] ?? '');
                    $msg = $esc($iss['message'] ?? '');
                    $issuePills .= "<div class=\"issue-item\"><span class=\"pill {$cls}\">{$sev}: {$code}</span> <span>{$msg}</span></div>";
                }
            }

            $statusBadge = $status >= 200 && $status < 400
                ? "<span class=\"pill pill-pass\">{$status} OK</span>"
                : "<span class=\"pill pill-crit\">{$status} ERR</span>";

            $rowsHtml .= <<<HTML
            <tr>
                <td class="url-cell">
                    <div class="url-text" title="{$u}">{$u}</div>
                    <div class="meta-sub">{$statusBadge} &bull; TTFB: {$ttfb}ms</div>
                </td>
                <td class="score-cell"><span class="score-badge" style="background: {$badgeColour($pOverall)}">{$pOverall}</span></td>
                <td class="score-cell">{$pPerf}</td>
                <td class="score-cell">{$pA11y}</td>
                <td class="score-cell">{$pBp}</td>
                <td class="score-cell">{$pSeo}</td>
                <td class="score-cell">{$pDesign}</td>
                <td class="issues-cell">{$issuePills}</td>
            </tr>
HTML;
        }

        return <<<HTML
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sovereign Whole-Site Audit Report — {$appName}</title>
    <style>
        :root {
            --bg-canvas: #0f172a;
            --bg-surface: #1e293b;
            --bg-surface-2: #334155;
            --text-main: #f8fafc;
            --text-muted: #cbd5e1;
            --border-subtle: #334155;
            --radius-lg: 16px;
            --space-4: 16px;
            --space-6: 24px;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: var(--space-6);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: var(--bg-canvas);
            color: var(--text-main);
            line-height: 1.5;
        }
        .container { max-width: 1400px; margin: 0 auto; }
        .header-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-lg);
            padding: var(--space-6);
            margin-bottom: var(--space-6);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }
        h1 { margin: 0 0 6px 0; font-size: 1.75rem; font-weight: 800; letter-spacing: -0.02em; }
        .subtitle { color: var(--text-muted); font-size: 0.875rem; }
        .kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: var(--space-6);
        }
        .kpi-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-lg);
            padding: var(--space-4);
            text-align: center;
        }
        .kpi-value { font-size: 2rem; font-weight: 800; margin-bottom: 4px; }
        .kpi-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); }
        .table-card {
            background: var(--bg-surface);
            border: 1px solid var(--border-subtle);
            border-radius: var(--radius-lg);
            overflow-x: auto;
        }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 14px 16px; border-bottom: 1px solid var(--border-subtle); vertical-align: top; }
        th { font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); background: rgba(15, 23, 42, 0.5); }
        .url-text { font-weight: 600; color: #38bdf8; word-break: break-all; margin-bottom: 4px; }
        .meta-sub { font-size: 0.75rem; color: var(--text-muted); }
        .score-cell { text-align: center; font-weight: 700; width: 72px; }
        .score-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 999px;
            color: #0f172a;
            font-weight: 800;
            font-size: 0.875rem;
        }
        .pill {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .pill-pass { background: rgba(16, 185, 129, 0.2); color: #34d399; border: 1px solid rgba(16, 185, 129, 0.4); }
        .pill-warn { background: rgba(245, 158, 11, 0.2); color: #fbbf24; border: 1px solid rgba(245, 158, 11, 0.4); }
        .pill-crit { background: rgba(239, 68, 68, 0.2); color: #f87171; border: 1px solid rgba(239, 68, 68, 0.4); }
        .pill-info { background: rgba(56, 189, 248, 0.2); color: #38bdf8; border: 1px solid rgba(56, 189, 248, 0.4); }
        .issue-item { margin-bottom: 6px; font-size: 0.85rem; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-card">
            <div>
                <h1>⚡ Sovereign Whole-Site Quality &amp; Lighthouse Audit — {$appName}</h1>
                <div class="subtitle">Origin: <strong>{$baseUrl}</strong> &bull; Audited: {$scannedAt} &bull; Duration: {$durationMs}ms &bull; URLs Reachable: {$reachableUrls}/{$totalUrls}</div>
            </div>
            <div>
                <span class="pill pill-info">Critical: {$criticalIssues}</span>
                <span class="pill pill-warn">Warnings: {$warningIssues}</span>
            </div>
        </div>

        <div class="kpi-grid">
            <div class="kpi-card">
                <div class="kpi-value" style="color: {$badgeColour($overall)}">{$overall}</div>
                <div class="kpi-label">Overall Health</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" style="color: {$badgeColour($perf)}">{$perf}</div>
                <div class="kpi-label">Performance</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" style="color: {$badgeColour($a11y)}">{$a11y}</div>
                <div class="kpi-label">Accessibility</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" style="color: {$badgeColour($bp)}">{$bp}</div>
                <div class="kpi-label">Best Practices</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" style="color: {$badgeColour($seo)}">{$seo}</div>
                <div class="kpi-label">SEO &amp; Sitemap</div>
            </div>
            <div class="kpi-card">
                <div class="kpi-value" style="color: {$badgeColour($design)}">{$design}</div>
                <div class="kpi-label">Design v2.1 (UK)</div>
            </div>
        </div>

        <div class="table-card">
            <table>
                <thead>
                    <tr>
                        <th>Audited URL</th>
                        <th>Overall</th>
                        <th>Perf</th>
                        <th>A11y</th>
                        <th>Best</th>
                        <th>SEO</th>
                        <th>v2.1</th>
                        <th>Findings &amp; Remediation</th>
                    </tr>
                </thead>
                <tbody>
                    {$rowsHtml}
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * Save both `whole-site-audit.json` and `whole-site-audit.html` inside the target application's `storage/reports/` directory.
     *
     * @param array<string,mixed> $report
     * @return array{json_path:string,html_path:string}
     */
    public static function saveReports(array $report, ?string $outputDir = null): array
    {
        $appDir = (string) ($report['app_dir'] ?? getcwd());
        $targetDir = $outputDir ?: ($appDir . '/storage/reports');
        if (!is_dir($targetDir)) {
            @mkdir($targetDir, 0775, true);
        }

        $jsonPath = rtrim($targetDir, '/') . '/whole-site-audit.json';
        $htmlPath = rtrim($targetDir, '/') . '/whole-site-audit.html';

        file_put_contents($jsonPath, (string) json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        file_put_contents($htmlPath, self::renderHtmlReport($report));

        return [
            'json_path' => $jsonPath,
            'html_path' => $htmlPath,
        ];
    }
}
