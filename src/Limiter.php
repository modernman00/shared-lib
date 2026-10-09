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
     * Resets both argument and IP limiters safely, handling null states.
     */
    public static function resetAll(): void
    {
        self::$argLimiter?->reset();
        self::$ipLimiter?->reset();
    }

    /**
     * The two buckets a request counts against.
     *  - arg: for login it is the account (email), so guessing one account from many
     *    devices still hits one limit. For anything else (a table name from
     *    SubmitPostData, an action name) it is this person: connection + session.
     *    It used to be the bare name, which made one bucket for the whole platform.
     *  - ip: this connection, per action (as before). Deliberately not per $arg:
     *    callers such as the recovery-code check pass the guessed value as $arg, and
     *    a per-$arg bucket would let each guess start a fresh count. Dropping the
     *    session cookie does not reset it.
     *
     * @return array{arg: string, ip: string}
     */
    public static function bucketKeys(string $arg, string $action, string $ipAddress, string $sessionId): array
    {
        $argKey = str_replace('$', '', $arg);
        if ($action === 'login') {
            return ['arg' => "{$argKey}:{$argKey}", 'ip' => "ip:{$action}:{$ipAddress}"];
        }
        return [
            'arg' => "{$argKey}:{$ipAddress}:{$sessionId}",
            'ip' => "ip:{$action}:{$ipAddress}",
        ];
    }

    /**
     * The Cypress header skips limits for browser tests on developer machines only.
     * Anyone can send a header, so in production, staging or an unknown environment
     * it is ignored.
     *
     * @param array<string, mixed> $server
     */
    public static function testHeaderSkipsLimits(array $server, string $appEnv): bool
    {
        if (!isset($server['HTTP_X_CYPRESS_TEST'])) {
            return false;
        }
        return in_array(strtolower(trim($appEnv)), ['local', 'development', 'testing'], true);
    }

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
        $appEnv = $_ENV['APP_ENV'] ?? getenv('APP_ENV');
        if ((function_exists('isTestEnv') && \isTestEnv()) || self::testHeaderSkipsLimits($_SERVER, is_string($appEnv) ? $appEnv : '')) {
            $noop = new class {
                public function reset(): void {}
                public function consume(int $tokens = 1): object {
                    return new class {
                        public function isAccepted(): bool { return true; }
                    };
                }
            };
            self::$argLimiter ??= $noop;
            self::$ipLimiter ??= $noop;
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

            $keys = self::bucketKeys($arg, $action, $ipAddress, session_id() ?: 'no_session');

            // Check rate limit
            self::$argLimiter = $rateLimiterFactory->create($keys['arg']);
            self::$ipLimiter = $rateLimiterFactory->create($keys['ip']);

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
