<?php

declare(strict_types=1);

namespace Src\Security;

use Src\Exceptions\ValidationException;
use Src\LoggerFactory;
use Src\Media\PythonSidecarBridge;
use Throwable;
use ZipArchive;

/**
 * SovereignThreatEngine
 *
 * Elite, zero-dependency, self-hosted cybersecurity and threat defence engine
 * engineered by the Advanced Technology Group (ATG) under Olutobi (Deloitte) and Victor (CTO).
 *
 * Provides deep static heuristic inspection, magic-byte verification, polyglot webshell detection,
 * PDF exploit analysis, macro interception, and native ClamAV daemon UNIX/TCP socket streaming.
 * Eliminates all external SaaS dependencies (Cloudmersive) and eliminates third-party GDPR data leaks.
 */
class SovereignThreatEngine
{
    /** Maximum bytes to read for deep signature inspection (2 MB). */
    private const MAX_INSPECT_BYTES = 2_097_152;

    /** Maximum total uncompressed payload permitted inside an OpenXML/ZIP archive (50 MB). */
    public const MAX_ARCHIVE_UNCOMPRESSED_BYTES = 52_428_800;

    /** Maximum compression ratio permitted per archive entry (100:1 Zip Bomb threshold). */
    public const MAX_ARCHIVE_COMPRESSION_RATIO = 100.0;

    /** Strict magic-byte signatures for supported file types. */
    private const MAGIC_BYTE_MAP = [
        'jpg'  => ["\xFF\xD8\xFF"],
        'jpeg' => ["\xFF\xD8\xFF"],
        'png'  => ["\x89\x50\x4E\x47\x0D\x0A\x1A\x0A"],
        'gif'  => ["GIF87a", "GIF89a"],
        'webp' => ["RIFF"],
        'pdf'  => ["%PDF-"],
        'doc'  => ["\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1"], // OLE compound file
        'docx' => ["PK\x03\x04"],
        'mp4'  => ["\x00\x00\x00", "ftyp"],
        'mov'  => ["\x00\x00\x00", "ftyp", "moov", "mdat", "wide"],
        'webm' => ["\x1A\x45\xDF\xA3"], // Matroska EBML header
        'mpeg' => ["\x00\x00\x01\xBA", "\x00\x00\x01\xB3", "\xFF\xFB", "\xFF\xF3", "\xFF\xF2", "ID3"],
    ];

    /** High-risk server-side execution patterns. */
    private const HIGH_RISK_PATTERNS = [
        '/<\?(?:php|=)/i',
        '/<script\s+[^>]*language\s*=\s*["\']?php/i',
        '/\b__HALT_COMPILER\s*\(/i',
        '/\b(c99shell|r57shell|b374k|wso_version|FilesMan|weevely)\b/i',
        '/\b(passthru|shell_exec|exec|system|popen|proc_open)\s*\(/i',
        '/\b(eval|assert)\s*\(\s*(base64_decode|gzinflate|gzuncompress|str_rot13)\s*\(/i',
    ];

    /** Malicious PDF object markers (case-sensitive per PDF spec to avoid binary stream collisions). */
    private const PDF_RISK_MARKERS = [
        '/JavaScript'        => '/\/JavaScript\b/',
        '/JS'                => '/\/JS\b/',
        '/Launch'            => '/\/Launch\b/',
        '/EmbeddedFiles'     => '/\/EmbeddedFiles\b/',
        '/OpenAction+Action' => '/\/OpenAction\s*<<[^>]*\/S\s*\/(?:JavaScript|JS|Launch|SubmitForm|ImportData|GoToR)\b/',
    ];

    /**
     * Scan a file for security threats using Tier 1 Heuristics and Tier 2 ClamAV Daemon Socket.
     *
     * @param string $filePath Absolute path to the file on disk
     * @param string|null $originalName Original filename for extension detection
     * @param string|null $extension Explicit extension override
     * @return array<string, mixed> Structured scan report
     * @throws ValidationException If any security threat, polyglot, or virus is detected
     */
    public static function scan(string $filePath, ?string $originalName = null, ?string $extension = null): array
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new ValidationException('Security scan failed: File cannot be read.');
        }

        clearstatcache(true, $filePath);
        $fileSize = (int) filesize($filePath);
        $ext = strtolower($extension ?? pathinfo($originalName ?? $filePath, PATHINFO_EXTENSION));

        // 1. Magic byte & header structural validation
        self::validateMagicBytes($filePath, $ext);

        // 2. Polyglot and webshell deep heuristic inspection
        self::scanForPolyglotAndShells($filePath);

        // 3. Document-specific threat analysis (PDF / Office Macros / SVG)
        self::scanDocumentSpecificRisks($filePath, $ext);

        // 4. Tier 2: Persistent Warm Python Sidecar Threat Scan (YARA + PyMuPDF)
        $pythonResult = self::scanPythonSidecar($filePath);

        // 5. Tier 3: Native ClamAV Daemon Socket Stream (if daemon is active)
        $clamAvResult = self::scanClamAvDaemon($filePath);

        return [
            'clean'       => true,
            'size'        => $fileSize,
            'extension'   => $ext,
            'sha256'      => hash_file('sha256', $filePath),
            'python'      => $pythonResult,
            'clamav'      => $clamAvResult,
            'scanned_at'  => gmdate('Y-m-d\TH:i:s\Z'),
        ];
    }

    /**
     * Validates that the file header matches known magic bytes for its declared extension.
     */
    public static function validateMagicBytes(string $filePath, string $extension): void
    {
        if (!isset(self::MAGIC_BYTE_MAP[$extension])) {
            return;
        }

        $handle = @fopen($filePath, 'rb');
        if (!$handle) {
            throw new ValidationException('Security scan failed: Unable to open file stream.');
        }

        $header = (string) fread($handle, 32);
        fclose($handle);

        $matched = false;
        foreach (self::MAGIC_BYTE_MAP[$extension] as $signature) {
            if (str_contains($header, $signature)) {
                $matched = true;
                break;
            }
        }

        if (!$matched && $extension === 'webp') {
            // WebP requires 'RIFF' at 0 and 'WEBP' at 8
            if (str_starts_with($header, 'RIFF') && str_contains($header, 'WEBP')) {
                $matched = true;
            }
        }

        if (!$matched) {
            self::abortWithAlert($filePath, 'Header/Magic-Byte Mismatch', "SECURITY VIOLATION - File header does not match declared format (.{$extension}).");
        }
    }

    /**
     * Scans the file contents for embedded PHP tags, webshell signatures, and polyglots.
     */
    public static function scanForPolyglotAndShells(string $filePath): void
    {
        $handle = @fopen($filePath, 'rb');
        if (!$handle) {
            throw new ValidationException('Security scan failed: Unable to open file for heuristic inspection.');
        }

        $inspectedBytes = 0;
        $chunkSize = 65536; // 64 KB chunks
        $overlapBuffer = '';

        while (!feof($handle) && $inspectedBytes < self::MAX_INSPECT_BYTES) {
            $chunk = (string) fread($handle, $chunkSize);
            $inspectedBytes += strlen($chunk);
            $combined = $overlapBuffer . $chunk;

            foreach (self::HIGH_RISK_PATTERNS as $pattern) {
                if (preg_match($pattern, $combined)) {
                    fclose($handle);
                    self::abortWithAlert($filePath, 'Polyglot WebShell / Execution Wrapper', 'SECURITY VIOLATION - Malicious executable polyglot or webshell payload detected.');
                }
            }

            // Keep trailing 512 bytes to catch patterns split across chunk boundaries
            $overlapBuffer = substr($chunk, -512);
        }

        fclose($handle);
    }

    /**
     * Scans for format-specific threats: PDF exploits, Office macros, and SVG scripts.
     */
    public static function scanDocumentSpecificRisks(string $filePath, string $extension): void
    {
        if ($extension === 'pdf') {
            self::scanPdfExploits($filePath);
        } elseif (in_array($extension, ['docx', 'xlsx', 'pptx', 'doc', 'zip'], true)) {
            self::scanOfficeMacros($filePath, $extension);
        } elseif ($extension === 'svg') {
            self::scanSvgXmlExploits($filePath);
        }
    }

    /**
     * Inspects PDF object tables for malicious execution triggers.
     */
    private static function scanPdfExploits(string $filePath): void
    {
        $handle = @fopen($filePath, 'rb');
        if (!$handle) {
            return;
        }

        $content = '';
        $readBytes = 0;
        while (!feof($handle) && $readBytes < self::MAX_INSPECT_BYTES) {
            $part = (string) fread($handle, 65536);
            $content .= $part;
            $readBytes += strlen($part);
        }
        fclose($handle);

        foreach (self::PDF_RISK_MARKERS as $label => $marker) {
            if (preg_match($marker, $content)) {
                error_log(sprintf('[SovereignThreatEngine] Blocked PDF "%s" — matched marker: %s', basename($filePath), $label));
                self::abortWithAlert(
                    $filePath,
                    "Malicious Active PDF Object ({$label})",
                    'SECURITY VIOLATION - Malicious active PDF script or launch object detected.'
                );
            }
        }
    }

    /**
     * Unpacks OpenXML archives to detect weaponised VBA macros.
     */
    private static function scanOfficeMacros(string $filePath, string $extension): void
    {
        if ($extension === 'doc') {
            // Legacy binary Word doc: inspect for Macro storage signatures
            $content = (string) @file_get_contents($filePath, false, null, 0, 131072);
            if (str_contains($content, 'Macros') || str_contains($content, 'VBA_Project')) {
                self::abortWithAlert($filePath, 'Legacy OLE Macro Bomb', 'SECURITY VIOLATION - Legacy document contains embedded executable VBA macros.');
            }
            return;
        }

        if (!class_exists(ZipArchive::class)) {
            return;
        }

        $zip = new ZipArchive();
        if ($zip->open($filePath, ZipArchive::RDONLY) === true) {
            $totalUncompressed = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                $name = $stat['name'] ?? '';
                $uncompSize = (int) ($stat['size'] ?? 0);
                $compSize = (int) ($stat['comp_size'] ?? 0);
                $totalUncompressed += $uncompSize;

                if (
                    $totalUncompressed > self::MAX_ARCHIVE_UNCOMPRESSED_BYTES ||
                    ($compSize > 0 && $uncompSize > 1_048_576 && ($uncompSize / $compSize) > self::MAX_ARCHIVE_COMPRESSION_RATIO)
                ) {
                    $zip->close();
                    self::abortWithAlert(
                        $filePath,
                        'Archive Decompression Bomb (Zip Bomb)',
                        'SECURITY VIOLATION - Archive decompression ratio or uncompressed payload size exceeds safety limits.'
                    );
                }

                if (
                    str_contains(strtolower($name), 'vbaproject.bin') ||
                    str_contains(strtolower($name), 'vba_project') ||
                    str_ends_with(strtolower($name), '.vbs')
                ) {
                    $zip->close();
                    self::abortWithAlert($filePath, 'OpenXML VBA Macro Bomb', 'SECURITY VIOLATION - Malicious automated macro package detected in document.');
                }
            }
            $zip->close();
        }
    }

    /**
     * Validates XML/SVG files to eliminate XXE and inline script vulnerabilities.
     */
    private static function scanSvgXmlExploits(string $filePath): void
    {
        $content = (string) @file_get_contents($filePath, false, null, 0, 262144);
        if (
            preg_match('/<!ENTITY\b/i', $content) ||
            preg_match('/<!DOCTYPE\b[^>]*SYSTEM/i', $content) ||
            preg_match('/<script\b/i', $content) ||
            preg_match('/\bon[a-z]+\s*=/i', $content) || // onload=, onerror=
            preg_match('/xlink:href\s*=\s*["\']javascript:/i', $content)
        ) {
            self::abortWithAlert($filePath, 'SVG/XML Script or XXE Vector', 'SECURITY VIOLATION - Dangerous XML entity or script trigger detected in SVG vector.');
        }
    }

    /**
     * Helper to dispatch an immediate critical email alert to the owner, delete the malicious file,
     * and abort execution with a strict ValidationException.
     */
    private static function abortWithAlert(string $filePath, string $threatType, string $message): never
    {
        $sha = is_file($filePath) ? (string) hash_file('sha256', $filePath) : 'unknown';
        SovereignAlertNotifier::notifyThreatBlocked($threatType, basename($filePath), $sha, $message);
        if (is_file($filePath)) {
            @unlink($filePath);
        }
        throw new ValidationException($message);
    }

    /**
     * Tier 2: Dispatches file to the persistent warm Python sidecar daemon (YARA rules + PyMuPDF)
     * via local UNIX Domain Socket. Non-blocking with 50ms connect timeout.
     *
     * @return array<string, mixed>
     */
    public static function scanPythonSidecar(string $filePath): array
    {
        $res = PythonSidecarBridge::dispatch('scan', ['file_path' => $filePath]);
        if (!is_array($res)) {
            return [
                'status' => 'skipped',
                'reason' => 'python_sidecar_offline_or_timeout',
            ];
        }

        if (($res['clean'] ?? true) === false || ($res['status'] ?? '') === 'infected') {
            @unlink($filePath);
            $threat = (string) ($res['threat'] ?? 'Infected payload flagged by Python YARA sidecar');
            throw new ValidationException("SECURITY VIOLATION - {$threat}");
        }

        return $res;
    }

    /**
     * Tier 3: Stream raw bytes to ClamAV Daemon via local UNIX socket or TCP connection.
     * Uses the ClamAV zINSTREAM protocol. Gracefully falls back if daemon is not running.
     *
     * @return array<string, mixed>
     */
    public static function scanClamAvDaemon(string $filePath): array
    {
        $socketPath = (string)($_ENV['CLAMAV_SOCKET'] ?? getenv('CLAMAV_SOCKET') ?: '/var/run/clamav/clamd.ctl');
        $host = (string)($_ENV['CLAMAV_HOST'] ?? getenv('CLAMAV_HOST') ?: '');
        $port = (int)($_ENV['CLAMAV_PORT'] ?? getenv('CLAMAV_PORT') ?: 3310);

        $fp = null;
        $connectionType = 'none';

        if (file_exists($socketPath) && is_readable($socketPath)) {
            $fp = @stream_socket_client('unix://' . $socketPath, $errno, $errstr, 0.3);
            $connectionType = 'unix_socket';
        } elseif ($host !== '') {
            $fp = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, 0.3);
            $connectionType = 'tcp';
        }

        if (!$fp) {
            return [
                'status'  => 'skipped',
                'reason'  => 'daemon_not_configured_or_offline',
                'channel' => $connectionType,
            ];
        }

        stream_set_timeout($fp, 2, 0); // 2 second operational watchdog timeout

        // Initiate ClamAV zINSTREAM command
        fwrite($fp, "zINSTREAM\0");

        $handle = @fopen($filePath, 'rb');
        if (!$handle) {
            fclose($fp);
            return ['status' => 'skipped', 'reason' => 'unable_to_read_file'];
        }

        // Stream file in 8 KB chunks with 4-byte network-endian length prefixes
        while (!feof($handle)) {
            $chunk = (string) fread($handle, 8192);
            $len = strlen($chunk);
            if ($len === 0) {
                break;
            }
            fwrite($fp, pack('N', $len) . $chunk);
        }
        fclose($handle);

        // Terminate stream with zero length chunk
        fwrite($fp, pack('N', 0));

        $response = (string) fgets($fp, 4096);
        fclose($fp);

        $response = trim($response);

        if (str_contains($response, 'FOUND')) {
            @unlink($filePath);
            preg_match('/stream:\s*(.+)\s+FOUND/i', $response, $matches);
            $threat = $matches[1] ?? 'Malware signature';
            throw new ValidationException("SECURITY VIOLATION - ClamAV detected infected payload: {$threat}");
        }

        return [
            'status'   => 'clean',
            'channel'  => $connectionType,
            'response' => $response,
        ];
    }
}
