<?php 

namespace Src;

final class ErrorController
{
    public static function unauthorized401(): void
    {
        Utility::view2($_ENV['error401']); 
    }

    public static function forbidden403(): void
    {

         Utility::view2($_ENV['error403']);
    }

    public static function notFound404(): void
    {
         Utility::view2($_ENV['error404']);
    }

    public static function tooManyRequests429(): void
    {
         Utility::view2($_ENV['error429']);
    }

    public static function serverError500(): void
    {
         Utility::view2($_ENV['error500']);
    }

}
