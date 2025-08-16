<?php

declare(strict_types=1);

namespace Src\functionality;

use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\Logger;
use Src\CorsHandler;
use Src\LoggedOut;
use Src\Utility;

class LogoutFunctionality 
{
    public static function signout(array $redirect): void
    {
        // printArr($redirect);
        $redirect = $redirect['redirect'] ?? '/'; // Default to 'home' if not provided

        Utility::checkInput($redirect);

        try {
            CorsHandler::setHeaders();

            // Setup logger
            $logger = new Logger('logout');
            $logger->pushHandler(new StreamHandler(
                __DIR__ . $_ENV['LOGGER_PATH'],
                Level::Debug
            ));

            $logoutService = new LoggedOut($logger);
            $logoutService->logout($redirect);
        } catch (\Throwable $e) {
            Utility::showError($e);
           
        }
    }
}
