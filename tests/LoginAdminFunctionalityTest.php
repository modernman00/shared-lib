<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Src\functionality\LoginAdminFunctionality;

class LoginAdminFunctionalityTest extends TestCase
{
    protected function setUp(): void
    {
        // Defined-but-unreachable DB so the fallback lookup fails defensively (no notices).
        $_ENV['DB_HOST'] = '127.0.0.1:1';
        $_ENV['DB_NAME'] = 'none';
        $_ENV['DB_USERNAME'] = 'none';
        $_ENV['DB_PASSWORD'] = 'none';
    }

    private function resolve(string|int $id, array $row): bool
    {
        $m = new ReflectionMethod(LoginAdminFunctionality::class, 'resolveTotpEnrollment');

        return (bool) $m->invoke(null, $id, $row);
    }

    public function testEnrolledWithGoogle2faColumns(): void
    {
        $this->assertTrue($this->resolve('a1', ['google2fa_secret' => 'ABC', 'google2fa_enabled' => 1]));
    }

    public function testEnrolledWithTotpColumns(): void
    {
        $this->assertTrue($this->resolve('a1', ['totp_secret' => 'ABC', 'totp_enabled' => 1]));
    }

    public function testEnrolledWithTwoFaColumns(): void
    {
        $this->assertTrue($this->resolve('a1', ['two_fa_secret' => 'ABC', 'two_fa_enabled' => 1]));
    }

    public function testSecretWithoutEnabledFlagIsNotEnrolled(): void
    {
        // DB fallback fails defensively (no connection) -> false
        $this->assertFalse($this->resolve('a1', ['google2fa_secret' => 'ABC', 'google2fa_enabled' => 0]));
    }

    public function testEmptyRowIsNotEnrolled(): void
    {
        $this->assertFalse($this->resolve('a1', []));
    }

    public function testCustomResolverContractExists(): void
    {
        $m = new ReflectionMethod(LoginAdminFunctionality::class, 'login');
        $params = array_map(static fn ($p) => $p->getName(), $m->getParameters());

        $this->assertContains('totpResolver', $params);
        $this->assertContains('requireSecretCode', $params);
        $this->assertSame('json', $m->getParameters()[3]->getDefaultValue());
    }

    public function testUnsetSecretCodeFailsClosed(): void
    {
        unset($_ENV['CODING']);
        putenv('CODING');
        $_POST = ['email' => 'a@b.com', 'password' => 'whatever', 'code' => ''];

        $this->expectException(\Throwable::class);
        LoginAdminFunctionality::login(isCaptchaV3: false, returnType: 'array');
    }

    public function testInvalidAdminCodeIsRejectedInArrayMode(): void
    {
        $_ENV['CODING'] = 'secret-code';
        $_POST = ['email' => 'a@b.com', 'password' => 'whatever', 'code' => 'wrong'];
        $_SERVER['REQUEST_METHOD'] = 'POST';

        $this->expectException(\Throwable::class);
        LoginAdminFunctionality::login(isCaptchaV3: false, returnType: 'array');
    }
}
