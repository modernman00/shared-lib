<?php

declare(strict_types=1);

namespace Src;

use Src\Exceptions\RecaptchaBrokenException;
use Src\Exceptions\RecaptchaCheatingException;
use Src\Exceptions\RecaptchaException;
use Src\Exceptions\RecaptchaFailedException;

/*
|--------------------------------------------------------------------------
| 🧒 reCAPTCHA Helper (Now With Custom Exceptions!)
|--------------------------------------------------------------------------
| Like a robot bouncer with special walkie-talkie messages:
| 1. 👶 Checks: "Are you human?"
| 2. 📡 Sends special alerts when things go wrong
*/

// 🚨 Our custom walkie-talkie messages

class Recaptcha
{
    /**
     * 🚪 THE MAIN DOOR CHECK.
     *
     *
     * @throws RecaptchaException When something fishy happens
     *                            ENV MUST HAVE SECRET_RECAPTCHA_KEY and SECRET_RECAPTCHA_KEY_TWO_START_LETTER
     *
     * @param string $token reCAPTCHA response token

     *                       INCLUDE $_ENV['DOMAIN_NAME'] in your ENV file
     *
     * @return bool True if verification succeeds
     *
     * @throws RecaptchaException On verification failure
     */
    public static function verifyCaptcha()
    {
        // 1. 🕵️‍♂️ Get their CAPTCHA answer
        $token = $_POST['g-recaptcha-response'] ?? '';
        if ($token === '') {
            throw new RecaptchaFailedException("🚨 Oops! Forgot the 'I'm not a robot' box!");
        }

        // 2. 🔑 Get our secret password
        $secret = $_ENV['SECRET_RECAPTCHA_KEY'] ?? '';
        if ($secret === '') {
            throw new RecaptchaBrokenException('🔐 Our guard is asleep! Tell the admin!');
        }

        if (!str_starts_with($secret, $_ENV['SECRET_RECAPTCHA_KEY_TWO_START_LETTER'])) {
            throw new RecaptchaBrokenException('Invalid reCAPTCHA secret key format');
        }

        //2.2
        if (empty($action)) {
            throw new RecaptchaException('Action parameter cannot be empty');
        }

        // 3. 📞 Call Google's robot-checker
        try {
            $data = sendPostRequest(
                url: 'https://www.google.com/recaptcha/api/siteverify',
                formData: [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null,
                ]
            );

            // 5. 🤖 Did Google respond with expected structure?
            if (!isset($data['success'])) {
                throw new RecaptchaBrokenException('🤯 Unexpected response from Google!');
            }

            // 6. ❌ Was verification successful?
            if (!$data['success']) {
                throw new RecaptchaFailedException('🚫 reCAPTCHA failed — bot suspected!');
            }

            // 7. 🧾 Optional: Check hostname matches your domain
            if (!empty($data['hostname']) && $data['hostname'] !== $_ENV['DOMAIN_NAME']) {
                throw new RecaptchaCheatingException('🔐 Hostname mismatch — possible tampering!');
            }

            // 8. 🎉 Welcome, human!
            return true;
        } catch (\Throwable $e) {
            Utility::showError($e);
        }
    }
}
