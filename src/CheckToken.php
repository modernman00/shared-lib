<?php

declare(strict_types=1);

namespace Src;

use Src\Exceptions\UnauthorisedException;

class CheckToken
{
    /**
     * Undocumented function.
     *
     * @param $token the post token   this token is the same for session and post
     *
     * @psalm-param 'token' $token
     */
    public static function tokenCheck($token): void
    {
        // try {
        $sessionToken = $_SESSION['token'] ?? '';
        $postToken = $token;
        $headerToken = $_SERVER['HTTP_X_XSRF_TOKEN'] ?? '';

        $valid = false;
        if ($sessionToken && hash_equals($sessionToken, $headerToken)) {
            $valid = true;
        } elseif ($sessionToken && hash_equals($sessionToken, $postToken)) {
            $valid = true;
        }

        if (!$valid) {
            throw new UnauthorisedException('We are not familiar with the nature of your activities.');
        }
    }
}
