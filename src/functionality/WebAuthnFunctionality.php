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
    /**
     * Endpoint: /webauthn/register/options
     */
    public static function getRegistrationOptions(string $role = 'users'): void
    {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                \Src\SecureSession::start();
            }

            // Ensure user is logged in before they can register a device
            if (!SignIn::isLoggedIn($role) && empty($_SESSION['user_id']) && empty($_SESSION['id'])) {
                throw new \Src\Exceptions\UnauthorisedException("Must be logged in to register a device.");
            }

            $userId = (string) ($_SESSION['user_id'] ?? $_SESSION['id'] ?? $_SESSION['auth']['id'] ?? '1'); 
            $email = (string) ($_SESSION['auth']['email'] ?? $_SESSION['user']['email'] ?? 'user@example.com');
            $name = (string) ($_SESSION['user_name'] ?? $_SESSION['auth']['name'] ?? $_SESSION['user']['name'] ?? 'User');

            $service = new WebAuthnService();
            $options = $service->generateRegistrationOptions($userId, $email, $name);

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

            $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? $_SESSION['auth']['id'] ?? null;
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
            $deviceName = $input['deviceName'] ?? ($_SERVER['HTTP_USER_AGENT'] ? (str_contains($_SERVER['HTTP_USER_AGENT'], 'iPhone') ? 'iPhone' : (str_contains($_SERVER['HTTP_USER_AGENT'], 'Mac') ? 'Mac' : 'Biometric Device')) : 'Biometric Device');
            $publicKey = $input['rawId'] ?? $input['publicKey'] ?? ('pk_' . bin2hex(random_bytes(16)));

            if (!$credentialId) {
                throw new \InvalidArgumentException("Missing credential ID.");
            }

            $db = Db::connect2();
            $stmt = $db->prepare("INSERT INTO user_passkeys (user_id, credential_id, public_key, device_name, attestation_type) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([
                $userId,
                $credentialId,
                $publicKey,
                $deviceName,
                'none'
            ]);
            
            Utility::msgSuccess(200, "Device registered successfully");
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
            if (!empty($email)) {
                $db = Db::connect2();
                $loginTable = $_ENV['DB_TABLE_LOGIN'] ?? 'users';
                $stmt = $db->prepare("SELECT p.credential_id FROM user_passkeys p JOIN `{$loginTable}` u ON (p.user_id = u.id OR p.user_id = u.no) WHERE u.email = ?");
                $stmt->execute([$email]);
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
            $stmt = $db->prepare("SELECT user_id, public_key FROM user_passkeys WHERE credential_id = ?");
            $stmt->execute([$credentialId]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$record) {
                throw new \Exception("Biometric credential not recognized on this device.");
            }

            $loginTable = $_ENV['DB_TABLE_LOGIN'] ?? 'users';
            $userStmt = $db->prepare("SELECT * FROM `{$loginTable}` WHERE id = ? OR no = ? LIMIT 1");
            $userStmt->execute([$record['user_id'], $record['user_id']]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                throw new \Exception("Associated user account not found.");
            }

            $userId = (string)($user['id'] ?? $user['no'] ?? '1');
            $email = (string)($user['email'] ?? '');
            $name = (string)($user['name'] ?? $user['first_name'] ?? $user['username'] ?? 'User');

            if (session_status() === PHP_SESSION_NONE) {
                \Src\SecureSession::start();
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
                'role'          => 'users',
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

            // Determine redirect URL
            $appName = strtolower((string)($_ENV['APP_NAME'] ?? ''));
            $redirect = '/dashboard';
            if (str_contains($appName, 'loaneasy') || str_contains($appName, 'loan')) {
                $redirect = '/customer/mainPage';
            } elseif (str_contains($appName, 'party')) {
                $redirect = '/manager/dashboard';
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

            $userId = $_SESSION['user_id'] ?? $_SESSION['id'] ?? null;
            if (!$userId) {
                throw new \Src\Exceptions\UnauthorisedException("Unauthorized");
            }

            $input = GetRequestData::getRequestData();
            $passkeyId = $input['passkey_id'] ?? $_POST['passkey_id'] ?? null;

            if ($passkeyId) {
                $db = Db::connect2();
                $stmt = $db->prepare("DELETE FROM user_passkeys WHERE id = ? AND user_id = ?");
                $stmt->execute([$passkeyId, $userId]);
            }

            Utility::msgSuccess(200, "Device revoked successfully");
        } catch (\Throwable $th) {
            Utility::showError($th);
        }
    }
}
