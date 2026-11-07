<?php

declare(strict_types=1);

namespace Tests;

use Mockery;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Src\ToSendText;
use TextMagic\ApiException;

/**
 * Runs in its own process because ToSendText::send() short-circuits once any earlier
 * test defines TESTING_ENV, and because the TextMagic client is overloaded with a mock
 * (no live network call — the previous version depended on a real 401 from TextMagic).
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class ToSendTextTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    private function textMagicRejects(): void
    {
        Mockery::mock('overload:TextMagic\Api\TextMagicApi')
            ->shouldReceive('sendMessage')
            ->andThrow(new ApiException('Unauthorized', 401));
    }

    public function testSendThrowsExceptionOnApiFailure(): void
    {
        $this->textMagicRejects();
        $this->expectException(ApiException::class);
        ToSendText::send('07123456789', 'Your OTP is 1234', 'textmagic', 'TestApp');
    }

    public function testCallerCanCatchExceptionInTryCatchWithoutScriptExiting(): void
    {
        $this->textMagicRejects();
        $caught = false;
        try {
            ToSendText::send('07123456789', 'Your OTP is 1234', 'textmagic', 'TestApp');
        } catch (ApiException $e) {
            $caught = true;
            $this->assertSame(401, $e->getCode());
        }
        $this->assertTrue($caught, 'Consuming app should be able to catch TextMagic\\ApiException without the process exiting.');
    }

    public function testUnknownProviderReturnsFalse(): void
    {
        $this->assertFalse(ToSendText::send('07123456789', 'Test message', 'nonexistent_provider', 'TestApp'));
    }
}
