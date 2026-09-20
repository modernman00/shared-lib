<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\SocialCardGenerator;

class SocialCardGeneratorTest extends TestCase
{
    private string $tempOutputFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempOutputFile = sys_get_temp_dir() . '/test_social_card_' . uniqid() . '.png';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempOutputFile)) {
            unlink($this->tempOutputFile);
        }
        parent::tearDown();
    }

    public function testGenerateAndSaveSocialCardPng(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension not loaded');
        }

        $generator = SocialCardGenerator::create([
            'brand_name' => 'PortfolioTest',
            'brand_badge' => 'VERIFIED',
            'title' => 'Growth Hypothesis Test',
            'score' => 90,
            'verdict' => 'STRONG GO',
            'palette' => 'emerald',
        ]);

        $generator->addMetric('Velocity', 9, 10);
        $generator->addMetric('Retention', 8, 10);

        $saved = $generator->save($this->tempOutputFile);
        $this->assertTrue($saved);
        $this->assertFileExists($this->tempOutputFile);

        $imageInfo = getimagesize($this->tempOutputFile);
        $this->assertIsArray($imageInfo);
        $this->assertSame(1200, $imageInfo[0]);
        $this->assertSame(630, $imageInfo[1]);
        $this->assertSame(IMAGETYPE_PNG, $imageInfo[2]);
    }

    public function testToPngStringReturnsValidPngBinary(): void
    {
        if (!extension_loaded('gd')) {
            $this->markTestSkipped('GD extension not loaded');
        }

        $generator = SocialCardGenerator::create([
            'brand_name' => 'iDecide',
            'score' => 88,
            'palette' => 'purple',
        ]);

        $binary = $generator->toPngString();
        $this->assertNotEmpty($binary);

        // Verify PNG magic header bytes: 0x89 0x50 0x4E 0x47 0x0D 0x0A 0x1A 0x0A
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($binary, 0, 8));
    }
}
