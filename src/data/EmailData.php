<?php

namespace Src\Data;

class EmailData
{
    /**
     * Load email config based on sender type.
     *
     * @param string $sender 'member' or 'admin'
     * @param array $config Environment config (e.g. $_ENV)
     * @return array
     */
    public static function getEmailConfig(string $sender, array $config): array
    {
        $prefix = strtoupper($sender); // 'MEMBER' or 'ADMIN'

        return [
            'username'    => $config["{$prefix}_USERNAME"] ?? null,
            'password'    => $config["{$prefix}_PASSWORD"] ?? null,
            'senderName'  => $config["{$prefix}_SENDER"] ?? null,
            'senderEmail' => $config["{$prefix}_EMAIL"] ?? null,
            'testEmail'   => $config["TEST_EMAIL"] ?? null,
        ];
    }

    /**
     * Define constants (optional; not recommended in modern code)
     */
    public static function defineConstants(string $sender, array $config): void
    {
        $data = self::getEmailConfig($sender, $config);

        define('USER_APP', $data['username']);
        define('PASS', $data['password']);
        define('APP_EMAIL', $data['senderEmail']);
        define('APP_NAME', $data['senderName']);
        define('TEST_EMAIL', $data['testEmail']);
    }
}

