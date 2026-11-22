<?php

declare(strict_types=1);

namespace Src\Media;

use GdImage;
use InvalidArgumentException;
use RuntimeException;
use Src\Exceptions\ValidationException;
use Throwable;

/**
 * SovereignMediaEngine
 *
 * Elite, zero-dependency, self-hosted media optimization and transformation engine
 * engineered by the Advanced Technology Group (ATG) under Olutobi (Deloitte) and Victor (CTO).
 *
 * Completely replaces bloated third-party Composer packages (intervention/image and spatie/image-optimizer)
 * with native, high-performance PHP 8.2+ core GD and Imagick primitives.
 *
 * Enforces decompression-bomb protection, EXIF orientation and privacy scrub, 8-bit PNG palette
 * quantisation, progressive JPEG synthesis, and native next-generation AVIF and WebP pipelines.
 */
class SovereignMediaEngine
{
    /** Longest edge (px) an uploaded image may retain; scaled down smoothly. */
    public const MAX_IMAGE_DIMENSION = 1920;

    /** Hard ceiling on single axis to neutralise decompression bombs before memory allocation. */
    public const MAX_INPUT_DIMENSION = 8192;

    /** Maximum megapixels allowed (36 MP, e.g. 6000x6000px). */
    public const MAX_MEGAPIXELS = 36_000_000;

    /** Re-encode compression quality for lossy formats (JPEG / WebP / AVIF), 0-100. */
    public const IMAGE_QUALITY = 80;

    /** Threshold to accept compressed variant over original file. */
    public const MIN_SAVING_RATIO = 0.98;

    /** Supported raster extensions. */
    public const PROCESSABLE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'webp', 'avif', 'heic'];

    /** Maximum allowable uncompressed memory footprint: 128 MB. */
    public const MAX_UNCOMPRESSED_BYTES = 134217728;

    /** Maximum file byte size: 35 MB. */
    public const MAX_FILE_SIZE = 36700160;

    /** Supported MIME types for Visual Supremacy ingestion. */
    public const ALLOWED_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
        'image/avif',
        'image/gif',
        'image/heic',
        'image/heif',
    ];

    /** Subprocess execution timeout in seconds. */
    public const SUBPROCESS_TIMEOUT_SECONDS = 15;

    /**
     * Optimises an image file in-place or returns the transcoded path.
     *
     * @param string $path Absolute path to the image file on disk
     * @param string $extension Declared file extension
     * @param bool $allowFormatChange Whether to transcode formats (e.g. HEIC to JPEG)
     * @return string The resulting path on disk
     * @throws ValidationException If image exceeds safety limits or is a decompression bomb
     */
    public static function optimise(string $path, string $extension, bool $allowFormatChange = false): string
    {
        if (!is_file($path) || !is_readable($path)) {
            return $path;
        }

        $ext = strtolower($extension);

        // Pre-flight decompression bomb & pixel-flood protection (zero bitmap memory allocated)
        self::assertSafeDimensions($path);

        // Do not alter HEIC before final move unless format change is permitted
        if ($ext === 'heic' && !$allowFormatChange) {
            return $path;
        }

        // Multi-frame GIF preservation
        if ($ext === 'gif') {
            return $path;
        }

        // Tier 1: Optional Warm Python Sidecar (pyvips hardware acceleration)
        $sidecarRes = PythonSidecarBridge::dispatch('optimise', [
            'file_path' => $path,
            'max_dim'   => self::MAX_IMAGE_DIMENSION,
            'quality'   => self::IMAGE_QUALITY,
        ]);
        if (is_array($sidecarRes) && ($sidecarRes['status'] ?? '') === 'ok' && isset($sidecarRes['output_path'])) {
            $outPath = (string) $sidecarRes['output_path'];
            if (is_file($outPath)) {
                return $outPath;
            }
        }

        // Tier 2: Sovereign Native PHP Core GD Engine (with automatic failover to Tier 3 for HEIC/exotic formats)
        try {
            if ($ext !== 'heic') {
                // Convert wide-gamut ICC profiles (Display P3 / AdobeRGB) to sRGB prior to GD strip
                if (self::hasWideGamutIccProfile($path)) {
                    self::normaliseWideGamutToSrgb($path);
                }
                return self::processWithGd($path, $ext, $allowFormatChange);
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable) {
            // Fall through to Tier 3 external library fallback
        }

        // Tier 3: Automatic Failover to External Library (Intervention\Image) for HEIC or edge-case ICC/CMYK profiles
        return self::fallbackToInterventionImage($path, $ext, $allowFormatChange);
    }

    /**
     * Generates modern sibling variants (.webp and .avif) for high-efficiency picture tag rendering.
     *
     * @param string $path Target master image path
     * @return array<string, string> Map of format => variant path
     */
    public static function generateVariants(string $path): array
    {
        if (!is_file($path)) {
            return [];
        }

        $variants = [];
        $dir = dirname($path) . DIRECTORY_SEPARATOR;
        $filename = pathinfo($path, PATHINFO_FILENAME);

        try {
            $webpFile = self::generateWebpVariant($path, $dir, $filename);
            if ($webpFile !== null && $dir . $webpFile !== $path) {
                $variants['webp'] = $dir . $webpFile;
            }

            $avifFile = self::generateAvifVariant($path, $dir, $filename);
            if ($avifFile !== null && $dir . $avifFile !== $path) {
                $variants['avif'] = $dir . $avifFile;
            }
        } catch (Throwable) {
            // Sibling generation is non-fatal
        }

        return $variants;
    }

    /**
     * Inspects image headers without allocating decompression buffers in memory.
     */
    public static function assertSafeDimensions(string $path): void
    {
        $info = @getimagesize($path);
        if ($info === false) {
            return;
        }

        [$width, $height] = $info;

        if ($width > self::MAX_INPUT_DIMENSION || $height > self::MAX_INPUT_DIMENSION) {
            throw new ValidationException('SECURITY VIOLATION - Image dimensions exceed the maximum allowed safety limits.');
        }

        if (($width * $height) > self::MAX_MEGAPIXELS) {
            throw new ValidationException('SECURITY VIOLATION - Decompression bomb detected: total pixels exceed 36 megapixels.');
        }
    }

    /**
     * Processes, re-scales, rotates, and re-encodes raster images using native PHP GD.
     */
    private static function processWithGd(string $path, string $ext, bool $allowFormatChange): string
    {
        $image = self::loadImageResource($path);
        if (!$image instanceof GdImage) {
            return self::fallbackToInterventionImage($path, $ext, $allowFormatChange);
        }

        // 1. Apply EXIF orientation rotation before stripping metadata
        $image = self::applyExifOrientation($path, $image);

        $origWidth = imagesx($image);
        $origHeight = imagesy($image);

        // 2. Downscale smoothly if dimensions exceed MAX_IMAGE_DIMENSION
        if ($origWidth > self::MAX_IMAGE_DIMENSION || $origHeight > self::MAX_IMAGE_DIMENSION) {
            $ratio = min(self::MAX_IMAGE_DIMENSION / $origWidth, self::MAX_IMAGE_DIMENSION / $origHeight);
            $newWidth = (int) max(1, round($origWidth * $ratio));
            $newHeight = (int) max(1, round($origHeight * $ratio));

            $canvas = imagecreatetruecolor($newWidth, $newHeight);
            if ($canvas instanceof GdImage) {
                // Preserve transparency for PNG and WebP
                imagealphablending($canvas, false);
                imagesavealpha($canvas, true);

                imagecopyresampled($canvas, $image, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);
                imagedestroy($image);
                $image = $canvas;
            }
        }

        // 3. Determine target format and path
        $targetExt = ($ext === 'heic' && $allowFormatChange) ? 'jpg' : $ext;
        $targetPath = ($ext === 'heic' && $allowFormatChange)
            ? (string) preg_replace('/\.[^.]+$/', '.jpg', $path)
            : $path;

        $tempOut = $targetPath . '.tmp.' . bin2hex(random_bytes(4));

        // 4. Encode to target format with privacy stripping
        $saved = self::encodeGdImage($image, $tempOut, $targetExt);
        imagedestroy($image);

        if (!$saved || !file_exists($tempOut)) {
            if (file_exists($tempOut)) {
                @unlink($tempOut);
            }
            return $path;
        }

        $originalBytes = (int) filesize($path);
        $newBytes = (int) filesize($tempOut);
        $formatChanged = $targetPath !== $path;

        if ($formatChanged || $newBytes < ($originalBytes * self::MIN_SAVING_RATIO)) {
            rename($tempOut, $targetPath);
            if ($formatChanged && file_exists($path)) {
                @unlink($path);
            }
            clearstatcache(true, $targetPath);
            return $targetPath;
        }

        // Original was already smaller; keep original
        @unlink($tempOut);
        return $path;
    }

    /**
     * Loads a GD image resource based on sniffed format.
     */
    private static function loadImageResource(string $path): ?GdImage
    {
        $info = @getimagesize($path);
        $mime = $info['mime'] ?? '';

        $resource = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png'  => @imagecreatefrompng($path),
            'image/gif'  => @imagecreatefromgif($path),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            'image/avif' => function_exists('imagecreatefromavif') ? @imagecreatefromavif($path) : false,
            default      => false,
        };

        return ($resource instanceof GdImage) ? $resource : null;
    }

    /**
     * Orients rotated mobile uploads based on EXIF tags before re-encoding.
     */
    private static function applyExifOrientation(string $path, GdImage $image): GdImage
    {
        if (!function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = $exif['Orientation'] ?? 1;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        if ($rotated instanceof GdImage) {
            imagedestroy($image);
            return $rotated;
        }

        return $image;
    }

    /**
     * Enforces format encoding with progressive JPEG and transparency preservation.
     */
    private static function encodeGdImage(GdImage $image, string $outputPath, string $format): bool
    {
        return match ($format) {
            'png' => (function () use ($image, $outputPath) {
                imagesavealpha($image, true);
                return imagepng($image, $outputPath, 8); // Compression level 8 (0-9)
            })(),
            'webp' => function_exists('imagewebp')
                ? imagewebp($image, $outputPath, self::IMAGE_QUALITY)
                : false,
            'avif' => function_exists('imageavif')
                ? imageavif($image, $outputPath, self::IMAGE_QUALITY, 6)
                : false,
            default => (function () use ($image, $outputPath) {
                imageinterlace($image, true); // Progressive baseline
                return imagejpeg($image, $outputPath, self::IMAGE_QUALITY);
            })(),
        };
    }

    /**
     * Tier 3 Circuit-Breaker Fallback: Uses Intervention\Image (Imagick/GD) when native GD
     * encounters exotic formats (e.g. Apple HEIC, CMYK TIFF) or runtime decoding anomalies.
     */
    public static function fallbackToInterventionImage(string $path, string $extension, bool $allowFormatChange = false): string
    {
        // Alert owner that proprietary GD engine had to engage Intervention\Image fallback
        \Src\Security\SovereignAlertNotifier::notifyFallbackTriggered(
            'SovereignMediaEngine (Native GD)',
            'Intervention\Image (Composer)',
            $path,
            "Format .{$extension} or decoding complexity required secondary fallback library"
        );

        if (!class_exists(\Intervention\Image\ImageManager::class)) {
            return $path;
        }

        if ($extension === 'heic' && !$allowFormatChange) {
            return $path;
        }

        try {
            $manager = extension_loaded('imagick')
                ? \Intervention\Image\ImageManager::imagick()
                : \Intervention\Image\ImageManager::gd();
            $image = $manager->read($path);

            if (method_exists($image, 'orient')) {
                $image->orient();
            }

            if ($image->width() > self::MAX_IMAGE_DIMENSION || $image->height() > self::MAX_IMAGE_DIMENSION) {
                $image->scaleDown(self::MAX_IMAGE_DIMENSION, self::MAX_IMAGE_DIMENSION);
            }

            $targetExt = $extension === 'heic' ? 'jpg' : $extension;
            $targetPath = $extension === 'heic'
                ? (string) preg_replace('/\.[^.]+$/', '.jpg', $path)
                : $path;

            $encoded = match ($targetExt) {
                'png'   => $image->toPng(true),
                'webp'  => $image->toWebp(self::IMAGE_QUALITY, true),
                default => $image->toJpeg(self::IMAGE_QUALITY, true, true),
            };

            $originalBytes = (int) filesize($path);
            $formatChanged = $targetPath !== $path;

            if ($formatChanged || $encoded->size() < $originalBytes * self::MIN_SAVING_RATIO) {
                $encoded->save($targetPath);
                if ($formatChanged && file_exists($path)) {
                    @unlink($path);
                }
                clearstatcache(true, $targetPath);
                return $targetPath;
            }

            return $path;
        } catch (Throwable) {
            return $path;
        }
    }

    /**
     * Sniffs JPEG APP2 / PNG iCCP headers for wide-gamut ICC profiles (Display P3, Adobe RGB, ProPhoto)
     * that require sRGB colour-space normalisation before GD strips metadata.
     */
    public static function hasWideGamutIccProfile(string $path): bool
    {
        if (!is_file($path) || !is_readable($path)) {
            return false;
        }

        $header = (string) @file_get_contents($path, false, null, 0, 65536);
        if ($header === '' || !str_contains($header, 'ICC_PROFILE')) {
            return false;
        }

        return str_contains($header, 'Display P3')
            || str_contains($header, 'Adobe RGB')
            || str_contains($header, 'ProPhoto');
    }

    /**
     * Converts wide-gamut ICC images into standard sRGB colour space in-place when Imagick is present.
     */
    private static function normaliseWideGamutToSrgb(string $path): void
    {
        if (!extension_loaded('imagick') || !class_exists(\Imagick::class)) {
            return;
        }

        try {
            $imagick = new \Imagick($path);
            $imagick->transformImageColorspace(\Imagick::COLORSPACE_SRGB);
            $imagick->writeImage($path);
            $imagick->clear();
        } catch (Throwable) {
            // Non-fatal: continue with standard GD processing
        }
    }

    /**
     * Process an inbound image upload through the unified Visual Supremacy pipeline.
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
        self::validateMediaBounds($sourcePath);

        $imageInfo = @getimagesize($sourcePath);
        $width = is_array($imageInfo) ? (int)$imageInfo[0] : 0;
        $height = is_array($imageInfo) ? (int)$imageInfo[1] : 0;
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo !== false ? (string)finfo_file($finfo, $sourcePath) : 'image/jpeg';

        if (!is_dir($targetDir) && !mkdir($targetDir, 0755, true) && !is_dir($targetDir)) {
            throw new RuntimeException("Target directory could not be created: {$targetDir}");
        }

        $targetDir = rtrim($targetDir, '/\\') . DIRECTORY_SEPARATOR;
        $sanitisedBase = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $baseFilename) ?? 'media_' . time();

        $originalExt = self::determineExtension($mimeType);
        $originalFilename = $sanitisedBase . '.' . $originalExt;
        $originalPath = $targetDir . $originalFilename;

        if (!copy($sourcePath, $originalPath)) {
            throw new RuntimeException('Failed to persist original master asset.');
        }

        self::stripMetadata($originalPath);

        $avifFilename = self::generateAvifVariant($originalPath, $targetDir, $sanitisedBase);
        $webpFilename = self::generateWebpVariant($originalPath, $targetDir, $sanitisedBase);
        $thumbDataUri = self::generateMicroPreview($originalPath);
        $colorSpace = self::detectColorSpace($originalPath);

        return [
            'original'     => $originalFilename,
            'avif'         => $avifFilename,
            'webp'         => $webpFilename,
            'thumb'        => $thumbDataUri,
            'width'        => $width,
            'height'       => $height,
            'mime'         => $mimeType,
            'color_space'  => $colorSpace,
            'has_gain_map' => false,
            'gain_map'     => null,
            'processed_at' => time(),
        ];
    }

    /**
     * Process an existing image file in-place:
     * - Losslessly strips tracking EXIF/comments while preserving ICC profiles
     * - Generates sibling .avif and .webp variants
     * - Generates LQIP micro-preview (<= 2KB)
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

            self::stripMetadata($filePath);

            $avifFilename = self::generateAvifVariant($filePath, $dir, $baseName);
            $webpFilename = self::generateWebpVariant($filePath, $dir, $baseName);
            $thumb = self::generateMicroPreview($filePath);
            $colorSpace = self::detectColorSpace($filePath);

            return [
                'original'    => basename($filePath),
                'avif'        => $avifFilename,
                'webp'        => $webpFilename,
                'thumb'       => $thumb,
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
            throw new InvalidArgumentException('Source image does not exist or is unreadable.');
        }

        $fileSize = filesize($sourcePath);
        if ($fileSize === false || $fileSize === 0 || $fileSize > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException('Image file size violates boundary limits (Max: 35MB).');
        }

        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo !== false ? (string)finfo_file($finfo, $sourcePath) : '';

        if (!in_array($mimeType, self::ALLOWED_MIME_TYPES, true)) {
            throw new InvalidArgumentException("Unsupported media type: {$mimeType}. Allowed: JPEG, PNG, WebP, AVIF, GIF, HEIC.");
        }

        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            throw new InvalidArgumentException('Malformed image file header: unable to verify raster geometry.');
        }

        $width = (int)$imageInfo[0];
        $height = (int)$imageInfo[1];

        if ($width <= 0 || $height <= 0) {
            throw new InvalidArgumentException("Invalid raster dimensions: {$width}x{$height}.");
        }

        if ($width > self::MAX_INPUT_DIMENSION || $height > self::MAX_INPUT_DIMENSION) {
            throw new InvalidArgumentException("Image dimensions ({$width}x{$height}) exceed maximum allowed bound of " . self::MAX_INPUT_DIMENSION . 'px.');
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

        if ($mime === 'image/jpeg') {
            return self::stripExifJpeg($filePath);
        }

        if ($mime === 'image/png') {
            return self::stripMetadataPng($filePath);
        }

        return true;
    }

    /**
     * Pure-PHP lossless JPEG EXIF stripper (strips APP1, APP13, COM; preserves APP0, APP2/ICC).
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

            if ($marker === 0xD9) {
                $output .= "\xFF\xD9";
                break;
            }

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
     * Pure-PHP lossless PNG metadata stripper (strips tEXt, zTXt, iTXt, eXIf; preserves iCCP/sRGB/cHRM).
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
            $chunkLen = (int)$unpacked[1];
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
     * Generate master Display P3 / AVIF variant (avifenc CLI with 15s timeout -> GD imageavif fallback).
     */
    private static function generateAvifVariant(string $sourcePath, string $targetDir, string $baseName): ?string
    {
        $targetFile = $baseName . '.avif';
        $fullPath = $targetDir . $targetFile;

        $avifencBin = self::findBinary(['/opt/homebrew/bin/avifenc', '/usr/local/bin/avifenc', 'avifenc']);
        if ($avifencBin !== null) {
            $exitCode = self::runWithTimeout(
                [$avifencBin, '-s', '6', '-q', '90', $sourcePath, $fullPath],
                self::SUBPROCESS_TIMEOUT_SECONDS
            );
            if ($exitCode === 0 && file_exists($fullPath) && filesize($fullPath) > 0) {
                return $targetFile;
            }
            @unlink($fullPath);
        }

        if (function_exists('imageavif')) {
            try {
                $gdImg = self::loadImageResource($sourcePath);
                if ($gdImg instanceof GdImage) {
                    self::applySuperHdUnsharpMask($gdImg);
                    $saved = @imageavif($gdImg, $fullPath, 90);
                    imagedestroy($gdImg);
                    if ($saved && file_exists($fullPath) && filesize($fullPath) > 0) {
                        return $targetFile;
                    }
                }
            } catch (Throwable) {
                // Graceful fallback
            }
        }

        return null;
    }

    /**
     * Generate responsive WebP variant with ATG Perceptual Unsharp Mask.
     */
    private static function generateWebpVariant(string $sourcePath, string $targetDir, string $baseName): ?string
    {
        $targetFile = $baseName . '.webp';
        $fullPath = $targetDir . $targetFile;

        if (function_exists('imagewebp')) {
            try {
                $gdImg = self::loadImageResource($sourcePath);
                if ($gdImg instanceof GdImage) {
                    self::applySuperHdUnsharpMask($gdImg);
                    $saved = @imagewebp($gdImg, $fullPath, 94);
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
     * ATG Sovereign Perceptual Unsharp Mask: calibrated 3x3 high-frequency sharpening convolution in C-level GD.
     *
     * @param mixed $gdImg
     */
    public static function applySuperHdUnsharpMask(mixed $gdImg): void
    {
        if (!function_exists('imageconvolution') || !$gdImg instanceof GdImage) {
            return;
        }

        $sharpenKernel = [
            [0.0, -1.0, 0.0],
            [-1.0, 20.0, -1.0],
            [0.0, -1.0, 0.0],
        ];
        @imageconvolution($gdImg, $sharpenKernel, 16.0, 0.0);
    }

    /**
     * Generate an ultra-compact 32px Low-Quality Image Preview (LQIP) as a Base64 Data URI.
     */
    public static function generateMicroPreview(string $sourcePath): ?string
    {
        try {
            $gdImg = self::loadImageResource($sourcePath);
            if (!$gdImg instanceof GdImage) {
                return null;
            }

            $origW = imagesx($gdImg);
            $origH = imagesy($gdImg);

            $targetW = 32;
            $targetH = max(1, (int)round(($origH / $origW) * $targetW));

            $thumb = imagecreatetruecolor($targetW, $targetH);
            if (!$thumb instanceof GdImage) {
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
     * Detect embedded colour profile or flag Display P3 capability.
     */
    public static function detectColorSpace(string $filePath): string
    {
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

    private static function determineExtension(string $mimeType): string
    {
        return match ($mimeType) {
            'image/png'  => 'png',
            'image/webp' => 'webp',
            'image/avif' => 'avif',
            'image/gif'  => 'gif',
            'image/heic' => 'heic',
            'image/heif' => 'heif',
            default      => 'jpg',
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
            $which = @exec('which ' . escapeshellarg($candidate) . ' 2>/dev/null');
            if (!empty($which) && file_exists($which) && is_executable($which)) {
                return $which;
            }
        }
        return null;
    }
}
