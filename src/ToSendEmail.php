<?php

declare(strict_types=1);

namespace Src;

use InvalidArgumentException;
use Pelago\Emogrifier\CssInliner;
use Pelago\Emogrifier\HtmlProcessor\CssToAttributeConverter;
use Pelago\Emogrifier\HtmlProcessor\HtmlPruner;
use Src\Data\EmailData;
use Src\Exceptions\ForbiddenException;

/**
 *   ## Setup
 * In your bootstrap file (e.g., bootstrap.php):
 * ```php
 * use Src\LoggerFactory;
 * use Monolog\Level;
 * use Dotenv\Dotenv;.
 *
 * require_once __DIR__ . '/vendor/autoload.php';
 * $dotenv = Dotenv::createImmutable(__DIR__);
 * $dotenv->load();
 *
 * $logger = LoggerFactory::createWithMailer(
 *     name: $_ENV['LOGGER_NAME'] ?? 'app',
 *     logPath: $_ENV['LOGGER_PATH'] ?? '/../../bootstrap/log/idecide.log',
 *     mailerDsn: $_ENV['MAILER_DSN'] ?? null,
 *     fromEmail: $_ENV['USER_EMAIL'] ?? 'no-reply@example.com',
 *     toEmail: 'waledevtest@gmail.com',
 *     level: Level::Error
 * );
 *
 * require_once __DIR__ . '/helpers.php';
 */
class ToSendEmail
{
    public static function genEmailArray($viewPath, $data, $subject, $file = null, $fileName = null): array
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
            $email = Utility::checkInputEmail($data['email'] ?? $array['email'] ?? '');
            if (empty($email)) {
                throw new InvalidArgumentException('Email address is required');
            }

            // check if $data['name'] or $array['name'] is set
            if (isset($data['name'])) {
                $name = $data['name'];
            } elseif (isset($array['name'])) {
                $name = $array['name'];
            } else {
                $name = 'there';
            }

            $name = Utility::cleanSession($name);

            SendEmail::sendEmail($email, $name, $array['subject'], $emailContent);
        } catch (ForbiddenException $e) {
            Utility::showError($e);
            throw $e;
        } catch (InvalidArgumentException $e) {
            Utility::showError($e);
            throw $e;
        } catch (\Throwable $e) {
            Utility::showError($e);
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

        ob_start();
        $emailPage = Utility::view($var['viewPath'], compact('data'));
        $emailContent = ob_get_contents();
        ob_end_clean();

        $email = Utility::checkInputEmail($data['email']);
        $name = $data['firstName'] ?? $data['first_name'] ?? 'there';
        $file = $var['file'];
        $filename = $var['fileName'];

        //  mail("waledevtest@gmail.com", "TEST_EMAIL", $email);

        SendEmail::sendEmail($email, $name, $var['subject'], $emailContent, $file, $filename);
    }
}
