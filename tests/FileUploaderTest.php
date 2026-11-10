<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Exceptions\ValidationException;
use Src\FileUploader;

class FileUploaderTest extends TestCase
{
    private string $uploadDir;
    private string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->uploadDir = sys_get_temp_dir() . '/test_uploads_' . uniqid() . '/';
        mkdir($this->uploadDir, 0777, true);
        $_SESSION = [];
        $_FILES = [];
    }

    protected function tearDown(): void
    {
        if (is_dir($this->uploadDir)) {
            $files = glob($this->uploadDir . '*');
            if (is_array($files)) {
                foreach ($files as $f) {
                    if (is_file($f)) {
                        @unlink($f);
                    }
                }
            }
            @rmdir($this->uploadDir);
        }
        parent::tearDown();
    }

    #[Test]
    public function it_successfully_uploads_and_optimises_single_image(): void
    {
        $tempSource = tempnam(sys_get_temp_dir(), 'test_img_');

        // Create a real JPEG
        $img = imagecreatetruecolor(800, 600);
        $green = imagecolorallocate($img, 0, 200, 50);
        imagefill($img, 0, 0, $green);
        imagejpeg($img, $tempSource, 90);
        imagedestroy($img);

        $_FILES['photo'] = [
            'name'     => 'test_avatar.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => $tempSource,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($tempSource),
        ];

        $result = FileUploader::fileUploadSingle($this->uploadDir, 'photo');

        $this->assertArrayHasKey('fileName', $result);
        $this->assertArrayHasKey('filePath', $result);
        $this->assertFileExists($result['filePath']);
        $this->assertSame('Image was successfully uploaded', $_SESSION['imageUploadOutcome'] ?? null);
    }

    #[Test]
    public function it_blocks_and_rejects_polyglot_threat_payload(): void
    {
        $tempSource = tempnam(sys_get_temp_dir(), 'test_malicious_');
        // Valid JPEG header + PHP payload
        file_put_contents($tempSource, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00" . "<?php phpinfo(); ?>\xFF\xD9");

        $_FILES['exploit'] = [
            'name'     => 'shell.jpg',
            'type'     => 'image/jpeg',
            'tmp_name' => $tempSource,
            'error'    => UPLOAD_ERR_OK,
            'size'     => filesize($tempSource),
        ];

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('SECURITY VIOLATION - Malicious executable polyglot or webshell payload detected.');

        FileUploader::fileUploadSingle($this->uploadDir, 'exploit');
    }
}
