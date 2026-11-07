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
        // Previously Utility::checkInput($redirect) was called and its result discarded, so the
        // value was never validated. Only same-site relative paths are accepted now.
        $redirect = self::safeRedirectPath($redirect['redirect'] ?? '/');

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

    /**
     * Normalise a post-logout redirect to a safe, same-site relative path.
     * Anything else (absolute URLs, protocol-relative "//host", backslashes, control
     * characters, scripts) falls back to "/".
     */
    public static function safeRedirectPath(mixed $value): string
    {
        if (!is_string($value)) {
            return '/';
        }
        $path = trim($value);
        if ($path === '') {
            return '/';
        }
        if (!str_starts_with($path, '/')) {
            $path = '/' . $path;
        }
        if (str_starts_with($path, '//') || preg_match('#^/[A-Za-z0-9/_\-.~?=&%+]*$#', $path) !== 1) {
            return '/';
        }
        return $path;
    }
}
