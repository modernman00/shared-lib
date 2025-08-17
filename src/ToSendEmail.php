<?php

namespace Src;

use Src\data\EmailData;
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

    public static function sendEmailGeneral(array $params, string $recipient)
    {

        try {

            if (!defined('PASS')) {
                EmailData::defineConstants($recipient, $_ENV);
                // if it is still not set, then throw an error
                if (!defined('PASS')) {
                    throw new ForbiddenException('Email credentials (constant) not set');
                }
            }

            // 2) Extract + validate inputs
            $data     = $params['data'] ?? [];
            $viewPath = $params['viewPath'] ?? 'email';

            $subjectRaw = $params['subject'] ?? 'No Subject';
            // Guard against header/HTML injection in subject
            $subject = trim(preg_replace('/[\r\n]+/', ' ', $subjectRaw) ?? '');
            $subject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');

            // Prefer data.email, fallback to params.email
            $email = Utility::checkInputEmail($data['email'] ?? ($params['email'] ?? ''));
            if ($email === null) {
                throw new InvalidArgumentException('A valid recipient email is required.');
            }

            $name = Utility::cleanSession($data['name'] ?? ($params['name'] ?? 'there'));
            // Mild header-injection protection for name
            $name = preg_replace('/[\r\n]+/', ' ', $name) ?? 'there';

            // 3) Render HTML from Blade
            $html = Utility::viewTemplateEmail($viewPath, ['data' => $data]);
            if ($html === '') {
                throw new ForbiddenException('Failed to render email content.');
            }

            // 4) CSS inline + prune (Emogrifier)
            $cssInliner  = CssInliner::fromHtml($html)->inlineCss();
            $domDocument = $cssInliner->getDomDocument();

            HtmlPruner::fromDomDocument($domDocument)
                ->removeElementsWithDisplayNone()
                ->removeRedundantClassesAfterCssInlined($cssInliner);

            $emogrifiedContent = CssToAttributeConverter::fromDomDocument($domDocument)
                ->convertCssToVisualAttributes()
                ->render();

            SendEmail::sendEmail(
                $email,
                $name,
                $subject,
                $emogrifiedContent
            );
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

        $emailContent = Utility::viewTemplateEmail($var['viewPath'], compact('data'));

        $email =  Utility::checkInputEmail($data['email']);
        $name = $data['firstName'] ?? $data['first_name'] ?? 'there';
        $file = $var['file'];
        $filename = $var['fileName'];

        //  mail("waledevtest@gmail.com", "TEST_EMAIL", $email);

        SendEmail::sendEmail($email, $name, $var['subject'], $emailContent, $file, $filename);
    }
}
