<?php

namespace Src;

use Src\Utility;
use Src\VirusScan as ScanVirus;
use Src\Exceptions\ValidationException;
use Intervention\Image\ImageManager as Image;
use Spatie\ImageOptimizer\OptimizerChainFactory as ImgOptimizer;
use Exception;

class FileUploader
{

  public static function fileUploadMultiple($fileLocation, $formInputName, $apiKeyVirusScan = null): array
  {
    // Validate the file input
    if (!isset($_FILES[$formInputName]) || empty($_FILES[$formInputName]['name'])) {
      Utility::throwError(400, 'No files were uploaded');
    }

    // Validate each file
    foreach ($_FILES[$formInputName]['name'] as $file) {
      self::ValidateFile($file);
    }

    // If a virus scan API key is provided, initialize the virus scan
    if ($apiKeyVirusScan) {
      new ScanVirus(tempFileLocation: $_FILES[$formInputName]['tmp_name'][0], apiKey: $apiKeyVirusScan);
    }

    // scanFileForVirus($_FILES[$formInputName]);
    // Count total files
    $saveFiles = [];
    $countFiles = count($_FILES[$formInputName]['name']);

    // Looping all files
    for ($i = 0; $i < $countFiles; $i++) {
      $fileName = basename($_FILES[$formInputName]['name'][$i]);
      // trim out the space in the file name
      $fileName = str_replace(' ', '', $fileName);
      $fileName = str_replace(',', '', $fileName);
      $fileInfo = pathinfo($fileName);
      $baseName = $fileInfo['filename']; // e.g., "WhatsAppImage2021-01-24at12.00.04(1)"
      $extension = strtolower($fileInfo['extension']); // e.g., "jpeg"
      // Sanitize base name: replace dots and parentheses
      $baseName = preg_replace('/\./', '_', $baseName); // Replace dots with underscores
      $baseName = preg_replace('/[()]/', '', $baseName); // Replace parentheses with underscores

      // Remove any extra underscores that might result from consecutive replacements
      $baseName = preg_replace('/_+/', '_', $baseName); // Replace multiple underscores with a single one
      $fileName = time() . '_' . $baseName . '.' . $extension; // e.g., "WhatsAppImage2021-01-24at12_00_04_1.jpeg"
      $fileTemp = $_FILES[$formInputName]['tmp_name'][$i];
      $fileSize = $_FILES[$formInputName]['size'][$i];
      $pathToImage = "$fileLocation$fileName"; // e.g., "1652634567_WhatsAppImage2021-01-24at12_00_04_1.jpeg"
      $fileError = $_FILES[$formInputName]['error'][$i];


      // Validate file
      $picError = "";
      $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
      $allowedFormats = ['png', 'jpg', 'gif', 'jpeg', 'heic'];

      if (!in_array($fileExtension, $allowedFormats)) {
        $picError .= 'Format must be PNG, JPG, GIF, HEIC or JPEG. ';
        throw new ValidationException("IMAGE FORMAT - $picError");
      }


      if ($fileSize > 10485760) { // 10 MB
        $picError .= 'File size must not exceed 10MB';
        throw new ValidationException("Error Processing Request - post images - $picError");
      }
      // if (file_exists($pathToImage)) {
      //     $picError .= "File $fileName already uploaded";
      //     throwError(401, "Error Processing Request - post images - $picError");
      // }
      if ($picError) {
        throw new ValidationException("Error Processing Request - post images - $picError");
        continue; // skip this file upload
      }

      // Move uploaded file
      if (!move_uploaded_file($fileTemp, $pathToImage)) {
        $_SESSION['imageUploadOutcome'] = 'Image was not successfully uploaded';
        throw new ValidationException("Error Processing Request - post images - Image was not successfully uploaded");
        continue; // Skip optimization if upload failed
      }

      // Resize and crop image
      self::processImageWithImagick($pathToImage);
      // Optimize the image
      self::optimiseImg($pathToImage);

      $saveFiles[] = $fileName;
    }

    return $saveFiles;
  }
  private static function ValidateFile($formInputName): void
  {

    $file = $_FILES[$formInputName];
    if (!isset($file) || empty($file)) {
      throw new ValidationException("No file uploaded");
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
  private static function processImageWithImagick($pathToImage): void
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

  private static function optimiseImg($pathToImage)
  {
    // Optimise the image
    $optimizerChain = ImgOptimizer::create();

    $_SESSION['imageUploadOutcome'] = 'Image was successfully uploaded';
    return  $optimizerChain->optimize($pathToImage);
  }

  public static function fileUploadSingle($fileLocation, $formInputName, $apiKeyVirusScan = null): string
  {
    // Check if file is uploaded
    if (!isset($_FILES[$formInputName]) || $_FILES[$formInputName]['error'] === UPLOAD_ERR_NO_FILE) {
      Utility::throwError(400, 'No file was uploaded');
    }

    $fileName = basename($_FILES[$formInputName]['name']);
    $fileName = str_replace([' ', ','], '', $fileName);
    $fileInfo = pathinfo($fileName);
    $baseName = preg_replace('/_+/', '_', preg_replace('/[().]/', '_', $fileInfo['filename']));
    $extension = strtolower($fileInfo['extension']);
    $fileName = time() . '_' . $baseName . '.' . $extension;
    $fileTemp = $_FILES[$formInputName]['tmp_name'];
    $fileSize = $_FILES[$formInputName]['size'];
    $fileError = $_FILES[$formInputName]['error'];
    $pathToImage = "$fileLocation$fileName";



    // Handle upload errors
    self::ValidateFile($formInputName);

    // If a virus scan API key is provided, initialize the virus scan
    if ($apiKeyVirusScan) {
      new ScanVirus(tempFileLocation: $_FILES[$formInputName]['tmp_name'], apiKey: $apiKeyVirusScan);
    }

    // Validate file
    $allowedFormats = ['png', 'jpg', 'gif', 'jpeg', 'heic'];
    if (!in_array($extension, $allowedFormats)) {
      throw new ValidationException("IMAGE FORMAT - Format must be PNG, JPG, GIF, HEIC or JPEG.");
    }

    if ($fileSize > 10485760) { // 10MB
      throw new ValidationException("Error Processing Request - File size must not exceed 10MB");
    }

    if (!move_uploaded_file($fileTemp, $pathToImage)) {
      $_SESSION['imageUploadOutcome'] = 'Image was not successfully uploaded';
      throw new ValidationException("Error Processing Request - Image was not successfully uploaded");
    }

    // Resize and crop
 
      self::processImageWithImagick($pathToImage);
    
    self::optimiseImg($pathToImage);


    return $fileName;
  }
}
