<?php

declare(strict_types=1);

namespace Src\Vision;

/**
 * One HTTPS POST. A seam so extractors can be tested (and chaos-tested) without the network.
 */
interface VisionTransport
{
    /**
     * @param list<string> $headers "Name: value" lines
     * @return array{status: int, body: string}
     * @throws VisionTimeoutException when the provider does not answer in time
     * @throws VisionRequestFailedException on a connection-level failure
     */
    public function post(string $url, array $headers, string $body, int $connectTimeout, int $timeout): array;
}
