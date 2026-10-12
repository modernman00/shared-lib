<?php

declare(strict_types=1);

namespace Src;

use GuzzleHttp\Client;
use Src\Exceptions\ValidationException;
use Swagger\Client\Api\ScanApi;
use Swagger\Client\Configuration;
use Throwable;

// https://api.cloudmersive.com/php-client.asp
class VirusScan
{
    /**
     * Scan a temporary file for viruses via Cloudmersive Virus Scan API.
     *
     * @param string $tempFileLocation Path to temporary file on disk
     * @param ?string $apiKey Cloudmersive API key
     * @throws ValidationException If an infected file is detected
     */
    public function __construct(string $tempFileLocation, ?string $apiKey = null)
    {
        if (empty($apiKey)) {
            return;
        }

        if (!file_exists($tempFileLocation)) {
            return;
        }

        try {
            $setApiKey = Configuration::getDefaultConfiguration()->setApiKey('Apikey', $apiKey);

            $apiInstance = new ScanApi(
                new Client(['timeout' => 5.0, 'connect_timeout' => 3.0]),
                $setApiKey
            );

            $file = new \SplFileObject($tempFileLocation, 'r');
            $result = $apiInstance->scanFile($file);

            if ($result !== null && $result->getCleanResult() === false) {
                throw new ValidationException('Virus detected in uploaded file.');
            }
        } catch (ValidationException $e) {
            throw $e;
        } catch (Throwable $e) {
            // External API / connectivity failure (e.g. 401 invalid key, rate limit exceeded, 500 downtime, timeout).
            // Do NOT crash the upload flow or corrupt the HTTP response buffer with showError().
            try {
                LoggerFactory::getLogger()->warning('Cloudmersive virus scan unavailable or failed: ' . $e->getMessage(), [
                    'code' => $e->getCode(),
                    'file' => $tempFileLocation,
                ]);
            } catch (Throwable) {
                error_log('Cloudmersive virus scan unavailable or failed: ' . $e->getMessage());
            }
        }
    }
}
