<?php

declare(strict_types=1);

namespace App\services;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * VisualSupremacyService
 *
 * Implements the Next-Gen Visual Supremacy Engine (VSE) for ultra-high-definition,
 * wide-colour-gamut (Display P3) media ingestion, multi-tier responsive transcoding,
 * and adversarial security gating (Dr. Viktor Kovács & Marcus).
 *
 * Architecture Approved by TAT 2.0 (Victor, Olutobi, David).
 */
final class VisualSupremacyService
{
    /** Maximum allowable uncompressed memory footprint: 128 MB */
    public const MAX_UNCOMPRESSED_BYTES = 134217728; // 128 * 1024 * 1024

    /** Maximum allowable pixel dimensions */
    public const MAX_DIMENSION = 8192;

    /** Maximum file byte size: 35 MB */
    public const MAX_FILE_SIZE = 36700160;

    /** Supported MIME types */
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/avif',
        'image/gif',
        'image/heic',
        'image/heif',
    ];

    /** Subprocess execution timeout in seconds (David Gate 3) */
    public const SUBPROCESS_TIMEOUT_SECONDS = 15;

    /**
     * Process an inbound image upload through the Visual Supremacy pipeline.
     *
     * @param string $sourcePath Path to uploaded temporary file
     * @param string $targetDir Target storage directory on disk
     * @param string $baseFilename Desired base filename (without extension)
     * @return array<string, mixed> Metadata describing the generated multi-tier assets
     *
     * @throws InvalidArgumentException When media fails security gating or bounds checks
     * @throws RuntimeException When target storage cannot be prepared
     */
    public static function process(string $sourcePath, string $targetDir, string $baseFilename): array
    {
        // 1 & 2. Mandatory Adversarial Security Gate: Bounds & Decompression Bomb Guard
        self::validateMediaBounds($sourcePath);

        $imageInfo = @getimagesize($sourcePath);
        $width = (int)($imageInfo[0] ?? 0);
        $height = (int)($imageInfo[1] ?? 0);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo !== false ? (string)finfo_file($finfo, $sourcePath) : 'image/jpeg';
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new RuntimeException("Target directory could not be created: {$targetDir}");
        }

        $targetDir = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR;
        $sanitisedBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $baseFilename) ?? 'media_' . time();

        // 3. Persist the master asset and strip tracking EXIF metadata losslessly
        $originalExt = self::determineExtension($mimeType);
        $originalFilename = $sanitisedBase . '.' . $originalExt;
        $originalPath = $targetDir . $originalFilename;

        if (!copy($sourcePath, $originalPath)) {
            throw new RuntimeException("Failed to persist original master asset.");
        }

        self::stripMetadata($originalPath);

        // 4. Generate Responsive Multi-Tier Variants (AVIF, WebP, LQIP Thumb)
        $avifFilename = self::generateAvifVariant($originalPath, $targetDir, $sanitisedBase);
        $webpFilename = self::generateWebpVariant($originalPath, $targetDir, $sanitisedBase);
        $thumbDataUri = self::generateMicroPreview($originalPath);

        // Detect if Wide Gamut P3 or HDR can be confirmed
        $colorSpace = self::detectColorSpace($originalPath);

        return [
            'original' => $originalFilename,
            'avif' => $avifFilename,
            'webp' => $webpFilename,
            'thumb' => $thumbDataUri,
            'width' => $width,
            'height' => $height,
            'mime' => $mimeType,
            'color_space' => $colorSpace,
            'has_gain_map' => false,
            'gain_map' => null,
            'processed_at' => time(),
        ];
    }

    /**
     * Process an existing image file in-place:
     * - Losslessly strips tracking EXIF/comments
     * - Generates sibling .avif and .webp variants
     * - Generates LQIP micro-preview (≤ 2KB)
     *
     * @param string $filePath Full path to image on disk
     * @return array<string, mixed>|null Returns variant metadata or null on failure
     */
    public static function processInPlace(string $filePath): ?array
    {
        if (!file_exists($filePath) || !is_file($filePath)) {
            return null;
        }

        try {
            self::validateMediaBounds($filePath);

            $dir = dirname($filePath) . DIRECTORY_SEPARATOR;
            $baseName = pathinfo($filePath, PATHINFO_FILENAME);

            // 1. Losslessly strip EXIF / camera serials / GPS tags
            self::stripMetadata($filePath);

            // 2. Generate high-definition AVIF & WebP siblings in the same directory
            $avifFilename = self::generateAvifVariant($filePath, $dir, $baseName);
            $webpFilename = self::generateWebpVariant($filePath, $dir, $baseName);
            $thumb = self::generateMicroPreview($filePath);
            $colorSpace = self::detectColorSpace($filePath);

            return [
                'original' => basename($filePath),
                'avif' => $avifFilename,
                'webp' => $webpFilename,
                'thumb' => $thumb,
                'color_space' => $colorSpace,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * Validate image file existence, size, MIME type, and raster dimension bounds.
     * Prevents decompression bombs and pixel flood attacks.
     *
     * @throws InvalidArgumentException
     */
    public static function validateMediaBounds(string $sourcePath): void
    {
        if (!file_exists($sourcePath) || !is_readable($sourcePath)) {
            throw new InvalidArgumentException("Source image does not exist or is unreadable.");
        }

        $fileSize = filesize($sourcePath);
        if ($fileSize === false || $fileSize === 0 || $fileSize > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException("Image file size violates boundary limits (Max: 35MB).");
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo !== false ? (string)finfo_file($finfo, $sourcePath) : '';
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidArgumentException("Unsupported media type: {$mimeType}. Allowed: JPEG, PNG, WebP, AVIF, GIF, HEIC.");
        }

        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            throw new InvalidArgumentException("Malformed image file header: unable to verify raster geometry.");
        }

        $width = (int)($imageInfo[0] ?? 0);
        $height = (int)($imageInfo[1] ?? 0);

        if ($width <= 0 || $height <= 0) {
            throw new InvalidArgumentException("Invalid raster dimensions: {$width}x{$height}.");
        }

        if ($width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION) {
            throw new InvalidArgumentException("Image dimensions ({$width}x{$height}) exceed maximum allowed bound of " . self::MAX_DIMENSION . "px.");
        }

        $uncompressedMemory = $width * $height * 4;
        if ($uncompressedMemory > self::MAX_UNCOMPRESSED_BYTES) {
            throw new InvalidArgumentException("Uncompressed image raster ({$uncompressedMemory} bytes) exceeds the 128MB security threshold.");
        }
    }

    /**
     * Losslessly strip EXIF, comments, and application metadata from JPEG and PNG files.
     * Preserves ICC colour profiles (APP2 / iCCP) for Display P3 integrity.
     */
    public static function stripMetadata(string $filePath): bool
    {
        if (!file_exists($filePath) || !is_writable($filePath)) {
            return false;
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo !== false ? (string)finfo_file($finfo, $filePath) : '';
        if ($finfo !== false) {
            finfo_close($finfo);
        }

        if ($mime === 'image/jpeg') {
            return self::stripExifJpeg($filePath);
        }

        if ($mime === 'image/png') {
            return self::stripMetadataPng($filePath);
        }

        return true;
    }

    /**
     * Pure-PHP lossless JPEG EXIF stripper.
     * Strips APP1 (Exif/XMP), APP13 (Photoshop IPTC), and COM (Comment) segments.
     * Preserves APP0 (JFIF) and APP2 (ICC profile).
     */
    private static function stripExifJpeg(string $filePath): bool
    {
        $data = @file_get_contents($filePath);
        if ($data === false || strlen($data) < 4 || substr($data, 0, 2) !== "\xFF\xD8") {
            return false;
        }

        $pos = 2;
        $len = strlen($data);
        $output = "\xFF\xD8";

        while ($pos < $len) {
            if ($data[$pos] !== "\xFF") {
                $output .= substr($data, $pos);
                break;
            }

            while ($pos < $len && $data[$pos] === "\xFF") {
                $pos++;
            }
            if ($pos >= $len) {
                break;
            }

            $marker = ord($data[$pos]);
            $pos++;

            if ($marker === 0xD9) { // EOI
                $output .= "\xFF\xD9";
                break;
            }

            // Standalone markers with no length
            if ($marker === 0x00 || ($marker >= 0xD0 && $marker <= 0xD7) || $marker === 0x01) {
                $output .= "\xFF" . chr($marker);
                continue;
            }

            if ($pos + 2 > $len) {
                break;
            }

            $segLen = (ord($data[$pos]) << 8) + ord($data[$pos + 1]);
            if ($pos + $segLen > $len) {
                break;
            }

            // Strip APP1 (0xE1 = Exif/XMP), APP13 (0xED = Photoshop), COM (0xFE = Comments)
            if ($marker === 0xE1 || $marker === 0xED || $marker === 0xFE) {
                $pos += $segLen;
                continue;
            }

            $output .= "\xFF" . chr($marker) . substr($data, $pos, $segLen);
            $pos += $segLen;
        }

        return file_put_contents($filePath, $output) !== false;
    }

    /**
     * Pure-PHP lossless PNG metadata stripper.
     * Strips text chunks ('tEXt', 'zTXt', 'iTXt') and 'eXIf'.
     * Preserves IHDR, PLTE, IDAT, IEND, and colour profile chunks (iCCP, sRGB, cHRM).
     */
    private static function stripMetadataPng(string $filePath): bool
    {
        $data = @file_get_contents($filePath);
        if ($data === false || strlen($data) < 8) {
            return false;
        }
        $sig = "\x89PNG\r\n\x1a\n";
        if (substr($data, 0, 8) !== $sig) {
            return false;
        }

        $pos = 8;
        $len = strlen($data);
        $output = $sig;
        $stripTypes = ['tEXt', 'zTXt', 'iTXt', 'eXIf'];

        while ($pos + 12 <= $len) {
            $unpacked = unpack('N', substr($data, $pos, 4));
            if (!$unpacked || !isset($unpacked[1])) {
                break;
            }
            $chunkLen = $unpacked[1];
            $chunkType = substr($data, $pos + 4, 4);
            $totalChunkLen = 4 + 4 + $chunkLen + 4;

            if ($pos + $totalChunkLen > $len) {
                break;
            }

            if (in_array($chunkType, $stripTypes, true)) {
                $pos += $totalChunkLen;
                continue;
            }

            $output .= substr($data, $pos, $totalChunkLen);
            $pos += $totalChunkLen;

            if ($chunkType === 'IEND') {
                break;
            }
        }

        return file_put_contents($filePath, $output) !== false;
    }

    /**
     * Generate master Display P3 / AVIF variant.
     * Prioritises avifenc CLI with fallback to PHP GD imageavif.
     */
    private static function generateAvifVariant(string $sourcePath, string $targetDir, string $baseName): ?string
    {
        $targetFile = $baseName . '.avif';
        $fullPath = $targetDir . $targetFile;

        // Strategy A: avifenc CLI
        $avifencBin = self::findBinary(['/opt/homebrew/bin/avifenc', '/usr/local/bin/avifenc', 'avifenc']);
        if ($avifencBin !== null) {
            // No --cicp override: avifenc preserves the source ICC profile, so
            // P3 sources stay P3 and sRGB sources are never mis-tagged.
            $exitCode = self::runWithTimeout(
                [$avifencBin, '-s', '6', '-q', '80', $sourcePath, $fullPath],
                self::SUBPROCESS_TIMEOUT_SECONDS
            );
            if ($exitCode === 0 && file_exists($fullPath) && filesize($fullPath) > 0) {
                return $targetFile;
            }
            @unlink($fullPath);
        }

        // Strategy B: Native PHP GD imageavif fallback
        if (function_exists('imageavif')) {
            try {
                $gdImg = self::createGdResource($sourcePath);
                if ($gdImg !== null) {
                    $saved = @imageavif($gdImg, $fullPath, 80);
                    imagedestroy($gdImg);
                    if ($saved && file_exists($fullPath) && filesize($fullPath) > 0) {
                        return $targetFile;
                    }
                }
            } catch (Throwable) {
                // Graceful fallback to next tier
            }
        }

        return null;
    }

    /**
     * Generate responsive WebP variant for modern cross-browser fallback.
     */
    private static function generateWebpVariant(string $sourcePath, string $targetDir, string $baseName): ?string
    {
        $targetFile = $baseName . '.webp';
        $fullPath = $targetDir . $targetFile;

        if (function_exists('imagewebp')) {
            try {
                $gdImg = self::createGdResource($sourcePath);
                if ($gdImg !== null) {
                    $saved = @imagewebp($gdImg, $fullPath, 85);
                    imagedestroy($gdImg);
                    if ($saved && file_exists($fullPath) && filesize($fullPath) > 0) {
                        return $targetFile;
                    }
                }
            } catch (Throwable) {
                // Graceful degradation
            }
        }

        return null;
    }

    /**
     * Generate an ultra-compact 32px Low-Quality Image Preview (LQIP) as a Base64 Data URI.
     * Guarantees zero Cumulative Layout Shift (CLS) and instant perceived load.
     */
    public static function generateMicroPreview(string $sourcePath): ?string
    {
        try {
            $gdImg = self::createGdResource($sourcePath);
            if ($gdImg === null) {
                return null;
            }

            $origW = imagesx($gdImg);
            $origH = imagesy($gdImg);
            if ($origW <= 0 || $origH <= 0) {
                imagedestroy($gdImg);
                return null;
            }

            $targetW = 32;
            $targetH = max(1, (int)round(($origH / $origW) * $targetW));

            $thumb = imagecreatetruecolor($targetW, $targetH);
            if ($thumb === false) {
                imagedestroy($gdImg);
                return null;
            }

            imagealphablending($thumb, false);
            imagesavealpha($thumb, true);
            imagecopyresampled($thumb, $gdImg, 0, 0, 0, 0, $targetW, $targetH, $origW, $origH);

            ob_start();
            $success = false;
            if (function_exists('imagewebp')) {
                $success = @imagewebp($thumb, null, 40);
                $mime = 'image/webp';
            } else {
                $success = @imagejpeg($thumb, null, 40);
                $mime = 'image/jpeg';
            }
            $rawBytes = ob_get_clean();

            imagedestroy($thumb);
            imagedestroy($gdImg);

            if ($success && is_string($rawBytes) && strlen($rawBytes) > 0) {
                return 'data:' . $mime . ';base64,' . base64_encode($rawBytes);
            }
        } catch (Throwable) {
            // Fail safely without breaking the request
        }

        return null;
    }

    /**
     * Detect embedded color profile or flag Display P3 capability.
     */
    public static function detectColorSpace(string $filePath): string
    {
        // Simple probe for P3 profile tag in ICC chunks
        $handle = @fopen($filePath, 'rb');
        if ($handle === false) {
            return 'srgb';
        }

        $headerChunk = (string)fread($handle, 4096);
        fclose($handle);

        if (str_contains($headerChunk, 'Display P3') || str_contains($headerChunk, 'DCI-P3')) {
            return 'display-p3';
        }

        return 'srgb';
    }

    /**
     * Create a standard GD image resource safely.
     *
     * @return \GdImage|resource|null
     */
    private static function createGdResource(string $path)
    {
        $info = @getimagesize($path);
        if ($info === false) {
            return null;
        }

        $mime = $info['mime'] ?? '';
        return match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null,
            'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($path) : null,
            'image/gif' => @imagecreatefromgif($path),
            default => null,
        };
    }

    /**
     * Determine file extension safely from verified MIME type.
     */
    private static function determineExtension(string $mimeType): string
    {
        return match ($mimeType) {
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            'image/gif' => 'gif',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
            default => 'jpg',
        };
    }

    /**
     * Run a command (no shell) and kill it if it exceeds the timeout.
     *
     * @param array<int, string> $command
     * @return int Exit code, or -1 on timeout / launch failure
     */
    private static function runWithTimeout(array $command, int $timeoutSeconds): int
    {
        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['file', '/dev/null', 'w'],
            2 => ['file', '/dev/null', 'w'],
        ];
        $process = @proc_open($command, $descriptors, $pipes);
        if (!is_resource($process)) {
            return -1;
        }

        $deadline = microtime(true) + $timeoutSeconds;
        while (true) {
            $status = proc_get_status($process);
            if (!$status['running']) {
                $code = (int)$status['exitcode'];
                proc_close($process);
                return $code;
            }
            if (microtime(true) >= $deadline) {
                proc_terminate($process, 9);
                proc_close($process);
                return -1;
            }
            usleep(50000);
        }
    }

    /**
     * Find an executable binary path from candidate paths.
     *
     * @param array<int, string> $candidates
     */
    private static function findBinary(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (str_starts_with($candidate, '/') && file_exists($candidate) && is_executable($candidate)) {
                return $candidate;
            }
            // Check in PATH
            $which = @exec('which ' . escapeshellarg($candidate) . ' 2>/dev/null');
            if (!empty($which) && file_exists($which) && is_executable($which)) {
                return $which;
            }
        }
        return null;
    }
}
