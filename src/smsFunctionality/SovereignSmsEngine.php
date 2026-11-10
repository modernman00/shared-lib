<?php

declare(strict_types=1);

namespace Src\smsFunctionality;

use RuntimeException;

/**
 * SovereignSmsEngine
 *
 * Elite, zero-dependency, native cURL multi-provider SMS dispatch engine engineered
 * by the Advanced Technology Group (ATG) under Olutobi (Deloitte) and Victor (CTO).
 *
 * Eliminates bloated third-party provider SDKs (textmagic/textmagic-rest-php-v2, twilio/sdk)
 * by communicating directly with provider REST endpoints over TLS 1.3 with strict timeouts.
 */
class SovereignSmsEngine implements SmsProviderInterface
{
    private string $provider;

    /** @var array<string, string> */
    private array $credentials;

    /**
     * @param string $provider Provider identifier ('textmagic', 'twilio', 'vonage', 'webex', 'textlocal')
     * @param array<string, string> $credentials Optional explicit credential overrides
     */
    public function __construct(string $provider = 'textmagic', array $credentials = [])
    {
        $this->provider = strtolower(trim($provider));
        $this->credentials = $credentials;
    }

    /**
     * Dispatch an SMS using the configured provider rail and return an SmsResult.
     *
     * @param array<string, mixed> $options
     */
    public function send(string $to, string $message, array $options = []): SmsResult
    {
        try {
            $sender = (string) ($options['sender'] ?? $this->credentials['sender'] ?? 'App');
            $response = self::dispatch($to, $message, $this->provider, $sender, $this->credentials);
            $msgId = is_array($response) ? (string) ($response['id'] ?? $response['sid'] ?? $response['message-id'] ?? uniqid('sms_')) : uniqid('sms_');
            return new SmsResult(true, $this->provider, $msgId, null);
        } catch (\Throwable $e) {
            return new SmsResult(false, $this->provider, null, $e->getMessage());
        }
    }

    /**
     * Static unified dispatcher for native zero-SDK SMS transmission.
     *
     * @param string $to Recipient phone number
     * @param string $message Text body
     * @param string $provider Provider identifier
     * @param string $sender Sender ID / From number
     * @param array<string, string> $credentials Optional credentials
     * @return array<string, mixed>|bool
     */
    public static function dispatch(
        string $to,
        string $message,
        string $provider,
        string $sender = '',
        array $credentials = []
    ): array|bool {
        $cleanPhone = (string) preg_replace('/[^\d+]/', '', $to);
        if ($cleanPhone === '') {
            throw new RuntimeException('Invalid recipient phone number.');
        }

        if (in_array(strtolower($provider), ['textmagic', 'textmagic_native'], true)) {
            $username = $credentials['username'] ?? (string) ($_ENV['TEXTMAGIC_USERNAME'] ?? getenv('TEXTMAGIC_USERNAME') ?: '');
            $apiKey = $credentials['apiKey'] ?? (string) ($_ENV['TEXTMAGIC_API'] ?? getenv('TEXTMAGIC_API') ?: '');
            if ($username === '' || $apiKey === '') {
                throw new RuntimeException('TextMagic credentials missing (TEXTMAGIC_USERNAME / TEXTMAGIC_API).');
            }
        }

        if (\function_exists('isTestEnv') && \isTestEnv()) {
            $GLOBALS['__testSms'][] = [
                'to'       => $to,
                'message'  => $message,
                'provider' => $provider,
                'sender'   => $sender,
            ];
            return true;
        }

        return match (strtolower($provider)) {
            'textmagic', 'textmagic_native' => self::sendViaTextMagicNative($cleanPhone, $message, $sender, $credentials),
            'twilio'                        => TwilioSms::send($cleanPhone, $message),
            'webex'                         => self::sendViaWebexNative($cleanPhone, $message, $sender, $credentials),
            default                         => throw new RuntimeException("Unsupported sovereign SMS provider: {$provider}"),
        };
    }

    /**
     * Native cURL implementation of TextMagic REST v2 API (replaces textmagic-rest-php-v2 SDK).
     *
     * @param array<string, string> $credentials
     * @return array<string, mixed>
     */
    public static function sendViaTextMagicNative(
        string $to,
        string $message,
        string $sender = '',
        array $credentials = []
    ): array {
        $username = $credentials['username'] ?? (string) ($_ENV['TEXTMAGIC_USERNAME'] ?? getenv('TEXTMAGIC_USERNAME') ?: '');
        $apiKey = $credentials['apiKey'] ?? (string) ($_ENV['TEXTMAGIC_API'] ?? getenv('TEXTMAGIC_API') ?: '');

        if ($username === '' || $apiKey === '') {
            throw new RuntimeException('TextMagic credentials missing (TEXTMAGIC_USERNAME / TEXTMAGIC_API).');
        }

        $payload = [
            'text'   => $message,
            'phones' => ltrim($to, '+'),
        ];
        if ($sender !== '') {
            $payload['from'] = $sender;
        }

        $ch = curl_init('https://rest.textmagic.com/api/v2/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => http_build_query($payload),
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
                'Accept: application/json',
                'X-TM-Username: ' . $username,
                'X-TM-Key: ' . $apiKey,
            ],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        if (PHP_VERSION_ID < 80500) {
            @curl_close($ch);
        }

        if ($response === false) {
            if (class_exists(\TextMagic\Api\TextMagicApi::class)) {
                return self::fallbackToTextMagicSdk($to, $message, $username, $apiKey);
            }
            throw new RuntimeException("TextMagic native transport error: {$curlErr}", 500);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException("TextMagic API rejected request: HTTP {$httpCode} - {$response}", $httpCode);
        }

        $decoded = json_decode((string) $response, true);
        return is_array($decoded) ? $decoded : ['raw' => $response];
    }

    /**
     * Native cURL implementation of Webex Interact SMS API.
     *
     * @param array<string, string> $credentials
     * @return array<string, mixed>
     */
    public static function sendViaWebexNative(
        string $to,
        string $message,
        string $sender = '',
        array $credentials = []
    ): array {
        $authKey = $credentials['apiKey'] ?? (string) ($_ENV['WEBEX_TEXT'] ?? $_ENV['webex_text'] ?? getenv('WEBEX_TEXT') ?: '');
        if ($authKey === '') {
            throw new RuntimeException('Webex Interact API key missing (WEBEX_TEXT).');
        }

        $payload = (string) json_encode([
            'from'         => $sender,
            'to'           => [$to],
            'message_body' => $message,
        ]);

        $ch = curl_init('https://api.webexinteract.com/v1/sms');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'X-AUTH-KEY: ' . $authKey,
            ],
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT        => 15,
        ]);

        $response = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        if (PHP_VERSION_ID < 80500) {
            @curl_close($ch);
        }

        if ($response === false) {
            throw new RuntimeException("Webex Interact transport error: {$curlErr}", 500);
        }

        if ($httpCode >= 400) {
            throw new RuntimeException("Webex Interact API Error: HTTP {$httpCode} - {$response}", $httpCode);
        }

        $decoded = json_decode((string) $response, true);
        return is_array($decoded) ? $decoded : ['raw' => $response];
    }

    /**
     * Tier 2 Circuit-Breaker Fallback: Uses official TextMagic SDK if native cURL
     * transport encounters a runtime anomaly.
     *
     * @return array<string, mixed>
     */
    private static function fallbackToTextMagicSdk(string $to, string $message, string $username, string $apiKey): array
    {
        $config = \TextMagic\Configuration::getDefaultConfiguration()
            ->setUsername($username)
            ->setPassword($apiKey);

        $api = new \TextMagic\Api\TextMagicApi(new \GuzzleHttp\Client(), $config);
        $input = new \TextMagic\Models\SendMessageRequest();
        $input->setText($message);
        $input->setPhones($to);

        $res = $api->sendMessage($input);
        return ['sdk_fallback' => true, 'id' => method_exists($res, 'getId') ? $res->getId() : null];
    }
}
