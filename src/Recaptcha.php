<?php

namespace Src;

use Src\Exceptions\CaptchaVerificationException;

class Recaptcha

{

  public static function verifyCaptcha($action)
  {
    $recaptcha_secret = getenv('SECRET_RECAPTCHA_KEY');
    $recaptcha_response = $_POST['g-recaptcha-response'];
    $recaptcha_url = 'https://www.google.com/recaptcha/api/siteverify';

    $recaptcha = file_get_contents($recaptcha_url . '?secret=' . $recaptcha_secret . '&response=' . $recaptcha_response);

    $recaptcha = json_decode($recaptcha);

    if ($recaptcha->success && $recaptcha->score >= 0.5 && $recaptcha->action == $action) {
      return true;
    } else {
      // reCAPTCHA verification failed
      throw new CaptchaVerificationException("reCAPTCHA verification failed. Please try again.");
    }
  }
}
