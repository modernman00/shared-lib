<?php

declare(strict_types=1);

namespace Tests\Unit\Media;

use PHPUnit\Framework\TestCase;
use Src\Media\UniversalPosterStudioController;
use Src\Media\UniversalPosterSynthesisService;

class UniversalPosterSynthesisServiceTest extends TestCase
{
    private string $tempOutputFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempOutputFile = sys_get_temp_dir() . '/test_poster_' . uniqid('', true) . '.png';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempOutputFile)) {
            unlink($this->tempOutputFile);
        }
        parent::tearDown();
    }

    public function testInitialisationWithDefaultStoryAspectRatio(): void
    {
        $service = UniversalPosterSynthesisService::create();

        $this->assertSame(1080, $service->getWidth());
        $this->assertSame(1920, $service->getHeight());
        $this->assertSame(UniversalPosterSynthesisService::THEME_PARTY_BLACK_GOLD, $service->getTheme());
    }

    public function testSupportsFeedAndCardAspectRatios(): void
    {
        $feed = UniversalPosterSynthesisService::create([
            'aspect_ratio' => UniversalPosterSynthesisService::RATIO_FEED,
        ]);
        $this->assertSame(1080, $feed->getWidth());
        $this->assertSame(1080, $feed->getHeight());

        $card = UniversalPosterSynthesisService::create([
            'aspect_ratio' => UniversalPosterSynthesisService::RATIO_CARD,
        ]);
        $this->assertSame(1200, $card->getWidth());
        $this->assertSame(630, $card->getHeight());
    }

    public function testEnforcesBritishEnglishInvariantsOnTextInput(): void
    {
        $service = UniversalPosterSynthesisService::create([
            'data' => [
                'title' => 'Optimize Your Event Colors and Customize Behavior',
                'subtitle' => 'Our favorite venue in city center',
            ],
        ]);

        $data = $service->getData();
        $this->assertStringContainsString('optimise', strtolower((string) $data['title']));
        $this->assertStringContainsString('colours', strtolower((string) $data['title']));
        $this->assertStringContainsString('customise', strtolower((string) $data['title']));
        $this->assertStringContainsString('behaviour', strtolower((string) $data['title']));
        $this->assertStringContainsString('favourite', strtolower((string) $data['subtitle']));
        $this->assertStringContainsString('centre', strtolower((string) $data['subtitle']));
    }

    public function testAutoBindsStatutoryDisclaimers(): void
    {
        // FinTech
        $fintech = UniversalPosterSynthesisService::create([
            'theme' => UniversalPosterSynthesisService::THEME_FINTECH_SME_CAPITAL,
        ]);
        $this->assertStringContainsString('Financial Conduct Authority', (string) $fintech->getData()['regulatory_notice']);

        // BCP Healthcare
        $bcp = UniversalPosterSynthesisService::create([
            'theme' => UniversalPosterSynthesisService::THEME_BCP_URGENT_SHIFT,
        ]);
        $this->assertStringContainsString('Care Quality Commission', (string) $bcp->getData()['regulatory_notice']);

        // ExecMind
        $exec = UniversalPosterSynthesisService::create([
            'theme' => UniversalPosterSynthesisService::THEME_EXEC_SAPPHIRE_SUMMIT,
        ]);
        $this->assertStringContainsString('Chatham House Rule', (string) $exec->getData()['regulatory_notice']);
    }

    public function testRendersValidPngImageAndSavesToFile(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension not loaded');
        }

        $service = UniversalPosterSynthesisService::create([
            'aspect_ratio' => UniversalPosterSynthesisService::RATIO_FEED,
            'theme' => UniversalPosterSynthesisService::THEME_PARTY_BLACK_GOLD,
            'data' => [
                'title' => "AARON'S BIRTHDAY",
                'subtitle' => 'All Black Luxury Affair',
                'date' => 'SAT MAY 07 2026',
                'venue' => 'Tape London',
                'accent_metric' => 'VIP GUEST',
            ],
        ]);

        $saved = $service->save($this->tempOutputFile);
        $this->assertTrue($saved);
        $this->assertFileExists($this->tempOutputFile);

        $imageInfo = getimagesize($this->tempOutputFile);
        $this->assertNotFalse($imageInfo);
        $this->assertSame(1080, $imageInfo[0]);
        $this->assertSame(1080, $imageInfo[1]);
        $this->assertSame(IMAGETYPE_PNG, $imageInfo[2]);

        // Test Data URI
        $dataUri = $service->toDataUri();
        $this->assertStringStartsWith('data:image/png;base64,', $dataUri);
    }

    public function testExposesAllSixArchetypesAndInfersFromTheme(): void
    {
        $archetypes = UniversalPosterSynthesisService::getArchetypes();
        $this->assertGreaterThanOrEqual(6, count($archetypes));
        $this->assertArrayHasKey(UniversalPosterSynthesisService::ARCHETYPE_POOL_TROPICAL_SPLIT, $archetypes);
        $this->assertArrayHasKey(UniversalPosterSynthesisService::ARCHETYPE_NEON_SCRIPT_CLASH, $archetypes);
        $this->assertArrayHasKey(UniversalPosterSynthesisService::ARCHETYPE_VIP_BLACK_GOLD, $archetypes);
        $this->assertArrayHasKey(UniversalPosterSynthesisService::ARCHETYPE_FLORAL_CHROME_OVERLAP, $archetypes);
        $this->assertArrayHasKey(UniversalPosterSynthesisService::ARCHETYPE_KINETIC_CLUB_TUNNEL, $archetypes);
        $this->assertArrayHasKey(UniversalPosterSynthesisService::ARCHETYPE_SECTOR_AUTHORITY_GRID, $archetypes);
        $this->assertArrayHasKey(UniversalPosterSynthesisService::ARCHETYPE_CLINICAL_SHIFT_SURGE, $archetypes);
        $this->assertArrayHasKey(UniversalPosterSynthesisService::ARCHETYPE_FINTECH_FACILITY, $archetypes);

        // Test sector filtering
        $bcpArchetypes = UniversalPosterSynthesisService::getArchetypes('bcp');
        $this->assertArrayHasKey(UniversalPosterSynthesisService::ARCHETYPE_CLINICAL_SHIFT_SURGE, $bcpArchetypes);
        $this->assertArrayNotHasKey(UniversalPosterSynthesisService::ARCHETYPE_POOL_TROPICAL_SPLIT, $bcpArchetypes);

        $floral = UniversalPosterSynthesisService::create([
            'theme' => UniversalPosterSynthesisService::THEME_PARTY_FLORAL_CHROME,
        ]);
        $this->assertSame(UniversalPosterSynthesisService::ARCHETYPE_FLORAL_CHROME_OVERLAP, $floral->getArchetype());
    }

    public function testStudioControllerProvidesAllEightPortfolioAppsAndRendersHtmlAndApiJson(): void
    {
        $catalog = UniversalPosterStudioController::getAppCatalog();
        $expectedApps = [
            'partyplatform',
            'familyplatform',
            'execmindapp',
            'bcp',
            'loaneasyfinance',
            'iaccountapp',
            'idecideapp',
            'scoretenant',
        ];

        foreach ($expectedApps as $appKey) {
            $this->assertArrayHasKey($appKey, $catalog);
            $this->assertNotEmpty($catalog[$appKey]['presets']);
        }

        $controller = new UniversalPosterStudioController();
        $html = $controller->renderStudioHtml('bcp');
        $this->assertStringContainsString('ATG Sovereign Poster &amp; Flyer Studio', $html);
        $this->assertStringContainsString('Best Care Professionals', $html);
        $this->assertStringContainsString('AtgUniversalPosterEngine', $html);

        ob_start();
        $controller->renderApiPoster([
            'app' => 'partyplatform',
            'preset' => 'floral_alisa_birthday',
            'title' => '<script>alert(1)</script>ALISA BIRTHDAY',
        ]);
        $jsonRaw = (string) ob_get_clean();
        $decoded = json_decode($jsonRaw, true);

        $this->assertIsArray($decoded);
        $this->assertSame('success', $decoded['status']);
        $this->assertSame('partyplatform', $decoded['app']);
        $this->assertSame('floral_chrome_overlap', $decoded['archetype']);
        $this->assertStringNotContainsString('<script>', (string) $decoded['data']['title']);
        $this->assertStringStartsWith('data:image/png;base64,', (string) $decoded['data_uri']);
    }
}
