<?php

declare(strict_types=1);

namespace Tests\Media;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Exceptions\ValidationException;
use Src\Media\SovereignMediaEngine;

class SovereignMediaEngineTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/media_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            $files = glob($this->tempDir . '/*');
            if (is_array($files)) {
                foreach ($files as $f) {
                    if (is_file($f)) {
                        @unlink($f);
                    }
                }
            }
            @rmdir($this->tempDir);
        }
        parent::tearDown();
    }

    #[Test]
    public function it_rejects_decompression_bomb_dimensions(): void
    {
        $path = $this->tempDir . '/bomb.png';

        // Create a PNG header claiming 9000x9000 pixels (> MAX_INPUT_DIMENSION 8192)
        $pngHeader = "\x89PNG\r\n\x1a\n"
            . "\x00\x00\x00\x0D" // IHDR length
            . "IHDR"
            . pack('NN', 9000, 9000) // 9000x9000 width & height
            . "\x08\x02\x00\x00\x00" // 8-bit truecolor
            . "\x00\x00\x00\x00";   // dummy CRC

        file_put_contents($path, $pngHeader);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('SECURITY VIOLATION - Image dimensions exceed the maximum allowed safety limits.');

        SovereignMediaEngine::assertSafeDimensions($path);
    }

    #[Test]
    public function it_rescales_and_optimises_oversized_jpeg(): void
    {
        $path = $this->tempDir . '/oversized.jpg';

        // Generate a 2400x1200 image in GD
        $img = imagecreatetruecolor(2400, 1200);
        $blue = imagecolorallocate($img, 0, 120, 255);
        imagefill($img, 0, 0, $blue);
        imagejpeg($img, $path, 100);
        imagedestroy($img);

        $initialSize = filesize($path);

        $resultPath = SovereignMediaEngine::optimise($path, 'jpg');

        $this->assertSame($path, $resultPath);
        $this->assertFileExists($resultPath);

        $info = getimagesize($resultPath);
        $this->assertLessThanOrEqual(1920, $info[0]);
        $this->assertLessThanOrEqual(1920, $info[1]);
        $this->assertSame(1920, $info[0]);
        $this->assertSame(960, $info[1]); // Preserves 2:1 aspect ratio
    }

    #[Test]
    public function it_generates_sibling_variants_for_modern_picture_tags(): void
    {
        $path = $this->tempDir . '/sample.png';

        $img = imagecreatetruecolor(200, 200);
        $red = imagecolorallocate($img, 255, 0, 0);
        imagefill($img, 0, 0, $red);
        imagepng($img, $path);
        imagedestroy($img);

        $variants = SovereignMediaEngine::generateVariants($path);

        if (function_exists('imagewebp')) {
            $this->assertArrayHasKey('webp', $variants);
            $this->assertFileExists($variants['webp']);
        }

        if (function_exists('imageavif')) {
            $this->assertArrayHasKey('avif', $variants);
            $this->assertFileExists($variants['avif']);
        }
    }

    #[Test]
    public function it_leaves_animated_gifs_unflattened(): void
    {
        $path = $this->tempDir . '/animated.gif';
        // Minimal GIF89a header
        file_put_contents($path, "GIF89a\x01\x00\x01\x00\x80\x00\x00\xFF\xFF\xFF\x00\x00\x00!\xF9\x04\x01\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;");

        $result = SovereignMediaEngine::optimise($path, 'gif');

        $this->assertSame($path, $result);
    }

    #[Test]
    public function it_detects_wide_gamut_display_p3_icc_profile_in_header(): void
    {
        $cleanPath = $this->tempDir . '/srgb.jpg';
        file_put_contents($cleanPath, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9");
        $this->assertFalse(SovereignMediaEngine::hasWideGamutIccProfile($cleanPath));

        $p3Path = $this->tempDir . '/iphone_p3.jpg';
        file_put_contents(
            $p3Path,
            "\xFF\xD8\xFF\xE2\x00\x40ICC_PROFILE\x00\x01\x01applDisplay P3 wide gamut profile\xFF\xD9"
        );
        $this->assertTrue(SovereignMediaEngine::hasWideGamutIccProfile($p3Path));
    }
}
