<?php

namespace helpers\Middleware;

use helpers\classes\CSP;


class CSPMiddleware
{
    public static function handle(array &$data): void
    {
        $data['nonce'] = CSP::apply();
    }
}