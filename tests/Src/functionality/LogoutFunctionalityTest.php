<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Src\functionality\LogoutFunctionality;

/**
 * LogoutFunctionality (formerly LogoutController). signout() ends in a Location header +
 * exit(), so these tests cover its public contract and the redirect validation it applies.
 */
class LogoutFunctionalityTest extends TestCase
{
    public function testClassAndSignoutMethodExist(): void
    {
        $this->assertTrue(class_exists(LogoutFunctionality::class));
        $this->assertTrue(method_exists(LogoutFunctionality::class, 'signout'));
    }

    public function testSignoutMethodSignature(): void
    {
        $reflection = new ReflectionMethod(LogoutFunctionality::class, 'signout');
        $this->assertTrue($reflection->isStatic());
        $this->assertTrue($reflection->isPublic());
        $this->assertSame(1, $reflection->getNumberOfParameters());
        $this->assertSame('array', (string) $reflection->getParameters()[0]->getType());
    }

    /** @return array<string, array{mixed, string}> */
    public static function redirects(): array
    {
        return [
            'default' => ['/', '/'],
            'simple path' => ['/dashboard', '/dashboard'],
            'missing slash added' => ['login', '/login'],
            'query string kept' => ['/login?next=home', '/login?next=home'],
            'empty string' => ['', '/'],
            'absolute url' => ['https://evil.example/phish', '/'],
            'protocol-relative' => ['//evil.example', '/'],
            'backslash trick' => ['/\\evil.example', '/'],
            'script' => ['<script>alert("xss")</script>', '/'],
            'header injection' => ["/ok\r\nSet-Cookie: x=1", '/'],
            'non-string' => [['/array'], '/'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('redirects')]
    public function testSafeRedirectPath(mixed $input, string $expected): void
    {
        $this->assertSame($expected, LogoutFunctionality::safeRedirectPath($input));
    }
}
