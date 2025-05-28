<?php





declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class SendEmail
{
	function sendEmail($email, $name, $subject, $message, $file = null, $filename = null)
	{
		$mail = new PHPMailer(true);
		try {
			//Server settings
			// $mail->SMTPDebug = SMTP::DEBUG_SERVER;
			$mail->isSMTP();
			$mail->Host = getenv('SMTP_HOST');
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
			$mail->addAddress($email, $name);
			$mail->addBCC(TEST_EMAIL);
			if ($file) {
				$mail->AddStringAttachment($file, $filename, ENCODING, TYPE);
			}
			//Content
			$mail->isHTML(true);                                  // Set email format to HTML
			$mail->Subject = "$subject";
			$mail->Body    = $message;
			$mail->AltBody = BODY_TEXT;
			return $mail->send();
		} catch (Exception $e) {
			errorMsg($mail, $e);
		}
	}

	function sendBulkEmail(array $emailAddresses, $subject, $message, $file = null, $filename = null)
	{
		$mail = new PHPMailer(true);
		try {
			//Server settings
			// $mail->SMTPDebug = SMTP::DEBUG_SERVER;
			$mail->isSMTP();
			$mail->Host = getenv('SMTP_HOST');
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
				$mail->AddStringAttachment($file, $filename, ENCODING, TYPE);
			}
			//Content
			$mail->isHTML(true);                                  // Set email format to HTML
			$mail->Subject = "$subject";
			$mail->Body    = $message;
			$mail->AltBody = BODY_TEXT;
			return $mail->send();
		} catch (Exception $e) {
			errorMsg($mail, $e);
		}
	}


	/**
	 * @return bool|null
	 */
	function send_email_pdf($email, $name, $subject, $message, $file, $filename)
	{
		try {
			$mail = new PHPMailer(true);
			$mail->isSMTP();
			$mail->Host = getenv('SMTP_HOST');
			$mail->SMTPAuth = true;
			$mail->Username = USER_APP;
			$mail->Password = PASS;
			$mail->SMTPSecure = 'ssl';
			$mail->Port = 465;
			$mail->setFrom(APP_EMAIL, APP_NAME);
			$mail->addAddress($email, $name);
			$mail->addBCC(APP_EMAIL);
			$mail->AddStringAttachment($file, $filename, ENCODING, TYPE);
			//Content
			$mail->isHTML(true);                                  // Set email format to HTML
			$mail->Subject = $subject;
			$mail->Body    = $message;
			$mail->AltBody = BODY_TEXT;
			return $mail->send();
		} catch (Exception $e) {
			echo errorMsg($mail, $e);
		}
	}


	function send_email(string $email, string $name, string $subject, string $message): void
	{
		try {
			$mail = new PHPMailer(true);
			$mail->isSMTP();
			$mail->Host = getenv('SMTP_HOST');
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
			$mail->AltBody = BODY_TEXT;
			$mail->send();
		} catch (Exception $e) {
			echo errorMsg($mail, $e);
		}
	}

	function normal_email(string $email, string $name, $subject, string $message): void
	{
		try {
			$mail = new PHPMailer(true);
			$mail->isSMTP();
			$mail->Host = getenv('SMTP_HOST');
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
			$mail->AltBody = BODY_TEXT;
			$mail->send();
		} catch (Exception $e) {
			echo errorMsg($mail, $e);
		}
	}

	/**
	 * @return bool|null
	 */
	function send_email_self(string $subject, string $message)
	{
		try {
			$mail = new PHPMailer(true);
			$mail->isSMTP();
			$mail->Host = getenv('SMTP_HOST');
			$mail->SMTPAuth = true;
			$mail->Username = getenv('SYSTEM_EMAIL');
			$mail->Password = getenv('APP_PASSWORD');
			$mail->SMTPSecure = 'ssl';
			$mail->Port = 465;
			$mail->setFrom(getenv('SYSTEM_EMAIL'), 'LOANEASY');
			$mail->addAddress(getenv('SYSTEM_EMAIL'));
			$mail->isHTML(true);
			$mail->CharSet = "utf-8";                              // Set email format to HTML
			$mail->Subject = $subject;
			$mail->Body    = $message;
			$mail->AltBody = BODY_TEXT;
			return $mail->send();
		} catch (Exception $e) {
			echo errorMsg($mail, $e);
		}
	}

	/**
	 * @return bool|null
	 */
	function send_to_self_pdf($subject, $message, $file, $filename)
	{
		try {
			$mail = new PHPMailer(true);
			$mail->isSMTP();
			$mail->Host = getenv('SMTP_HOST');
			$mail->SMTPAuth = true;
			$mail->Username = getenv('SYSTEM_EMAIL');
			$mail->Password = getenv('APP_PASSWORD');
			$mail->SMTPSecure = 'ssl';
			$mail->Port = 465;
			$mail->setFrom(getenv('SYSTEM_EMAIL'), 'LOANEASY');
			$mail->addAddress(getenv('SYSTEM_EMAIL'), 'LOANEASY');
			$mail->AddStringAttachment($file, $filename, ENCODING, TYPE);
			$mail->isHTML(true);
			$mail->Subject = $subject;
			$mail->Body    = $message;
			$mail->AltBody = BODY_TEXT;
			return $mail->send();
		} catch (\Exception $e) {
			echo errorMsg($mail, $e);
		}
	}
}


function errorMsg($mail, $e): string
{
	return "Mailer Error: {$mail->ErrorInfo} " . showError($e);
}
