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

        if (!defined('USER_APP')) {
            define('USER_APP', $data['username']);
        }
        if (!defined('PASS')) {
            define('PASS', $data['password']);
        }
        if (!defined('APP_EMAIL')) {
            define('APP_EMAIL', $data['senderEmail']);
        }
        if (!defined('APP_NAME')) {
            define('APP_NAME', $data['senderName']);
        }
        if (!defined('TEST_EMAIL') && !empty($data['testEmail'])) {
            define('TEST_EMAIL', $data['testEmail']);
        }
    }
}
