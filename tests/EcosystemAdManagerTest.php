<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\EcosystemAdManager;
use Src\EcosystemPromoService;

class EcosystemAdManagerTest extends TestCase
{
    private string $tempStorage;

    protected function setUp(): void
    {
        parent::setUp();
        EcosystemAdManager::clearMemoryCache();
        $this->tempStorage = sys_get_temp_dir() . '/test_ecosystem_adverts_' . uniqid() . '.json';
        $_ENV['ECOSYSTEM_ADVERTS_PATH'] = $this->tempStorage;
    }

    protected function tearDown(): void
    {
        EcosystemAdManager::clearMemoryCache();
        if (file_exists($this->tempStorage)) {
            @unlink($this->tempStorage);
        }
        unset($_ENV['ECOSYSTEM_ADVERTS_PATH']);
        parent::tearDown();
    }

    public function testFallbackToSisterAppsWhenNoCustomAdverts(): void
    {
        $promoService = new EcosystemPromoService();
        $promo = $promoService->getPromoForContext('partyplatform', 'dashboard');

        $this->assertNotNull($promo);
        $this->assertEquals('ecosystem', $promo['type']);
        $this->assertNotEquals('partyplatform', $promo['target_app']);
    }

    public function testSaveAndRetrieveCustomAdvert(): void
    {
        $saveResult = EcosystemAdManager::saveAdvert([
            'headline' => 'Black Friday 50% Off SaaS',
            'brand_name' => 'MegaTool',
            'tagline' => 'Productivity Unleashed',
            'description' => 'Double your output with our newest tool.',
            'target_url' => 'https://megatool.example.com/blackfriday',
            'cta_text' => 'Get Deal Now',
            'badge' => 'LIMITED OFFER',
            'accent_color' => '#f59e0b',
            'active' => 1,
            'target_apps' => ['all'],
            'mode' => 'exclusive',
        ]);

        $this->assertTrue($saveResult['success']);
        $this->assertNotNull($saveResult['id']);

        // Check via EcosystemPromoService
        $promoService = new EcosystemPromoService();
        $promo = $promoService->getPromoForContext('partyplatform', 'dashboard');

        $this->assertNotNull($promo);
        $this->assertEquals('custom_campaign', $promo['type']);
        $this->assertEquals('Black Friday 50% Off SaaS', $promo['title']);
        $this->assertEquals('LIMITED OFFER', $promo['badge']);
        $this->assertEquals('#f59e0b', $promo['accent_color']);
        $this->assertStringContainsString('utm_source=partyplatform', $promo['target_url']);
    }

    public function testAdvertTargetingSpecificAppOnly(): void
    {
        EcosystemAdManager::saveAdvert([
            'headline' => 'Exclusive for Family Platform Only',
            'target_url' => 'https://familyexclusive.com',
            'active' => 1,
            'target_apps' => ['familyplatform'],
            'mode' => 'exclusive',
        ]);

        $promoService = new EcosystemPromoService();

        // Should NOT show on PartyPlatform (falls back to sister apps)
        $partyPromo = $promoService->getPromoForContext('partyplatform', 'dashboard');
        $this->assertEquals('ecosystem', $partyPromo['type']);

        // MUST show on FamilyPlatform
        $familyPromo = $promoService->getPromoForContext('familyplatform', 'dashboard');
        $this->assertEquals('custom_campaign', $familyPromo['type']);
        $this->assertEquals('Exclusive for Family Platform Only', $familyPromo['title']);
    }

    public function testToggleAdvertActiveState(): void
    {
        $result = EcosystemAdManager::saveAdvert([
            'headline' => 'Toggleable Campaign',
            'target_url' => 'https://example.com/test',
            'active' => 1,
            'target_apps' => ['all'],
            'mode' => 'exclusive',
        ]);

        $adId = (string) $result['id'];
        $promoService = new EcosystemPromoService();

        // 1. Initially active
        $promo = $promoService->getPromoForContext('loaneasyfinance', 'dashboard');
        $this->assertEquals('custom_campaign', $promo['type']);

        // 2. Pause the advert
        $toggled = EcosystemAdManager::toggleAdvert($adId, false);
        $this->assertTrue($toggled);

        // 3. Now falls back to sister apps
        $promoAfterPause = $promoService->getPromoForContext('loaneasyfinance', 'dashboard');
        $this->assertEquals('ecosystem', $promoAfterPause['type']);
    }

    public function testDeleteAdvert(): void
    {
        $result = EcosystemAdManager::saveAdvert([
            'headline' => 'To Be Deleted',
            'target_url' => 'https://example.com/del',
            'active' => 1,
        ]);

        $adId = (string) $result['id'];
        $this->assertTrue(EcosystemAdManager::deleteAdvert($adId));

        $all = EcosystemAdManager::getAllAdverts();
        $this->assertArrayNotHasKey($adId, $all);
    }

    public function testInvalidUrlIsRejected(): void
    {
        $result = EcosystemAdManager::saveAdvert([
            'headline' => 'Bad URL Test',
            'target_url' => 'javascript:alert(1)',
            'active' => 1,
        ]);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('valid destination URL', (string) $result['error']);
    }

    public function testBrandColorsAndVectorIconsForRefreshedApps(): void
    {
        $catalog = EcosystemPromoService::getCatalog();

        // 1. FamilyPlatform must use unified Crimson #a31621 and crimson gradient
        $this->assertArrayHasKey('familyplatform', $catalog);
        $family = $catalog['familyplatform'];
        $this->assertEquals('#a31621', $family['accent_color']);
        $this->assertStringContainsString('#b8202c', $family['gradient']);
        $this->assertStringContainsString('pp-brand-logo-svg', $family['svg_icon']);
        $this->assertStringContainsString('#b8202c', $family['svg_icon']);

        // 2. Best Care Professionals (BCP) must be present with healthcare assets
        $this->assertArrayHasKey('bcp', $catalog);
        $bcp = $catalog['bcp'];
        $this->assertEquals('#006699', $bcp['accent_color']);
        $this->assertEquals('CQC REGULATED', $bcp['badge']);
        $this->assertStringContainsString('pp-brand-logo-svg', $bcp['svg_icon']);
        $this->assertStringContainsString('#006699', $bcp['svg_icon']);

        // 3. PartyPlatform must use authentic electric violet/fuchsia gradient
        $this->assertArrayHasKey('partyplatform', $catalog);
        $party = $catalog['partyplatform'];
        $this->assertEquals('#9B5CFB', $party['accent_color']);
        $this->assertStringContainsString('#7b03fc', $party['gradient']);
    }

    public function testAdvertsRotationAcrossImpressions(): void
    {
        $promoService = new EcosystemPromoService();

        // With mock session offsets, consecutive requests on same placement must rotate
        $ad1 = $promoService->getPromoForContext('partyplatform', 'dashboard', ['mock_session_offset' => 0]);
        $ad2 = $promoService->getPromoForContext('partyplatform', 'dashboard', ['mock_session_offset' => 1]);
        $ad3 = $promoService->getPromoForContext('partyplatform', 'dashboard', ['mock_session_offset' => 2]);

        $this->assertNotNull($ad1);
        $this->assertNotNull($ad2);
        $this->assertNotNull($ad3);

        // Target apps must cycle and not be stuck on the exact same app
        $this->assertNotEquals($ad1['target_app'], $ad2['target_app']);
        $this->assertNotEquals($ad2['target_app'], $ad3['target_app']);
    }

    public function testCustomAdvertsRotationAcrossMultipleCandidates(): void
    {
        EcosystemAdManager::saveAdvert([
            'id' => 'campaign_alpha',
            'headline' => 'Campaign Alpha Special',
            'target_url' => 'https://alpha.example.com',
            'active' => 1,
            'target_apps' => ['all'],
        ]);

        EcosystemAdManager::saveAdvert([
            'id' => 'campaign_beta',
            'headline' => 'Campaign Beta Special',
            'target_url' => 'https://beta.example.com',
            'active' => 1,
            'target_apps' => ['all'],
        ]);

        $ad1 = EcosystemAdManager::getAdvertForApp('partyplatform', 'dashboard', ['mock_session_offset' => 0]);
        $ad2 = EcosystemAdManager::getAdvertForApp('partyplatform', 'dashboard', ['mock_session_offset' => 1]);

        $this->assertNotNull($ad1);
        $this->assertNotNull($ad2);
        $this->assertNotEquals($ad1['id'], $ad2['id']);
    }

    public function testImpressionAndClickTrackingTelemetry(): void
    {
        $saveResult = EcosystemAdManager::saveAdvert([
            'id' => 'trackable_campaign',
            'headline' => 'Trackable Campaign 2026',
            'target_url' => 'https://example.com/tracked-landing',
            'active' => 1,
            'target_apps' => ['all'],
        ]);

        $this->assertTrue($saveResult['success']);

        // 1. Initial telemetry must be zero
        $statsInitial = EcosystemAdManager::getStats('trackable_campaign');
        $this->assertEquals(0, $statsInitial['impressions']);
        $this->assertEquals(0, $statsInitial['clicks']);
        $this->assertEquals(0.0, $statsInitial['ctr']);

        // 2. Record 2 impressions
        EcosystemAdManager::recordImpression('trackable_campaign', 'partyplatform');
        EcosystemAdManager::recordImpression('custom_trackable_campaign', 'familyplatform');

        $statsAfterImpressions = EcosystemAdManager::getStats('trackable_campaign');
        $this->assertEquals(2, $statsAfterImpressions['impressions']);
        $this->assertNotNull($statsAfterImpressions['last_impression_at']);

        // 3. Record 1 click
        $redirectUrl = EcosystemAdManager::recordClick('trackable_campaign', 'partyplatform');
        $this->assertNotNull($redirectUrl);
        $this->assertStringContainsString('https://example.com/tracked-landing', $redirectUrl);
        $this->assertStringContainsString('utm_source=partyplatform', $redirectUrl);

        $statsAfterClick = EcosystemAdManager::getStats('trackable_campaign');
        $this->assertEquals(1, $statsAfterClick['clicks']);
        $this->assertEquals(50.0, $statsAfterClick['ctr']); // 1 click / 2 impressions = 50.0%
        $this->assertNotNull($statsAfterClick['last_click_at']);
    }

    public function testCustomBannerInheritsCatalogBrandAssetsWhenTargetingSisterApp(): void
    {
        $banner = EcosystemAdManager::formatCustomBanner([
            'id' => 'ad_promo_family',
            'headline' => 'Join the Family Heritage Sanctuary',
            'target_url' => 'https://myfamilyplatform.com/join',
            'target_app_key' => 'familyplatform',
        ], 'partyplatform', 'dashboard');

        // Must inherit FamilyPlatform's updated Crimson brand color and authentic SVG icon
        $this->assertEquals('#a31621', $banner['accent_color']);
        $this->assertStringContainsString('#b8202c', $banner['gradient']);
        $this->assertStringContainsString('pp-brand-logo-svg', $banner['svg_icon']);
        $this->assertStringContainsString('action=click', $banner['tracking_click_url']);
    }
}
