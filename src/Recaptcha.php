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
    public static function verifyCaptcha($input)
    {
        // 1. 🕵️‍♂️ Get their CAPTCHA answer
        $token = $input['g-recaptcha-response'] ?? '';
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


    /**
     * 🚪 MAIN SECURITY GATE — reCAPTCHA Enterprise Assessment
     *
     * @throws RecaptchaException
     */
    public static function verifyCaptchaEnterprise(array $input, string $action): bool
    {
           $expectedAction = $input['action'] ?? $action;
        $token = $input['siteKey'] ?? '';
        

        if (empty($token)) {
            throw new RecaptchaFailedException("🚨 Missing reCAPTCHA token — please try again.");
        }

        $projectId = $_ENV['RECAPTCHA_PROJECT_ID'] ?? '';
        $apiKey    = $_ENV['RECAPTCHA_API_KEY'] ?? '';
        $siteKey   = $_ENV['RECAPTCHA_SITE_KEY'] ?? '';

        if (!$projectId || !$apiKey || !$siteKey) {
            throw new RecaptchaBrokenException("🔐 Missing reCAPTCHA Enterprise configuration.");
        }

        $url = "https://recaptchaenterprise.googleapis.com/v1/projects/{$projectId}/assessments?key={$apiKey}";

        // Payload for Google
        $payload = [
            'event' => [
                'token'          => $token,
                'siteKey'        => $siteKey,
                'expectedAction' => $expectedAction,
            ]
        ];

        try {
            // Your Axios-style request wrapper
            $response = sendPostRequest($url, $payload);

            if (!isset($response['tokenProperties'])) {
                throw new RecaptchaBrokenException("🤯 Invalid response from Google.");
            }

            // 1. Token validity
            if (!$response['tokenProperties']['valid']) {
                throw new RecaptchaFailedException("❌ Invalid reCAPTCHA token.");
            }

            // 2. Action match
            if ($response['tokenProperties']['action'] !== $expectedAction) {
                throw new RecaptchaCheatingException("⚠️ Suspicious action mismatch.");
            }

            // 3. Risk score
            $score = $response['riskAnalysis']['score'] ?? 0;
            if ($score < 0.7) {
                throw new RecaptchaFailedException("🤖 High‑risk activity detected.");
            }

            return true;
        } catch (\Throwable $e) {
            Utility::showError($e);
            return false;
        }
    }
}
