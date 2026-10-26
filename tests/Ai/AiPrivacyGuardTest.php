<?php

declare(strict_types=1);

namespace Tests\Ai;

use PHPUnit\Framework\TestCase;
use Src\Ai\AiPrivacyGuard;

class AiPrivacyGuardTest extends TestCase
{
    public function testRedactsUkPostcodes(): void
    {
        $input = "The venue is near Buckingham Palace, SW1A 1AA or Manchester M1 1AE.";
        $scrubbed = AiPrivacyGuard::scrubText($input);

        $this->assertStringNotContainsString('SW1A 1AA', $scrubbed);
        $this->assertStringNotContainsString('M1 1AE', $scrubbed);
        $this->assertStringContainsString('[REDACTED_POSTCODE]', $scrubbed);
    }

    public function testRedactsEmailAddresses(): void
    {
        $input = "Send complaints to john.doe@example.co.uk or admin@partyplatform.com";
        $scrubbed = AiPrivacyGuard::scrubText($input);

        $this->assertStringNotContainsString('john.doe@example.co.uk', $scrubbed);
        $this->assertStringNotContainsString('admin@partyplatform.com', $scrubbed);
        $this->assertStringContainsString('[REDACTED_EMAIL]', $scrubbed);
    }

    public function testRedactsUkPhoneNumbers(): void
    {
        $input = "Call Uncle Tunde on 07123 456789 or +44 7987 654321 for entry.";
        $scrubbed = AiPrivacyGuard::scrubText($input);

        $this->assertStringNotContainsString('07123 456789', $scrubbed);
        $this->assertStringNotContainsString('+44 7987 654321', $scrubbed);
        $this->assertStringContainsString('[REDACTED_PHONE]', $scrubbed);
    }

    public function testRedactsDatesOfBirthAndExplicitDates(): void
    {
        $input = "Honouree was born on 14th May 1985 and Liam was born 1992-05-14.";
        $scrubbed = AiPrivacyGuard::scrubText($input);

        $this->assertStringNotContainsString('14th May 1985', $scrubbed);
        $this->assertStringNotContainsString('1992-05-14', $scrubbed);
        $this->assertTrue(str_contains($scrubbed, '[REDACTED_DOB]') || str_contains($scrubbed, '[REDACTED_DATE]'));
    }

    public function testRedactsStreetAddresses(): void
    {
        $input = "The secret party is at 25 Baker Street or 10 Downing Street.";
        $scrubbed = AiPrivacyGuard::scrubText($input);

        $this->assertStringNotContainsString('Baker Street', $scrubbed);
        $this->assertStringNotContainsString('Downing Street', $scrubbed);
        $this->assertStringContainsString('[REDACTED_ADDRESS]', $scrubbed);
    }

    public function testRedactsUkNinoAndFinancialIdentifiers(): void
    {
        $input = "My NINO is QQ 123456 C and card is 4111-2222-3333-4444.";
        $scrubbed = AiPrivacyGuard::scrubText($input);

        $this->assertStringNotContainsString('QQ 123456 C', $scrubbed);
        $this->assertStringNotContainsString('4111-2222-3333-4444', $scrubbed);
        $this->assertStringContainsString('[REDACTED_NINO]', $scrubbed);
        $this->assertStringContainsString('[REDACTED_FINANCIAL]', $scrubbed);
    }

    public function testScrubMessagesPreservesChatStructureWhileScrubbing(): void
    {
        $messages = [
            ['role' => 'system', 'content' => 'You are a toastmaster.'],
            ['role' => 'user', 'content' => 'Speech for John at 14 High Street, London SW1A 1AA born 1980-01-01.'],
        ];

        $scrubbed = AiPrivacyGuard::scrubMessages($messages);

        $this->assertCount(2, $scrubbed);
        $this->assertSame('system', $scrubbed[0]['role']);
        $this->assertSame('You are a toastmaster.', $scrubbed[0]['content']);
        $this->assertSame('user', $scrubbed[1]['role']);
        $this->assertStringNotContainsString('High Street', (string)$scrubbed[1]['content']);
        $this->assertStringNotContainsString('SW1A 1AA', (string)$scrubbed[1]['content']);
        $this->assertStringNotContainsString('1980-01-01', (string)$scrubbed[1]['content']);
    }

    public function testAuditDetectsPiiViolations(): void
    {
        $cleanText = "Great barbecue party with music, dancing and good vibes!";
        $dirtyText = "Call Dave on 07123 456789 at 10 Baker Street, London EC1A 1BB.";

        $cleanAudit = AiPrivacyGuard::audit($cleanText);
        $this->assertFalse($cleanAudit['has_pii']);
        $this->assertEmpty($cleanAudit['detected_types']);

        $dirtyAudit = AiPrivacyGuard::audit($dirtyText);
        $this->assertTrue($dirtyAudit['has_pii']);
        $this->assertContains('PHONE_NUMBER', $dirtyAudit['detected_types']);
        $this->assertContains('STREET_ADDRESS', $dirtyAudit['detected_types']);
        $this->assertContains('UK_POSTCODE', $dirtyAudit['detected_types']);
    }
}
