<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\ToSendEmail;

/**
 * Email subjects. sendEmailGeneral() ran subjects through Utility::checkInput(), whose
 * whitelist (letters, digits, . @ _ - and spaces) stripped apostrophes, emoji and
 * punctuation: "⏳ Ada wants to join Jib's Party" arrived as "Ada wants to join Jibs Party",
 * and names stored HTML-escaped showed "&#039;". PartyPlatform's live server carried an
 * unofficial edit inside vendor/ to fix this (found 2026-10-04); this makes it official.
 */
class ToSendEmailSubjectTest extends TestCase
{
    /** @return array<string, array{string, string}> */
    public static function subjects(): array
    {
        return [
            'apostrophe kept' => ["You're Confirmed for Jib's Party", "You're Confirmed for Jib's Party"],
            'html entity decoded' => ['You&#039;re Confirmed for afrobeat night', "You're Confirmed for afrobeat night"],
            'double-escaped entity decoded' => ['Tom &amp;amp; Jerry', 'Tom & Jerry'],
            'emoji kept' => ['⏳ Ada wants to join 🎉 Party', '⏳ Ada wants to join 🎉 Party'],
            'accents kept' => ['Fête à Zoë', 'Fête à Zoë'],
            'header injection flattened' => ["Hello\r\nBcc: victim@example.com", 'Hello Bcc: victim@example.com'],
            'control characters removed' => ["Hi\0 there\x07", 'Hi there'],
            'tags stripped' => ['<b>Bold</b> invite<script>x</script>', 'Bold invitex'],
            'surrounding space trimmed' => ['   Spaced   out   ', 'Spaced out'],
            'empty falls back' => ['', 'No Subject'],
        ];
    }

    #[DataProvider('subjects')]
    public function testCleanSubject(string $raw, string $expected): void
    {
        $this->assertSame($expected, ToSendEmail::cleanSubject($raw));
    }

    public function testNonStringSubjectsFallBack(): void
    {
        $this->assertSame('No Subject', ToSendEmail::cleanSubject(null));
        $this->assertSame('No Subject', ToSendEmail::cleanSubject(['x']));
    }
}
