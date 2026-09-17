<?php

declare(strict_types=1);

namespace Src;

use InvalidArgumentException;
use Pelago\Emogrifier\CssInliner;
use Pelago\Emogrifier\HtmlProcessor\CssToAttributeConverter;
use Pelago\Emogrifier\HtmlProcessor\HtmlPruner;
use Src\data\EmailData;
use Src\Exceptions\ForbiddenException;
use Src\Exceptions\NotFoundException;

/**
 * Class ToSendEmail
 * @package Src
 * @param $viewPath - the path to the view
 * @param $data - the data to be sent to the view - data: ['email' => $email, 'code' => $token, 'isFunctional' => true],
 * @param $subject - the subject of the email
 * @param $file - the file to be sent to the view
 * @param $fileName - the name of the file to be sent to the view
 */     
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
    // This is a more general function that can be used for any email sending, it will handle the rendering of the email and the sending of the email.

    /**
     * @param mixed $array 'viewPath' => string $viewPath, 'data' => array $data,'subject' => string $subject, 'file' => $file, 'fileName' => $fileName
     * @param mixed $recipient - member or admin
     */
    public static function sendEmailGeneral(array $params, string $recipient)
    {
        if (\isTestEnv()) {
            $GLOBALS['__testMail'][] = [
                'to' => $params['data']['email'] ?? $params['email'] ?? '',
                'subject' => $params['subject'] ?? '',
                'view' => $params['viewPath'] ?? '',
                'recipient' => $recipient,
            ];
            return true;
        }
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

            // Check if recipient has unsubscribed from non-functional communications
            $isFunctional = !empty($data['isFunctional']) || !empty($params['isFunctional']);
            if (!$isFunctional) {
                $subjectUpper = strtoupper($subject);
                if (str_contains($subjectUpper, 'TOKEN') || 
                    str_contains($subjectUpper, 'VERIF') || 
                    str_contains($subjectUpper, 'PASSWORD') || 
                    str_contains($subjectUpper, 'SECURITY') || 
                    str_contains($subjectUpper, '2FA') || 
                    str_contains($subjectUpper, 'ALERT')) {
                    $isFunctional = true;
                }
            }

            if (!$isFunctional && self::isEmailUnsubscribed($email)) {
                // Suppress non-functional email send for unsubscribed recipient
                return false;
            }

            // 3) Render HTML from Blade
            $html = Utility::viewTemplateEmail($viewPath, ['data' => $data]);
            if ($html === \null) {
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
            throw $e;
        } catch (InvalidArgumentException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw $e;
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

    private static function isEmailUnsubscribed(string $email): bool
    {
        try {
            if (!class_exists('\\Src\\Db')) {
                return false;
            }
            $db = \Src\Db::connect2();
            $stmt = $db->prepare('SELECT email_unsubscribed FROM account WHERE email = ? LIMIT 1');
            $stmt->execute([$email]);
            $res = $stmt->fetchColumn();
            return !empty($res);
        } catch (\Throwable $e) {
            return false;
        }
    }
}
