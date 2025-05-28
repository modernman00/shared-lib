<?php

namespace App\shared;

use App\shared\PdoStorage;


use App\shared\Exceptions\TooManyRequestsException;
use Symfony\Component\RateLimiter\RateLimiterFactory;



class Limiter extends Db
{

    private const int MAX_ATTEMPTS = 5;
    private const int TIME_WINDOW = 15 * 60;
    public static $argLimiter;
    public static $ipLimiter;


    public static function limit($arg)
    {

        try {
            $ipAddress = Utility::getUserIpAddr();

            $db = Db::connect2();
            $storage = new PdoStorage($db);
            $rateLimiterFactory = new RateLimiterFactory([
                'id' => 'login',
                'policy' => 'fixed_window',
                'limit' => self::MAX_ATTEMPTS,
                'interval' => sprintf('%d seconds', self::TIME_WINDOW),
            ], $storage);

            // remove $ from $email 
            $argKey = str_replace('$', '', $arg);

            // Check rate limit
            self::$argLimiter = $rateLimiterFactory->create("$argKey:$arg");
            self::$ipLimiter = $rateLimiterFactory->create("ip:{$ipAddress}");

            $emailLimit = self::$argLimiter->consume(1);
            $ipLimit = self::$ipLimiter->consume(1);

            if (!$emailLimit->isAccepted() || !$ipLimit->isAccepted()) {
                // For fixed_window, calculate retry time based on the window interval
                $currentTime = time();
                $windowStart = $currentTime - ($currentTime % self::TIME_WINDOW);
                $nextWindow = $windowStart + self::TIME_WINDOW;
                $retryAfter = max(1, $nextWindow - $currentTime); // Ensure at least 1 second

                header('Retry-After: ' . $retryAfter);
                throw new TooManyRequestsException('Too many login attempts. Please try again in ' . ceil($retryAfter / 60) . ' minutes.');
            }
        } catch (\Throwable $e) {

            Utility::showError($e);
        }
    }
}
