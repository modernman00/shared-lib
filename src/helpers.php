<?php


use Src\Data\EmailData;
use Src\SendEmail;
use eftec\bladeone\BladeOne;
use Src\LoggerFactory;
use Monolog\Logger;
// use RuntimeException;



  /**
   * @param string $viewFile
   * @param array $data
   * @param array $cspOptions
   *   - enable: boolean (default: true)
   *   - report_only: boolean (default: true)
   *   - extra: array of custom CSP directives
   * @return string
   *   The rendered view with CSP headers enabled
   */
  function view2(string $viewFile, array $data = [])
  {
    return viewBuilderWithCSP($viewFile, $data, ['enable' => true]);
  }

  /**
 * echo view('checkout', ['cart' => $cartItems], ['enable' => true,
 *    'report_only' => false, // Enforce CSP (not just report)
 *    'extra' => [
 *        "script-src https://js.stripe.com",
 *        "frame-src https://js.stripe.com"
 *    ]
 * ]);
 * 
 * <!-- Script Tag -->
 *  <script nonce="{{ $csp_nonce }}">
 *    window.userData = @json(auth()->user());
 * </script>

 * <!-- External Script -->
 * <script 
 *    nonce="{{ $csp_nonce }}" 
 *    src="https://platform.sharethis.com/loader.js"
 *    defer
 * ></script>

 * <!-- Inline Styles -->
 * <style nonce="{{ $csp_nonce }}">
 *    .featured { background: #f0f8ff; }
 * </style>
 * 
 * Check browser console for blocked resources. Examine /csp-report-log endpoint.Temporarily add 'unsafe-inline' to diagnose: 'extra' => ["script-src 'unsafe-inline'"]
 * 
 * Phase Out unsafe-inline.
 * Move all inline scripts to external files
 * Use nonce-{{ $csp_nonce }} for critical inline code
 * 
 * Implement report-to
 * 'extra' => ["report-to csp-endpoint"]
 */

function viewBuilderWithCSP(string $viewFile, array $data = [], array $cspOptions = [])
{
    try {
        // ===== 1. CSP SETUP =====
        $cspEnabled = $cspOptions['enable'] ?? true;
        $reportOnly = $cspOptions['report_only'] ?? true;
        $nonce = '';

        if ($cspEnabled) {
            // Generate cryptographic nonce
            $nonce = bin2hex(random_bytes(16));

            // Build dynamic CSP header
            $directives = [
                "default-src 'self'",
                // Scripts: Allow scripts with nonce and HTTPS sources, strict-dynamic allows dynamic loading
                "script-src 'self' 'nonce-$nonce' 'strict-dynamic' https:",

                "script-src-elem 'self' 'nonce-$nonce' https://cdn.jsdelivr.net https://platform.sharethis.com https://buttons-config.sharethis.com https://count-server.sharethis.com ",

                // Styles
                "style-src 'self' 'nonce-$nonce' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com",
                "style-src-elem 'self' 'nonce-$nonce' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com",

                // Fonts
                "font-src 'self' https://cdnjs.cloudflare.com https://fonts.gstatic.com",

                // Images
                "img-src 'self' data: https://*.sharethis.com https://www.google-analytics.com",

                // Connections
                "connect-src 'self' https://data.stbuttons.click https://l.sharethis.com https://www.google-analytics.com",

                // Frames
                "frame-src 'self' https://platform.sharethis.com",

                // Reporting
                "report-uri " . ($cspOptions['report_uri'] ?? '/csp-report-log'),
                "report-to csp-endpoint"
            ];
            // Add custom directives if provided
            if (!empty($cspOptions['extra'])) {
                $directives = array_merge($directives, $cspOptions['extra']);
            }

            header(($reportOnly ? 'Content-Security-Policy-Report-Only: ' : 'Content-Security-Policy: ')
                . implode('; ', $directives));
        }


        // 2. Initialize Blade
        static $blade = null;
        if (!$blade) {
            // 1. Get validated paths
            $viewsPath = realpath(__DIR__ . '/../../../../resources/views');
            $cachePath = realpath(__DIR__ . '/../../../../bootstrap/cache');
            $blade = new BladeOne($viewsPath, $cachePath, BladeOne::MODE_DEBUG);
            $blade->setIsCompiled(false);
        }

        // 3. Normalize and verify view path
        $viewFile = str_replace(['.', '/'], DIRECTORY_SEPARATOR, $viewFile);
        $data['nonce'] = $nonce;
        // 4. Render with debug
        echo $blade->run($viewFile, $data);
    } catch (\Exception $e) {
        error_log("VIEW ERROR: " . $e->getMessage());
        return "<!-- VIEW ERROR -->\n"
            . "<h1>Rendering Error</h1>\n"
            . "<pre>" . htmlspecialchars($e->getMessage()) . "</pre>\n"
            . "<p>Template: " . htmlspecialchars($viewFile) . "</p>\n"
            . "<p>Search Path: " . htmlspecialchars($viewsPath ?? '') . "</p>";
    }
}

  /**
   * Renders a BladeOne template with the given data.
   *
   * @param string $path The template path (e.g., 'index' or 'msg.customer.token')
   * @param array $data Associative array of data to pass to the template
   * @return string The rendered template output
   * @throws \Throwable If rendering fails
   */

  function view($path, array $data = [])
  {

    try {
      $view = rtrim(__DIR__ . "/../../../../resources/views", '/'); // Remove trailing slash
      $cache = rtrim(__DIR__ . "/../../../../bootstrap/cache", '/');
      $viewFile = str_replace('/', '.', $path); // Convert to dot notation: msg.customer.token
      // echo $viewFile;
      static $blade = null;
      if (!$blade) {
        $mode = getenv('APP_ENV') === 'production' ? BladeOne::MODE_AUTO : BladeOne::MODE_DEBUG;
        $blade = new BladeOne($view, $cache, $mode);


        $blade->pipeEnable = true;
        $blade->setBaseUrl(getenv('APP_URL'));
        // $blade->setAutoescape(true);
      }

      echo $blade->run($viewFile, $data);
    } catch (\Throwable $e) {
     showError($e);
    }
  }



  function printArr($data): void
  {

    if ($data === array()) {
      echo "<pre>";
      var_export($data);
      echo "</pre>";
    } else {
      echo "<pre>";
      print_r($data);
      echo "</pre>";
    }
  }

  function loggedDetection(string $filename, string $receivingEmail): bool
  {
    //TODO send text to the user with the code
    EmailData::defineConstants('admin', $_ENV);
    $getIp =getUserIpAddr();
    $msg = "Hello, <br><br> This is a notification that a <strong>logged -in</strong> has been detected from this file : $filename at this time: " .  date("h:i:sa") . "  and with this IP address: $getIp  <br><br>  IT Security Team";

    SendEmail::sendEmail($receivingEmail, 'logged-in', 'LOGGED-IN DETECTION', $msg);
    return true;
  }



  /**
   * compare two variable or use to verify
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
   * while the date is the month
   *
   * @return string[]
   *
   * @psalm-return array{fullDate: string, dateFormat: string}
   */
  function addMonthsToDate($months, $date): array
  {

    $dt = new \DateTime($date, new \DateTimeZone('Europe/London'));
    $oldDay = $dt->format("d");
    $dt->add(new \DateInterval("P{$months}M"));
    $newDay = $dt->format("d");
    if ($oldDay != $newDay) {
      // Check if the day is changed, if so we skipped to the next month.
      // Substract days to go back to the last day of previous month.
      $dt->sub(new \DateInterval("P" . $newDay . "D"));
    }
    $newDay3 = $dt->format("Y-m-d");
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

  // SHOW THE ERROR EXCEPTION MESSAGE

  function showError(\Throwable $th): void
  {
    $isLocal = isLocalEnv();
    $statusCode = ($th instanceof \Src\Exceptions\HttpException)
      ? $th->getStatusCode()
      : ((int) $th->getCode() >= 100 && (int) $th->getCode() <= 599 ? (int) $th->getCode() : 500);

    http_response_code($statusCode);

    $logMessage = "[" . date('Y-m-d H:i:s') . "] "
      . "Code: {$statusCode}, "
      . "Message: {$th->getMessage()}, "
      . "File: {$th->getFile()}, "
      . "Line: {$th->getLine()}\n";



    file_put_contents(__DIR__ . "/../../../../bootstrap/log/" . date('Y-m-d') . '.log', $logMessage, FILE_APPEND);

    if ($isLocal) {
      echo json_encode([
        'error' => $th instanceof \Src\Exceptions\HttpException
          ? $th->getMessage()
          : "Error on line {$th->getLine()} in {$th->getFile()}: {$th->getMessage()}"
      ]);
    } else {
      echo json_encode([
        'error' => $th instanceof \Src\Exceptions\HttpException
          ? $th->getMessage()
          : "An unexpected error occurred."
      ]);
    }
  }


  // FUNCTION TO SEND TEXT TO PHONE


  function sendText($message, $numbers): void
  {

    $apiKey = urlencode('y9X1o/Ko6M4-MCz6zJfBeGMv9TMOLG54k0c53EfCfo');
    $numbers = array($numbers);
    $sender = urlencode('Loaneasy Finance');
    $message = rawurlencode($message);
    $numbers = implode(',', $numbers);
    // Prepare data for POST request
    $data = array('apikey' => $apiKey, 'numbers' => $numbers, "sender" => $sender, "message" => $message);
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
   * return a bulma panel row
   */


  function changeToJs($variableName, $variable): void
  {
    echo "<script> const $variableName = $variable </script>";
  }

  /**
   * 
   * @param mixed $time that is the full date and time e.g 2010-04-28 17:25:43
   * @return string | bool
   */

  function humanTiming($time)
  {
    try {
      $time = strtotime($time);
      $time = time() - $time; // to get the time since that moment
      $time = ($time < 1) ? 1 : $time;
      $tokens = array(
        31536000 => 'year',
        2592000 => 'month',
        604800 => 'week',
        86400 => 'day',
        3600 => 'hour',
        60 => 'minute',
        1 => 'second'
      );

      foreach ($tokens as $unit => $text) {
        if ($time < $unit) continue;
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
      'status' => "success"
    ]);
  }
  /**
   * only use in the catch block
   * @param int $code
   * @param mixed $msg
   * @return void
   */
  function msgException(int $code, mixed $msg): void
  {
    http_response_code($code);
    echo json_encode([
      'message' => $msg
    ]);
  }

  function throwError(int $code, mixed $msg): void
  {
    throw new \Exception($msg, $code);
  }

  /**
   * Summary of checkInput
   * @param mixed $data
   * @return array|string|null
   */
  function checkInput($data): mixed
  {
    if ($data !== null) {
      $data = trim($data);
      $data = stripslashes($data);
      $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
      $data = strip_tags($data);
      $data = preg_replace('/[^0-9A-Za-z.@\s-]/', '', $data);
      return $data;
    } else {
      return msgException(406, 'problem with your entry');
      return null;
    }
  }

  function checkInputImage($data): string|null
  {
    if ($data !== null) {
      $data = trim($data);
      $data = stripslashes($data);
      $data = htmlspecialchars($data);
      $data = strip_tags($data);
      $data = preg_replace('/[^a-zA-Z0-9\-\_\.\s]/', '', $data);
      return $data;
    } else {
      return msgException(406, 'image name not well formed');
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
     * Generate or retrieve a CSRF token from the session.
     */
    function csrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (!isset($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['_csrf_token'];
    }

    /**
     * Return a hidden input field for CSRF protection.
     */
    function csrfField(): string
    {
        return '<input type="hidden" name="_token" value="' . csrfToken() . '">';
    }

    /**
     * Retrieve a value from the session.
     */
    function sessionGet(string $key, $default = null): mixed
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        return $_SESSION[$key] ?? $default;
    }

    /**
     * Flash a value to the session (store temporarily).
     */
    function sessionFlash(string $key, $value): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION['_flash'][$key] = $value;
    }

    /**
     * Check if a session key exists.
     */
    function sessionHas(string $key): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        return isset($_SESSION[$key]);
    }

    /**
     * Remove a session key.
     */
    function sessionForget(string $key): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        unset($_SESSION[$key]);
    }

    /**
     * Redirect the user to a given path and stop execution.
     */
    function redirect(string $path): void
    {
        header('Location: ' . url($path));
        exit;
    }

  function viteAsset(string $path): string
{    // If you're in development, point to the dev server
  $isDev = $_ENV['APP_ENV'] === 'local'; // Or use getenv(), or set a flag

  if ($isDev) {
    return "http://localhost:5173/$path"; // served by Vite dev server
  }

    static $manifest = null;

        // Cache the manifest only once
        if ($manifest === null) {
            $manifestPath = __DIR__ . '/../../../../public/build/manifest.json';
            if (!file_exists($manifestPath)) {
                throw new RuntimeException('Vite manifest not found.');
            }

            $manifestContent = file_get_contents($manifestPath);
            $manifest = json_decode($manifestContent, true);
        }

        if (!isset($manifest[$path])) {
            throw new RuntimeException("path {$path} not found in Vite manifest.");
        }

        return '/public/build/' . $manifest[$path]['file'];
}

function logger(): Logger
{
    return LoggerFactory::getLogger();
}


