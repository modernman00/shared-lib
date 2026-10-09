<?php

declare(strict_types=1);

namespace Src\functionality;

use InvalidArgumentException;
use Src\CorsHandler;
use Src\Db;
use Src\Exceptions\NotFoundException;
use Src\Exceptions\UnauthorisedException;
use Src\functionality\middleware\GetRequestData;
use Src\functionality\middleware\Validator;
use Src\Limiter;
use Src\LoginUtility;
use Src\Recaptcha;
use Src\Utility;

/**
 * Handles administrative login with Google Authenticator (TOTP) 2FA detection and session gating.
 *
 * Enforces:
 * 1. Admin Secret Access Code verification ($_ENV['CODING']).
 * 2. IP Ban and reCAPTCHA verification.
 * 3. Rate limiting by email/username.
 * 4. Input validation and password verification.
 * 5. TOTP enrollment status inspection (google2fa_secret / totp_secret).
 * 6. Secure session binding and deferred JWT issuance until 2FA pass.
 */
class LoginAdminFunctionality
{
    public static function show(string $viewPath): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION['auth']['showLogin'] = true;
        view2($viewPath);
    }

    /**
     * Authenticates an admin login request and branches to TOTP verification or enrollment.
     *
     * @param bool $isCaptcha whether to enforce CAPTCHA v2 verification
     * @param bool $isCaptchaV3 whether to use reCAPTCHA v3 / Enterprise verification
     * @param string $captchaAction action label used for CAPTCHA verification
     * @param string $returnType response type ('json' or 'array')
     * @param string $role default role to assign (default 'admin')
     * @param bool $requireSecretCode whether to require and validate $_ENV['CODING']
     * @param (callable(string|int, array<string, mixed>): bool)|null $totpResolver Custom resolver for 2FA enrollment check
     *
     * @return array<string, mixed>|void
     *
     * @throws NotFoundException|UnauthorisedException|InvalidArgumentException
     */
    public static function login(
        bool $isCaptcha = false,
        bool $isCaptchaV3 = true,
        string $captchaAction = 'login',
        string $returnType = 'json',
        string $role = 'admin',
        bool $requireSecretCode = true,
        ?callable $totpResolver = null
    ) {
        try {
            $input = GetRequestData::getRequestData();
            if (!$input) {
                throw new NotFoundException('There was no post data');
            }

            // 1. Admin Secret Code Gate
            if ($requireSecretCode) {
                $expectedCode = trim((string) ($_ENV['CODING'] ?? getenv('CODING') ?: ''));
                $code = trim((string) ($input['code'] ?? ''));

                if ($expectedCode === '' || $code === '' || !hash_equals($expectedCode, $code)) {
                    throw new UnauthorisedException('Invalid Admin Code provided.');
                }
            }

            // 2. IP Ban & Perimeter Defense
            LoginUtility::checkIpBan(Utility::getUserIpAddr());
            CorsHandler::setHeaders();

            if ($isCaptchaV3) {
                Recaptcha::verifyCaptchaEnterprise($input, $captchaAction);
                unset($input['action'], $input['token']);
            } elseif ($isCaptcha) {
                Recaptcha::verifyCaptcha($input);
            }

            // 3. Rate Limiting
            $email = (string) (Utility::cleanSession($input['email'] ?? null) ?? Utility::cleanSession($input['username'] ?? null) ?? '');
            if ($email === '') {
                throw new InvalidArgumentException('Email or username is required');
            }
            Limiter::limit($email);

            // 4. Validation & Password Check
            Validator::requireKeys($input, ['email', 'password']);
            $sanitised = LoginUtility::getSanitisedInputData($input, [
                'data' => ['email', 'password'],
                'min'  => [5, 5],
                'max'  => [50, 100],
            ]);

            $user = LoginUtility::useEmailToFindData($sanitised);
            LoginUtility::checkPassword($sanitised, $user);

            $userId = $user['id'] ?? $user['no'] ?? '';
            $userEmail = (string) ($user['email'] ?? $email);

            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
            $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown');

            LoginUtility::logAudit($userId, $userEmail, 'success', $ip, $ua);
            unset($user['password']);

            // 5. TOTP Enrollment Status Inspection
            $hasTotpEnrolled = false;
            if ($totpResolver !== null) {
                $hasTotpEnrolled = (bool) $totpResolver($userId, $user);
            } else {
                $hasTotpEnrolled = self::resolveTotpEnrollment($userId, $user);
            }

            // 6. Secure Session Binding (Gated: totp_verified = false)
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }

            $_SESSION['admin_id']      = $userId;
            $_SESSION['role']          = $role;
            $_SESSION['totp_verified'] = false; // Strictly deferred until 2FA gate passes

            // Bind session security headers & fingerprint
            $_SESSION['admin_session_ip']          = $ip;
            $_SESSION['admin_session_ua']          = $ua;
            $_SESSION['admin_session_fingerprint'] = hash('sha256', $ip . $ua);

            if (!isset($_SESSION['auth']) || !is_array($_SESSION['auth'])) {
                $_SESSION['auth'] = [];
            }
            $_SESSION['auth']['identifyCust'] = $userId;
            $_SESSION['auth']['email']        = $userEmail;
            $_SESSION['auth']['role']         = $role;

            // Reset rate limiter on authenticated credentials
            Limiter::$argLimiter?->reset();
            Limiter::$ipLimiter?->reset();

            if ($hasTotpEnrolled) {
                $_SESSION['totp_setup_required'] = false;
                if ($returnType === 'json') {
                    Utility::msgSuccess(200, '2FA_REQUIRED', '2FA_REQUIRED');
                    return;
                }
                return [
                    'status' => '2FA_REQUIRED',
                    'userId' => $userId,
                    'email'  => $userEmail,
                    'totp_enrolled' => true,
                ];
            }

            // Not yet enrolled: Force admin into mandatory TOTP onboarding
            $_SESSION['totp_setup_required'] = true;
            if ($returnType === 'json') {
                Utility::msgSuccess(200, '2FA_SETUP_REQUIRED', '2FA_SETUP_REQUIRED');
                return;
            }
            return [
                'status' => '2FA_SETUP_REQUIRED',
                'userId' => $userId,
                'email'  => $userEmail,
                'totp_enrolled' => false,
            ];
        } catch (\Throwable $th) {
            if ($returnType === 'json') {
                Utility::showError($th);
            } else {
                throw $th;
            }
        }
    }

    /**
     * Resolves whether an admin has 2FA enrolled across diverse schema naming conventions.
     *
     * @param string|int $userId
     * @param array<string, mixed> $userRow
     */
    private static function resolveTotpEnrollment(string|int $userId, array $userRow): bool
    {
        // 1. Direct check in existing user row (google2fa_* or totp_*)
        $secret = $userRow['google2fa_secret'] ?? $userRow['totp_secret'] ?? $userRow['two_fa_secret'] ?? null;
        $enabled = $userRow['google2fa_enabled'] ?? $userRow['totp_enabled'] ?? $userRow['two_fa_enabled'] ?? null;

        if (!empty($secret) && !empty($enabled)) {
            return true;
        }

        // 2. Query database if fields weren't in the initial login query
        try {
            $table = (string) ($_ENV['DB_TABLE_LOGIN'] ?? getenv('DB_TABLE_LOGIN') ?: 'account');
            $db = Db::connect2();
            $stmt = $db->prepare(
                "SELECT * FROM `{$table}` WHERE `id` = ? LIMIT 1"
            );
            $stmt->execute([$userId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row) {
                $secret = $row['google2fa_secret'] ?? $row['totp_secret'] ?? $row['two_fa_secret'] ?? null;
                $enabled = $row['google2fa_enabled'] ?? $row['totp_enabled'] ?? $row['two_fa_enabled'] ?? null;
                return !empty($secret) && !empty($enabled);
            }
        } catch (\Throwable) {
            // Defensive: DB table missing columns or connection error
        }

        return false;
    }
}
