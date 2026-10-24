<?php

declare(strict_types=1);

namespace Src\Vision;

/** The provider did not answer in time. Callers should offer manual entry; do not retry in a loop. */
final class VisionTimeoutException extends VisionException
{
}
