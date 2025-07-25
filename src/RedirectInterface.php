<?php 

namespace Src;

interface RedirectInterface
{
    public function redirect(string $uri, int $statusCode = 302): void;
}
