<?php

namespace Src;

use Src\Data\EmailData;
use Src\Exceptions\ForbiddenException;
use InvalidArgumentException;
use Src\SendEmail;
use Src\Utility;
use Pelago\Emogrifier\CssInliner;
use Pelago\Emogrifier\HtmlProcessor\HtmlPruner;
use Pelago\Emogrifier\HtmlProcessor\CssToAttributeConverter;

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
    
  try {

    if (!defined('PASS')) {
      EmailData::defineConstants($recipient, $_ENV);
      // if it is still not set, then throw an error
      if (!defined('PASS')) {
        throw new ForbiddenException('Email credentials (constant) not set');
      }
    }
      $data = $array['data'];

      ob_start();
      $viewPath = $array['viewPath'] ?? 'email';
      $viewContent = Utility::view($viewPath, compact('data'));
      $emailContent = ob_get_clean() ?: throw new ForbiddenException('Failed to render email content');

      // Emogrify the HTML for email client compatibility
            $cssInliner = CssInliner::fromHtml($emailContent)->inlineCss();
            $domDocument = $cssInliner->getDomDocument();
            HtmlPruner::fromDomDocument($domDocument)
                ->removeElementsWithDisplayNone()
                ->removeRedundantClassesAfterCssInlined($cssInliner);
            $converter = CssToAttributeConverter::fromDomDocument($domDocument)
                ->convertCssToVisualAttributes();
            $emogrifiedContent = $converter->render();

      ob_end_clean();

     // Determine email recipient
            $email = Utility::checkInputEmail($data['email'] ?? '') ?? $array['email'] ?? '';
            if (empty($email)) {
                throw new InvalidArgumentException('Email address is required');
            }

      $name = Utility::cleanSession($data['name']) ?? Utility::cleanSession($array['name']) ?? "";

      SendEmail::sendEmail($email, $name, $array['subject'], $emailContent);
      } catch (ForbiddenException $e) {
            Utility::showError($e);
           
        } catch (InvalidArgumentException $e) {
            Utility::showError($e);
           
        } catch (\Exception $e) {
            Utility::showError($e);
    
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
