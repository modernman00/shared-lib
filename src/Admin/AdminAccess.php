<?php

declare(strict_types=1);

namespace Src\Admin;

use PDO;

/**
 * Allowlist-based admin authorisation for apps without a role column.
 * Fails closed: an empty or missing ADMIN_EMAILS denies everyone.
 */
final class AdminAccess
{
    /** @return array<int, string> */
    public static function allowedEmails(): array
    {
        $raw = (string) ($_ENV['ADMIN_EMAILS'] ?? getenv('ADMIN_EMAILS') ?: '');

        return array_values(array_filter(array_map(
            static fn (string $e): string => strtolower(trim($e)),
            explode(',', $raw)
        )));
    }

    public static function isAdminUser(PDO $db, int|string $userId, string $table = 'users'): bool
    {
        $allowed = self::allowedEmails();
        if ($allowed === []) {
            return false;
        }

        // Fast-path: verified session admin email
        $sessionEmail = strtolower(trim((string) ($_SESSION['auth']['email'] ?? ($_SESSION['email'] ?? ''))));
        if ($sessionEmail !== '' && in_array($sessionEmail, $allowed, true)) {
            return true;
        }

        if ((string) $userId === '' || (string) $userId === '0') {
            return false;
        }

        try {
            $table = preg_replace('/[^a-zA-Z0-9_]/', '', $table) ?: 'users';
            $stmt = $db->prepare("SELECT email FROM `{$table}` WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $email = strtolower(trim((string) $stmt->fetchColumn()));
        } catch (\Throwable) {
            return false;
        }

        return $email !== '' && in_array($email, $allowed, true);
    }
}
