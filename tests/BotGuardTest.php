<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\BotGuard;
use Src\Exceptions\BadRequestException;

class BotGuardTest extends TestCase
{
    public function testHoneypotDetectionPassesWhenEmpty(): void
    {
        $cleanInput = [
            'name' => 'Alice Doe',
            'email' => 'alice@example.com',
            'website_hp' => '',
        ];

        $this->assertFalse(BotGuard::isBotHoneypotHit($cleanInput));
    }

    public function testHoneypotDetectionCatchesBotPayload(): void
    {
        $botInput = [
            'name' => 'odjypfgwtn',
            'email' => 'kmgrlyju@formtests.info',
            'website_hp' => 'http://spam-site.com',
        ];

        $this->assertTrue(BotGuard::isBotHoneypotHit($botInput));
    }

    public function testHoneypotDetectionCatchesAlternateHoneypotKeys(): void
    {
        $botInput = [
            'name' => 'SpamBot9000',
            'email' => 'bot@spambot.net',
            'hp_username' => 'admin_bot',
        ];

        $this->assertTrue(BotGuard::isBotHoneypotHit($botInput));
    }

    public function testDisposableEmailDetectionBlocksKnownSpamDomains(): void
    {
        $this->assertTrue(BotGuard::isDisposableEmail('kmgrlyju@formtests.info', false));
        $this->assertTrue(BotGuard::isDisposableEmail('xhemail1970@belettersmail.com', false));
        $this->assertTrue(BotGuard::isDisposableEmail('test@mailinator.com', false));
        $this->assertTrue(BotGuard::isDisposableEmail('bot@guerrillamail.com', false));
        $this->assertTrue(BotGuard::isDisposableEmail('throw@10minutemail.com', false));
        $this->assertTrue(BotGuard::isDisposableEmail('temp@yopmail.com', false));
    }

    public function testDisposableEmailDetectionAllowsLegitimateProviders(): void
    {
        $this->assertFalse(BotGuard::isDisposableEmail('john.doe@gmail.com', false));
        $this->assertFalse(BotGuard::isDisposableEmail('jane.smith@outlook.com', false));
        $this->assertFalse(BotGuard::isDisposableEmail('ceo@modernman.co.uk', false));
        $this->assertFalse(BotGuard::isDisposableEmail('user@yahoo.com', false));
        $this->assertFalse(BotGuard::isDisposableEmail('student@ox.ac.uk', false));
    }

    public function testEnforceThrowsExceptionOnHoneypotHit(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Security validation failed. Please try again.');

        BotGuard::enforce([
            'email' => 'legit@gmail.com',
            'website_hp' => 'bot_filling_every_field',
        ]);
    }

    public function testEnforceThrowsExceptionOnDisposableEmail(): void
    {
        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Please provide a valid permanent email address from an established provider.');

        BotGuard::enforce([
            'email' => 'kmgrlyju@formtests.info',
            'website_hp' => '',
        ]);
    }

    public function testEnforceSucceedsForCleanHumanSubmission(): void
    {
        $cleanInput = [
            'name' => 'Wally Olaogun',
            'email' => 'wally@gmail.com',
            'website_hp' => '',
        ];

        // Should not throw any exception
        BotGuard::enforce($cleanInput);
        $this->assertTrue(true);
    }
}
