<?php

declare(strict_types=1);

namespace Src;

use Intervention\Image\ImageManager as Image;
use Spatie\ImageOptimizer\OptimizerChainFactory as ImgOptimizer;
use Src\Exceptions\ValidationException;
use Src\Media\SovereignMediaEngine;
use Src\Security\SovereignThreatEngine;
use Src\VirusScan as ScanVirus;
use Throwable;

class FileUploader
{
    /** Longest edge (px) an uploaded image may keep. Anything larger is scaled down (never up). */
    public const MAX_IMAGE_DIMENSION = 1920;

    /** Re-encode quality for lossy formats (JPEG / WebP), 0-100. */
    public const IMAGE_QUALITY = 80;

    /** Only replace a re-encoded file when it is at least this much smaller (or the format changed). */
    public const MIN_SAVING_RATIO = 0.98;

    /** Hard upload ceiling: 25 MB (allowing high quality phone video clips). */
    private const MAX_UPLOAD_BYTES = 26_214_400;

    /** Cloudmersive free tier refuses payloads larger than 3 MB. */
    private const VIRUS_SCAN_MAX_BYTES = 3_145_728;

    private const MAX_MULTIPLE_FILES = 5;

    /** Raster formats we decode / resize / re-encode ourselves. */
    private const PROCESSABLE_IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'heic', 'webp'];

    /** All image formats (adds gif, which we hand straight to the binary optimizer). */
    private const IMAGE_EXTENSIONS = ['png', 'jpg', 'jpeg', 'heic', 'webp', 'gif'];

    private const ALLOWED_FORMATS = [
        'png', 'jpg', 'jpeg', 'gif', 'heic', 'webp',
        'pdf', 'doc', 'docx', 'mpeg', 'mp4', 'webm', 'mov',
    ];

    /** Extension => acceptable content-sniffed MIME types. */
    private const MIME_MAP = [
        'png'  => ['image/png'],
        'jpg'  => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'gif'  => ['image/gif'],
        'webp' => ['image/webp'],
        'heic' => ['image/heic', 'image/heif'],
        'pdf'  => ['application/pdf'],
        'doc'  => ['application/msword'],
        'docx' => [
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/zip',
        ],
        'mpeg' => ['video/mpeg', 'audio/mpeg'],
        'mp4'  => ['video/mp4', 'application/mp4', 'video/x-m4v'],
        'webm' => ['video/webm'],
        'mov'  => ['video/quicktime'],
    ];

    /** UPLOAD_ERR_* => human-readable message. */
    private const UPLOAD_ERROR_MESSAGES = [
        UPLOAD_ERR_INI_SIZE   => 'File size exceeds the maximum allowed size (upload_max_filesize)',
        UPLOAD_ERR_FORM_SIZE  => 'File size exceeds the maximum allowed size (form limit)',
        UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded',
        UPLOAD_ERR_NO_FILE    => 'No file was uploaded',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
        UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload',
    ];

    public static function fileUploadMultiple(string $fileLocation, string $formInputName): array
    {
        $saveFiles = [
            'fileName' => [],
            'filePath' => null,
            'errors'   => [], // non-fatal per-file problems (PHP upload errors on individual slots)
        ];

        if (!isset($_FILES[$formInputName]) || empty($_FILES[$formInputName]['name'][0])) {
            Utility::throwError(400, 'No files were uploaded');
        }

        $countFiles = count($_FILES[$formInputName]['name']);

        if ($countFiles > self::MAX_MULTIPLE_FILES) {
            throw new ValidationException('You can only upload up to ' . self::MAX_MULTIPLE_FILES . ' files.');
        }

        $validFiles = [];

        // Pass 1: Validation and pre-processing
        for ($i = 0; $i < $countFiles; ++$i) {
            $uploadError = $_FILES[$formInputName]['error'][$i];
            if ($uploadError !== UPLOAD_ERR_OK) {
                if ($uploadError !== UPLOAD_ERR_NO_FILE) {
                    $message = self::UPLOAD_ERROR_MESSAGES[$uploadError] ?? 'Unknown upload error';
                    $saveFiles['errors'][] = $message;
                    error_log("FileUploader: upload slot $i failed - $message");
                }
                continue;
            }

            $originalName = basename($_FILES[$formInputName]['name'][$i]);
            $extension = self::extensionOf($originalName);
            $fileName = self::buildFileName($originalName, $extension);
            $fileTemp = $_FILES[$formInputName]['tmp_name'][$i];

            self::assertValidUpload($fileTemp, $extension, (int) $_FILES[$formInputName]['size'][$i]);

            // Shrink oversized images before scanning only if virus scan is active and required
            $wasPreOptimised = false;
            if (self::shouldVirusScan($fileTemp) && self::isProcessableImage($extension) && filesize($fileTemp) > self::VIRUS_SCAN_MAX_BYTES) {
                $fileTemp = self::optimiseImageFile($fileTemp, $extension);
                $wasPreOptimised = true;

                // Red Team Fix: Abort if it's still too large to be scanned
                clearstatcache(true, $fileTemp);
                if (filesize($fileTemp) > self::VIRUS_SCAN_MAX_BYTES) {
                    throw new ValidationException("File $originalName is too large for security scanning after optimisation attempt.");
                }
            }

            if (self::shouldVirusScan($fileTemp)) {
                try {
                    $cloudmersiveKey = $_ENV['FILE_UPLOAD_CLOUDMERSIVE'] ?? getenv('FILE_UPLOAD_CLOUDMERSIVE') ?: null;
                    new ScanVirus($fileTemp, is_string($cloudmersiveKey) ? $cloudmersiveKey : null);
                } catch (ValidationException $e) {
                    if (is_file($fileTemp)) {
                        @unlink($fileTemp);
                    }
                    $saveFiles['errors'][] = "File {$originalName}: " . $e->getMessage();
                    continue; // virus found - skip this file
                }
            }

            $validFiles[] = [
                'temp'         => $fileTemp,
                'name'         => $fileName,
                'extension'    => $extension,
                'preOptimised' => $wasPreOptimised,
            ];
        }

        // Pass 2: Move and finalise (Single-pass guarantee: avoid redundant re-encoding)
        foreach ($validFiles as $fileData) {
            $pathToImage = $fileLocation . $fileData['name'];

            $moved = move_uploaded_file($fileData['temp'], $pathToImage);
            if (!$moved) {
                // If the file was pre-optimised in-place in /tmp, is_uploaded_file() might be false
                if (!is_uploaded_file($fileData['temp']) && file_exists($fileData['temp'])) {
                    $moved = copy($fileData['temp'], $pathToImage);
                    if ($moved) {
                        @unlink($fileData['temp']);
                    }
                }
            }

            if (!$moved) {
                $_SESSION['imageUploadOutcome'] = "File {$fileData['name']} failed to save";
                throw new ValidationException("Error Processing Request - post files - File {$fileData['name']} failed to save");
            }

            // Only run finaliseImage if the image was NOT already downscaled/optimised in Pass 1
            if (empty($fileData['preOptimised'])) {
                $pathToImage = self::finaliseImage($pathToImage, $fileData['extension']);
            }
            $finalName = basename($pathToImage);

            $saveFiles['fileName'][] = $finalName;
            $saveFiles['filePath'] = $pathToImage;
        }

        if ($saveFiles['fileName'] !== []) {
            $_SESSION['imageUploadOutcome'] = 'Files were successfully uploaded';
        }

        return $saveFiles;
    }

    public static function fileUploadSingle(string $fileLocation, string $formInputName): array
    {
        if (!isset($_FILES[$formInputName]) || $_FILES[$formInputName]['error'] === UPLOAD_ERR_NO_FILE) {
            Utility::throwError(400, 'No file was uploaded');
        }

        self::validateFile($formInputName);

        $originalName = basename($_FILES[$formInputName]['name']);
        $extension = self::extensionOf($originalName);
        $fileName = self::buildFileName($originalName, $extension);
        $fileTemp = $_FILES[$formInputName]['tmp_name'];

        self::assertValidUpload($fileTemp, $extension, (int) $_FILES[$formInputName]['size']);

        // Shrink oversized images before scanning only if virus scan is active and required
        $wasPreOptimised = false;
        if (self::shouldVirusScan($fileTemp) && self::isProcessableImage($extension) && filesize($fileTemp) > self::VIRUS_SCAN_MAX_BYTES) {
            $fileTemp = self::optimiseImageFile($fileTemp, $extension);
            $wasPreOptimised = true;

            // Red Team Fix: Abort if it's still too large to be scanned
            clearstatcache(true, $fileTemp);
            if (filesize($fileTemp) > self::VIRUS_SCAN_MAX_BYTES) {
                throw new ValidationException("File $originalName is too large for security scanning after optimisation attempt.");
            }
        }

        if (self::shouldVirusScan($fileTemp)) {
            $cloudmersiveKey = $_ENV['FILE_UPLOAD_CLOUDMERSIVE'] ?? getenv('FILE_UPLOAD_CLOUDMERSIVE') ?: null;
            new ScanVirus($fileTemp, is_string($cloudmersiveKey) ? $cloudmersiveKey : null);
        }

        $pathToImage = $fileLocation . $fileName;

        $moved = move_uploaded_file($fileTemp, $pathToImage);
        if (!$moved) {
            if (!is_uploaded_file($fileTemp) && file_exists($fileTemp)) {
                $moved = copy($fileTemp, $pathToImage);
                if ($moved) {
                    @unlink($fileTemp);
                }
            }
        }

        if (!$moved) {
            $_SESSION['imageUploadOutcome'] = 'Image was not successfully uploaded';
            throw new ValidationException('Error Processing Request - Image was not successfully uploaded');
        }

        if (!$wasPreOptimised) {
            $pathToImage = self::finaliseImage($pathToImage, $extension);
        }

        $_SESSION['imageUploadOutcome'] = 'Image was successfully uploaded';

        return [
            'fileName' => basename($pathToImage),
            'filePath' => $pathToImage,
        ];
    }

    private static function validateFile(string $formInputName): void
    {
        $file = $_FILES[$formInputName] ?? null;
        if (empty($file)) {
            throw new ValidationException('No file uploaded');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new ValidationException(
                self::UPLOAD_ERROR_MESSAGES[$file['error']] ?? 'Unknown upload error'
            );
        }
    }

    /* --------------------------------------------------------------------- */
    /* Validation helpers                                                     */
    /* --------------------------------------------------------------------- */

    private static function extensionOf(string $name): string
    {
        return strtolower(pathinfo($name, PATHINFO_EXTENSION) ?: '');
    }

    private static function buildFileName(string $originalName, string $extension): string
    {
        $base = pathinfo($originalName, PATHINFO_FILENAME);
        $base = (string) preg_replace('/[^\w-]/', '_', $base);
        $base = (string) preg_replace('/_+/', '_', $base);
        $base = trim($base, '_-') ?: 'file';

        return uniqid() . '_' . time() . '_' . $base . '.' . $extension;
    }

    private static function isProcessableImage(string $extension): bool
    {
        return in_array($extension, self::PROCESSABLE_IMAGE_EXTENSIONS, true);
    }

    private static function isImage(string $extension): bool
    {
        return in_array($extension, self::IMAGE_EXTENSIONS, true);
    }

    private static function assertValidUpload(string $fileTemp, string $extension, int $reportedSize): void
    {
        if (!in_array($extension, self::ALLOWED_FORMATS, true)) {
            throw new ValidationException(
                'IMAGE/VIDEO FORMAT - Format must be PNG, JPG, GIF, WEBP, HEIC, DOC, DOCX, PDF, MPEG, MP4, WEBM, MOV or JPEG.'
            );
        }

        $actualSize = is_file($fileTemp) ? (int) filesize($fileTemp) : $reportedSize;
        if ($actualSize > self::MAX_UPLOAD_BYTES) {
            throw new ValidationException('Error Processing Request - File size must not exceed 25MB');
        }

        // Content-sniff every allowed type so an executable renamed to .jpg/.pdf cannot reach the web root.
        if (is_file($fileTemp)) {
            $mime = self::sniffMime($fileTemp);
            if ($mime !== null && !in_array($mime, self::MIME_MAP[$extension], true)) {
                throw new ValidationException('FILE FORMAT - File contents do not match its extension.');
            }

            // ATG Sovereign Threat Engine: Magic bytes, polyglots, webshells, PDF exploits, and macros
            SovereignThreatEngine::scan($fileTemp, null, $extension);
        }
    }

    private static function sniffMime(string $path): ?string
    {
        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $path);

                return $mime ?: null; // no finfo_close(): GC releases the resource when it goes out of scope
            }
        }

        $info = @getimagesize($path);

        return $info['mime'] ?? null;
    }

    private static function shouldVirusScan(string $fileTemp): bool
    {
        $key = $_ENV['FILE_UPLOAD_CLOUDMERSIVE'] ?? getenv('FILE_UPLOAD_CLOUDMERSIVE') ?: '';
        if (empty($key)) {
            return false;
        }

        clearstatcache(true, $fileTemp);

        return is_file($fileTemp) && filesize($fileTemp) <= self::VIRUS_SCAN_MAX_BYTES;
    }

    /* --------------------------------------------------------------------- */
    /* Image optimisation                                                     */
    /* --------------------------------------------------------------------- */

    /**
     * Post-move pipeline: resize + re-encode, transcode HEIC to JPEG, then run the
     * binary optimizer. Never throws - a processing failure leaves the moved file intact.
     *
     * @return string final path on disk (differs from the input only when HEIC is transcoded)
     */
    /**
     * Finalise image: orient according to EXIF, downscale if > MAX_IMAGE_DIMENSION,
     * re-encode to strip metadata, and run binary optimizer.
     */
    public static function finaliseImage(string $path, string $extension): string
    {
        if (self::isProcessableImage($extension)) {
            $path = self::optimiseImageFile($path, $extension, allowFormatChange: true);
        }

        if (self::isImage($extension)) {
            self::runBinaryOptimizer($path);
        }

        return $path;
    }

    /**
     * Scale an image down to MAX_IMAGE_DIMENSION, strip EXIF metadata, and re-encode
     * using the ATG SovereignMediaEngine (native PHP core GD/Imagick with AVIF/WebP).
     *
     * @return string the resulting path (unchanged unless a HEIC file was transcoded)
     */
    private static function optimiseImageFile(string $path, string $extension, bool $allowFormatChange = false): string
    {
        return SovereignMediaEngine::optimise($path, $extension, $allowFormatChange);
    }

    private static function runBinaryOptimizer(string $path): void
    {
        $disableOptimizer = (string)($_ENV['DISABLE_BINARY_IMAGE_OPTIMIZER'] ?? getenv('DISABLE_BINARY_IMAGE_OPTIMIZER') ?: '');
        if ($disableOptimizer === 'true' || $disableOptimizer === '1') {
            return;
        }

        try {
            ImgOptimizer::create()->optimize($path);
        } catch (Throwable $e) {
            // jpegoptim / pngquant / cwebp / gifsicle may be absent on the host - not fatal
        }
    }
}
