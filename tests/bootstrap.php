<?php

if (!defined('PHPUNIT_RUNNING')) {
    define('PHPUNIT_RUNNING', true);
}

require __DIR__ . '/../vendor/autoload.php';



// Set env vars early
putenv('LOGGER_NAME=TestLogger');
putenv('LOGGER_PATH=/test-logs');



// Set env vars early
putenv('LOGGER_NAME=TestLogger');
putenv('LOGGER_PATH=/tmp/test-logs');

// Set up logger mock before LoggerFactory is autoloaded
// A real Monolog logger that discards everything. (A Mockery 'Logger' stand-in used to be
// returned here, but Utility::showError2() type-hints Monolog\Logger, so every test that
// reached the standard error handler died with a TypeError.)
$loggerMock = new \Monolog\Logger('test', [new \Monolog\Handler\NullHandler()]);

Mockery::mock('alias:Src\LoggerFactory')
    ->shouldReceive('getLogger')
    ->andReturn($loggerMock)
    ->shouldReceive('resetLogger')
    ->andReturnNull();

