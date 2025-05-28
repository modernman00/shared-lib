<?php

namespace App\Shared;

class Utility
{


  public static function printArr($data): void
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



  /**
   * compare two variable or use to verify
   */
  public static function compare($var1, $var2): bool
  {
    if ($var1 != $var2) {
      return false;
    }
    return true;
  }

  // GET IP ADDRESS

  public static function getUserIpAddr(): string
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
  public static function addMonthsToDate($months, $date): array
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
    $datetime = ['fullDate' => $newDay2, 'dateFormat' => $newDay3];
    return $datetime;
  }


  public static function cleanSession($x): string|null|int
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

  public static function showError(\Throwable $th): void
  {
    $isLocal = getenv('APP_ENV') === 'local';
    $statusCode = ($th instanceof \App\shared\Exceptions\HttpException)
      ? $th->getStatusCode()
      : ((int) $th->getCode() >= 100 && (int) $th->getCode() <= 599 ? (int) $th->getCode() : 500);

    http_response_code($statusCode);

    $logMessage = "[" . date('Y-m-d H:i:s') . "] "
      . "Code: {$statusCode}, "
      . "Message: {$th->getMessage()}, "
      . "File: {$th->getFile()}, "
      . "Line: {$th->getLine()}\n";



    file_put_contents(__DIR__ . "/../../../bootstrap/log/" . date('Y-m-d') . '.log', $logMessage, FILE_APPEND);

    if ($isLocal) {
      echo json_encode([
        'error' => $th instanceof \App\shared\Exceptions\HttpException
          ? $th->getMessage()
          : "Error on line {$th->getLine()} in {$th->getFile()}: {$th->getMessage()}"
      ]);
    } else {
      echo json_encode([
        'error' => $th instanceof \App\shared\Exceptions\HttpException
          ? $th->getMessage()
          : "An unexpected error occurred."
      ]);
    }
  }


  // public static FUNCTION TO SEND TEXT TO PHONE


  public static function sendText($message, $numbers): void
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

  public static function addCountryCode($mobile, $code): string
  {
    $telephone = $mobile;
    $telephone = substr($telephone, 1);
    $telephone = $code . $telephone;
    return $telephone;
  }

  /**
   * return a bulma panel row
   */


  public static function changeToJs($variableName, $variable): void
  {
    echo "<script> const $variableName = $variable </script>";
  }

  /**
   * 
   * @param mixed $time that is the full date and time e.g 2010-04-28 17:25:43
   * @return string | bool
   */

  public static function humanTiming($time)
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
      showError($th);
      return false;
    }
  }

  public static function milliSeconds(): string
  {
    $microtime = microtime();
    $comps = explode(' ', $microtime);

    // Note: Using a string here to prevent loss of precision
    // in case of "overflow" (PHP converts it to a double)
    return sprintf('%d%03d', $comps[1], $comps[0] * 1000);
  }



  public static function msgSuccess(int $code, mixed $msg, mixed $token = null): void
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
  public static function msgException(int $code, mixed $msg): void
  {
    http_response_code($code);
    echo json_encode([
      'message' => $msg
    ]);
  }

  public static function throwError(int $code, mixed $msg): void
  {
    throw new \Exception($msg, $code);
  }

  /**
   * Summary of checkInput
   * @param mixed $data
   * @return array|string|null
   */
  public static function checkInput($data): mixed
  {
    if ($data !== null) {
      $data = trim($data);
      $data = stripslashes($data);
      $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
      $data = strip_tags($data);
      $data = preg_replace('/[^0-9A-Za-z.@\s-]/', '', $data);
      return $data;
    } else {
      msgException(406, 'problem with your entry');
      return null;
    }
  }

  public static function checkInputImage($data): string|null
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

  public static function checkInputEmail($data): string
  {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    $data = strip_tags($data);
    $data = filter_var($data, FILTER_SANITIZE_EMAIL);
    return $data;
  }
}
