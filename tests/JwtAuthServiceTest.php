<?php

use PHPUnit\Framework\TestCase;
use Firebase\JWT\JWT;
use App\service\JwtAuthService;

final class JwtAuthServiceTest extends TestCase
{
    private string $privateKey;
    private string $publicKey;
    private JwtAuthService $authService;

    protected function setUp(): void
    {
        $res = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($res, $private);
        $details = openssl_pkey_get_details($res);
        $public = $details['key'];

        $this->privateKey = $private;
        $this->publicKey = $public;

        $this->authService = new class($private, $public) extends JwtAuthService {
            public function __construct(string $private, string $public)
            {
                $this->privateKey = $private;
                $this->publicKey  = $public;
            }
        };
    }

    public function testTokenIsValid(): void
    {
        $user = ['id' => 1, 'email' => 'hello@php.dev', 'role' => 'admin'];

        $token = $this->authService->generateToken($user);
        $decoded = $this->authService->validateToken($token);

        $this->assertEquals(1, $decoded->sub);
        $this->assertEquals('admin', $decoded->role);
    }
}
