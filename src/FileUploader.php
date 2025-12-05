<?php

declare(strict_types=1);

namespace Src;

use Exception;
use Intervention\Image\ImageManager as Image;
use Spatie\ImageOptimizer\OptimizerChainFactory as ImgOptimizer;
use Src\Exceptions\ValidationException;
use Src\VirusScan as ScanVirus;

class FileUploader
{
    public static function fileUploadMultiple(string $fileLocation, string $formInputName): array
    {
        // 1. Initialize the specific return structure you requested
        $saveFiles = [
            'fileName' => [],
            'filePath' => null
        ];

        // Validate the file input
        if (!isset($_FILES[$formInputName]) || empty($_FILES[$formInputName]['name'][0])) {
            Utility::throwError(400, 'No files were uploaded');
        }

        $countFiles = count($_FILES[$formInputName]['name']);

        // 3. Limit Check
        if ($countFiles > 5) {
            throw new ValidationException('You can only upload up to 5 images.');
        }

        // Looping all files
        for ($i = 0; $i < $countFiles; ++$i) {

            // 4. Check for PHP Upload Errors first
            if ($_FILES[$formInputName]['error'][$i] !== UPLOAD_ERR_OK) {
                // Log error or skip. 
                continue;
            }

            $rawName = basename($_FILES[$formInputName]['name'][$i]);
            $fileInfo = pathinfo($rawName);
            $extension = strtolower($fileInfo['extension'] ?? '');

            // Sanitize Filename
            $baseName = $fileInfo['filename'];
            $baseName = preg_replace('/[^\w-]/', '_', $baseName); // Replace non-alphanumeric chars with _
            $baseName = preg_replace('/_+/', '_', $baseName);     // No double underscores

            // 4. Prevent collisions using uniqid
            $fileName = uniqid() . '_' . time() . '_' . $baseName . '.' . $extension;

            $fileTemp = $_FILES[$formInputName]['tmp_name'][$i];
            $fileSize = $_FILES[$formInputName]['size'][$i];
            $pathToImage = $fileLocation . $fileName;

            // Virus Scan
            if (isset($_ENV['FILE_UPLOAD_CLOUDMERSIVE'])) {
                // Assuming this throws an exception on virus found
                try {
                    new ScanVirus($fileTemp, $_ENV['FILE_UPLOAD_CLOUDMERSIVE']);
                } catch (\Exception $e) {
                    // Virus found, skip this file
                    continue;
                }
            }

            // 5. Validation
            $allowedFormats = ['png', 'jpg', 'gif', 'jpeg', 'heic', 'docx', 'pdf', 'doc', 'mpeg'];

            if (!in_array($extension, $allowedFormats)) {
                throw new ValidationException("IMAGE FORMAT - Format must be PNG, JPG, GIF, DOC, PDF, HEIC, MPEG or JPEG.");
            }


            if ($fileSize > 10485760) { // 10 MB
                throw new ValidationException("Error Processing Request - post images - File size must not exceed 10MB");
            }


            // Move uploaded file
            if (!move_uploaded_file($fileTemp, $pathToImage)) {
                $_SESSION['imageUploadOutcome'] = "File $fileName failed to save";
                throw new ValidationException("Error Processing Request - post images - File $fileName failed to save");
            }

            // 7. Image Processing (Only for images)
            $imageExtensions = ['png', 'jpg', 'gif', 'jpeg', 'heic'];
            if (\in_array($extension, $imageExtensions)) {
                try {
                    self::processImageWithImagick($pathToImage);
                    self::optimiseImg($pathToImage);
                } catch (Exception $e) {
                    // Handle image processing error (optional)
                }
            }

            // We push the values into their respective keys independently
            $saveFiles['fileName'][] = $fileName;
            $saveFiles['filePath'] = $pathToImage;
        }

        return $saveFiles;
    }

    private static function ValidateFile(string $formInputName): void
    {
        $file = $_FILES[$formInputName];
        if (!isset($file) || empty($file)) {
            throw new ValidationException('No file uploaded');
        }

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errorMessages = [
                UPLOAD_ERR_INI_SIZE => 'File size exceeds the maximum allowed size (upload_max_filesize)',
                UPLOAD_ERR_FORM_SIZE => 'File size exceeds the maximum allowed size (form limit)',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload',
            ];
            $errorMsg = $errorMessages[$file['error']] ?? 'Unknown upload error';
            throw new ValidationException($errorMsg);
        }
    }

    // private function for imagick
    private static function processImageWithImagick(string $pathToImage): void
    {
        if (extension_loaded('imagick')) {
            if (!file_exists($pathToImage) || !is_readable($pathToImage)) {
                throw new Exception("Cannot read image at: $pathToImage");
            }

            $image = Image::imagick()->read($pathToImage) ?? Image::gd()->read($pathToImage);
            $image->cover(300, 200);
            $tempPath = $pathToImage . '.tmp';
            $image->save($tempPath);
            if (file_exists($tempPath)) {
                rename($tempPath, $pathToImage);
            } else {
                throw new Exception("Failed to save resized image at $tempPath");
            }
        }
    }

    private static function optimiseImg(string $pathToImage)
    {
        // Optimise the image
        $optimizerChain = ImgOptimizer::create();
        $_SESSION['imageUploadOutcome'] = 'Image was successfully uploaded';
        return  $optimizerChain->optimize($pathToImage);
    }

    public static function fileUploadSingle(string $fileLocation, string $formInputName): array
    {
        // Initialize return array
        $saveFiles = [];

        // 1. Check if file is uploaded
        if (!isset($_FILES[$formInputName]) || $_FILES[$formInputName]['error'] === UPLOAD_ERR_NO_FILE) {
            Utility::throwError(400, 'No file was uploaded');
        }

        // Handle upload errors
        self::ValidateFile($formInputName);

        // 3. Sanitize Name
        $rawName = basename($_FILES[$formInputName]['name']);
        $fileInfo = pathinfo($rawName);
        $extension = strtolower($fileInfo['extension'] ?? '');

        // Clean the filename (standardized regex)
        $baseName = $fileInfo['filename'];
        $baseName = preg_replace('/[^\w-]/', '_', $baseName);
        $baseName = preg_replace('/_+/', '_', $baseName);

        // Add uniqid for safety
        $fileName = uniqid() . '_' . time() . '_' . $baseName . '.' . $extension;

        $fileTemp = $_FILES[$formInputName]['tmp_name'];
        $fileSize = $_FILES[$formInputName]['size'];
        $pathToImage = $fileLocation . $fileName;

        // 4. Virus Scan
        if (isset($_ENV['FILE_UPLOAD_CLOUDMERSIVE'])) {
            new ScanVirus($fileTemp, $_ENV['FILE_UPLOAD_CLOUDMERSIVE']);
        }

        // 5. Validation
        $allowedFormats = ['png', 'jpg', 'gif', 'jpeg', 'doc', 'pdf', 'docx', 'heic', 'mpeg'];

        if (!in_array($extension, $allowedFormats)) {
            throw new ValidationException('IMAGE FORMAT - Format must be PNG, JPG, GIF, HEIC, DOC, PDF, MPEG or JPEG.');
        }

        if ($fileSize > 10485760) { // 10MB
            throw new ValidationException('Error Processing Request - File size must not exceed 10MB');
        }

        // 6. Move File
        if (!move_uploaded_file($fileTemp, $pathToImage)) {
            $_SESSION['imageUploadOutcome'] = 'Image was not successfully uploaded';
            throw new ValidationException('Error Processing Request - Image was not successfully uploaded');
        }

        // 7. Image Processing (ONLY for images)
        // Good job on this check - this is critical!
        $imageExtensions = ['png', 'jpg', 'gif', 'jpeg', 'heic'];
        if (in_array($extension, $imageExtensions)) {
            try {
                self::processImageWithImagick($pathToImage);
                self::optimiseImg($pathToImage);
            } catch (\Exception $e) {
                // Optional: Log error, but file is already saved, so we can proceed
            }
        }

        // 8. Return formatted array
        $saveFiles['fileName'] = $fileName;
        $saveFiles['filePath'] = $pathToImage;

        return $saveFiles;
    }
}
