<?php

declare(strict_types=1);

namespace Tests\WorldEngine;

use PHPUnit\Framework\TestCase;
use Src\Explainer\PortfolioExplainerRegistry;
use Src\WorldEngine\StudioBlueprints;
use Src\WorldEngine\WorldStudio;

final class WorldEngineStudioTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function apps(): array
    {
        $out = [];
        foreach (StudioBlueprints::appKeys() as $k) {
            $out[$k] = [$k];
        }
        return $out;
    }

    /** @dataProvider apps */
    public function testEveryAppHasACompleteStoryArcAndPosterSamples(string $app): void
    {
        $cfg = StudioBlueprints::forApp($app);
        $this->assertNotEmpty($cfg['brand']['name']);
        foreach (['a', 'b', 'c', 'd'] as $c) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/i', $cfg['brand'][$c]);
        }
        $this->assertNotEmpty($cfg['explainers']);
        foreach ($cfg['explainers'] as $ex) {
            $stages = array_column($ex['scenes'], 'stage');
            $this->assertContains('problem', $stages, 'Problem stage missing');
            $this->assertContains('solution', $stages, 'Solution stage missing');
            $this->assertSame('cta', end($stages), 'Explainers must end on a call to action');
            $ids = array_column($ex['scenes'], 'id');
            $this->assertSame(count($ids), count(array_unique($ids)), 'Scene ids must be unique');
            foreach ($ex['scenes'] as $s) {
                $this->assertNotSame('', trim($s['narration']));
                $this->assertDoesNotMatchRegularExpression('/\[(?!pause\]|sfx:\w+\]|voice:\w+\])[^\]]*\]/', $s['narration'], 'Unknown performance tag');
            }
        }
        $this->assertNotEmpty($cfg['posters']);
    }

    /** @dataProvider apps */
    public function testCopyIsBritishEnglishAndCompliant(string $app): void
    {
        $json = (string) json_encode(StudioBlueprints::forApp($app));
        foreach (['organize', 'color', 'favorite', 'optimize', 'center ', 'behavior'] as $us) {
            $this->assertStringNotContainsStringIgnoringCase($us, $json, "US spelling '$us' in $app");
        }
        foreach (['guarantee', 'instant approval', 'zero penalties', '100% approval'] as $risky) {
            $this->assertStringNotContainsStringIgnoringCase($risky, $json, "Risky claim '$risky' in $app");
        }
        if ($app === 'execmindapp') {
            $this->assertDoesNotMatchRegularExpression('/\bAI\b|artificial intelligence|multi-agent/i', $json);
        }
    }

    public function testUnknownAppFallsBackSafely(): void
    {
        $this->assertSame('partyplatform', StudioBlueprints::normaliseAppKey('<script>'));
        $this->assertSame('tenantscore', StudioBlueprints::normaliseAppKey('scoretenant'));
        $this->assertSame('bcp', StudioBlueprints::normaliseAppKey('BestCareProfessionals'));
    }

    public function testSectionInlinesEngineWithNonceAndNoScriptBreakout(): void
    {
        $GLOBALS['__cspNonce'] = 'abc123XYZnonce';
        try {
            $html = WorldStudio::renderSection('familyplatform');
        } finally {
            unset($GLOBALS['__cspNonce']);
        }
        $this->assertStringContainsString('<script nonce="abc123XYZnonce">', $html);
        $this->assertStringContainsString('<style nonce="abc123XYZnonce">', $html);
        $this->assertStringContainsString('WorldEngine.Studio.mount', $html);
        $this->assertStringContainsString('FamilyPlatform', $html);
        // exactly one closing script tag: the bundle can never terminate the inline script early
        $this->assertSame(1, substr_count(strtolower($html), '</script>'));
    }

    public function testMaliciousNonceAndAiEndpointAreIgnored(): void
    {
        $GLOBALS['__cspNonce'] = '"><script>alert(1)</script>';
        try {
            $html = WorldStudio::renderSection('party');
        } finally {
            unset($GLOBALS['__cspNonce']);
        }
        $this->assertStringNotContainsString('alert(1)', $html);
        $cfg = WorldStudio::clientConfig('party', ['aiEndpoint' => 'https://evil.example/collect']);
        $this->assertNull($cfg['aiEndpoint'], 'Only same-origin relative endpoints are allowed');
    }

    public function testLegacyExplainerDelegatesToWorldEngine(): void
    {
        $html = PortfolioExplainerRegistry::renderAdminStudioSection('loaneasyfinance');
        $this->assertStringContainsString('we-studio-section', $html);
        $this->assertStringContainsString('LoanEasy', $html);
    }

    public function testFontServingRejectsTraversal(): void
    {
        ob_start();
        (new WorldStudio())->serveFont('../../../etc/passwd');
        $this->assertSame('Not found', ob_get_clean());
        foreach (array_keys(['caveat' => 1, 'anton' => 1, 'interbold' => 1]) as $f) {
            $this->assertFileExists(WorldStudio::resourceDir() . "/fonts/we-font-$f.woff2");
        }
    }
}
