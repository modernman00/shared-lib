<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\Quality\ProductionBundles;

final class ProductionBundlesTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/pb_' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/public/js', 0777, true);
        mkdir($this->dir . '/public/css', 0777, true);
        $this->sh('git init -q && git config user.email t@t && git config user.name t');
    }

    protected function tearDown(): void
    {
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST) as $f) {
            $f->isDir() ? rmdir($f->getPathname()) : unlink($f->getPathname());
        }
        rmdir($this->dir);
    }

    private function sh(string $cmd): void
    {
        exec('cd ' . escapeshellarg($this->dir) . ' && ' . $cmd . ' 2>&1');
    }

    private function put(string $rel, string $body): void
    {
        file_put_contents($this->dir . '/' . $rel, $body);
    }

    /** Lay out an app whose webpack.mix.js calls .version(), with a production-shaped build. */
    private function productionApp(): void
    {
        $this->put('webpack.mix.js', "mix.js('a','js'); if (mix.inProduction()) { mix.version(); }");
        $this->put('public/mix-manifest.json', json_encode(['/js/index.js' => '/js/index.js?id=abc', '/css/main.css' => '/css/main.css?id=def']));
        $this->put('public/js/index.js', "(()=>{console.log(1)})();\n");
        $this->put('public/css/main.css', "body{margin:0}\n");
    }

    private function commitAll(): void
    {
        $this->sh('git add -A && git commit -q -m x');
    }

    public function testProductionBuildPasses(): void
    {
        $this->productionApp();
        $this->commitAll();
        $r = ProductionBundles::scan($this->dir);
        $this->assertSame([], $r['problems']);
        $this->assertSame(2, $r['checked']);
        $this->assertSame(0, ProductionBundles::report($r, false, false)['exit']);
    }

    public function testEvalSourceMapBannerIsRefused(): void
    {
        $this->productionApp();
        $this->put('public/js/index.js', "\"use strict\";\n/*\n * ATTENTION: An \"eval-source-map\" devtool has been used.\n */\n");
        $this->commitAll();
        $r = ProductionBundles::scan($this->dir);
        $this->assertSame(['public/js/index.js is a dev build (eval-source-map)'], $r['problems']);
        $this->assertSame(1, ProductionBundles::report($r, false, false)['exit']);
    }

    public function testUnversionedManifestIsRefused(): void
    {
        $this->productionApp();
        $this->put('public/mix-manifest.json', json_encode(['/js/index.js' => '/js/index.js', '/css/main.css' => '/css/main.css']));
        $this->commitAll();
        $this->assertStringContainsString('unversioned entry (/js/index.js)', ProductionBundles::scan($this->dir)['problems'][0]);
    }

    public function testExpandedCssIsRefusedForVersionedApps(): void
    {
        $this->productionApp();
        $this->put('public/css/main.css', str_repeat("a {\n  color: red;\n}\n", 40));
        $this->commitAll();
        $this->assertStringContainsString('public/css/main.css is expanded', ProductionBundles::scan($this->dir)['problems'][0]);
    }

    public function testAppWithoutVersioningOnlyChecksTheJsBanner(): void
    {
        $this->productionApp();
        $this->put('webpack.mix.js', "mix.js('a','js');");
        $this->put('public/mix-manifest.json', json_encode(['/js/index.js' => '/js/index.js', '/css/main.css' => '/css/main.css']));
        $this->put('public/css/main.css', str_repeat("a {\n  color: red;\n}\n", 40));
        $this->commitAll();
        $this->assertSame([], ProductionBundles::scan($this->dir)['problems']);
    }

    public function testNoTrackedManifestIsSkippedNotFailed(): void
    {
        $this->put('README.md', 'x');
        $this->commitAll();
        $r = ProductionBundles::scan($this->dir);
        $this->assertSame([], $r['problems']);
        $this->assertNotNull($r['skipped']);
    }

    public function testCorruptManifestFailsClosed(): void
    {
        $this->productionApp();
        $this->put('public/mix-manifest.json', '{not json');
        $this->commitAll();
        $r = ProductionBundles::scan($this->dir);
        $this->assertSame(['public/mix-manifest.json is missing or not valid JSON'], $r['problems']);
    }

    public function testMissingDirectoryFailsClosed(): void
    {
        $this->assertNotSame([], ProductionBundles::scan($this->dir . '/nope')['problems']);
    }

    public function testStagedModeOnlyJudgesWhatIsStaged(): void
    {
        $this->productionApp();
        $this->commitAll();
        // A dev build sits in the working tree but is not staged: the commit is allowed.
        $this->put('public/js/index.js', "/* ATTENTION: An \"eval-source-map\" devtool has been used. */\n");
        $this->assertSame([], ProductionBundles::scan($this->dir, true)['problems']);
        // Staged: refused, even if the working tree is later fixed.
        $this->sh('git add public/js/index.js');
        $this->put('public/js/index.js', "(()=>{})();\n");
        $this->assertSame(['public/js/index.js is a dev build (eval-source-map)'], ProductionBundles::scan($this->dir, true)['problems']);
    }
}
