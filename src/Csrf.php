<?php 

namespace Src;

class Csrf
{
    public static function token(): string
    {
        if (empty($_SESSION['token'])) {
            $_SESSION['token'] = urlencode(base64_encode(random_bytes(32)));
        }

        return $_SESSION['token'];
    }

    public static function validate(?string $token): bool
    {
        return isset($_SESSION['token']) &&
               hash_equals($_SESSION['token'], $token ?? '');
    }

    public static function input(): string
    {
        $token = self::token();
        return '<input type="hidden" name="token" value="' . $token . '">';
    }
}