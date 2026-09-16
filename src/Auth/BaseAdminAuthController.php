<?php

declare(strict_types=1);

namespace Src\Auth;

use Src\Db;
use Src\AuditLogger;

abstract class BaseAdminAuthController
{
    abstract protected function getAppName(): string;
    abstract protected function getDashboardUrl(): string;
    abstract protected function getClientIp(): string;
    abstract protected function generateFingerprint(string $ip, string $userAgent): string;
    abstract protected function getAdminTableName(): string;
    abstract protected function getAdminColumnMap(): array;

    protected function adminUrl(string $path = ''): string
    {
        $prefix = '/' . trim((string) ($_ENV['ADMIN_SECRET_PATH'] ?? getenv('ADMIN_SECRET_PATH') ?: 'admin'), '/');
        return $path === '' ? $prefix : $prefix . '/' . ltrim($path, '/');
    }

    private function buildAdminSelectQuery(): array
    {
        $columnMap = $this->getAdminColumnMap();
        $tableName = $this->getAdminTableName();

        $selectParts = [];
        foreach ($columnMap as $alias => $dbColumn) {
            $selectParts[] = "{$dbColumn} AS {$alias}";
        }

        $selectClause = implode(', ', $selectParts);
        $query = "SELECT {$selectClause} FROM {$tableName} WHERE {$columnMap['email']} = ? LIMIT 1";

        return ['query' => $query, 'columnMap' => $columnMap];
    }

    private function enforceIpFilter(): void
    {
        $allowedIpsRaw = trim((string) ($_ENV['ADMIN_ALLOWED_IPS'] ?? getenv('ADMIN_ALLOWED_IPS') ?: ''));
        if ($allowedIpsRaw === '') {
            return;
        }

        $allowedIps = array_filter(array_map('trim', explode(',', $allowedIpsRaw)));
        $clientIp   = $this->getClientIp();

        if (!in_array($clientIp, $allowedIps, true)) {
            http_response_code(404);
            exit;
        }
    }

    private function logAudit(string $email, string $status): void
    {
        try {
            AuditLogger::log('admin_auth_event', [
                'email'      => $email,
                'ip_address' => $this->getClientIp(),
                'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
                'status'     => $status,
            ]);
        } catch (\Throwable) {}

        try {
            $db   = Db::connect2();
            $stmt = $db->prepare(
                'INSERT INTO audit_logs (email, ip_address, user_agent, status, created_at)
                 VALUES (?, ?, ?, ?, NOW())'
            );
            $stmt->execute([
                $email,
                $this->getClientIp(),
                substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
                $status,
            ]);
        } catch (\Throwable) {}
    }

    private function getLockoutRemaining(string $email): int
    {
        try {
            $db   = Db::connect2();
            $ip   = $this->getClientIp();
            $stmt = $db->prepare(
                'SELECT locked_until FROM admin_login_attempts
                 WHERE email = ? AND ip_address = ? LIMIT 1'
            );
            $stmt->execute([$email, $ip]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($row && !empty($row['locked_until'])) {
                $remaining = strtotime((string) $row['locked_until']) - time();
                return $remaining > 0 ? $remaining : 0;
            }
        } catch (\Throwable) {}
        return 0;
    }

    private function recordFailedAttempt(string $email): void
    {
        try {
            $db  = Db::connect2();
            $ip  = $this->getClientIp();
            $max = (int) ($_ENV['ADMIN_MAX_LOGIN_ATTEMPTS'] ?? 3);
            $lockoutSec = (int) ($_ENV['ADMIN_LOCKOUT_SECONDS'] ?? 900);

            $db->prepare(
                'INSERT INTO admin_login_attempts (email, ip_address, attempt_count, last_attempt_at)
                 VALUES (?, ?, 1, NOW())
                 ON DUPLICATE KEY UPDATE
                     attempt_count   = attempt_count + 1,
                     last_attempt_at = NOW(),
                     locked_until    = IF(attempt_count + 1 >= ?, DATE_ADD(NOW(), INTERVAL ? SECOND), locked_until)'
            )->execute([$email, $ip, $max, $lockoutSec]);
        } catch (\Throwable) {}
    }

    private function clearFailedAttempts(string $email): void
    {
        try {
            $db = Db::connect2();
            $ip = $this->getClientIp();
            $db->prepare(
                'DELETE FROM admin_login_attempts WHERE email = ? AND ip_address = ?'
            )->execute([$email, $ip]);
        } catch (\Throwable) {}
    }

    private function recordTotpUsedStep(string $email, string $otp): bool
    {
        $currentStep = (int) floor(time() / 30);

        try {
            $db = Db::connect2();
            $columnMap = $this->getAdminColumnMap();
            $tableName = $this->getAdminTableName();

            $stmt = $db->prepare(
                "SELECT {$columnMap['totp_last_used_step']} FROM {$tableName} WHERE {$columnMap['email']} = ? LIMIT 1"
            );
            $stmt->execute([$email]);
            $lastStep = $stmt->fetchColumn();

            if ($lastStep !== false && (int) $lastStep >= $currentStep) {
                return false;
            }

            $db->prepare(
                "UPDATE {$tableName} SET {$columnMap['totp_last_used_step']} = ? WHERE {$columnMap['email']} = ?"
            )->execute([$currentStep, $email]);
        } catch (\Throwable) {}

        return true;
    }

    public function showLogin(): void
    {
        $this->enforceIpFilter();

        if (isset($_SESSION['auth']) && ($_SESSION['auth']['type'] ?? '') === 'super_admin') {
            if (!empty($_SESSION['auth']['2fa_passed'])) {
                redirect($this->getDashboardUrl());
                return;
            }
            redirect($this->adminUrl('2fa'));
            return;
        }

        view('admin.auth.login', ['adminPrefix' => $this->adminUrl('')]);
    }

    public function login(): void
    {
        $this->enforceIpFilter();

        $email    = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = trim((string) ($_POST['password'] ?? ''));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $_SESSION['formError'] = 'Please provide a valid email and password.';
            redirect($this->adminUrl('login'));
            return;
        }

        $lockoutRemaining = $this->getLockoutRemaining($email);
        if ($lockoutRemaining > 0) {
            $mins = (int) ceil($lockoutRemaining / 60);
            $_SESSION['formError'] = "Account temporarily locked. Try again in {$mins} " . ($mins === 1 ? 'minute' : 'minutes') . '.';
            redirect($this->adminUrl('login'));
            return;
        }

        $db   = Db::connect2();
        ['query' => $query, 'columnMap' => $columnMap] = $this->buildAdminSelectQuery();
        $stmt = $db->prepare($query);
        $stmt->execute([$email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        $passwordHash = (string) ($user['password'] ?? '');
        $passwordValid = !empty($passwordHash) && password_verify($password, $passwordHash);

        if (!$user || !$passwordValid) {
            $this->recordFailedAttempt($email);
            $this->logAudit($email, 'Failed super admin login attempt');
            $_SESSION['formError'] = 'Invalid super admin credentials.';
            redirect($this->adminUrl('login'));
            return;
        }

        if (($user['type'] ?? '') !== 'super_admin') {
            $this->logAudit($email, 'Unauthorized admin portal access attempt');
            $_SESSION['formError'] = 'Access restricted to Super Admin accounts.';
            redirect($this->adminUrl('login'));
            return;
        }

        if (($user['status'] ?? '') === 'suspended') {
            $this->logAudit($email, 'Suspended admin account login attempt');
            $_SESSION['formError'] = 'This admin account has been suspended.';
            redirect($this->adminUrl('login'));
            return;
        }

        $this->clearFailedAttempts($email);
        session_regenerate_id(true);

        $_SESSION['id'] = $user['id'];
        $_SESSION['auth'] = [
            'id'           => $user['id'],
            'identifyCust' => $user['id'],
            'email'        => $user['email'],
            'firstName'    => $user['firstName'] ?? 'Admin',
            'lastName'     => $user['lastName'] ?? '',
            'type'         => 'super_admin',
            '2fa_passed'   => false,
            'fingerprint'  => $this->generateFingerprint(
                $this->getClientIp(),
                (string) ($_SERVER['HTTP_USER_AGENT'] ?? '')
            ),
        ];

        $totpRequired = filter_var($_ENV['ADMIN_TOTP_REQUIRED'] ?? true, FILTER_VALIDATE_BOOLEAN);
        $totpEnabled  = !empty($user['totp_enabled']) && !empty($user['totp_secret']);

        if (!$totpRequired && !$totpEnabled) {
            $_SESSION['auth']['2fa_passed'] = true;
            $this->logAudit($email, 'Super admin login successful (2FA bypass)');
            redirect($this->getDashboardUrl());
            return;
        }

        if (!$totpEnabled) {
            $this->logAudit($email, 'Super admin login successful — 2FA pairing required');
            redirect($this->adminUrl('2fa/setup'));
            return;
        }

        $this->logAudit($email, 'Super admin login step 1 passed — 2FA challenge required');
        redirect($this->adminUrl('2fa'));
    }

    public function show2faSetup(): void
    {
        $this->enforceIpFilter();

        if (!isset($_SESSION['auth']) || ($_SESSION['auth']['type'] ?? '') !== 'super_admin') {
            redirect($this->adminUrl('login'));
            return;
        }

        $email = (string) ($_SESSION['auth']['email'] ?? '');
        try {
            $db   = Db::connect2();
            $columnMap = $this->getAdminColumnMap();
            $tableName = $this->getAdminTableName();

            $stmt = $db->prepare(
                "SELECT {$columnMap['totp_enabled']} FROM {$tableName} WHERE {$columnMap['email']} = ? LIMIT 1"
            );
            $stmt->execute([$email]);
            if ((bool) $stmt->fetchColumn()) {
                $_SESSION['formError'] = 'Google Authenticator is already configured for this account. Contact support to re-pair.';
                redirect($this->adminUrl('2fa'));
                return;
            }
        } catch (\Throwable) {}

        if (empty($_SESSION['auth']['temp_totp_secret'])) {
            $_SESSION['auth']['temp_totp_secret'] = TotpService::generateSecret();
        }

        $secret          = $_SESSION['auth']['temp_totp_secret'];
        $provisioningUri = TotpService::getProvisioningUri($email, $secret, $this->getAppName());
        $qrCodeSvg       = TotpService::getQrCodeDataUri($provisioningUri);

        view('admin.auth.twoFactorSetup', [
            'secret'      => $secret,
            'qrCodeSvg'   => $qrCodeSvg,
            'adminPrefix' => $this->adminUrl(''),
        ]);
    }

    public function save2faSetup(): void
    {
        $this->enforceIpFilter();

        if (!isset($_SESSION['auth']) || ($_SESSION['auth']['type'] ?? '') !== 'super_admin') {
            redirect($this->adminUrl('login'));
            return;
        }

        $otp    = trim((string) ($_POST['otp'] ?? ''));
        $secret = (string) ($_SESSION['auth']['temp_totp_secret'] ?? '');
        $email  = (string) ($_SESSION['auth']['email'] ?? '');

        if ($secret === '' || !TotpService::verifyCode($secret, $otp)) {
            $_SESSION['formError'] = 'Invalid 6-digit Google Authenticator code. Please try again.';
            redirect($this->adminUrl('2fa/setup'));
            return;
        }

        if (!$this->recordTotpUsedStep($email, $otp)) {
            $_SESSION['formError'] = 'This code has already been used. Wait for the next 30-second code.';
            redirect($this->adminUrl('2fa/setup'));
            return;
        }

        $db = Db::connect2();
        $columnMap = $this->getAdminColumnMap();
        $tableName = $this->getAdminTableName();

        $db->prepare(
            "UPDATE {$tableName} SET {$columnMap['totp_secret']} = ?, {$columnMap['totp_enabled']} = 1 WHERE {$columnMap['email']} = ?"
        )->execute([$secret, $email]);

        unset($_SESSION['auth']['temp_totp_secret']);
        $_SESSION['auth']['2fa_passed'] = true;

        $this->logAudit($email, 'Super admin enabled and verified Google Authenticator 2FA');
        $_SESSION['formSuccess'] = 'Google Authenticator 2FA successfully paired and enabled!';
        redirect($this->getDashboardUrl());
    }

    public function show2fa(): void
    {
        $this->enforceIpFilter();

        if (!isset($_SESSION['auth']) || ($_SESSION['auth']['type'] ?? '') !== 'super_admin') {
            redirect($this->adminUrl('login'));
            return;
        }

        if (!empty($_SESSION['auth']['2fa_passed'])) {
            redirect($this->getDashboardUrl());
            return;
        }

        view('admin.auth.twoFactor', ['adminPrefix' => $this->adminUrl('')]);
    }

    public function verify2fa(): void
    {
        $this->enforceIpFilter();

        if (!isset($_SESSION['auth']) || ($_SESSION['auth']['type'] ?? '') !== 'super_admin') {
            redirect($this->adminUrl('login'));
            return;
        }

        $otp   = trim((string) ($_POST['otp'] ?? ''));
        $email = (string) ($_SESSION['auth']['email'] ?? '');

        $db   = Db::connect2();
        $columnMap = $this->getAdminColumnMap();
        $tableName = $this->getAdminTableName();

        $stmt = $db->prepare(
            "SELECT {$columnMap['totp_secret']} FROM {$tableName} WHERE {$columnMap['email']} = ? LIMIT 1"
        );
        $stmt->execute([$email]);
        $secret = $stmt->fetchColumn();

        if ($secret === false || $secret === '' || !TotpService::verifyCode((string) $secret, $otp)) {
            $this->logAudit($email, 'Failed TOTP 2FA code verification');
            $_SESSION['formError'] = 'Invalid Google Authenticator code.';
            redirect($this->adminUrl('2fa'));
            return;
        }

        if (!$this->recordTotpUsedStep($email, $otp)) {
            $this->logAudit($email, 'TOTP replay attack blocked');
            $_SESSION['formError'] = 'This code has already been used. Wait for the next 30-second code.';
            redirect($this->adminUrl('2fa'));
            return;
        }

        $_SESSION['auth']['2fa_passed'] = true;
        $this->logAudit($email, 'Passed TOTP 2FA challenge');
        redirect($this->getDashboardUrl());
    }

    public function showForgotPassword(): void
    {
        $this->enforceIpFilter();
        view('admin.auth.forgotPassword', ['adminPrefix' => $this->adminUrl('')]);
    }

    public function sendResetLink(): void
    {
        $this->enforceIpFilter();

        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['formError'] = 'Please enter a valid admin email address.';
            redirect($this->adminUrl('forgot-password'));
            return;
        }

        $db   = Db::connect2();
        $columnMap = $this->getAdminColumnMap();
        $tableName = $this->getAdminTableName();

        $stmt = $db->prepare(
            "SELECT {$columnMap['id']} AS id, {$columnMap['email']} AS email, {$columnMap['type']} AS type
             FROM {$tableName} WHERE {$columnMap['email']} = ? LIMIT 1"
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if ($user && ($user['type'] ?? '') === 'super_admin') {
            $token   = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + 3600);

            $db->prepare(
                "UPDATE {$tableName} SET {$columnMap['reset_token']} = ?, {$columnMap['reset_token_expires_at']} = ? WHERE {$columnMap['email']} = ?"
            )->execute([$token, $expires, $email]);

            $this->logAudit($email, 'Admin password reset link dispatched');
        }

        $_SESSION['formSuccess'] = 'If that email belongs to a registered super admin, reset instructions have been dispatched.';
        redirect($this->adminUrl('forgot-password'));
    }

    public function showResetPassword(): void
    {
        $this->enforceIpFilter();

        $token = (string) ($_GET['token'] ?? '');
        $email = (string) ($_GET['email'] ?? '');

        view('admin.auth.resetPassword', [
            'token'       => $token,
            'email'       => $email,
            'adminPrefix' => $this->adminUrl(''),
        ]);
    }

    public function updatePassword(): void
    {
        $this->enforceIpFilter();

        $token          = (string) ($_POST['token'] ?? '');
        $email          = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password       = (string) ($_POST['password'] ?? '');
        $passwordConfirm = (string) ($_POST['password_confirm'] ?? '');

        if (strlen($password) < 8) {
            $_SESSION['formError'] = 'Password must be at least 8 characters long.';
            redirect($this->adminUrl('reset-password?token=' . urlencode($token) . '&email=' . urlencode($email)));
            return;
        }

        if ($password !== $passwordConfirm) {
            $_SESSION['formError'] = 'Passwords do not match.';
            redirect($this->adminUrl('reset-password?token=' . urlencode($token) . '&email=' . urlencode($email)));
            return;
        }

        $db   = Db::connect2();
        $columnMap = $this->getAdminColumnMap();
        $tableName = $this->getAdminTableName();

        $stmt = $db->prepare(
            "SELECT {$columnMap['id']} AS id, {$columnMap['email']} AS email, {$columnMap['type']} AS type
             FROM {$tableName}
             WHERE {$columnMap['email']} = ? AND {$columnMap['reset_token']} = ? AND {$columnMap['reset_token_expires_at']} > NOW() LIMIT 1"
        );
        $stmt->execute([$email, $token]);
        $user = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$user || ($user['type'] ?? '') !== 'super_admin') {
            $_SESSION['formError'] = 'Invalid or expired password reset token.';
            redirect($this->adminUrl('forgot-password'));
            return;
        }

        $newHash = password_hash($password, PASSWORD_BCRYPT);
        $db->prepare(
            "UPDATE {$tableName} SET {$columnMap['password']} = ?, {$columnMap['reset_token']} = NULL, {$columnMap['reset_token_expires_at']} = NULL WHERE {$columnMap['id']} = ?"
        )->execute([$newHash, $user['id']]);

        $this->logAudit($email, 'Super admin successfully reset password');
        $_SESSION['formSuccess'] = 'Password reset successfully. Please log in with your new password.';
        redirect($this->adminUrl('login'));
    }

    public function logout(): void
    {
        if (!empty($_SESSION['auth']['email'])) {
            $this->logAudit($_SESSION['auth']['email'], 'Super admin logged out');
        }

        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            $name   = session_name();
            if (is_string($name)) {
                setcookie($name, '', time() - 42000, $params['path'], $params['domain'],
                    (bool) $params['secure'], (bool) $params['httponly']);
            }
        }
        session_destroy();

        redirect($this->adminUrl('login'));
    }
}
