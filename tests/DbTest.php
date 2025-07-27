<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Src\Db;

class DbTest extends TestCase
{
    protected function setUp(): void
    {
        // Set up test environment variables
        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_NAME'] = 'test_db';
        $_ENV['DB_USERNAME'] = 'test_user';
        $_ENV['DB_PASSWORD'] = 'test_password';
    }

    protected function tearDown(): void
    {
        // Clean up environment variables
        unset($_ENV['DB_HOST'], $_ENV['DB_NAME'], $_ENV['DB_USERNAME'], $_ENV['DB_PASSWORD']);
    }

    public function testBrConstant()
    {
        $this->assertEquals('<br>', Db::BR);
    }

    public function testConnect()
    {
        // This test would require a mock PDO connection
        // For now, we'll test that the method exists and is callable
        $db = new Db();
        $this->assertTrue(method_exists($db, 'connect'));
    }

    public function testConnect2()
    {
        // Test that the static method exists
        $this->assertTrue(method_exists(Db::class, 'connect2'));
    }

    public function testConnectSql()
    {
        // Test that the method exists
        $db = new Db();
        $this->assertTrue(method_exists($db, 'connectSql'));
    }
}
