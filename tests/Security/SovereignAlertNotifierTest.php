<?php

declare(strict_types=1);

namespace Tests\Security;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Src\Quality\SovereignDiagnostic;
use Src\Security\SovereignAlertNotifier;

class SovereignAlertNotifierTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!defined('TESTING_ENV')) {
            define('TESTING_ENV', true);
        }
        $GLOBALS['__testMail'] = [];
    }

    #[Test]
    public function it_resolves_default_owner_email_accurately(): void
    {
        putenv('OWNER_ALERT_EMAIL=');
        $email = SovereignAlertNotifier::getOwnerEmail();

        $this->assertSame('waledevtest@gmail.com', $email);
    }

    #[Test]
    public function it_dispatches_email_alert_when_proprietary_software_fails_over(): void
    {
        $sent = SovereignAlertNotifier::notifyFallbackTriggered(
            'SovereignMediaEngine',
            'Intervention\Image',
            '/tmp/test.heic',
            'HEIC container decode anomaly'
        );

        $this->assertTrue($sent);
        $this->assertNotEmpty($GLOBALS['__testMail']);
        $lastMail = end($GLOBALS['__testMail']);

        $this->assertSame('waledevtest@gmail.com', $lastMail['to']);
        $this->assertStringContainsString('[PROPRIETARY ENGINE FALLBACK]', $lastMail['subject']);
    }

    #[Test]
    public function it_dispatches_email_alert_when_malicious_threat_is_blocked(): void
    {
        $sent = SovereignAlertNotifier::notifyThreatBlocked(
            'Polyglot WebShell',
            'exploit.jpg',
            'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            'Embedded <?php eval(...) detected'
        );

        $this->assertTrue($sent);
        $this->assertNotEmpty($GLOBALS['__testMail']);
        $lastMail = end($GLOBALS['__testMail']);

        $this->assertSame('waledevtest@gmail.com', $lastMail['to']);
        $this->assertStringContainsString('[SECURITY ALERT]', $lastMail['subject']);
    }

    #[Test]
    public function it_dispatches_executive_health_digest_to_owner(): void
    {
        $report = SovereignDiagnostic::checkAll();
        $sent = SovereignAlertNotifier::sendExecutiveHealthDigest($report);

        $this->assertTrue($sent);
        $this->assertNotEmpty($GLOBALS['__testMail']);
        $lastMail = end($GLOBALS['__testMail']);

        $this->assertSame('waledevtest@gmail.com', $lastMail['to']);
        $this->assertStringContainsString('[ATG HEALTH DIGEST]', $lastMail['subject']);
    }
}
