<?php

declare(strict_types=1);

namespace Src;

use Src\Exceptions\TooManyRequestsException;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class Limiter extends Db
{
    public static $argLimiter;
    public static $ipLimiter;

    private const PROFILES = [
        'login' => ['attempts' => 5, 'window' => 15 * 60, 'message' => 'Too many login attempts. Please try again in {minutes} minutes.'],
        'post' => ['attempts' => 30, 'window' => 5 * 60, 'message' => 'You are posting too rapidly. Please take a breather for {minutes} minutes.'],
        'comment_reactions' => ['attempts' => 60, 'window' => 5 * 60, 'message' => 'You are reacting too rapidly. Please wait {minutes} minutes.'],
        'default' => ['attempts' => 30, 'window' => 5 * 60, 'message' => 'Too many requests. Please try again in {minutes} minutes.'],
    ];

    /**
     * Applies rate limiting to a given argument and the user's IP address.
     *
     * @param string $arg the argument to be rate-limited, typically an email address or table name
     * @param string $action Optional action type to determine rate limit profile (e.g. 'login', 'post')
     *
     * @throws TooManyRequestsException if the limit is exceeded
     */
    public static function limit(string $arg, string $action = 'default')
    {
        if (\isTestEnv()) {
            return;
        }
        try {
            // Infer action if it's default
            if ($action === 'default') {
                if (filter_var($arg, FILTER_VALIDATE_EMAIL) || str_contains($arg, '@')) {
                    $action = 'login';
                } elseif (isset(self::PROFILES[$arg])) {
                    $action = $arg;
                }
            }
            
            $profile = self::PROFILES[$action] ?? self::PROFILES['default'];
            $attempts = $profile['attempts'];
            $window = $profile['window'];
            
            $ipAddress = Utility::getUserIpAddr();

            $db = Db::connect2();
            $storage = new PdoStorage($db);
            $rateLimiterFactory = new RateLimiterFactory([
                'id' => $action,
                'policy' => 'fixed_window',
                'limit' => $attempts,
                'interval' => sprintf('%d seconds', $window),
            ], $storage);

            // remove $ from $arg
            $argKey = str_replace('$', '', $arg);

            // Check rate limit
            self::$argLimiter = $rateLimiterFactory->create("$argKey:$arg");
            self::$ipLimiter = $rateLimiterFactory->create("ip:$action:{$ipAddress}");

            $emailLimit = self::$argLimiter->consume(1);
            $ipLimit = self::$ipLimiter->consume(1);

            if (!$emailLimit->isAccepted() || !$ipLimit->isAccepted()) {
                // For fixed_window, calculate retry time based on the window interval
                $currentTime = time();
                $windowStart = $currentTime - ($currentTime % $window);
                $nextWindow = $windowStart + $window;
                $retryAfter = max(1, $nextWindow - $currentTime); // Ensure at least 1 second

                header('Retry-After: ' . $retryAfter);
                $msg = str_replace('{minutes}', (string)ceil($retryAfter / 60), $profile['message']);
                throw new TooManyRequestsException($msg);
            }
        } catch (\Throwable $e) {
            throw $e; // Let the exception bubble up to be handled by the caller
        }
    }
}
