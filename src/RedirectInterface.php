<?php

declare(strict_types=1);

namespace Src;

interface RedirectInterface
{
    public function redirect(string $uri, int $statusCode = 302): void;
}
