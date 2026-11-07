<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\Quality\WholeSiteScanner;

final class WholeSiteScannerTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/wss_' . bin2hex(random_bytes(4));
        mkdir($this->tmpDir . '/public', 0777, true);
    }

    protected function tearDown(): void
    {
        if (!is_dir($this->tmpDir)) {
            return;
        }
        $it = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->tmpDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($it as $f) {
            $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        }
        @rmdir($this->tmpDir);
    }

    public function testAdversarialSsrfAndLfiPayloadsAreNeutralized(): void
    {
        // PoC 1: LFI via file:// scheme
        $lfi = WholeSiteScanner::validateTargetUrl('file:///etc/passwd');
        $this->assertFalse($lfi['valid']);
        $this->assertStringContainsString("Forbidden URL scheme 'file://'", (string) $lfi['reason']);

        // PoC 2: AWS/Cloud Metadata SSRF
        $ssrf = WholeSiteScanner::validateTargetUrl('http://169.254.169.254/latest/meta-data/');
        $this->assertFalse($ssrf['valid']);
        $this->assertStringContainsString('Blocked cloud metadata', (string) $ssrf['reason']);

        // PoC 3: GCP Metadata SSRF
        $gcp = WholeSiteScanner::validateTargetUrl('http://metadata.google.internal/computeMetadata/v1/');
        $this->assertFalse($gcp['valid']);

        // PoC 4: CRLF header injection in URL
        $crlf = WholeSiteScanner::validateTargetUrl("https://olaogun.test/\r\nX-Injected: true");
        $this->assertFalse($crlf['valid']);

        // Valid local .test URL passes
        $valid = WholeSiteScanner::validateTargetUrl('https://olaogun.test/playbook');
        $this->assertTrue($valid['valid']);
    }

    public function testSitemapOriginRewritingAndMaliciousLocRejection(): void
    {
        file_put_contents($this->tmpDir . '/.env', "APP_URL=https://olaogun.test/\n");
        $sitemapXml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url><loc>https://family.olaogun.com/</loc></url>
    <url><loc>https://family.olaogun.com/tools/kinship-calculator</loc></url>
    <url><loc>file:///etc/passwd</loc></url>
    <url><loc>http://169.254.169.254/latest/meta-data/</loc></url>
</urlset>
XML;
        file_put_contents($this->tmpDir . '/public/sitemap.xml', $sitemapXml);

        $discovered = WholeSiteScanner::discoverUrls($this->tmpDir, null, true, ['/custom-page'], true);

        $this->assertContains('https://olaogun.test/', $discovered['urls']);
        $this->assertContains('https://olaogun.test/tools/kinship-calculator', $discovered['urls']);
        $this->assertContains('https://olaogun.test/custom-page', $discovered['urls']);
        $this->assertContains('https://olaogun.test/home', $discovered['urls']);
        $this->assertCount(2, $discovered['rejected']);
    }

    public function testCompliantPageScores100AcrossAllFiveCategories(): void
    {
        $html = <<<HTML
<!DOCTYPE html>
<html lang="en-GB">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FamilyPlatform — Organise &amp; Honour Your Family Heritage</title>
    <meta name="description" content="Build your interactive family tree, preserve cherished memories, and celebrate milestones in British English.">
    <link rel="canonical" href="https://olaogun.test/playbook">
</head>
<body>
    <main id="main-content">
        <h1>FamilyPlatform Sovereign Playbook</h1>
        <img src="/logo.png" alt="FamilyPlatform Crest" width="120" height="40">
        <form method="POST" action="/save">
            <input type="hidden" name="_token" value="csrf_secret_123">
            <button type="submit">Save Favourite Memory</button>
        </form>
    </main>
</body>
</html>
HTML;

        $headers = [
            'x-content-type-options' => 'nosniff',
            'x-frame-options' => 'SAMEORIGIN',
        ];

        $eval = WholeSiteScanner::evaluatePage(
            'https://olaogun.test/playbook',
            'https://olaogun.test/playbook',
            200,
            120,
            180,
            $html,
            $headers
        );

        $this->assertSame(100, $eval['scores']['performance']);
        $this->assertSame(100, $eval['scores']['accessibility']);
        $this->assertSame(100, $eval['scores']['best_practices']);
        $this->assertSame(100, $eval['scores']['seo']);
        $this->assertSame(100, $eval['scores']['design_v21']);
        $this->assertSame(100, $eval['scores']['overall']);
        $this->assertSame([], $eval['issues']);
    }

    public function testDetectsAuthWallRedirectAndDesignViolations(): void
    {
        $badHtml = <<<HTML
<html>
<head><title>Short</title></head>
<body>
    <div class="bg-danger text-dark">Black text on red background</div>
    <div class="bg-white text-white">Ghost typography</div>
    <p>Please customize your favorite color and behavior.</p>
    <img src="/photo.jpg">
    <form method="POST" action="/login"><button type="submit"></button></form>
</body>
</html>
HTML;

        $eval = WholeSiteScanner::evaluatePage(
            'https://olaogun.test/home',
            'https://olaogun.test/login',
            200,
            1800,
            2100,
            $badHtml,
            []
        );

        $this->assertTrue($eval['auth_redirect_wall']);
        $codes = array_column($eval['issues'], 'code');
        $this->assertContains('AUTH_REDIRECT_WALL', $codes);
        $this->assertContains('RED_SURFACE_BLACK_TEXT', $codes);
        $this->assertContains('GHOST_TYPOGRAPHY', $codes);
        $this->assertContains('US_ENGLISH_COPY', $codes);
        $this->assertContains('FORM_MISSING_CSRF', $codes);
        $this->assertContains('IMG_MISSING_ALT', $codes);
        $this->assertContains('BUTTON_NAME_MISSING', $codes);
        $this->assertLessThan(60, $eval['scores']['overall']);
    }

    public function testHtmlReportEscapesStoredXssPayloads(): void
    {
        $fakeReport = [
            'app_name' => 'FamilyPlatform<script>alert(1)</script>',
            'app_dir' => $this->tmpDir,
            'base_url' => 'https://olaogun.test',
            'scanned_at' => '2026-11-05T14:30:00Z',
            'duration_ms' => 45,
            'summary' => [
                'total_urls' => 1,
                'reachable_urls' => 1,
                'critical_issues' => 0,
                'warning_issues' => 0,
                'avg_scores' => [
                    'performance' => 95,
                    'accessibility' => 95,
                    'best_practices' => 95,
                    'seo' => 95,
                    'design_v21' => 95,
                    'overall' => 95,
                ],
            ],
            'pages' => [[
                'url' => 'https://olaogun.test/?xss=<img src=x onerror=alert(1)>',
                'status_code' => 200,
                'ttfb_ms' => 50,
                'scores' => [
                    'overall' => 95,
                    'performance' => 95,
                    'accessibility' => 95,
                    'best_practices' => 95,
                    'seo' => 95,
                    'design_v21' => 95,
                ],
                'issues' => [],
            ]],
        ];

        $html = WholeSiteScanner::renderHtmlReport($fakeReport);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }
}
