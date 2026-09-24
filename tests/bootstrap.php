<?php

require __DIR__ . '/../vendor/autoload.php';



// Set env vars early
putenv('LOGGER_NAME=TestLogger');
putenv('LOGGER_PATH=/test-logs');



// Set env vars early
putenv('LOGGER_NAME=TestLogger');
putenv('LOGGER_PATH=/tmp/test-logs');

// Set up logger mock before LoggerFactory is autoloaded
$loggerMock = Mockery::mock('Logger');
$loggerMock->shouldReceive('error')->andReturnNull();
$loggerMock->shouldReceive('info')->andReturnNull();
$loggerMock->shouldReceive('debug')->andReturnNull();
$loggerMock->shouldReceive('log')->andReturnNull();
$loggerMock->shouldReceive('warning')->andReturnNull();
$loggerMock->shouldReceive('critical')->andReturnNull();

Mockery::mock('alias:Src\LoggerFactory')
    ->shouldReceive('getLogger')
    ->andReturn($loggerMock)
    ->shouldReceive('resetLogger')
    ->andReturnNull();

