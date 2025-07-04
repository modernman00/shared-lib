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
  public function __construct($tempFileLocation, $apiKey)
  {

    try {

      $setApiKey = Configuration::getDefaultConfiguration()->setApiKey('Apikey', $apiKey);

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
