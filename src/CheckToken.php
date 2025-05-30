<?php

namespace Src;

use Src\Exceptions\UnauthorisedException;

class CheckToken
{

    /**
     * Undocumented function
     *
     * @param [type] $token  this token is the same for session and post
     *
     * @return void
     *
     * @psalm-param 'token' $token
     */
    public static function tokenCheck(): void
    {
        // try {
        $tokenCheck = $_SESSION['csrf_token'] ?? "bad";
        $postToken = $_POST['_token'] ?? "bad";
        // invalidate $token stored in session
        unset($_SESSION['csrf_token']);
        if ($tokenCheck != $postToken) {

            throw new UnauthorisedException("We are not familiar with the nature of your activities.");
        }
    }
}
