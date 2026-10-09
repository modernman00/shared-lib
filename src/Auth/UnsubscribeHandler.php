<?php

declare(strict_types=1);

namespace Src\Auth;

use Src\Db;

class UnsubscribeHandler
{
    /**
     * Generate an HMAC-SHA256 signature token for the email.
     */
    public static function generateToken(string $email): string
    {
        $cleanEmail = strtolower(trim($email));
        $secretKey = (string) ($_ENV['APP_KEY'] ?? getenv('APP_KEY') ?: '');
        if (empty($secretKey)) {
            $secretKey = 'SECURE_FALLBACK_KEY_' . hash('sha256', __DIR__);
        }

        return hash_hmac('sha256', $cleanEmail, $secretKey);
    }

    /**
     * Verifies the HMAC-SHA256 signature token using constant-time comparison.
     */
    public static function verifyToken(string $email, string $token): bool
    {
        if (empty($email) || empty($token)) {
            return false;
        }

        $expected = self::generateToken($email);
        return hash_equals($expected, trim($token));
    }

    /**
     * Constructs a fully qualified unsubscribe URL.
     */
    public static function getUnsubscribeUrl(string $email, ?string $baseUrl = null): string
    {
        $cleanEmail = strtolower(trim($email));
        $token = self::generateToken($cleanEmail);
        $base = rtrim($baseUrl ?? (string) ($_ENV['APP_URL'] ?? getenv('APP_URL') ?: ''), '/');

        return "{$base}/email/unsubscribe?email=" . urlencode($cleanEmail) . '&token=' . urlencode($token);
    }

    /**
     * Checks if the given email is unsubscribed across supported tables.
     */
    public static function isEmailUnsubscribed(string $email): bool
    {
        if (empty($email)) {
            return false;
        }

        try {
            if (!class_exists('\\Src\\Db')) {
                return false;
            }
            $db = Db::connect2();
            $cleanEmail = strtolower(trim($email));

            $tables = ['account', 'users'];
            $envTable = (string) ($_ENV['DB_TABLE_LOGIN'] ?? getenv('DB_TABLE_LOGIN') ?: '');
            if (!empty($envTable) && !in_array($envTable, $tables, true)) {
                array_unshift($tables, $envTable);
            }

            foreach ($tables as $table) {
                try {
                    $stmt = $db->prepare("SELECT email_unsubscribed FROM `{$table}` WHERE LOWER(email) = ? LIMIT 1");
                    $stmt->execute([$cleanEmail]);
                    $val = $stmt->fetchColumn();
                    if ($val !== false && !empty($val)) {
                        return true;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }

            return false;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Flags an email as unsubscribed from non-functional communications.
     */
    public static function unsubscribeEmail(string $email): bool
    {
        if (empty($email)) {
            return false;
        }

        try {
            if (!class_exists('\\Src\\Db')) {
                return false;
            }
            $db = Db::connect2();
            $cleanEmail = strtolower(trim($email));

            $tables = ['account', 'users'];
            $envTable = (string) ($_ENV['DB_TABLE_LOGIN'] ?? getenv('DB_TABLE_LOGIN') ?: '');
            if (!empty($envTable) && !in_array($envTable, $tables, true)) {
                array_unshift($tables, $envTable);
            }

            $success = false;
            foreach ($tables as $table) {
                try {
                    $stmt = $db->prepare("UPDATE `{$table}` SET email_unsubscribed = 1 WHERE LOWER(email) = ?");
                    $stmt->execute([$cleanEmail]);
                    if ($stmt->rowCount() > 0) {
                        $success = true;
                    }
                } catch (\Throwable) {
                    continue;
                }
            }

            return $success;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Handles the HTTP request for /email/unsubscribe (both GET and POST).
     */
    public static function handleRequest(?string $appName = null, ?string $baseUrl = null): void
    {
        $rawEmail = filter_input(INPUT_GET, 'email', FILTER_DEFAULT) ?: (filter_input(INPUT_POST, 'email', FILTER_DEFAULT) ?: '');
        $email = filter_var(trim((string) $rawEmail), FILTER_VALIDATE_EMAIL) ?: '';
        $token = trim((string) ($_GET['token'] ?? $_POST['token'] ?? ''));

        $name = $appName ?? (string) ($_ENV['APP_NAME'] ?? getenv('APP_NAME') ?: 'Our Platform');
        $base = rtrim($baseUrl ?? (string) ($_ENV['APP_URL'] ?? getenv('APP_URL') ?: ''), '/');

        $isValid = !empty($email) && self::verifyToken((string) $email, $token);

        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isValid) {
            self::unsubscribeEmail((string) $email);

            $wantsJson = (!empty($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'application/json')) ||
                         (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

            if ($wantsJson) {
                header('Content-Type: application/json');
                echo json_encode(['status' => 'success', 'message' => 'You have been successfully unsubscribed.']);
                exit;
            }

            header('Content-Type: text/html; charset=utf-8');
            $safeEmail = htmlspecialchars((string) $email, ENT_QUOTES, 'UTF-8');
            $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            $safeBase = htmlspecialchars($base, ENT_QUOTES, 'UTF-8');

            echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Unsubscribed Successfully - {$safeName}</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #0f172a; color: #f8fafc; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; box-sizing: border-box; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,0,0,0.4); max-width: 480px; width: 100%; padding: 40px; text-align: center; }
        .btn { display: inline-block; background: #3b82f6; color: #ffffff; text-decoration: none; padding: 12px 24px; border-radius: 8px; font-weight: 600; margin-top: 24px; }
    </style>
</head>
<body>
    <div class="card">
        <div style="font-size: 40px; margin-bottom: 16px;">✉️</div>
        <h2 style="color: #ffffff; margin-top: 0; font-size: 22px;">Unsubscribed Successfully</h2>
        <p style="color: #94a3b8; line-height: 1.6; font-size: 15px;">
            <strong>{$safeEmail}</strong> has been removed from non-essential communications and marketing updates for {$safeName}.
        </p>
        <p style="color: #64748b; font-size: 13px; line-height: 1.5; margin-top: 16px; padding: 12px; background: rgba(255,255,255,0.03); border-radius: 8px;">
            🔒 <strong>Note:</strong> You will still receive essential transactional notifications such as password changes, verification codes, and security alerts.
        </p>
        <a href="{$safeBase}" class="btn">Return to {$safeName}</a>
    </div>
</body>
</html>
HTML;
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        $safeEmail = htmlspecialchars((string) $email, ENT_QUOTES, 'UTF-8');
        $safeToken = htmlspecialchars($token, ENT_QUOTES, 'UTF-8');
        $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
        $safeBase = htmlspecialchars($base, ENT_QUOTES, 'UTF-8');

        echo '<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><title>Email Preferences - ' . $safeName . '</title><meta name="viewport" content="width=device-width, initial-scale=1"><style>body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;background:#0f172a;color:#f8fafc;display:flex;align-items:center;justify-content:center;min-height:100vh;margin:0;padding:20px;box-sizing:border-box}.card{background:#1e293b;border:1px solid #334155;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,0.4);max-width:480px;width:100%;padding:40px;text-align:center}.btn-red{background:#ef4444;color:white;padding:12px 24px;border:none;border-radius:8px;font-size:15px;font-weight:600;cursor:pointer;width:100%;transition:background .2s;}.btn-red:hover{background:#dc2626;}.btn-secondary{display:inline-block;background:#334155;color:white;padding:12px 24px;text-decoration:none;border-radius:8px;font-weight:600;font-size:15px;margin-top:12px}</style></head><body><div class="card">';

        if ($isValid) {
            echo '<div style="font-size:40px;margin-bottom:16px;">🔕</div>';
            echo '<h2 style="color:#ffffff;margin-top:0;font-size:22px;">Confirm Unsubscribe</h2>';
            echo '<p style="color:#94a3b8;line-height:1.6;font-size:15px;">Are you sure you want to unsubscribe <strong>' . $safeEmail . '</strong> from non-essential communications from ' . $safeName . '?</p>';
            echo '<form method="POST" style="margin-top:25px;"><input type="hidden" name="email" value="' . $safeEmail . '"><input type="hidden" name="token" value="' . $safeToken . '"><button type="submit" class="btn-red">Confirm Unsubscribe</button></form>';
            echo '<p style="margin-top:20px;"><a href="' . $safeBase . '" style="color:#64748b;text-decoration:none;font-size:14px;">Cancel and go back</a></p>';
        } else {
            echo '<div style="font-size:40px;margin-bottom:16px;">🛡️</div>';
            echo '<h2 style="color:#ef4444;margin-top:0;font-size:22px;">Invalid or Expired Link</h2>';
            echo '<p style="color:#94a3b8;line-height:1.6;font-size:15px;">The unsubscribe link is invalid or could not be verified. If this email was an authentication or security notification (e.g. login code, new sign-in alert, or password change), opt-out is not available for essential service security messages.</p>';
            echo '<div style="margin-top:25px;"><a href="' . $safeBase . '/settings/notifications" class="btn-secondary">Manage Notification Preferences</a></div>';
            echo '<p style="margin-top:20px;"><a href="' . $safeBase . '/login" style="color:#64748b;text-decoration:none;font-size:14px;">Sign In to Account</a></p>';
        }

        echo '</div></body></html>';
        exit;
    }
}
