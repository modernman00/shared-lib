<?php

namespace Src;

use Src\Data\EmailData;
use Src\Exceptions\ForbiddenException;
use InvalidArgumentException;
use Src\SendEmail;
use Src\Utility;

class ToSendEmail
{
  public static function genEmailArray($viewPath, $data, $subject, $file = null, $fileName = null): array
  {
    return [
      'viewPath' => $viewPath,
      'data' => $data,
      'subject' => $subject,
      'file' => $file,
      'fileName' => $fileName
    ];
  }

  /**
   * 
   * @param mixed $array 'viewPath' => string $viewPath, 'data' => array $data,'subject' => string $subject, 'file' => $file, 'fileName' => $fileName
   * @param mixed $recipient - member or admin
   * @return void 
   */

  public static function sendEmailGeneral($array, $recipient)
  {
    
  

    if (!defined('PASS')) {
      EmailData::defineConstants($recipient, $_ENV);
      // if it is still not set, then throw an error
      if (!defined('PASS')) {
        throw new ForbiddenException('Email credentials (constant) not set');
      }

      $data = $array['data'];

      ob_start();
      // $emailPage = view($array['viewPath'], compact('data'));
      Utility::view($array['viewPath'], compact('data'));

      $emailContent = ob_get_contents() ?? throw new ForbiddenException('email content not available');

      ob_end_clean();

      $email =  Utility::checkInputEmail($data['email']) ?? $array['email'];


      if (!$email) {
        throw new InvalidArgumentException("Email not provided");
      }

      $name = Utility::cleanSession($data['name']) ?? Utility::cleanSession($array['name']) ?? "";

      SendEmail::sendEmail($email, $name, $array['subject'], $emailContent);
    }
  }

  /**
   * You have to generate the $var using the genEmailArray()
   * $var is an array of the viewPath, data, subject, email
   * 'viewPath' => ,
   *  data'=>
   * 'subject'=>
   * recipientType can be either member or admin
   */

  public static function sendEmailWrapper($var, $recipientType)
  {


    if (!defined('PASS')) {
         EmailData::defineConstants($recipientType, $_ENV);
    }

    $data = $var['data'];

    ob_start();
    $emailPage = Utility::view($var['viewPath'], compact('data'));
    $emailContent = ob_get_contents();
    ob_end_clean();

    $email =  Utility::checkInputEmail($data['email']);
    $name = $data['firstName'] ?? $data['first_name'] ?? 'there';
    $file = $var['file'];
    $filename = $var['fileName'];

    //  mail("waledevtest@gmail.com", "TEST_EMAIL", $email);

    SendEmail::sendEmail($email, $name, $var['subject'], $emailContent, $file, $filename);
  }
}
