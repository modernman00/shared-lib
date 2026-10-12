<?php

declare(strict_types=1);

namespace Src\Auth;

/**
 * Pure PHP RFC 6238 TOTP Service for Google Authenticator 2FA.
 * Shared across all portfolio applications via modernman00/shared-lib.
 */
class TotpService
{
    private static string $base32Chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /**
     * Generate a random base32 secret (16 chars = 80 bits secret)
     */
    public static function generateSecret(int $length = 16): string
    {
        $secret = '';
        for ($i = 0; $i < $length; $i++) {
            $secret .= self::$base32Chars[random_int(0, 31)];
        }
        return $secret;
    }

    /**
     * Decode Base32 string to binary
     */
    private static function base32Decode(string $base32): string
    {
        $base32 = strtoupper($base32);
        $buffer = 0;
        $bitsLeft = 0;
        $binary = '';

        for ($i = 0; $i < strlen($base32); $i++) {
            $char = $base32[$i];
            $pos = strpos(self::$base32Chars, $char);
            if ($pos === false) {
                continue;
            }

            $buffer = ($buffer << 5) | $pos;
            $bitsLeft += 5;

            if ($bitsLeft >= 8) {
                $bitsLeft -= 8;
                $binary .= chr(($buffer >> $bitsLeft) & 0xFF);
            }
        }

        return $binary;
    }

    /**
     * Calculate 6-digit TOTP code for a given timestamp
     */
    public static function getCode(string $secret, ?int $timestamp = null, int $period = 30, int $digits = 6): string
    {
        if ($timestamp === null) {
            $timestamp = time();
        }

        $timeStep = (int) floor($timestamp / $period);
        $timeBinary = pack('N*', 0) . pack('N*', $timeStep);
        $secretBinary = self::base32Decode($secret);

        $hash = hash_hmac('sha1', $timeBinary, $secretBinary, true);
        $offset = ord($hash[strlen($hash) - 1]) & 0x0F;

        $truncatedHash = (
            ((ord($hash[$offset]) & 0x7F) << 24) |
            ((ord($hash[$offset + 1]) & 0xFF) << 16) |
            ((ord($hash[$offset + 2]) & 0xFF) << 8) |
            (ord($hash[$offset + 3]) & 0xFF)
        );

        $otp = $truncatedHash % (10 ** $digits);
        return str_pad((string) $otp, $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Verify a 6-digit TOTP code with time drift window (default +/- 1 period = 30 seconds)
     */
    public static function verifyCode(string $secret, string $code, int $discrepancy = 1, ?int $currentTime = null): bool
    {
        $code = trim($code);
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return false;
        }

        if ($currentTime === null) {
            $currentTime = time();
        }

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $calculatedCode = self::getCode($secret, $currentTime + ($i * 30));
            if (hash_equals($calculatedCode, $code)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Verify a 6-digit TOTP code and return the matched time step for replay protection.
     * Returns the integer step if valid, or null if invalid.
     */
    public static function verifyCodeAndGetStep(string $secret, string $code, int $discrepancy = 1, ?int $currentTime = null): ?int
    {
        $code = trim($code);
        if (strlen($code) !== 6 || !ctype_digit($code)) {
            return null;
        }

        if ($currentTime === null) {
            $currentTime = time();
        }

        for ($i = -$discrepancy; $i <= $discrepancy; $i++) {
            $ts = $currentTime + ($i * 30);
            $calculatedCode = self::getCode($secret, $ts);
            if (hash_equals($calculatedCode, $code)) {
                return (int) floor($ts / 30);
            }
        }

        return null;
    }

    /**
     * Complete enterprise 2FA verification using shared-lib rate limiting (Src\Limiter) and replay protection.
     *
     * @param string $secret The account's Base32 TOTP secret
     * @param string $code The submitted 6-digit code
     * @param string $email The user's email for rate limiting and logging
     * @param int|null $lastUsedStep The last recorded time-step from DB (to block replays)
     * @return array{success: bool, step?: int, error?: string, reason?: string}
     */
    public static function verifySubmission(string $secret, string $code, string $email, ?int $lastUsedStep = null): array
    {
        // 1. Enforce shared-lib rate limiting (5 attempts / 15-minute window)
        try {
            if (class_exists(\Src\Limiter::class)) {
                \Src\Limiter::limit($email, 'login');
            }
        } catch (\Src\Exceptions\TooManyRequestsException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'reason' => 'rate_limited'
            ];
        }

        // 2. Validate TOTP code & extract matched time-step
        $matchedStep = self::verifyCodeAndGetStep($secret, $code);

        // 3. Replay attack detection
        if ($matchedStep !== null && $lastUsedStep !== null && $matchedStep <= $lastUsedStep) {
            return [
                'success' => false,
                'error' => 'This security code has already been used. Please wait for the next 30-second code.',
                'reason' => 'replay_blocked',
                'step' => $matchedStep
            ];
        }

        // 4. Success path: reset rate limiter on verified credentials
        if ($matchedStep !== null) {
            if (class_exists(\Src\Limiter::class)) {
                \Src\Limiter::resetAll();
            }
            return [
                'success' => true,
                'step' => $matchedStep
            ];
        }

        // 5. Invalid code
        return [
            'success' => false,
            'error' => 'Invalid security code. Please enter the current 6-digit code from your Google Authenticator or 1Password app.',
            'reason' => 'invalid_code'
        ];
    }

    /**
     * Generate otpauth:// provisioning URI for Google Authenticator
     */
    public static function getProvisioningUri(string $accountEmail, string $secret, string $issuer = 'PartyPlatform'): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?secret=%s&issuer=%s&period=30&digits=6',
            rawurlencode($issuer),
            rawurlencode($accountEmail),
            $secret,
            rawurlencode($issuer)
        );
    }

    /**
     * Generate inline Data URI SVG QR Code image
     */
    public static function getQrCodeDataUri(string $provisioningUri): string
    {
        if (class_exists(\chillerlan\QRCode\QRCode::class)) {
            $options = new \chillerlan\QRCode\QROptions([
                'outputInterface' => \chillerlan\QRCode\Output\QRMarkupSVG::class,
                'eccLevel' => \chillerlan\QRCode\Common\EccLevel::M,
                'scale' => 5,
                'svgAddXmlHeader' => false,
            ]);

            $qrcode = new \chillerlan\QRCode\QRCode($options);
            return $qrcode->render($provisioningUri);
        }

        // Fallback to Google Chart API Data URI if chillerlan unavailable
        return 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode($provisioningUri);
    }
}
