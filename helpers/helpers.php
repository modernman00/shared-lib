<?php

declare(strict_types=1);


use helpers\classes\Blade;
use helpers\Middleware\CSPMiddleware;
use Monolog\Level;
use Monolog\Logger;
use Src\Data\EmailData;
use Src\Exceptions\ForbiddenException;
use Src\LoggerFactory;
use Src\Select;
use Src\SendEmail;

// use RuntimeException;

if (!function_exists('view2')) {
    /**
     * Renders a view with CSP middleware enabled.
     */
    function view2(string $viewFile, array $data = [])
    {
        viewBuilderWithCSP($viewFile, $data, ['enable' => true]);
    }
}

if (!function_exists('viewBuilderWithCSP')) {
    /**
     * Internal helper for rendering views with CSP options.
     */
    function viewBuilderWithCSP(string $viewFile, array $data = [], array $cspOptions = [])
    {
        try {
            CSPMiddleware::handle($data);
            view($viewFile, $data);
        } catch (\Throwable $e) {
            showError($e);
        }
    }
}

if (!function_exists('view')) {
    /**
     * Renders a BladeOne template.
     * Supports both dot notation (msg.user) and path notation (msg/user).
     */
    function view(string $path, array $data = [])
    {
        try {
            $blade = Blade::get();
            $normalizedPath = str_replace(['/', '\\'], '.', $path);
            echo $blade->run($normalizedPath, $data);
        } catch (\Throwable $e) {
            showError($e);
        }
    }
}

function p($data): void
{
    if ($data === []) {
        echo '<pre>';
        var_export($data);
        echo '</pre>';
    } else {
        echo '<pre>';
        print_r($data);
        echo '</pre>';
    }
}

function loggedDetection(string $filename, string $receivingEmail): bool
{
    //TODO send text to the user with the code
    EmailData::defineConstants('admin');
    $getIp = getUserIpAddr();
    $msg = "Hello, <br><br> This is a notification that a <strong>logged -in</strong> has been detected from this file : $filename at this time: " . date('h:i:sa') . "  and with this IP address: $getIp  <br><br>  IT Security Team";

    SendEmail::sendEmail($receivingEmail, 'logged-in', 'LOGGED-IN DETECTION', $msg);

    return true;
}

/**
 * compare two variable or use to verify.
 */
function compare($var1, $var2): bool
{
    if ($var1 != $var2) {
        return false;
    }

    return true;
}

// GET IP ADDRESS

function getUserIpAddr(): string
{
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        //ip from share internet
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        //ip pass from proxy
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'];
    }

    return $ip;
}

/**
 * $month is the terms
 * while the date is the month.
 *
 * @return string[]
 *
 * @psalm-return array{fullDate: string, dateFormat: string}
 */
function addMonthsToDate($months, $date): array
{
    $dt = new \DateTime($date, new \DateTimeZone('Europe/London'));
    $oldDay = $dt->format('d');
    $dt->add(new \DateInterval("P{$months}M"));
    $newDay = $dt->format('d');
    if ($oldDay != $newDay) {
        // Check if the day is changed, if so we skipped to the next month.
        // Substract days to go back to the last day of previous month.
        $dt->sub(new \DateInterval('P' . $newDay . 'D'));
    }
    $newDay3 = $dt->format('Y-m-d');
    $newDay2 = $dt->format(" jS \of F Y"); // 2016-02-29

    //  return $newDay2;
    return ['fullDate' => $newDay2, 'dateFormat' => $newDay3];
}

function cleanSession($x): string|null|int
{
    if ($x) {
        $z = preg_replace(
            pattern: '/[^0-9A-Za-z@.]/',
            replacement: '',
            subject: $x
        );

        return htmlspecialchars(trim($x), ENT_QUOTES, 'UTF-8');
    } else {
        return null;
    }
}

/**
 * Display or log an error, using Monolog for logging.
 *
 * @param \Throwable $th The exception to handle
 * @param Logger $logger Monolog logger instance
 * @param bool $returnResponse If true, return the response instead of echoing it
 *                             ENSURE YOU HAVE THESE ENVIRONMENT VARIABLES SET:
 *                             - LOGGER_NAME: The name of the logger channel (e.g., 'app', 'errors')
 *                             - USER_EMAIL: The email address to send error alerts from
 *                             - MAILER_DSN: The SMTP DSN for sending emails
 *
 * @return string|null JSON response if $returnResponse is true
 *
 * @example - Utility::showError2($e, LoggerFactory::getLogger());
 */
function showError2(\Throwable $th, Logger $logger): ?string
{
    $isLocal = isLocalEnv();

    // Determine HTTP status code
    $statusCode = ($th instanceof \Src\Exceptions\HttpException)
        ? $th->getStatusCode()
        : ((int) $th->getCode() >= 100 && (int) $th->getCode() <= 599 ? (int) $th->getCode() : 500);

    // Set HTTP response code
    if (!headers_sent()) {
        http_response_code($statusCode);
    } else {
        $logger->warning('⚠️ Headers already sent, cannot set HTTP response code: ' . $statusCode);
    }

    // Map status codes or exception types to Monolog log levels
    $logLevel = match (true) {
        $statusCode >= 500 => Level::Critical, // Server errors (500-599)
        $th instanceof \Src\Exceptions\ForbiddenException => Level::Alert, // Security-related
        $th instanceof \Src\Exceptions\InvalidArgumentException => Level::Error, // Input errors
        $th instanceof \Src\Exceptions\NotFoundException => Level::Warning, // Input errors
        $th instanceof \Src\Exceptions\UnauthorisedException => Level::Warning, // Input errors
        $th instanceof \Src\Exceptions\HttpException => Level::Warning, // Input errors
        $th instanceof \Src\Exceptions\TooManyLoginAttemptsException => Level::Warning, // Input errors
        $th instanceof \Src\Exceptions\TooManyRequestsException => Level::Warning, // Input errors
        $th instanceof \Src\Exceptions\ValidationException => Level::Warning, // Input errors
        $th instanceof \Src\Exceptions\CaptchaVerificationException => Level::Warning, // Input errors
        $th instanceof \Src\Exceptions\RecaptchaCheatingException => Level::Alert,
        $th instanceof \Src\Exceptions\RecaptchaFailedException || $th instanceof \Src\Exceptions\InvalidArgumentException => Level::Error,
        $th instanceof \Src\Exceptions\RecaptchaBrokenException => Level::Critical,
        default => Level::Error // Default for other exceptions
    };

    // Log the error with context
    $logger->log($logLevel, '🚨 Application Error', [
        'message' => $th->getMessage(),
        'code' => $statusCode,
        'file' => $th->getFile(),
        'line' => $th->getLine(),
        'trace' => $th->getTraceAsString(),
    ]);

    // 5. Prepare a nice message for the user or developer
    $errorMessage = $th instanceof \Src\Exceptions\HttpException
        ? $th->getMessage()
        : ($isLocal
            ? "Error on line {$th->getLine()} in {$th->getFile()}: {$th->getMessage()}"
            : 'An unexpected error occurred.');

    // 6. Return or display the JSON error message
    // Your API response
    header('Content-Type: application/json');
    $response = json_encode(['message' => $errorMessage, 'code' => $statusCode, 'status' => 'error'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return $response;
}

function showError($th): void
{
    $error = showError2($th, LoggerFactory::getLogger());
    if ($error) {
        echo $error;
    }
    exit();
}
// FUNCTION TO SEND TEXT TO PHONE

function sendText($message, $numbers, $sender): void
{
    $apiKey = urlencode('y9X1o/Ko6M4-MCz6zJfBeGMv9TMOLG54k0c53EfCfo');
    $numbers = [$numbers];
    $sender = urlencode($sender);
    $message = rawurlencode($message);
    $numbers = implode(',', $numbers);
    // Prepare data for POST request
    $data = ['apikey' => $apiKey, 'numbers' => $numbers, 'sender' => $sender, 'message' => $message];
    $ch = curl_init('http://api.txtlocal.com/send/');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch); // This is the result from the API
    curl_close($ch);
    echo $result;
}

// ADD COUNTRY CODE

function addCountryCode($mobile, $code): string
{
    $telephone = $mobile;
    $telephone = substr($telephone, 1);
    $telephone = $code . $telephone;

    return $telephone;
}

/**
 * return a bulma panel row.
 */
function changeToJs($variableName, $variable): void
{
    echo "<script> const $variableName = $variable </script>";
}

/**
 * @param mixed $time that is the full date and time e.g 2010-04-28 17:25:43
 *
 * @return string | bool
 */
function humanTiming($time)
{
    try {
        $time = strtotime($time);
        $time = time() - $time; // to get the time since that moment
        $time = ($time < 1) ? 1 : $time;
        $tokens = [
            31536000 => 'year',
            2592000 => 'month',
            604800 => 'week',
            86400 => 'day',
            3600 => 'hour',
            60 => 'minute',
            1 => 'second',
        ];

        foreach ($tokens as $unit => $text) {
            if ($time < $unit) {
                continue;
            }
            $numberOfUnits = floor($time / $unit);

            return $numberOfUnits . ' ' . $text . (($numberOfUnits > 1) ? 's' : '');
        }

        return 'just now'; // Default fallback if time is not within any unit
    } catch (\Throwable $th) {
        return showError($th);

        return false;
    }
}

function milliSeconds(): string
{
    $microtime = microtime();
    $comps = explode(' ', $microtime);

    // Note: Using a string here to prevent loss of precision
    // in case of "overflow" (PHP converts it to a double)
    return sprintf('%d%03d', $comps[1], $comps[0] * 1000);
}

function msgSuccess(int $code, mixed $msg, mixed $token = null): void
{
    http_response_code($code);
    echo json_encode([
        'message' => $msg,
        'token' => $token,
        'status' => 'success',
    ]);
}
/**
 * only use in the catch block.
 *
 * @param int $code
 * @param mixed $msg
 */
function msgException(int $code, mixed $msg): void
{
    http_response_code($code);
    echo json_encode([
        'message' => $msg,
        'status' => 'error',
        'code' => $code,
    ]);
}

function throwError(int $code, mixed $msg): void
{
    throw new \Exception($msg, $code);
}

/**
 * Summary of checkInput.
 *
 * @param mixed $data
 *
 * @return array|string|null
 */
function checkInput($data)
{
    if ($data !== null) {
        $data = (string)$data;
        $data = trim($data);
        $data = strip_tags($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        $data = preg_replace('/[^\p{L}\p{N}\p{P}\p{Z}\p{So}]/u', '', $data);

        return $data;
    } else {
        msgException(406, 'problem with your entry');
    }
}

function checkInputImage($data)
{
    if ($data !== null) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        $data = strip_tags($data);
        $data = preg_replace('/[^a-zA-Z0-9\-\_\.\s]/', '', $data);

        return $data;
    } else {
        msgException(406, 'image name not well formed');

        return null;
    }
}

function checkInputEmail(string $data): string
{
    if ($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        $data = strip_tags($data);
        $data = filter_var($data, FILTER_SANITIZE_EMAIL);
    }

    return $data;
}

// check if it is local env and return true or false
function isLocalEnv(): bool
{
    $env = $_ENV['APP_ENV'] ?? '';

    return in_array($env, ['local', 'development'], true);
}

/**
 * Get the base URL of the application.
 */
function baseUrl(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';

    return $scheme . '://' . $_SERVER['HTTP_HOST'];
}

/**
 * Generate a full URL from a relative path.
 */
function url(string $path = ''): string
{
    return rtrim(baseUrl(), '/') . '/' . ltrim($path, '/');
}

/**
 * Generate the full URL to an asset in the /public directory.
 */
function asset(string $path = ''): string
{
    return url('public/' . ltrim($path, '/'));
}

/**
 * Generate a URL from a named route with optional parameters.
 */
function route(string $name, array $params = []): string
{
    $uri = $name;
    foreach ($params as $key => $val) {
        $uri = str_replace("{{$key}}", urlencode($val), $uri);
    }

    return url($uri);
}

/**
 * Retrieve a value from the old POST data or return default.
 */
function old(string $key, $default = ''): mixed
{
    return $_POST[$key] ?? $default;
}



/**


 * Redirect the user to a given path and stop execution.
 */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/**
 * destroy all cookies.
 */
function destroyCookie(): void
{
    foreach ($_COOKIE as $key => $value) {
        setcookie($key, '', time() - 3600);
    }
}

/**
 * Return the full URL to an asset in the /public/build directory.
 * If in development, points to the Vite dev server.
 * If not in development, uses the Vite manifest to determine the correct URL.
 * @param string $path The relative path to the asset.
 * @return string The full URL to the asset.
 * @throws RuntimeException If the Vite manifest is not found, or if the given path is not found in the manifest.
 */
if (!function_exists('viteAsset')) {
    /**
     * Resolves Vite assets using the project root.
     */
    function viteAsset(string $path): string
    {
        $isDev = ($_ENV['APP_ENV'] ?? 'local') === 'local';

        if ($isDev) {
            return "http://localhost:5173/$path";
        }

        static $manifest = null;

        if ($manifest === null) {
            $root = defined('BASE_PATH') ? BASE_PATH : dirname(__DIR__, 4);
            $manifestPath = "$root/public/build/manifest.json";
            
            if (!file_exists($manifestPath)) {
                throw new RuntimeException('Vite manifest not found at: ' . $manifestPath);
            }

            $manifestContent = file_get_contents($manifestPath);
            $manifest = json_decode($manifestContent, true);
        }

        if (!isset($manifest[$path])) {
            throw new RuntimeException("Path {$path} not found in Vite manifest.");
        }

        return '/public/build/' . $manifest[$path]['file'];
    }
}

function logger(): Logger
{
    return LoggerFactory::getLogger();
}

/**
 * Send a POST request with Guzzle.
 *
 * @param string $url
 * @param array $formData
 * @param array $options
 *
 * @return array|null
 */
function sendPostRequest(string $url, array $formData, array $options = []): ?array
{
    try {
        static $client = null;

        if ($client === null) {
            $client = new \GuzzleHttp\Client();
        }

        // Merge default options
        $requestOptions = array_merge([
            'form_params' => $formData,
            'timeout' => 5,
        ], $options);

        $response = $client->post($url, $requestOptions);

        $body = $response->getBody()->getContents();
        $data = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \Src\Exceptions\RecaptchaException('Invalid JSON: ' . json_last_error_msg());
        }

        return $data;
    } catch (\Exception $e) {
        showError($e);

        return null;
    }
}

function checkEmailExist($email): array|int|string
{
    $query = Select::formAndMatchQuery(
        selection: 'SELECT_COUNT_ONE',
        table: $_ENV['DB_TABLE_LOGIN'],
        identifier1: 'email'
    );
    $result = Select::selectFn2(query: $query, bind: [$email]);

    return $result ? $result[0] : 0;
}

/**
 * Hashes a given password using bcrypt with a specified cost.
 * 
 * @param string $password The password to hash
 * @param int $cost The cost of the hash (default is 12)
 * 
 * @return string The hashed password
 */
function hashPassword($password, $cost = 12)
{
    return password_hash($password, PASSWORD_DEFAULT, ['cost' => $cost]);
}

// unset post data 
function unsetPostData($data, $keysToRemove)
{

    if ($keysToRemove) {
        foreach ($data as $key => $value) {
            // Remove key if it matches
            if (in_array($key, $keysToRemove, true)) {
                unset($data[$key]);
                continue;
            }

            // If value is an array, recurse
            if (is_array($value)) {
                $data[$key] = unsetPostData($value, $keysToRemove);
            }
        }
        return $data;
    } else {
        return $data;
    }
}

/**
 * Recursively hashes all passwords in an array using the given hash function.
 * 
 * This function will hash any string value that has a key of 'password'.
 * It will also recurse into any sub-arrays that may contain passwords.
 * 
 * @param array $data The array to hash passwords in
 * @return array The array with hashed passwords
 */
function hashPasswordsInArray(array $data): array
{
    foreach ($data as $key => $value) {
        // If the key matches and the value is a string, hash it
        if (in_array($key, ['password'], true) && is_string($value)) {
            $data[$key] = \hashPassword($value); // Replace with your actual hash function
            continue;
        }

        // If value is an array, recurse
        if (is_array($value)) {
            $data[$key] = hashPasswordsInArray($value);
        }
    }

    return $data;
}

/**
 * Prevents abuse by limiting the rate at which a user can react to
 * certain stimuli. If the user reacts too quickly, an exception is thrown.
 *
 * This function should be used in conjunction with a try-catch block to
 * handle the exception that is thrown when abuse is detected.
 *
 * @throws ForbiddenException If the user is reacting too quickly.
 */
function preventAbuseTogglin()
{
    $lastReactionTime = $_SESSION['last_reaction_time'] ?? 0;
    if (time() - $lastReactionTime < 2) {
        throw new ForbiddenException('You are reacting too quickly. Please slow down.');
    }
    $_SESSION['last_reaction_time'] = time();
}

// send text to phone using twilio api

