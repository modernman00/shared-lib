<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Src\ApiResponse;

class ApiResponseTest extends TestCase
{
    public function testToSuccessEnvelopeProducesStrictContract(): void
    {
        $payload = ['id' => 42, 'name' => 'John Doe'];
        $envelope = ApiResponse::toSuccessEnvelope($payload, 'User retrieved successfully');

        $this->assertTrue($envelope['ok']);
        $this->assertSame('success', $envelope['status']);
        $this->assertSame($payload, $envelope['data']);
        $this->assertSame('User retrieved successfully', $envelope['message']);
        $this->assertNull($envelope['error']);
    }

    public function testToErrorEnvelopeWithStringProducesStringError(): void
    {
        $envelope = ApiResponse::toErrorEnvelope('Invalid credentials provided', 'AUTH_FAILED');

        $this->assertFalse($envelope['ok']);
        $this->assertSame('error', $envelope['status']);
        $this->assertNull($envelope['data']);
        $this->assertSame('Invalid credentials provided', $envelope['error']);
        $this->assertSame('Invalid credentials provided', $envelope['message']);
        $this->assertSame('AUTH_FAILED', $envelope['code']);
        $this->assertSame([], $envelope['errors']);
    }

    public function testToErrorEnvelopeWithValidationMapExtractsCleanString(): void
    {
        $validationMap = [
            'email' => ['The email field is required.'],
            'password' => ['Password must be at least 8 characters.'],
        ];

        $envelope = ApiResponse::toErrorEnvelope($validationMap, 'VALIDATION_ERROR');

        $this->assertFalse($envelope['ok']);
        $this->assertSame('error', $envelope['status']);
        $this->assertSame('The email field is required.', $envelope['error']);
        $this->assertSame('The email field is required.', $envelope['message']);
        $this->assertSame($validationMap, $envelope['errors']);
        $this->assertSame('VALIDATION_ERROR', $envelope['code']);
    }
}
