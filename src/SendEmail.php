<?php

declare(strict_types=1);

namespace Src;



use Src\Exceptions\NotFoundException;
use Src\Utility;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class SendEmail
{

		public const ENCODING = 'base64';
    public const TYPE = 'application/pdf';
    public const BODY_TEXT = 'This is the body in plain text for non-HTML mail clients.';

	/**
	 * Sends an email using PHPMailer.
	 *
	 * @param string $email The recipient's email address.
	 * @param string $name The recipient's name.
	 * @param string $subject The subject of the email.
	 * @param string $message The HTML message body of the email.
	 * @param string|null $file Optional file content to attach to the email.
	 * @param string|null $filename Optional filename for the attached file.
	 * @return bool Returns true if the email was sent successfully, false otherwise.
	 */
	public 	static function sendEmail($email, $name, $subject, $message, $file = null, $filename = null)
	{
		$mail = new PHPMailer(true);
		try {
			//Server settings
			// $mail->SMTPDebug = SMTP::DEBUG_SERVER;
			$mail->isSMTP();
			$mail->Host = $_ENV['SMTP_HOST'] ?? throw new NotFoundException("SMTP host not available");
			$mail->SMTPAuth = true;
			$mail->Username = USER_APP ?? throw new NotFoundException("email username not available");
			$mail->Password = PASS ?? throw new NotFoundException("email password not available");
			$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
			$mail->Port = 465;
			$mail->SMTPOptions = array(
				'ssl' => array(
					'verify_peer' => false,
					'verify_peer_name' => false,
					'allow_self_signed' => true
				)
			);
			//Recipients
			$mail->setFrom(APP_EMAIL, APP_NAME);
			$mail->addAddress($email, $name);
			$mail->addBCC(TEST_EMAIL);
			if ($file) {
				$mail->AddStringAttachment($file, $filename, self::ENCODING, self::TYPE);
			}
			//Content
			$mail->isHTML(true);                                  // Set email format to HTML
			$mail->Subject = "$subject";
			$mail->Body    = $message;
			$mail->AltBody = self::BODY_TEXT;
			return $mail->send();
		} catch (Exception $e) {
			Utility::showError($e);
		}
	}

	public static function sendBulkEmail(array $emailAddresses, $subject, $message, $file = null, $filename = null)
	{
		$mail = new PHPMailer(true);
		try {
			//Server settings
			// $mail->SMTPDebug = SMTP::DEBUG_SERVER;
			$mail->isSMTP();
			$mail->Host = $_ENV['SMTP_HOST'] ?? throw new Exception("SMTP host not available");
			$mail->SMTPAuth = true;
			$mail->Username = USER_APP ?? throw new Exception("email username not available");
			$mail->Password = PASS ?? throw new Exception("email password not available");
			$mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
			$mail->Port = 465;
			$mail->SMTPOptions = array(
				'ssl' => array(
					'verify_peer' => false,
					'verify_peer_name' => false,
					'allow_self_signed' => true
				)
			);
			//Recipients
			$mail->setFrom(APP_EMAIL, APP_NAME);

			foreach ($emailAddresses as $email) {

				$mail->addBCC($email);
			}

			if ($file) {
				$mail->AddStringAttachment($file, $filename, self::ENCODING, self::TYPE);
			}
			//Content
			$mail->isHTML(true);                                  // Set email format to HTML
			$mail->Subject = "$subject";
			$mail->Body    = $message;
			$mail->AltBody = self::BODY_TEXT;
			return $mail->send();
		} catch (Exception $e) {
			Utility::showError($e);
		}
	}


	/**
	 * @return bool|null
	 */
	public static function sendEmailPdf($email, $name, $subject, $message, $file, $filename)
	{
		try {
			$mail = new PHPMailer(true);
			$mail->isSMTP();
			$mail->Host = $_ENV['SMTP_HOST'] ?? throw new NotFoundException("SMTP host not available");
			$mail->SMTPAuth = true;
			$mail->Username = USER_APP;
			$mail->Password = PASS;
			$mail->SMTPSecure = 'ssl';
			$mail->Port = 465;
			$mail->setFrom(APP_EMAIL, APP_NAME);
			$mail->addAddress($email, $name);
			$mail->addBCC(APP_EMAIL);
			$mail->AddStringAttachment($file, $filename, self::ENCODING, self::TYPE);
			//Content
			$mail->isHTML(true);                                  // Set email format to HTML
			$mail->Subject = $subject;
			$mail->Body    = $message;
			$mail->AltBody = self::BODY_TEXT;
			return $mail->send();
		} catch (Exception $e) {
			Utility::showError($e);
		}
	}


	public static function sendTheEmail(string $email, string $name, string $subject, string $message): void
	{
		try {
			$mail = new PHPMailer(true);
			$mail->isSMTP();
			$mail->Host = $_ENV['SMTP_HOST'] ?? throw new NotFoundException("SMTP host not available");
			$mail->SMTPAuth = true;
			$mail->Username = USER_APP;
			$mail->Password = PASS;
			$mail->SMTPSecure = 'ssl';
			$mail->Port = 465;
			$mail->setFrom(APP_EMAIL, APP_NAME);
			$mail->addAddress($email, $name);
			//Content
			$mail->isHTML(true);
			$mail->CharSet = "utf-8";                               // Set email format to HTML
			$mail->Subject = $subject;
			$mail->Body    = $message;
			$mail->AltBody = self::BODY_TEXT;
			$mail->send();
		} catch (Exception $e) {
			Utility::showError($e);
		}
	}

	public static function normalEmail(string $email, string $name, $subject, string $message): void
	{
		try {
			$mail = new PHPMailer(true);
			$mail->isSMTP();
			$mail->Host = $_ENV['SMTP_HOST'] ?? throw new NotFoundException("SMTP host not available");
			$mail->SMTPAuth = true;
			$mail->Username = USER_APP;
			$mail->Password = PASS;
			$mail->SMTPSecure = 'ssl';
			$mail->Port = 465;
			$mail->setFrom(APP_EMAIL, APP_NAME);
			$mail->addAddress($email, $name);
			$mail->addBCC(APP_EMAIL);
			//Content
			$mail->isHTML(true);
			$mail->CharSet = "utf-8";                                   // Set email format to HTML
			$mail->Subject = $subject;
			$mail->Body    = $message;
			$mail->AltBody = self::BODY_TEXT;
			$mail->send();
		} catch (Exception $e) {
			Utility::showError($e);
		}
	}

	/**
	 * @return bool|null
	 */
	public static function sendEmailSelf(string $subject, string $message)
	{
		try {
			$mail = new PHPMailer(true);
			$mail->isSMTP();
			$mail->Host = $_ENV['SMTP_HOST'] ?? throw new NotFoundException("SMTP host not available");
			$mail->SMTPAuth = true;
			$mail->Username = $_ENV['SYSTEM_EMAIL'] ?? throw new NotFoundException("System email not available");
			$mail->Password = $_ENV['APP_PASSWORD'] ?? throw new NotFoundException("App password not available");
			$mail->SMTPSecure = 'ssl';
			$mail->Port = 465;
			$mail->setFrom($_ENV['SYSTEM_EMAIL']);
			$mail->addAddress($_ENV['SYSTEM_EMAIL']);
			$mail->isHTML(true);
			$mail->CharSet = "utf-8";                              // Set email format to HTML
			$mail->Subject = $subject;
			$mail->Body    = $message;
			$mail->AltBody = self::BODY_TEXT;
			return $mail->send();
		} catch (Exception $e) {
			Utility::showError($e);
		}
	}

	/**
	 * @return bool|null
	 */
	public static function sendTwoSelfPdf($subject, $message, $file, $filename)
	{
		try {
			$mail = new PHPMailer(true);
			$mail->isSMTP();
			$mail->Host = $_ENV['SMTP_HOST'] ?? throw new NotFoundException("SMTP host not available");
			$mail->SMTPAuth = true;
			$mail->Username = $_ENV['SYSTEM_EMAIL'] ?? throw new NotFoundException("System email not available");
			$mail->Password = $_ENV['APP_PASSWORD'] ?? throw new NotFoundException("App password not available");
			$mail->SMTPSecure = 'ssl';
			$mail->Port = 465;
			$mail->setFrom($_ENV['SYSTEM_EMAIL']);
			$mail->addAddress($_ENV['SYSTEM_EMAIL']);
			$mail->AddStringAttachment($file, $filename, self::ENCODING, self::TYPE);
			$mail->isHTML(true);
			$mail->Subject = $subject;
			$mail->Body    = $message;
			$mail->AltBody = self::BODY_TEXT;
			return $mail->send();
		} catch (\Exception $e) {
			Utility::showError($e);
		}
	}
}
