<?php

declare(strict_types=1);

namespace Tests\Media;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Exceptions\ValidationException;
use Src\Media\PythonSidecarBridge;
use Src\Media\SovereignPdfEngine;
use Src\smsFunctionality\SovereignSmsEngine;

class SovereignPdfEngineTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/pdf_test_' . uniqid();
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
    public function it_compiles_valid_iso_pdf_binary_from_html_using_native_compiler(): void
    {
        $html = '
            <h1>Sovereign Financial Statement</h1>
            <h2>Account Summary</h2>
            <p>Account holder: Wally Olaogun</p>
            <hr/>
            <table>
                <tr><th>Date</th><th>Description</th><th>Amount</th></tr>
                <tr><td>2026-11-10</td><td>ATG Subscription Replacement Saving</td><td>£1,250.00</td></tr>
            </table>
        ';

        $pdfBytes = SovereignPdfEngine::renderHtmlToPdf($html, [
            'title'        => 'Statement Q4',
            'author'       => 'ATG FinTech Pod',
            'preferNative' => true,
        ]);

        $this->assertStringStartsWith('%PDF-1.4', $pdfBytes);
        $this->assertStringContainsString('Sovereign Financial Statement', $pdfBytes);
        $this->assertStringContainsString('ATG SovereignPdfEngine v1.0', $pdfBytes);
        $this->assertStringContainsString('%%EOF', $pdfBytes);
    }

    #[Test]
    public function it_saves_pdf_file_to_disk(): void
    {
        $outPath = $this->tempDir . '/contract.pdf';
        $saved = SovereignPdfEngine::saveHtmlToPdf('<h1>Digital Tenancy Agreement</h1><p>Signed and verified.</p>', $outPath, [
            'preferNative' => true,
        ]);

        $this->assertTrue($saved);
        $this->assertFileExists($outPath);
        $this->assertGreaterThan(250, filesize($outPath));
    }

    #[Test]
    public function it_blocks_ssrf_and_local_file_inclusion_in_pdf_html(): void
    {
        $maliciousHtml = '<h1>Invoice</h1><iframe src="http://169.254.169.254/latest/meta-data/"></iframe>';

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('SECURITY VIOLATION - Unsafe local file or SSRF reference detected in PDF HTML source.');

        SovereignPdfEngine::renderHtmlToPdf($maliciousHtml);
    }

    #[Test]
    public function it_gracefully_returns_false_when_python_sidecar_is_offline(): void
    {
        putenv('ATG_PYTHON_SIDECAR_SOCKET=/nonexistent/socket.sock');
        putenv('ATG_PYTHON_SIDECAR_HOST=');

        $this->assertFalse(PythonSidecarBridge::isAvailable());
        $this->assertNull(PythonSidecarBridge::dispatch('scan', ['file_path' => '/tmp/none']));
    }

    #[Test]
    public function it_handles_sovereign_sms_missing_credentials_gracefully(): void
    {
        $engine = new SovereignSmsEngine('textmagic', ['username' => '', 'apiKey' => '']);
        $result = $engine->send('+447700900123', 'Verification code: 9988');

        $this->assertFalse($result->success);
        $this->assertSame('textmagic', $result->provider);
        $this->assertStringContainsString('TextMagic credentials missing', (string) $result->error);
    }

    #[Test]
    public function it_executes_external_pdf_library_fallback_when_invoked(): void
    {
        $fallbackPdf = SovereignPdfEngine::fallbackToExternalPdfLibrary('<h1>Fallback Resilience Test</h1><p>Verified via Dompdf/Mpdf safety net.</p>');

        $this->assertNotNull($fallbackPdf);
        $this->assertStringStartsWith('%PDF-', $fallbackPdf);
    }

    #[Test]
    public function it_extracts_svg_labels_blockquotes_and_code_blocks_and_reports_readiness(): void
    {
        $html = '
            <h1>Architecture Audit</h1>
            <blockquote>Zero external SaaS dependencies required.</blockquote>
            <pre>composer remove dompdf/dompdf</pre>
            <svg width="100" height="50"><title>Revenue Growth Chart</title><text>Q4 +42%</text></svg>
        ';

        $pdfBytes = SovereignPdfEngine::renderHtmlToPdf($html, ['preferNative' => true]);

        $this->assertStringContainsString('Zero external SaaS dependencies required.', $pdfBytes);
        $this->assertStringContainsString('[Code] composer remove dompdf/dompdf', $pdfBytes);
        $this->assertStringContainsString('[Vector Graphic: Revenue Growth Chart', $pdfBytes);

        $readiness = SovereignPdfEngine::verifyProductionReadiness();
        $this->assertTrue($readiness['native_iso32000_ready']);
        $this->assertNotEmpty($readiness['active_rail']);

        $jitterUs = \Src\Realtime\SovereignBroadcaster::getJitteredPollIntervalUs();
        $this->assertGreaterThanOrEqual(400_000, $jitterUs);
        $this->assertLessThanOrEqual(600_000, $jitterUs);
    }
}
