<?php

declare(strict_types=1);

namespace Src;

use InvalidArgumentException;
use Mockery\Matcher\Not;
use Pelago\Emogrifier\CssInliner;
use Pelago\Emogrifier\HtmlProcessor\CssToAttributeConverter;
use Pelago\Emogrifier\HtmlProcessor\HtmlPruner;
use Src\data\EmailData;
use Src\Exceptions\ForbiddenException;
use Src\Exceptions\NotFoundException;

class ToSendEmail
{
    public static function genEmailArray(string $viewPath, array $data, string $subject, $file = null, $fileName = null): array
    {
        return [
            'viewPath' => $viewPath,
            'data' => $data,
            'subject' => $subject,
            'file' => $file,
            'fileName' => $fileName,
        ];
    }

    /**
     * @param mixed $array 'viewPath' => string $viewPath, 'data' => array $data,'subject' => string $subject, 'file' => $file, 'fileName' => $fileName
     * @param mixed $recipient - member or admin
     */
    public static function sendEmailGeneral(array $params, string $recipient)
    {
        try {
            if (!defined('PASS')) {
                EmailData::defineConstants($recipient);
                // if it is still not set, then throw an error
                if (!defined('PASS')) {
                    throw new NotFoundException('Email credentials (constant) not set');
                }
            }

            // 2) Extract + validate inputs
            $data = $params['data'];
            $viewPath = $params['viewPath'];
            $subject = Utility::checkInput($params['subject']) ?? 'No Subject';
            $email = Utility::checkInputEmail($data['email'] ?? ($params['email'] ?? ''));
            if ($email === null) {
                throw new NotFoundException('A valid recipient email is required.');
            }

            $name = Utility::cleanSession($data['name'] ?? ($params['name'] ?? 'there'));

            // 3) Render HTML from Blade
            $html = Utility::viewTemplateEmail($viewPath, ['data' => $data]);
            if ($html === '') {
                throw new ForbiddenException('Failed to render email content.');
            }

            // 4) CSS inline + prune (Emogrifier)
            $cssInliner = CssInliner::fromHtml($html)->inlineCss();
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
     * recipientType can be either member or admin.
     */
    public static function sendEmailWrapper($var, $recipientType)
    {
        if (!defined('PASS')) {
            EmailData::defineConstants($recipientType, $_ENV);
        }

        $data = $var['data'];

        $emailContent = Utility::viewTemplateEmail($var['viewPath'], compact('data'));

        $email = Utility::checkInputEmail($data['email']);
        $email = Utility::checkInputEmail($data['email'] ?? ($params['email'] ?? ''));
        if ($email === null) {
            throw new NotFoundException('A valid recipient email is required.');
        }
        $name = $data['firstName'] ?? $data['first_name'] ?? 'there';

        $file = $var['file'];
        $filename = $var['fileName'];

        //  mail("waledevtest@gmail.com", "TEST_EMAIL", $email);

        SendEmail::sendEmail($email, $name, $var['subject'], $emailContent, $file, $filename);
    }
}
