<?php

namespace Src;

use Exception;
use Swagger\Client\Configuration;
use GuzzleHttp\Client;
use Swagger\Client\Api\ScanApi;

// https://api.cloudmersive.com/php-client.asp
class VirusScan
{

  // Constructor to initialize the API - remember to include the API key in your environment variables
  public function __construct($tempFileLocation)
  {

    $FILE_UPLOAD_CLOUDMERSIVE = "2623535a-ed7e-4992-9170-0bac31f9fa98";

    try {

      $setApiKey = Configuration::getDefaultConfiguration()->setApiKey('Apikey', $FILE_UPLOAD_CLOUDMERSIVE);

      $apiInstance = new ScanApi(
        client: new Client(),
        config: $setApiKey
      );

      if (!file_exists($tempFileLocation)) {
        Utility::throwError(400, "File not found at: $tempFileLocation");
      }
      $file = new \SplFileObject($tempFileLocation, 'r');
      $result = $apiInstance->scanFile($file);


      if (!$result->getCleanResult()) {
        Utility::throwError(401, 'Virus detected');
      }
    } catch (Exception $e) {
      Utility::showError($e);
    }
  }
}
