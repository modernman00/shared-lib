<?php

namespace Src;

class CorsHandler
{
  public static function setHeaders(

    string $contentType = 'application/json; charset=UTF-8',
    string $allowedMethods = 'POST',
    int $maxAge = 3600,
    array $allowedHeaders = ['Content-Type', 'Access-Control-Allow-Headers', 'Authorization', 'X-Requested-With', 'X-XSRF-TOKEN']
  ): void {
    $origin =  getenv('APP_URL');

    header("Access-Control-Allow-Origin: " . $origin);
    header("Content-Type: " . $contentType);
    header("Access-Control-Allow-Methods: " . $allowedMethods);
    header("Access-Control-Max-Age: " . $maxAge);
    header("Access-Control-Allow-Headers: " . implode(', ', $allowedHeaders));
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: DENY");
  }
}
