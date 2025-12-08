<?php


namespace Src\functionality\middleware;

use Src\{Utility, SubmitForm, FileUploader};


class FileUploadProcess
{

  // define a property call pathlocation 
  private static string $filePath = '';

  public static function process(array $sanitisedData, $fileTable, $fileName, $imgPath, $generalFileTable, $nested = 'true'): array
  {

    if (!empty($_FILES)) {

      // check if $_FILES[fileName]['name'] is an array 
      $isArray = is_array($_FILES[$fileName]['name']);
      if ($isArray) {
        $result = self::submitImgDataMultiple(
          $fileName,
          $imgPath
        );
        $getProcessedFileName = $result['fileName'];
        self::$filePath = $result['filePath'];

        // Map each uploaded file to a unique column name
        foreach ($getProcessedFileName as $key => $value) {
          $imgColumnName = $fileName . ($key + 1);
          if ($nested) { // if the array is nested 
            $sanitisedData[$fileTable][$imgColumnName] = $value;
            // also add the image to the image table by default 
            $data = [
              'img' => $value,
              'where_from' => $fileTable,
              'id' => checkInput($_SESSION['id'])
            ];

            // submit the file to the database to the default images table 
            SubmitForm::submitForm($generalFileTable, $data);
          } else {
            $sanitisedData[$imgColumnName] = $value;
            $data = [
              'img' => $value,
              'where_from' => $fileTable,
              'id' => checkInput($_SESSION['id'])
            ];
            // submit the file to the database to the default images table 
            SubmitForm::submitForm($generalFileTable, $data);
          }
        }
      } else {

        $result = self::submitImgDataSingle($fileName, $imgPath);

        $name = $result['fileName'];
        self::$filePath = $result['filePath'];

        if ($nested) {
          $sanitisedData[$fileTable][$fileName] = $name;
          $data = [
            'img' => $name,
            'where_from' => $fileTable,
            'id' => checkInput($_SESSION['id'])
          ];

          // submit the file to the database to the default images table 
          SubmitForm::submitForm($generalFileTable, $data);
        } else {
          $sanitisedData[$fileName] = $name;
        }
      }
    }

    return ['sanitisedData' => $sanitisedData, 'filePath' => self::$filePath];
  }



  /**
   * Upload a single image and return the sanitized filename.
   *
   * @param string $formInputName HTML file input field name
   * @param string $uploadPath    Destination directory path
   * @param mixed  $sFile         Raw file array from request
   *
   * @return array Sanitized filename (spaces removed, validated) and fileLocation
   */
  private static function submitImgDataSingle($formInputName, $uploadPath): array
  {
    return FileUploader::fileUploadSingle(
      $uploadPath,
      $formInputName
    );
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
  private static function submitImgDataMultiple(string $formInputName, string $uploadPath): array
  {
    return FileUploader::fileUploadMultiple(
      $uploadPath,
      $formInputName
    );
  }
}
