<?php

namespace Helper\Middleware;

use Helper\classes\CSP;


class CSPMiddleware
{
    public static function handle(array &$data): void
    {
        $data['nonce'] = CSP::apply();
    }
}