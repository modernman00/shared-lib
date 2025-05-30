<?php

namespace Src;

use Src\PdoStorage;


use Src\Exceptions\TooManyRequestsException;
use Symfony\Component\RateLimiter\RateLimiterFactory;



class Limiter extends Db
{

    private const int MAX_ATTEMPTS = 5;
    private const int TIME_WINDOW = 15 * 60;
    public static $argLimiter;
    public static $ipLimiter;


    /*************  ✨ Windsurf Command ⭐  *************/
    /**
     * Applies rate limiting to a given argument and the user's IP address.
     * Utilizes a fixed window policy to track the number of attempts within a specified time window.
     * If the limit is exceeded, sets a 'Retry-After' header indicating when the next attempt is allowed.
     *
     * @param string $arg The argument to be rate-limited, typically an email address in this format $email.
     * @throws TooManyRequestsException if the number of attempts exceeds the allowed limit within the time window.
     */

    /*******  e4bdaa72-218f-4b19-b900-f7a504ff2c2a  *******/
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
            $argKey = str_replace('$', '', "$arg");

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
