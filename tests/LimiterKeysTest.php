<?php

declare(strict_types=1);

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Src\Limiter;

/**
 * Who a rate limit counts, and when it may be skipped.
 * PartyPlatform invite-flow review 2026-09-26:
 *  - SubmitPostData limits on the table name, which made one bucket for the whole platform
 *    ("invitees": 30 new guests per 5 minutes across every user and event).
 *  - The Cypress test header switched every limit off in production too.
 */
class LimiterKeysTest extends TestCase
{
    public function testATableNameIsCountedPerPersonNotPlatformWide(): void
    {
        $a = Limiter::bucketKeys('invitees', 'default', '203.0.113.7', 'sessA');
        $b = Limiter::bucketKeys('invitees', 'default', '198.51.100.9', 'sessB');

        $this->assertNotSame($a['arg'], $b['arg'], 'Two people adding guests must not share a bucket');
        $this->assertNotSame($a['ip'], $b['ip']);
    }

    public function testLoginIsStillCountedPerAccountAcrossDevices(): void
    {
        $a = Limiter::bucketKeys('victim@example.com', 'login', '203.0.113.7', 'sessA');
        $b = Limiter::bucketKeys('victim@example.com', 'login', '198.51.100.9', 'sessB');

        $this->assertSame($a['arg'], $b['arg'], 'Guessing one account from many devices still hits one limit');
    }

    public function testThePerConnectionBucketCannotBeResetByChangingTheValueOrTheCookie(): void
    {
        // PwdRecoveryCodeFunctionality passes each guessed code as $arg: every guess
        // from one connection must still count against the same bucket.
        $guess1 = Limiter::bucketKeys('123456', 'default', '203.0.113.7', 'sess1');
        $guess2 = Limiter::bucketKeys('654321', 'default', '203.0.113.7', 'sess2');

        $this->assertSame($guess1['ip'], $guess2['ip']);
    }

    public function testDollarSignsAreStrippedFromKeys(): void
    {
        $this->assertStringNotContainsString('$', Limiter::bucketKeys('$table', 'default', '203.0.113.7', 's')['arg']);
    }

    /** @return array<string, array{string, bool}> */
    public static function environments(): array
    {
        return [
            'production' => ['production', false],
            'staging' => ['staging', false],
            'unset' => ['', false],
            'unknown' => ['live', false],
            'local' => ['local', true],
            'development' => ['development', true],
            'testing' => ['testing', true],
            'mixed case and spaces' => [' Development ', true],
        ];
    }

    #[DataProvider('environments')]
    public function testTheCypressHeaderOnlySkipsLimitsOnTestMachines(string $env, bool $skips): void
    {
        $this->assertSame($skips, Limiter::testHeaderSkipsLimits(['HTTP_X_CYPRESS_TEST' => '1'], $env));
    }

    public function testNoHeaderNeverSkips(): void
    {
        $this->assertFalse(Limiter::testHeaderSkipsLimits([], 'local'));
    }
}
