<?php

namespace Src\Data;

class EmailData
{
    private null|string $username;
    private null|string $password;
    private null|string $senderEmail;
    private null|string $senderName;
    private null|string $testEmail;

    public function __construct(private string $sender)
    {
        if ($sender === 'member') {
            $this->username = $_ENV("APP_USERNAME");
            $this->password = $_ENV("APP_PASSWORD");
            $this->senderName = $_ENV('APP_SENDER');
            $this->senderEmail = $_ENV("APP_EMAIL");
            $this->testEmail = $_ENV("TEST_EMAIL");
        } elseif ($sender === 'admin') {
            $this->username = $_ENV("ADMIN_USERNAME");
            $this->password = $_ENV("ADMIN_PASSWORD");
            $this->senderName = $_ENV('ADMIN_SENDER');
            $this->senderEmail = $_ENV("ADMIN_EMAIL");
            $this->testEmail = $_ENV("TEST_EMAIL");
        }
    }

    private function setEmailData(): void
    {
        define('USER_APP', $this->username);
        define('PASS', $this->password);
        define('APP_EMAIL', $this->senderEmail);
        define('APP_NAME', $this->senderName);
        define('TEST_EMAIL', $this->testEmail);
    }

    public function getEmailData(): void
    {
        $this->setEmailData();
    }
}
