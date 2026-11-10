<?php

declare(strict_types=1);

namespace Src;

use GuzzleHttp\Client;
use Src\Exceptions\ValidationException;
use Src\Security\SovereignThreatEngine;
use Swagger\Client\Api\ScanApi;
use Swagger\Client\Configuration;
use Throwable;

/**
 * VirusScan
 *
 * Scans files for viruses and security threats.
 * Powered primarily by the Advanced Technology Group (ATG) SovereignThreatEngine,
 * with backward-compatible fallback to Cloudmersive Virus Scan API when configured.
 */
class VirusScan
{
    /**
     * Scan a temporary file for viruses and malicious payloads.
     *
     * @param string $tempFileLocation Path to temporary file on disk
     * @param ?string $apiKey Cloudmersive API key (optional fallback)
     * @throws ValidationException If an infected file or security violation is detected
     */
    public function __construct(string $tempFileLocation, ?string $apiKey = null)
    {
        if (!file_exists($tempFileLocation)) {
            return;
        }

        // Tier 1: Always execute sovereign threat inspection
        SovereignThreatEngine::scan($tempFileLocation);

        // Tier 2: Cloudmersive scan if API key is explicitly configured
        if (empty($apiKey)) {
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

    /**
     * Convenience static sovereign scanner returning a full inspection report.
     *
     * @param string $path Path to file on disk
     * @return array<string, mixed>
     */
    public static function scanFile(string $path): array
    {
        return SovereignThreatEngine::scan($path);
    }
}
