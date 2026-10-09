<?php

use PHPUnit\Framework\TestCase;
use Src\Utility;
use Src\Exceptions\ValidationException;

class UtilityTest extends TestCase
{
    public function testAddMonthsToDate()
    {
        $result = Utility::addMonthsToDate(1, '2023-01-31');
        $this->assertEquals(' 28th of February 2023', $result['fullDate']);
        $this->assertEquals('2023-02-28', $result['dateFormat']);
    }

    public function testGetUserIpAddr()
    {
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $ip = Utility::getUserIpAddr();
        $this->assertEquals('127.0.0.1', $ip);
    }

    public function testCompare()
    {
        $this->assertTrue(Utility::compare(1, '1'));
        $this->assertFalse(Utility::compare(1, 2));
    }

    public function testCleanSession()
    {
        $result = Utility::cleanSession('Hello@World!');
        $this->assertEquals('Hello@World!', $result);
    }

    public function testOnlyLettersNumbersUnderscore()
    {
        $this->assertTrue(Utility::onlyLettersNumbersUnderscore('test_variable1'));
        $this->assertFalse(Utility::onlyLettersNumbersUnderscore('123_test'));
    }

    public function testMilliSeconds()
    {
        $result = Utility::milliSeconds();
        $this->assertIsString($result);
        $this->assertGreaterThan(0, (int)$result);
    }

    public function testHumanTiming()
    {
        $result = Utility::humanTiming('2023-01-01 00:00:00');
        $this->assertIsString($result);
    }

    public function testAddCountryCode() {
        $result = Utility::addCountryCode('0123456789', '+1');
        $this->assertEquals('+1123456789', $result);
    }

    public function testCheckInput() {
        $result = Utility::checkInput('<script>alert("Hi");</script>');
        $this->assertEquals('ltscriptgtalertquotHiquotltscriptgt', $result);
    }

    public function testIsLocalEnv() {
        $_ENV['APP_ENV'] = 'development';
        $this->assertTrue(Utility::isLocalEnv());

        $_ENV['APP_ENV'] = 'production';
        $this->assertFalse(Utility::isLocalEnv());
    }

    public function testAdminUrl() {
        // 1. Default fallback when ADMIN_SECRET_PATH is not set
        unset($_ENV['ADMIN_SECRET_PATH']);
        putenv('ADMIN_SECRET_PATH');
        $this->assertEquals('/admin', Utility::adminUrl());
        $this->assertEquals('/admin/dashboard', Utility::adminUrl('dashboard'));
        $this->assertEquals('/admin/dashboard', Utility::adminUrl('/dashboard/'));
        $this->assertEquals('/admin/customer/123', Utility::adminUrl('customer/123'));
        $this->assertEquals('/admin/dashboard', admin_url('dashboard'));

        // 2. Custom secret path without slashes
        $_ENV['ADMIN_SECRET_PATH'] = 'secure_portal';
        $this->assertEquals('/secure_portal', Utility::adminUrl());
        $this->assertEquals('/secure_portal/dashboard', Utility::adminUrl('dashboard'));
        $this->assertEquals('/secure_portal/dashboard', admin_url('dashboard'));

        // 3. Custom secret path with leading and trailing slashes
        $_ENV['ADMIN_SECRET_PATH'] = '/custom_admin/';
        $this->assertEquals('/custom_admin', Utility::adminUrl());
        $this->assertEquals('/custom_admin/profit', Utility::adminUrl('profit'));
        $this->assertEquals('/custom_admin/profit', admin_url('profit'));
    }
}
