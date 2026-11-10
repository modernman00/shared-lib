<?php

declare(strict_types=1);

namespace Tests\Media;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Exceptions\ValidationException;
use Src\Media\PythonSidecarBridge;
use Src\Security\SovereignThreatEngine;

class PythonSidecarLiveTest extends TestCase
{
    private string $socketPath;
    private ?int $daemonPid = null;
    private string $tempDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tempDir = sys_get_temp_dir() . '/atg_py_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);

        $this->socketPath = sys_get_temp_dir() . '/atg_live_' . uniqid() . '.sock';
        putenv("ATG_PYTHON_SIDECAR_SOCKET={$this->socketPath}");

        // Start python daemon in background
        $daemonScript = realpath(__DIR__ . '/../../sidecars/atg-media-threat-daemon/daemon.py');
        $cmd = sprintf(
            'ATG_PYTHON_SIDECAR_SOCKET=%s python3 %s > /dev/null 2>&1 & echo $!',
            escapeshellarg($this->socketPath),
            escapeshellarg($daemonScript)
        );

        $pid = trim((string) shell_exec($cmd));
        if (is_numeric($pid)) {
            $this->daemonPid = (int) $pid;
        }

        // Wait up to 1 second for socket to become active
        $started = false;
        for ($i = 0; $i < 20; $i++) {
            if (file_exists($this->socketPath) && PythonSidecarBridge::isAvailable()) {
                $started = true;
                break;
            }
            usleep(50000); // 50ms
        }

        if (!$started) {
            $this->markTestSkipped('Python daemon could not bind to UNIX socket in test environment.');
        }
    }

    protected function tearDown(): void
    {
        if ($this->daemonPid !== null) {
            posix_kill($this->daemonPid, SIGTERM);
        }

        if (file_exists($this->socketPath)) {
            @unlink($this->socketPath);
        }

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

        putenv('ATG_PYTHON_SIDECAR_SOCKET=');
        parent::tearDown();
    }

    #[Test]
    public function it_connects_to_live_python_daemon_via_unix_domain_socket(): void
    {
        $this->assertTrue(PythonSidecarBridge::isAvailable(), 'Python sidecar must report available over UNIX socket.');
        $ping = PythonSidecarBridge::dispatch('ping', []);

        $this->assertIsArray($ping);
        $this->assertSame('ok', $ping['status'] ?? null);
    }

    #[Test]
    public function it_scans_clean_file_through_live_python_sidecar(): void
    {
        $file = $this->tempDir . '/clean.pdf';
        file_put_contents($file, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\n");

        $res = PythonSidecarBridge::dispatch('scan', ['file_path' => $file]);

        $this->assertIsArray($res);
        $this->assertSame('ok', $res['status']);
        $this->assertTrue($res['clean']);
        $this->assertNotEmpty($res['sha256']);
    }

    #[Test]
    public function it_annihilates_threat_detected_by_python_sidecar_in_threat_engine(): void
    {
        $file = $this->tempDir . '/weaponized.pdf';
        // PDF containing embedded JavaScript trigger
        file_put_contents($file, "%PDF-1.4\n/JavaScript (eval('pwned'))\n");

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('SECURITY VIOLATION');

        try {
            SovereignThreatEngine::scan($file, 'weaponized.pdf', 'pdf');
        } finally {
            $this->assertFileDoesNotExist($file, 'File flagged as infected by Python sidecar must be unlinked.');
        }
    }
}
