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
}
