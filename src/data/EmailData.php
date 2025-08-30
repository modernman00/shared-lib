<?php

declare(strict_types=1);

namespace Src\data;

class EmailData
{
    /**
     * Load email config based on sender type.
     *
     * @param string $sender 'member' or 'admin'
     * @param array $config Environment config (e.g. $_ENV)
     *
     * @return array
     */
    public static function getEmailConfig(string $sender): array
    {
        $prefix = strtoupper($sender); // 'MEMBER' or 'ADMIN'

        return [
            'username'    => $_ENV["{$prefix}_USERNAME"] ?? null,
            'password'    => $_ENV["{$prefix}_PASSWORD"] ?? null,
            'senderName'  => $_ENV["{$prefix}_SENDER"] ?? null,
            'senderEmail' => $_ENV["{$prefix}_EMAIL"] ?? null,
            'testEmail'   => $_ENV['TEST_EMAIL'] ?? null,
        ];
    }

    /**
     * Define constants (optional; not recommended in modern code).
     */
    public static function defineConstants(string $sender): void
    {
        $data = self::getEmailConfig($sender);

        define('USER_APP', $data['username']);
        define('PASS', $data['password']);
        define('APP_EMAIL', $data['senderEmail']);
        define('APP_NAME', $data['senderName']);
        define('TEST_EMAIL', $data['testEmail']);
    }
}
