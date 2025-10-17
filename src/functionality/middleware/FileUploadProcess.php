<?php


namespace Src\functionality\middleware;

use Src\Utility;
use Src\FileUploader;


class FileUploadProcess
{
  public static function process(array $sanitisedData, $fileTable, $fileName, $imgPath, $multiple = 'true'): array
  {
    if (!empty($_FILES)) {

      // check if $_FILES[fileName]['name'] is an array 
      $isArray = is_array($_FILES[$fileName]['name']);
      if ($isArray) {
        $getProcessedFileName = self::submitImgDataMultiple(
          $fileName,
          $imgPath
        );

        // Map each uploaded file to a unique column name
        foreach ($getProcessedFileName as $key => $value) {
          $imgColumnName = $fileName . ($key + 1);
          if ($multiple) {
            $sanitisedData[$fileTable][$imgColumnName] = $value;
            // also add the image to the image table by default 
            $data = [
              'img' => $value,
              'where_from' => $fileTable,
              'id' => checkInput($_SESSION['id'])
            ];

            // submit the file to the database to the default images table 
            SubmitForm::submitForm('images', $data);
          } else {
            $sanitisedData[$imgColumnName] = $value;
            $data = [
              'img' => $value,
              'where_from' => $fileTable,
              'id' => checkInput($_SESSION['id'])
            ];
            // submit the file to the database to the default images table 
            SubmitForm::submitForm('images', $data);
          }
        }
      } else {

        $name = self::submitImgDataSingle($fileName, $imgPath);
        if ($multiple) {
          $sanitisedData[$fileTable][$fileName] = $name;
          $data = [
            'img' => $name,
            'where_from' => $fileTable,
            'id' => checkInput($_SESSION['id'])
          ];

          // submit the file to the database to the default images table 
          SubmitForm::submitForm('images', $data);
        } else {
          $sanitisedData[$fileName] = $name;
        }
      }
    }

    return $sanitisedData;
  }



  /**
   * Upload a single image and return the sanitized filename.
   *
   * @param string $formInputName HTML file input field name
   * @param string $uploadPath    Destination directory path
   * @param mixed  $sFile         Raw file array from request
   *
   * @return string Sanitized filename (spaces removed, validated)
   */
  public static function submitImgDataSingle($formInputName, $uploadPath): string
  {
    $fileName = FileUploader::fileUploadSingle(
      $uploadPath,
      $formInputName
    );
    return Utility::checkInputImage(str_replace(' ', '', $fileName));
  }


  /**
   * Upload multiple images and return an array of sanitized filenames.
   *
   * @param string $formInputName HTML file input field name (e.g. 'images[]')
   * @param string $uploadPath    Destination directory path
   * @param array  $postData      Full POST data array containing file data
   *
   * @return array|null Sanitized filenames, or null if no files were uploaded
   */
  public static function submitImgDataMultiple(string $formInputName, string $uploadPath): mixed
  {
    return FileUploader::fileUploadMultiple(
      $uploadPath,
      $formInputName
    );
  }
}
