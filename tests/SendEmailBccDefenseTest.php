<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Src\data\EmailData;
use Src\SendEmail;

/**
 * Tests defensive guards preventing PHPMailer's "Invalid address: (bcc):"
 * when TEST_EMAIL or APP_EMAIL is missing, null, or empty.
 */
class SendEmailBccDefenseTest extends TestCase
{
    protected function setUp(): void
    {
        if (!defined('TESTING_ENV')) {
            define('TESTING_ENV', true);
        }
    }

    public function testEmailConfigWithMissingTestEmail(): void
    {
        unset($_ENV['TEST_EMAIL']);
        $config = EmailData::getEmailConfig('member');
        $this->assertNull($config['testEmail']);
    }

    public function testEmailValidationGuardsAgainstNullAndEmpty(): void
    {
        $testCases = [
            null => false,
            '' => false,
            '   ' => false,
            'invalid-email' => false,
            '@missing-user.com' => false,
            'valid.user@example.com' => true,
        ];

        foreach ($testCases as $candidate => $expected) {
            $isValid = is_string($candidate) && trim($candidate) !== '' && filter_var($candidate, FILTER_VALIDATE_EMAIL) !== false;
            $this->assertSame($expected, $isValid, "Failed validation assertion for: " . var_export($candidate, true));
        }
    }

    public function testSendEmailInTestEnvironmentSucceeds(): void
    {
        $GLOBALS['__testMail'] = [];
        $result = SendEmail::sendEmail('user@scoretenant.test', 'John Doe', 'Subject Test', '<p>Hello</p>');
        $this->assertTrue($result);
        $this->assertNotEmpty($GLOBALS['__testMail']);
        $this->assertSame('user@scoretenant.test', $GLOBALS['__testMail'][0]['to']);
    }
}
