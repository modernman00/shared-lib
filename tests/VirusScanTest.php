<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Exceptions\ValidationException;
use Src\VirusScan;

class VirusScanTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempFile = tempnam(sys_get_temp_dir(), 'test_virus_scan_');
        file_put_contents($this->tempFile, 'clean file content');
    }

    protected function tearDown(): void
    {
        if (file_exists($this->tempFile)) {
            @unlink($this->tempFile);
        }
        parent::tearDown();
    }

    #[Test]
    public function it_handles_invalid_or_exceeded_limits_key_without_crashing_or_echoing(): void
    {
        ob_start();
        $initialCode = http_response_code();

        // 401 response from Cloudmersive should not echo to stdout or set 401 header
        new VirusScan($this->tempFile, 'invalid-test-api-key');

        $output = ob_get_clean();

        $this->assertEmpty($output, 'Must not echo error to output buffer on API failure');
        $this->assertNotSame(401, http_response_code(), 'Must not set HTTP 401 status code on API failure');
    }

    #[Test]
    public function it_returns_early_when_api_key_is_null_or_empty(): void
    {
        ob_start();
        new VirusScan($this->tempFile, null);
        new VirusScan($this->tempFile, '');
        $output = ob_get_clean();

        $this->assertEmpty($output);
        $this->assertNotSame(401, http_response_code());
    }

    #[Test]
    public function it_returns_early_when_file_does_not_exist(): void
    {
        ob_start();
        new VirusScan('/path/to/nonexistent/file_' . uniqid(), 'some-key');
        $output = ob_get_clean();

        $this->assertEmpty($output);
        $this->assertNotSame(401, http_response_code());
    }
}
