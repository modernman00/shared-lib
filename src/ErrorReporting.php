<?php 

declare(strict_types=1);

namespace Src;

class ErrorReporting
{
    public static function unauthorized401(): void
    {
        Utility::view($_ENV['error401ViewPath']); 
    }

    public static function forbidden403(): void
    {

         Utility::view($_ENV['error403ViewPath']);
    }

    public static function notFound404(): void
    {
         Utility::view($_ENV['error404ViewPath']);
    }

    public static function tooManyRequests429(): void
    {
         Utility::view($_ENV['error429ViewPath']);
    }

    public static function serverError500(): void
    {
         Utility::view($_ENV['error500ViewPath']);
    }

}
