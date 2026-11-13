<?php

declare(strict_types=1);

namespace Src\Media;

use InvalidArgumentException;
use RuntimeException;

/**
 * R2StorageService
 *
 * Sovereign Cloudflare R2 / AWS S3 Edge Object Storage Broker.
 * Engineered by the Advanced Technology Group (ATG) under Olutobi (Deloitte) and Victor (CTO).
 *
 * Completely replaces heavy, memory-bloated AWS SDK dependencies with a native, pure-PHP
 * AWS Signature Version 4 (SigV4) pre-signed URL generator (~1ms execution).
 *
 * Shields Namecheap shared hosting from video upload bandwidth, CPU exhaustion, and disk limits
 * by offloading guest and host 4K binary streams directly to Cloudflare R2 (zero egress fees).
 */
class R2StorageService
{
    public const DEFAULT_EXPIRY_SECONDS = 3600; // 1 hour upload ticket

    /**
     * Check if Cloudflare R2 environment variables are populated.
     */
    public static function isConfigured(): bool
    {
        $accountId = trim((string) ($_ENV['R2_ACCOUNT_ID'] ?? getenv('R2_ACCOUNT_ID') ?? ''));
        $accessKey = trim((string) ($_ENV['R2_ACCESS_KEY_ID'] ?? getenv('R2_ACCESS_KEY_ID') ?? ''));
        $secretKey = trim((string) ($_ENV['R2_SECRET_ACCESS_KEY'] ?? getenv('R2_SECRET_ACCESS_KEY') ?? ''));
        $bucket = trim((string) ($_ENV['R2_BUCKET'] ?? getenv('R2_BUCKET') ?? ''));

        return $accountId !== '' && $accessKey !== '' && $secretKey !== '' && $bucket !== '';
    }

    /**
     * Generate an AWS Signature Version 4 pre-signed PUT URL for direct-to-R2 uploads.
     *
     * @param string $objectKey File path key in the bucket (e.g. "vault/EVT123/vlt_abc.mp4")
     * @param string $contentType MIME type (e.g. "video/mp4")
     * @param int $expiresInSeconds Validity window in seconds
     * @return array{is_cloud: bool, upload_url: string, public_url: string, object_key: string, method: string, headers: array<string, string>}
     */
    public static function generatePresignedUploadUrl(
        string $objectKey,
        string $contentType = 'video/mp4',
        int $expiresInSeconds = self::DEFAULT_EXPIRY_SECONDS
    ): array {
        $cleanKey = ltrim(trim($objectKey), '/');
        if ($cleanKey === '') {
            throw new InvalidArgumentException('Object key cannot be empty.');
        }

        if (!self::isConfigured()) {
            // Local fallback target when R2 credentials are not yet configured in .env
            return [
                'is_cloud'   => false,
                'upload_url' => '/api/video-vault/upload',
                'public_url' => '/public/uploads/' . $cleanKey,
                'object_key' => $cleanKey,
                'method'     => 'POST',
                'headers'    => [],
            ];
        }

        $accountId = trim((string) ($_ENV['R2_ACCOUNT_ID'] ?? getenv('R2_ACCOUNT_ID')));
        $accessKey = trim((string) ($_ENV['R2_ACCESS_KEY_ID'] ?? getenv('R2_ACCESS_KEY_ID')));
        $secretKey = trim((string) ($_ENV['R2_SECRET_ACCESS_KEY'] ?? getenv('R2_SECRET_ACCESS_KEY')));
        $bucket = trim((string) ($_ENV['R2_BUCKET'] ?? getenv('R2_BUCKET')));
        $region = 'auto'; // Cloudflare R2 standard region
        $service = 's3';

        $host = $accountId . '.r2.cloudflarestorage.com';
        $endpoint = 'https://' . $host . '/' . $bucket . '/' . $cleanKey;

        $timestamp = time();
        $amzDate = gmdate('Ymd\THis\Z', $timestamp);
        $dateStamp = gmdate('Ymd', $timestamp);

        $credentialScope = $dateStamp . '/' . $region . '/' . $service . '/aws4_request';

        // Query parameters for SigV4 presigned PUT request
        $queryParams = [
            'X-Amz-Algorithm'     => 'AWS4-HMAC-SHA256',
            'X-Amz-Credential'    => $accessKey . '/' . $credentialScope,
            'X-Amz-Date'          => $amzDate,
            'X-Amz-Expires'       => (string) $expiresInSeconds,
            'X-Amz-SignedHeaders' => 'host',
        ];

        ksort($queryParams);
        $canonicalQueryString = http_build_query($queryParams, '', '&', PHP_QUERY_RFC3986);

        // Canonical URI and Headers
        $canonicalUri = '/' . $bucket . '/' . implode('/', array_map('rawurlencode', explode('/', $cleanKey)));
        $canonicalHeaders = "host:" . strtolower($host) . "\n";
        $signedHeaders = "host";
        $payloadHash = 'UNSIGNED-PAYLOAD';

        $canonicalRequest = "PUT\n"
            . $canonicalUri . "\n"
            . $canonicalQueryString . "\n"
            . $canonicalHeaders . "\n"
            . $signedHeaders . "\n"
            . $payloadHash;

        $stringToSign = "AWS4-HMAC-SHA256\n"
            . $amzDate . "\n"
            . $credentialScope . "\n"
            . hash('sha256', $canonicalRequest);

        // Derive SigV4 signing key
        $kDate = hash_hmac('sha256', $dateStamp, 'AWS4' . $secretKey, true);
        $kRegion = hash_hmac('sha256', $region, $kDate, true);
        $kService = hash_hmac('sha256', $service, $kRegion, true);
        $kSigning = hash_hmac('sha256', 'aws4_request', $kService, true);

        $signature = hash_hmac('sha256', $stringToSign, $kSigning);

        $presignedUrl = $endpoint . '?' . $canonicalQueryString . '&X-Amz-Signature=' . $signature;
        $publicUrl = self::getPublicUrl($cleanKey);

        return [
            'is_cloud'   => true,
            'upload_url' => $presignedUrl,
            'public_url' => $publicUrl,
            'object_key' => $cleanKey,
            'method'     => 'PUT',
            'headers'    => [
                'Content-Type' => $contentType,
            ],
        ];
    }

    /**
     * Resolve public edge streaming/download URL for an object key.
     */
    public static function getPublicUrl(string $objectKey): string
    {
        $cleanKey = ltrim(trim($objectKey), '/');
        $publicDomain = rtrim((string) ($_ENV['R2_PUBLIC_DOMAIN'] ?? getenv('R2_PUBLIC_DOMAIN') ?? ''), '/');

        if ($publicDomain !== '') {
            return $publicDomain . '/' . $cleanKey;
        }

        $bucket = trim((string) ($_ENV['R2_BUCKET'] ?? getenv('R2_BUCKET') ?? ''));
        $accountId = trim((string) ($_ENV['R2_ACCOUNT_ID'] ?? getenv('R2_ACCOUNT_ID') ?? ''));

        if ($bucket !== '' && $accountId !== '') {
            return 'https://' . $accountId . '.r2.cloudflarestorage.com/' . $bucket . '/' . $cleanKey;
        }

        return '/public/uploads/' . $cleanKey;
    }
}
