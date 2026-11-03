<?php
declare(strict_types=1);

namespace Src\functionality;

use Src\functionality\middleware\GetRequestData;
use Src\Utility;
use Src\Db;
use Src\JwtHandler;
use PDO;

/**
 * Handles WebAuthn HTTP routes for all applications.
 */
class WebAuthnFunctionality
{
    private static bool $schemaEnsured = false;

    /**
     * Idempotently ensures the user_passkeys table exists in the active database.
     */
    public static function ensureSchema(PDO $db): void
    {
        if (self::$schemaEnsured) {
            return;
        }
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS user_passkeys (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id VARCHAR(50) NOT NULL,
                user_role VARCHAR(50) NOT NULL DEFAULT 'users',
                credential_id TEXT NOT NULL,
                public_key TEXT NOT NULL,
                device_name VARCHAR(255) NULL,
                last_used_at TIMESTAMP NULL,
                attestation_type VARCHAR(100) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            self::$schemaEnsured = true;
        } catch (\Throwable) {
            // Ignore DDL errors on restricted connections; queries will handle fallback gracefully
        }
    }

    /**
     * Resolves the currently authenticated user across all portfolio session conventions.
     *
     * @return array{user_id: string, email: string, name: string, role: string}|null
     */
    public static function resolveAuthenticatedUser(): ?array
    {
        if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
            try {
                \Src\SecureSession::start();
            } catch (\Throwable) {
                @session_start();
            }
        }

        $userId = $_SESSION['user_id']
            ?? $_SESSION['id']
            ?? $_SESSION['manager_id']
            ?? $_SESSION['auth']['identifyCust']
            ?? $_SESSION['auth']['id']
            ?? $_SESSION['staff_email']
            ?? $_SESSION['client_email']
            ?? $_SESSION['landlord_id']
            ?? $_SESSION['tenant_id']
            ?? null;

        if (empty($userId)) {
            // Attempt passive JWT cookie rehydration if available
            try {
                if (SignIn::rehydrateSession('users')) {
                    $userId = $_SESSION['user_id']
                        ?? $_SESSION['id']
                        ?? $_SESSION['auth']['identifyCust']
                        ?? null;
                }
            } catch (\Throwable) {
                // Ignore rehydration failures for unauthenticated guests
            }
        }

        if (empty($userId)) {
            return null;
        }

        $userIdStr = trim((string) $userId);
        if ($userIdStr === '') {
            return null;
        }

        $role = 'users';
        if (!empty($_SESSION['role']) && is_string($_SESSION['role'])) {
            $role = $_SESSION['role'];
        } elseif (!empty($_SESSION['staff_email'])) {
            $role = 'staff';
        } elseif (!empty($_SESSION['client_email'])) {
            $role = 'client';
        } elseif (!empty($_SESSION['admin_code'])) {
            $role = 'admin';
        }

        $email = (string) (
            $_SESSION['auth']['email']
            ?? $_SESSION['user']['email']
            ?? $_SESSION['email']
            ?? $_SESSION['staff_email']
            ?? $_SESSION['client_email']
            ?? $userIdStr
        );

        $name = (string) (
            $_SESSION['user_name']
            ?? $_SESSION['auth']['name']
            ?? $_SESSION['user']['name']
            ?? $_SESSION['user']['fullName']
            ?? $_SESSION['staff_name']
            ?? $_SESSION['client_name']
            ?? $_SESSION['fullName']
            ?? 'Account Holder'
        );

        return [
            'user_id' => $userIdStr,
            'email'   => $email !== '' ? $email : 'user@example.com',
            'name'    => $name !== '' ? $name : 'Account Holder',
            'role'    => $role,
        ];
    }

    /**
     * Checks whether the specified user has at least one registered passkey.
     */
    public static function hasRegisteredPasskey(string $userId, bool $useSessionCache = true): bool
    {
        if ($userId === '') {
            return false;
        }

        $cacheKey = 'webauthn_enrolled_' . $userId;
        if ($useSessionCache && isset($_SESSION[$cacheKey]) && is_bool($_SESSION[$cacheKey])) {
            return $_SESSION[$cacheKey];
        }

        try {
            $db = Db::connect2();
            self::ensureSchema($db);
            $stmt = $db->prepare("SELECT COUNT(*) FROM user_passkeys WHERE user_id = ?");
            $stmt->execute([$userId]);
            $count = (int) $stmt->fetchColumn();
            $enrolled = $count > 0;
            if (session_status() === PHP_SESSION_ACTIVE) {
                $_SESSION[$cacheKey] = $enrolled;
            }
            return $enrolled;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Returns the client-side prompt state for SSR hydration and /webauthn/status.
     *
     * @return array{authenticated: bool, hasPasskey: bool, userId: string, userName: string, userEmail: string, userRole: string, userKey: string, appName: string}
     */
    public static function getClientPromptState(bool $useSessionCache = true): array
    {
        $appName = (string) ($_ENV['APP_NAME'] ?? 'Enterprise App');
        $resolved = self::resolveAuthenticatedUser();

        if ($resolved === null) {
            return [
                'authenticated' => false,
                'hasPasskey'    => false,
                'userId'        => '',
                'userName'      => '',
                'userEmail'     => '',
                'userRole'      => 'guest',
                'userKey'       => '',
                'appName'       => $appName,
            ];
        }

        $userId = $resolved['user_id'];
        $hasPasskey = self::hasRegisteredPasskey($userId, $useSessionCache);

        return [
            'authenticated' => true,
            'hasPasskey'    => $hasPasskey,
            'userId'        => $userId,
            'userName'      => $resolved['name'],
            'userEmail'     => $resolved['email'],
            'userRole'      => $resolved['role'],
            'userKey'       => substr(hash('sha256', $appName . ':' . $userId), 0, 16),
            'appName'       => $appName,
        ];
    }

    /**
     * Endpoint: /webauthn/status
     */
    public static function getPasskeyStatus(): void
    {
        try {
            $state = self::getClientPromptState(false);
            Utility::msgSuccess(200, "Passkey status retrieved", $state);
        } catch (\Throwable $th) {
            Utility::showError($th);
        }
    }

    /**
     * Endpoint: /webauthn/register/options
     */
    public static function getRegistrationOptions(string $role = 'users'): void
    {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                \Src\SecureSession::start();
            }

            $resolved = self::resolveAuthenticatedUser();
            if ($resolved === null && !SignIn::isLoggedIn($role)) {
                throw new \Src\Exceptions\UnauthorisedException("Must be logged in to register a device.");
            }

            $userId = trim((string) ($resolved['user_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? $_SESSION['auth']['id'] ?? ''));
            if ($userId === '') {
                throw new \Src\Exceptions\UnauthorisedException("Must be logged in to register a device.");
            }
            $email = (string) ($resolved['email'] ?? $_SESSION['auth']['email'] ?? $_SESSION['user']['email'] ?? 'user@example.com');
            $name = (string) ($resolved['name'] ?? $_SESSION['user_name'] ?? $_SESSION['auth']['name'] ?? $_SESSION['user']['name'] ?? 'User');

            $excludeCredentials = [];
            try {
                $db = Db::connect2();
                self::ensureSchema($db);
                $stmt = $db->prepare("SELECT credential_id FROM user_passkeys WHERE user_id = ?");
                $stmt->execute([$userId]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($rows as $row) {
                    if (!empty($row['credential_id'])) {
                        $excludeCredentials[] = [
                            'type' => 'public-key',
                            'id'   => (string) $row['credential_id'],
                        ];
                    }
                }
            } catch (\Throwable) {
                // Non-fatal if table lookup fails before first registration
            }

            $service = new WebAuthnService();
            $options = $service->generateRegistrationOptions($userId, $email, $name, $excludeCredentials);

            Utility::msgSuccess(200, "Registration options generated", $options);
        } catch (\Throwable $th) {
            Utility::showError($th);
        }
    }

    /**
     * Endpoint: /webauthn/register
     */
    public static function registerDevice(): void
    {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                \Src\SecureSession::start();
            }

            $resolved = self::resolveAuthenticatedUser();
            $userId = $resolved['user_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? $_SESSION['auth']['id'] ?? null;
            if (!$userId) {
                throw new \Src\Exceptions\UnauthorisedException("Must be logged in to register a device.");
            }

            $input = GetRequestData::getRequestData();
            
            $service = new WebAuthnService();
            $isValid = $service->verifySignature($input);

            if (!$isValid) {
                throw new \Exception("Invalid Passkey signature.");
            }

            $credentialId = $input['id'] ?? null;
            $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
            $defaultDevice = str_contains($ua, 'iPhone') ? 'iPhone (Face ID / Touch ID)'
                : (str_contains($ua, 'iPad') ? 'iPad (Face ID / Touch ID)'
                : (str_contains($ua, 'Mac') ? 'Mac (Touch ID)'
                : (str_contains($ua, 'Android') ? 'Android Biometrics'
                : (str_contains($ua, 'Windows') ? 'Windows Hello' : 'Biometric Passkey'))));
            $rawDeviceName = !empty($input['deviceName']) && is_string($input['deviceName']) ? trim(strip_tags($input['deviceName'])) : $defaultDevice;
            $deviceName = mb_substr($rawDeviceName !== '' ? $rawDeviceName : $defaultDevice, 0, 120);
            $publicKey = (string) ($input['rawId'] ?? $input['publicKey'] ?? ('pk_' . bin2hex(random_bytes(16))));

            if (!$credentialId || !is_string($credentialId) || strlen($credentialId) > 1024 || !preg_match('/^[A-Za-z0-9+\/_=-]+$/', $credentialId)) {
                throw new \InvalidArgumentException("Invalid or missing credential ID.");
            }

            // Marcus SecOps Mandate: Never trust client-supplied role ($input['role']); derive strictly from authenticated session
            $sessionRole = (string) ($resolved['role'] ?? $_SESSION['role'] ?? 'users');
            $userRole = in_array($sessionRole, ['users', 'staff', 'client', 'admin'], true) ? $sessionRole : 'users';

            $db = Db::connect2();
            self::ensureSchema($db);

            // Idempotency guard: check if this credential_id is already registered for this user
            $checkStmt = $db->prepare("SELECT id FROM user_passkeys WHERE user_id = ? AND credential_id = ? LIMIT 1");
            $checkStmt->execute([(string) $userId, $credentialId]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);

            if (!$existing) {
                try {
                    $stmt = $db->prepare("INSERT INTO user_passkeys (user_id, user_role, credential_id, public_key, device_name, attestation_type) VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        (string) $userId,
                        $userRole,
                        $credentialId,
                        $publicKey,
                        $deviceName,
                        'none'
                    ]);
                } catch (\PDOException $e) {
                    // Defensive fallback for legacy tables where user_role column does not exist yet
                    if (str_contains($e->getMessage(), 'user_role') || (string)$e->getCode() === '42S22') {
                        $stmt = $db->prepare("INSERT INTO user_passkeys (user_id, credential_id, public_key, device_name, attestation_type) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([
                            (string) $userId,
                            $credentialId,
                            $publicKey,
                            $deviceName,
                            'none'
                        ]);
                    } else {
                        throw $e;
                    }
                }
            }

            $_SESSION['webauthn_enrolled_' . (string) $userId] = true;
            
            Utility::msgSuccess(200, "Biometric Passkey activated successfully", [
                'enrolled'   => true,
                'deviceName' => $deviceName,
            ]);
        } catch (\Throwable $th) {
            Utility::showError($th);
        }
    }

    /**
     * Endpoint: /webauthn/login/options
     */
    public static function getLoginOptions(): void
    {
        try {
            $input = GetRequestData::getRequestData();
            $email = $input['email'] ?? $_GET['email'] ?? '';

            $service = new WebAuthnService();
            $challenge = random_bytes(32);
            if (session_status() === PHP_SESSION_NONE) {
                \Src\SecureSession::start();
            }
            $_SESSION['webauthn_challenge'] = base64_encode($challenge);

            $allowCredentials = [];
            if (!empty($email) && is_string($email) && strlen($email) <= 254) {
                $db = Db::connect2();
                $loginTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($_ENV['DB_TABLE_LOGIN'] ?? 'users')) ?: 'users';
                $stmt = $db->prepare("SELECT p.credential_id FROM user_passkeys p JOIN `{$loginTable}` u ON (p.user_id = u.id OR p.user_id = u.no) WHERE u.email = ?");
                $stmt->execute([trim($email)]);
                $creds = $stmt->fetchAll(PDO::FETCH_ASSOC);
                foreach ($creds as $c) {
                    $allowCredentials[] = [
                        'type' => 'public-key',
                        'id' => $c['credential_id']
                    ];
                }
            }

            $options = [
                'challenge' => base64_encode($challenge),
                'timeout' => 60000,
                'userVerification' => 'preferred',
                'allowCredentials' => $allowCredentials
            ];
            $rpId = explode(':', $_SERVER['HTTP_HOST'] ?? 'localhost')[0];
            if (!filter_var($rpId, FILTER_VALIDATE_IP) && $rpId !== '127.0.0.1') {
                $options['rpId'] = $rpId;
            }

            Utility::msgSuccess(200, "Login options generated", $options);
        } catch (\Throwable $th) {
            Utility::showError($th);
        }
    }

    /**
     * Endpoint: /webauthn/login
     */
    public static function login(): void
    {
        try {
            $input = GetRequestData::getRequestData();
            
            $service = new WebAuthnService();
            $isValid = $service->verifySignature($input);

            if (!$isValid) {
                throw new \Exception("Invalid Passkey signature.");
            }

            $credentialId = $input['id'] ?? null;
            if (!$credentialId) {
                throw new \InvalidArgumentException("Missing credential identifier.");
            }

            $db = Db::connect2();
            try {
                $stmt = $db->prepare("SELECT user_id, public_key, user_role FROM user_passkeys WHERE credential_id = ?");
                $stmt->execute([$credentialId]);
                $record = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                // Defensive fallback for legacy tables where user_role column does not exist yet
                if (str_contains($e->getMessage(), 'user_role') || (string)$e->getCode() === '42S22') {
                    $stmt = $db->prepare("SELECT user_id, public_key FROM user_passkeys WHERE credential_id = ?");
                    $stmt->execute([$credentialId]);
                    $record = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($record) {
                        $record['user_role'] = 'users';
                    }
                } else {
                    throw $e;
                }
            }

            if (!$record) {
                throw new \Exception("Biometric credential not recognized on this device.");
            }

            if (session_status() === PHP_SESSION_NONE) {
                \Src\SecureSession::start();
            }

            $userRole = $record['user_role'] ?? 'users';
            $user = null;
            $redirect = '/dashboard';

            if ($userRole === 'staff') {
                $staffStmt = $db->prepare("SELECT * FROM `staff` WHERE `staffEmail` = ? OR `no` = ? LIMIT 1");
                $staffStmt->execute([$record['user_id'], $record['user_id']]);
                $user = $staffStmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $email = (string)($user['staffEmail'] ?? '');
                    $name = trim(($user['staffFirstName'] ?? '') . ' ' . ($user['staffLastName'] ?? ''));
                    $_SESSION['staff_email'] = $email;
                    $_SESSION['staff_name'] = $name;
                    $_SESSION['role'] = 'staff';
                    $redirect = '/staff/portal';
                }
            } elseif ($userRole === 'client') {
                $clientStmt = $db->prepare("SELECT * FROM `clients` WHERE `clientEmail` = ? OR `no` = ? LIMIT 1");
                $clientStmt->execute([$record['user_id'], $record['user_id']]);
                $user = $clientStmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $email = (string)($user['clientEmail'] ?? '');
                    $name = (string)($user['clientName'] ?? 'Care Home');
                    $_SESSION['client_email'] = $email;
                    $_SESSION['client_name'] = $name;
                    $_SESSION['role'] = 'client';
                    $redirect = '/client/portal';
                }
            } elseif ($userRole === 'admin') {
                $adminStmt = $db->prepare("SELECT * FROM `account` WHERE `email` = ? OR `id` = ? LIMIT 1");
                $adminStmt->execute([$record['user_id'], $record['user_id']]);
                $user = $adminStmt->fetch(PDO::FETCH_ASSOC);
                if ($user) {
                    $email = (string)($user['email'] ?? '');
                    $name = (string)($user['fullName'] ?? 'Admin');
                    $_SESSION['id'] = $user['id'];
                    $_SESSION['email'] = $email;
                    $_SESSION['admin_code'] = $user['admin_code'] ?? 'TOP900';
                    $_SESSION['role'] = 'admin';
                    $redirect = '/admin/hub';
                }
            }

            if (!$user) {
                $loginTable = preg_replace('/[^a-zA-Z0-9_]/', '', (string) ($_ENV['DB_TABLE_LOGIN'] ?? 'users')) ?: 'users';
                $userStmt = $db->prepare("SELECT * FROM `{$loginTable}` WHERE id = ? OR no = ? LIMIT 1");
                $userStmt->execute([$record['user_id'], $record['user_id']]);
                $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            }

            if (!$user) {
                throw new \Exception("Associated user account not found.");
            }

            $userId = trim((string)($user['id'] ?? $user['no'] ?? $record['user_id'] ?? ''));
            if ($userId === '') {
                throw new \Exception("Associated user identifier is invalid.");
            }
            $email = (string)($user['email'] ?? $user['staffEmail'] ?? $user['clientEmail'] ?? '');
            $name = (string)($user['name'] ?? $user['fullName'] ?? $user['clientName'] ?? $user['staffFirstName'] ?? 'User');

            if (session_status() === PHP_SESSION_NONE) {
                \Src\SecureSession::start();
            }
            if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
                @session_regenerate_id(true);
            }

            // Establish unified session state
            $_SESSION['user_id'] = $userId;
            $_SESSION['id'] = $userId;
            $_SESSION['manager_id'] = $userId;
            $_SESSION['user_name'] = $name;
            $_SESSION['user'] = $user;
            $_SESSION['auth'] = [
                'identifyCust' => $userId,
                'email' => $email,
                'codeVerified' => true,
                '2FA_token_ts' => time(),
                'type' => $user['type'] ?? 'user',
            ];
            $_SESSION['is_logged_in'] = true;

            // Issue JWT auth cookie
            $userForJwt = [
                'id'            => $userId,
                'email'         => $email,
                'role'          => $userRole,
                'token_version' => $user['token_version'] ?? 1
            ];
            $generatedToken = JwtHandler::jwtEncodeData($userForJwt);
            $tokenName = $_ENV['COOKIE_TOKEN_LOGIN'] ?? 'auth_token';
            $env = $_ENV['APP_ENV'] ?? 'production';
            $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            $secure  = !in_array($env, ['local', 'development'], true) && $isHttps;
            $domain  = parse_url((string) ($_ENV['APP_URL'] ?? ''), PHP_URL_HOST) ?: '';

            setcookie(
                $tokenName, $generatedToken, [
                'expires'  => time() + (int)($_ENV['COOKIE_EXPIRE'] ?? 2592000),
                'path'     => '/',
                'domain'   => $domain,
                'secure'   => $secure,
                'httponly' => true,
                'samesite' => $secure ? 'Strict' : 'Lax'
                ]
            );

            // Update last_used_at
            $updateStmt = $db->prepare("UPDATE user_passkeys SET last_used_at = CURRENT_TIMESTAMP WHERE credential_id = ?");
            $updateStmt->execute([$credentialId]);

            // Determine redirect URL if not already assigned
            if ($redirect === '/dashboard') {
                $appName = strtolower((string)($_ENV['APP_NAME'] ?? ''));
                $redirect = '/dashboard';
                if (str_contains($appName, 'loaneasy') || str_contains($appName, 'loan')) {
                    $redirect = '/customer/mainPage';
                } elseif (str_contains($appName, 'party')) {
                    $redirect = '/manager/dashboard';
                }
            }

            Utility::msgSuccess(200, "Login successful", ['redirect' => $redirect]);
        } catch (\Throwable $th) {
            Utility::showError($th);
        }
    }

    /**
     * Endpoint: /webauthn/revoke
     */
    public static function revokeDevice(): void
    {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                \Src\SecureSession::start();
            }

            $resolved = self::resolveAuthenticatedUser();
            $userId = $resolved['user_id'] ?? $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
            if (!$userId) {
                throw new \Src\Exceptions\UnauthorisedException("Unauthorized");
            }

            $input = GetRequestData::getRequestData();
            $passkeyId = $input['passkey_id'] ?? $_POST['passkey_id'] ?? null;

            $db = Db::connect2();
            self::ensureSchema($db);
            if ($passkeyId) {
                $stmt = $db->prepare("DELETE FROM user_passkeys WHERE id = ? AND user_id = ?");
                $stmt->execute([$passkeyId, (string) $userId]);
            }

            unset($_SESSION['webauthn_enrolled_' . (string) $userId]);

            Utility::msgSuccess(200, "Device revoked successfully");
        } catch (\Throwable $th) {
            Utility::showError($th);
        }
    }

    /**
     * Renders the self-contained Universal Biometric Passkey Activation Prompt script
     * for authenticated users who have not yet activated a biometric passkey.
     */
    public static function renderActivationPrompt(?string $nonce = null): string
    {
        $uri = strtolower((string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/'));
        foreach (['/login', '/signin', '/register', '/signup', '/logout', '/signout', '/forgot', '/reset', '/verify'] as $excludedPrefix) {
            if ($uri === $excludedPrefix || str_ends_with($uri, $excludedPrefix) || str_contains($uri, $excludedPrefix . '/')) {
                return '';
            }
        }

        $state = self::getClientPromptState(true);
        if (!$state['authenticated']) {
            return '';
        }

        $resolvedNonce = $nonce ?? ($GLOBALS['csp_nonce'] ?? ($_SERVER['CSP_NONCE'] ?? ''));
        $nonceAttr = $resolvedNonce !== ''
            ? ' nonce="' . htmlspecialchars((string) $resolvedNonce, ENT_QUOTES, 'UTF-8') . '"'
            : '';

        $stateJson = json_encode($state, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if (!$stateJson) {
            return '';
        }

        if ($state['hasPasskey']) {
            return "<script{$nonceAttr}>try{window.__WEBAUTHN_PROMPT_STATE__={$stateJson};localStorage.setItem('biometric_enrolled_'+window.__WEBAUTHN_PROMPT_STATE__.userKey,'1');}catch(e){}</script>";
        }

        return <<<HTML
<script{$nonceAttr}>
(function(){
    if (window.__BIOMETRIC_PASSKEY_PROMPT_INITIALISED__) return;
    window.__BIOMETRIC_PASSKEY_PROMPT_INITIALISED__ = true;
    const STATE = {$stateJson};
    window.__WEBAUTHN_PROMPT_STATE__ = STATE;

    function base64URLToBuffer(str) {
        const base64 = String(str).replace(/-/g, '+').replace(/_/g, '/');
        const pad = (4 - (base64.length % 4)) % 4;
        const bin = atob(base64.padEnd(base64.length + pad, '='));
        const buf = new ArrayBuffer(bin.length);
        const bytes = new Uint8Array(buf);
        for (let i = 0; i < bin.length; i++) bytes[i] = bin.charCodeAt(i);
        return buf;
    }

    function bufferToBase64URL(buf) {
        const bytes = new Uint8Array(buf);
        let str = '';
        for (let i = 0; i < bytes.byteLength; i++) str += String.fromCharCode(bytes[i]);
        return btoa(str).replace(/\+/g, '-').replace(/\//g, '_').replace(/=/g, '');
    }

    function detectBiometricLabel() {
        const ua = navigator.userAgent || '';
        if (/iPhone|iPad|iPod/i.test(ua)) return 'Face ID or Touch ID';
        if (/Macintosh|Mac OS X/i.test(ua)) return 'Touch ID';
        if (/Android/i.test(ua)) return 'Fingerprint or Face Unlock';
        if (/Windows/i.test(ua)) return 'Windows Hello';
        return 'Biometric Passkey';
    }

    async function isPlatformAuthenticatorAvailable() {
        if (typeof window.PublicKeyCredential !== 'function') return false;
        try {
            if (typeof window.PublicKeyCredential.getClientCapabilities === 'function') {
                const caps = await window.PublicKeyCredential.getClientCapabilities();
                if (caps && typeof caps.userVerifyingPlatformAuthenticator === 'boolean') {
                    return caps.userVerifyingPlatformAuthenticator;
                }
            }
            if (typeof window.PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable === 'function') {
                return await window.PublicKeyCredential.isUserVerifyingPlatformAuthenticatorAvailable();
            }
        } catch (e) {}
        return false;
    }

    function shouldPrompt(force) {
        if (force) return true;
        if (!STATE || !STATE.authenticated || STATE.hasPasskey) return false;
        try {
            if (localStorage.getItem('biometric_enrolled_' + STATE.userKey) === '1') return false;
            const snooze = Number(localStorage.getItem('biometric_snooze_' + STATE.userKey) || '0');
            if (snooze > Date.now()) return false;
        } catch (e) {}
        return true;
    }

    function mountModal() {
        if (document.getElementById('universal-passkey-prompt-backdrop')) return;
        const bioLabel = detectBiometricLabel();
        const appName = STATE.appName || 'this application';
        const userEmail = STATE.userEmail || STATE.userName || 'Verified Account';

        const backdrop = document.createElement('div');
        backdrop.id = 'universal-passkey-prompt-backdrop';
        backdrop.setAttribute('role', 'dialog');
        backdrop.setAttribute('aria-modal', 'true');
        backdrop.setAttribute('aria-labelledby', 'universal-passkey-prompt-title');
        backdrop.style.cssText = 'position:fixed;inset:0;z-index:99998;background:rgba(15,23,42,0.68);backdrop-filter:blur(8px);-webkit-backdrop-filter:blur(8px);display:flex;align-items:flex-end;justify-content:center;padding:0;opacity:0;transition:opacity 0.22s ease-out;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;';

        const style = document.createElement('style');
        style.textContent = '@media(min-width:640px){#universal-passkey-prompt-backdrop{align-items:center!important;padding:16px!important;}#universal-passkey-prompt-card{border-radius:24px!important;max-width:420px!important;}}@keyframes passkeySpin{to{transform:rotate(360deg);}}';
        backdrop.appendChild(style);

        const card = document.createElement('div');
        card.id = 'universal-passkey-prompt-card';
        card.style.cssText = 'width:100%;max-width:480px;background:linear-gradient(180deg,#0f172a 0%,#1e293b 100%);color:#f8fafc;border:1px solid rgba(148,163,184,0.22);border-radius:24px 24px 0 0;padding:24px 24px calc(24px + env(safe-area-inset-bottom,0px));box-shadow:0 25px 50px -12px rgba(0,0,0,0.65);transform:translateY(16px);transition:transform 0.25s cubic-bezier(0.16,1,0.3,1);box-sizing:border-box;';

        card.innerHTML = '<div style="width:40px;height:4px;background:rgba(148,163,184,0.35);border-radius:999px;margin:0 auto 16px;"></div>'
            + '<div style="display:flex;align-items:center;justify-content:space-between;gap:8px;margin-bottom:16px;">'
            + '<div style="display:inline-flex;align-items:center;gap:8px;background:rgba(16,185,129,0.14);border:1px solid rgba(16,185,129,0.35);padding:6px 12px;border-radius:999px;">'
            + '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#34d399" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 10a2 2 0 0 0-2 2c0 1.02-.1 2.51-.26 4"/><path d="M14 13.12c0 2.38 0 6.38-1 8.88"/><path d="M2 12a10 10 0 0 1 18-6"/><path d="M5 19.5C5.5 18 6 15 6 12a6 6 0 0 1 .34-2"/><path d="M9 6.8a6 6 0 0 1 9 5.2v2"/></svg>'
            + '<span style="font-size:11px;font-weight:700;letter-spacing:0.05em;text-transform:uppercase;color:#6ee7b7;white-space:nowrap;">Biometric Security</span>'
            + '</div>'
            + '<span id="universal-passkey-account-pill" style="font-size:12px;color:#94a3b8;max-width:170px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;"></span>'
            + '</div>'
            + '<h2 id="universal-passkey-prompt-title" style="margin:0 0 8px;font-size:22px;line-height:1.25;font-weight:800;letter-spacing:-0.02em;color:#ffffff;">Activate Biometric Sign-In</h2>'
            + '<p id="universal-passkey-prompt-desc" style="margin:0 0 16px;font-size:14px;line-height:1.55;color:#cbd5e1;"></p>'
            + '<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:8px;margin-bottom:18px;">'
            + '<div style="background:rgba(30,41,59,0.75);border:1px solid rgba(148,163,184,0.16);border-radius:12px;padding:10px 8px;text-align:center;"><div style="font-size:12px;font-weight:700;color:#f8fafc;">1-Tap Access</div><div style="font-size:11px;color:#94a3b8;margin-top:2px;">Instant sign-in</div></div>'
            + '<div style="background:rgba(30,41,59,0.75);border:1px solid rgba(148,163,184,0.16);border-radius:12px;padding:10px 8px;text-align:center;"><div style="font-size:12px;font-weight:700;color:#f8fafc;">Device-Bound</div><div style="font-size:11px;color:#94a3b8;margin-top:2px;">Private enclave</div></div>'
            + '<div style="background:rgba(30,41,59,0.75);border:1px solid rgba(148,163,184,0.16);border-radius:12px;padding:10px 8px;text-align:center;"><div style="font-size:12px;font-weight:700;color:#f8fafc;">Phishing-Proof</div><div style="font-size:11px;color:#94a3b8;margin-top:2px;">FIDO2 standard</div></div>'
            + '</div>'
            + '<div id="universal-passkey-prompt-feedback" role="status" aria-live="polite" style="display:none;margin-bottom:14px;padding:10px 12px;border-radius:12px;font-size:13px;line-height:1.4;"></div>'
            + '<div style="display:flex;flex-direction:column;gap:10px;">'
            + '<button type="button" id="universal-passkey-activate-btn" style="min-height:48px;min-width:44px;width:100%;display:inline-flex;align-items:center;justify-content:center;gap:10px;padding:12px 20px;border-radius:14px;border:none;background:linear-gradient(135deg,#10b981 0%,#059669 100%);color:#ffffff;font-size:15px;font-weight:700;cursor:pointer;white-space:nowrap;flex-shrink:0;box-shadow:0 10px 20px -5px rgba(16,185,129,0.4);transition:transform 0.15s ease,filter 0.15s ease;">'
            + '<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 10a2 2 0 0 0-2 2c0 1.02-.1 2.51-.26 4"/><path d="M14 13.12c0 2.38 0 6.38-1 8.88"/><path d="M2 12a10 10 0 0 1 18-6"/><path d="M5 19.5C5.5 18 6 15 6 12a6 6 0 0 1 .34-2"/><path d="M9 6.8a6 6 0 0 1 9 5.2v2"/></svg>'
            + '<span id="universal-passkey-activate-label"></span>'
            + '</button>'
            + '<button type="button" id="universal-passkey-later-btn" style="min-height:44px;min-width:44px;width:100%;display:inline-flex;align-items:center;justify-content:center;padding:10px 16px;border-radius:12px;border:1px solid rgba(148,163,184,0.22);background:transparent;color:#cbd5e1;font-size:14px;font-weight:600;cursor:pointer;white-space:nowrap;flex-shrink:0;">Maybe Later</button>'
            + '</div>';

        backdrop.appendChild(card);
        document.body.appendChild(backdrop);

        const pill = card.querySelector('#universal-passkey-account-pill');
        if (pill) { pill.textContent = userEmail; pill.setAttribute('title', userEmail); }
        const desc = card.querySelector('#universal-passkey-prompt-desc');
        if (desc) {
            desc.textContent = "Sign in to " + appName + " instantly next time using your device's recognised biometric sensor (" + bioLabel + "). Your biometric data never leaves this device.";
        }
        const btnLabel = card.querySelector('#universal-passkey-activate-label');
        if (btnLabel) { btnLabel.textContent = 'Activate ' + bioLabel; }

        requestAnimationFrame(function(){
            backdrop.style.opacity = '1';
            card.style.transform = 'translateY(0)';
        });

        function closePrompt() {
            backdrop.style.opacity = '0';
            card.style.transform = 'translateY(16px)';
            setTimeout(function(){ if (backdrop.parentNode) backdrop.parentNode.removeChild(backdrop); }, 220);
        }

        function showFeedback(msg, ok) {
            const fb = card.querySelector('#universal-passkey-prompt-feedback');
            if (!fb) return;
            fb.style.display = 'block';
            fb.style.background = ok ? 'rgba(16,185,129,0.18)' : 'rgba(245,158,11,0.16)';
            fb.style.border = ok ? '1px solid rgba(16,185,129,0.4)' : '1px solid rgba(245,158,11,0.38)';
            fb.style.color = ok ? '#a7f3d0' : '#fde68a';
            fb.textContent = msg;
        }

        const laterBtn = card.querySelector('#universal-passkey-later-btn');
        if (laterBtn) {
            laterBtn.addEventListener('click', function(){
                try { localStorage.setItem('biometric_snooze_' + STATE.userKey, String(Date.now() + 72 * 3600 * 1000)); } catch (e) {}
                closePrompt();
            });
        }

        const activateBtn = card.querySelector('#universal-passkey-activate-btn');
        if (activateBtn) {
            const origHTML = activateBtn.innerHTML;
            activateBtn.addEventListener('click', async function(){
                if (activateBtn.disabled) return;
                activateBtn.disabled = true;
                activateBtn.style.opacity = '0.85';
                activateBtn.style.cursor = 'wait';
                activateBtn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" style="animation:passkeySpin 0.8s linear infinite;" aria-hidden="true"><path d="M21 12a9 9 0 1 1-6.219-8.56"/></svg><span>Authorising Biometrics...</span>';

                const csrf = document.querySelector('meta[name="csrf-token"]');
                const headers = { 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' };
                if (csrf && csrf.content) headers['X-CSRF-TOKEN'] = csrf.content;

                try {
                    const optRes = await fetch('/webauthn/register/options', {
                        method: 'POST',
                        headers: headers,
                        credentials: 'same-origin',
                        body: JSON.stringify({ role: STATE.userRole || 'users' })
                    });
                    const optJson = await optRes.json();
                    const opts = optJson.token || optJson.data || optJson;
                    if (!optRes.ok || !opts || !opts.challenge) {
                        throw new Error((optJson && optJson.message) || 'Could not initialise biometric registration.');
                    }

                    const publicKey = Object.assign({}, opts, {
                        challenge: base64URLToBuffer(opts.challenge),
                        user: Object.assign({}, opts.user, { id: base64URLToBuffer(opts.user.id) })
                    });
                    if (Array.isArray(publicKey.excludeCredentials)) {
                        publicKey.excludeCredentials = publicKey.excludeCredentials.map(function(c){
                            return Object.assign({}, c, { id: typeof c.id === 'string' ? base64URLToBuffer(c.id) : c.id });
                        });
                    }

                    const cred = await navigator.credentials.create({ publicKey: publicKey });
                    const payload = {
                        id: cred.id,
                        rawId: bufferToBase64URL(cred.rawId),
                        type: cred.type,
                        role: STATE.userRole || 'users',
                        deviceName: bioLabel,
                        response: {
                            attestationObject: bufferToBase64URL(cred.response.attestationObject),
                            clientDataJSON: bufferToBase64URL(cred.response.clientDataJSON)
                        }
                    };

                    const regRes = await fetch('/webauthn/register', {
                        method: 'POST',
                        headers: headers,
                        credentials: 'same-origin',
                        body: JSON.stringify(payload)
                    });
                    const regJson = await regRes.json();
                    if (!regRes.ok && (!regJson || regJson.status !== 'success')) {
                        throw new Error((regJson && regJson.message) || 'Verification failed.');
                    }

                    try {
                        localStorage.setItem('biometric_enrolled_' + STATE.userKey, '1');
                        localStorage.removeItem('biometric_snooze_' + STATE.userKey);
                    } catch (e) {}

                    showFeedback('Biometric Passkey activated! You can now sign in with 1 tap.', true);
                    setTimeout(closePrompt, 1100);
                } catch (err) {
                    if (err && (err.name === 'InvalidStateError' || err.code === 'InvalidStateError')) {
                        try { localStorage.setItem('biometric_enrolled_' + STATE.userKey, '1'); } catch (e) {}
                        showFeedback('Biometric Passkey is already active on this device.', true);
                        setTimeout(closePrompt, 1100);
                        return;
                    }
                    activateBtn.disabled = false;
                    activateBtn.style.opacity = '1';
                    activateBtn.style.cursor = 'pointer';
                    activateBtn.innerHTML = origHTML;
                    const lbl = activateBtn.querySelector('#universal-passkey-activate-label');
                    if (lbl) lbl.textContent = 'Activate ' + bioLabel;
                    showFeedback('Biometric verification was cancelled or did not complete. Tap Activate whenever you are ready to try again.', false);
                }
            });
        }
    }

    async function boot(force) {
        if (!shouldPrompt(force)) return;
        const ok = await isPlatformAuthenticatorAvailable();
        if (!ok && !force) return;
        mountModal();
    }

    window.BiometricPasskeyPrompt = {
        open: function(opts) { boot(Boolean(opts && opts.force)); },
        state: STATE
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function(){ setTimeout(function(){ boot(false); }, 650); });
    } else {
        setTimeout(function(){ boot(false); }, 650);
    }
})();
</script>
HTML;
    }
}
