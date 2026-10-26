<?php

declare(strict_types=1);

namespace Src\Vision;

/** Default transport: cURL with certificate verification on and no redirects. */
final class CurlVisionTransport implements VisionTransport
{
    public function post(string $url, array $headers, string $body, int $connectTimeout, int $timeout): array
    {
        $ch = curl_init($url);
        if ($ch === false) {
            throw new VisionRequestFailedException('Could not start the request to the vision service.');
        }

        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => $connectTimeout,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        ]);

        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errno = curl_errno($ch);
        if (PHP_VERSION_ID < 80500) {
            @curl_close($ch);
        }

        if ($errno === CURLE_OPERATION_TIMEDOUT) {
            throw new VisionTimeoutException('The vision service took too long to answer.');
        }
        if ($response === false || $errno !== 0) {
            // The cURL message can contain the URL, so it is deliberately not passed on.
            throw new VisionRequestFailedException('Could not reach the vision service.');
        }

        return ['status' => $status, 'body' => (string) $response];
    }
}
