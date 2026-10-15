<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\ToSendText;
use TextMagic\ApiException;

class ToSendTextTest extends TestCase
{
    public function testSendThrowsExceptionOnApiFailure(): void
    {
        $this->expectException(ApiException::class);
        ToSendText::send('07123456789', 'Your OTP is 1234', 'textmagic', 'TestApp');
    }

    public function testCallerCanCatchExceptionInTryCatchWithoutScriptExiting(): void
    {
        $caught = false;
        try {
            ToSendText::send('07123456789', 'Your OTP is 1234', 'textmagic', 'TestApp');
        } catch (ApiException $e) {
            $caught = true;
            $this->assertSame(401, $e->getCode());
        }

        $this->assertTrue($caught, 'Consuming app was able to catch TextMagic\\ApiException cleanly in try...catch without process exit.');
    }

    public function testUnknownProviderReturnsFalse(): void
    {
        $result = ToSendText::send('07123456789', 'Test message', 'nonexistent_provider', 'TestApp');
        $this->assertFalse($result);
    }
}
