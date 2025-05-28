<?php

namespace App\shared;

use App\data\EmailData;
use App\shared\Exceptions\ForbiddenException;
use InvalidArgumentException;

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
   * @param mixed $recipient 
   * @return void 
   */

  public static function sendEmailGeneral($array, $recipient)
  {
    $notifyCustomer = new EmailData($recipient);

    if (!defined('PASS')) {
      $notifyCustomer->getEmailData();
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

      $name = Utility::checkInput($data['name']) ?? $array['name'];

      SendEmail::sendEmail($email, $name, $array['subject'], $emailContent);
    }
  }
}
