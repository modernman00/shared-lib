<?php

declare(strict_types=1);

namespace Src\Vision;

/**
 * Base for every failure of the vision extractors. Messages are safe to show a signed-in user:
 * they never contain the API key, the request URL, file paths or the model's raw output.
 * Extractors throw these instead of returning simulated data (RFC 0003).
 */
class VisionException extends \RuntimeException
{
}
