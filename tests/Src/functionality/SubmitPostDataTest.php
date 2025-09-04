<?php

declare(strict_types=1);

namespace Tests\Src\functionality;

use PHPUnit\Framework\TestCase;
use Src\functionality\SubmitPostData;
use Src\{
    CorsHandler,
    Db,
    LoginUtility,
    FileUploader,
    Recaptcha,
    SubmitForm,
    Transaction,
    Utility
};
use Src\functionality\middleware\GetRequestData;
use PDO;
use RuntimeException;
use Mockery;

class SubmitPostDataTest extends TestCase
{
    private $pdoMock;

    protected function setUp(): void
    {
        // Mock PDO for database interactions
        $this->pdoMock = Mockery::mock(PDO::class);
    }

    protected function tearDown(): void
    {
        // Clean up Mockery and environment variables
        Mockery::close();
        putenv('FILE_UPLOAD_CLOUDMERSIVE');
    }

    /**
     * Test submitToOneTablenImage with valid input and no file upload.
     */
    public function testSubmitToOneTablenImageSuccessNoFile(): void
    {
        // Mock static methods with Mockery
        Mockery::mock('alias:Src\CorsHandler')
            ->shouldReceive('setHeaders')
            ->once();

        Mockery::mock('alias:Src\functionality\middleware\GetRequestData')
            ->shouldReceive('getRequestData')
            ->once()
            ->andReturn(['captcha' => 'valid_token', 'data' => 'test']);

        Mockery::mock('alias:Src\Recaptcha')
            ->shouldReceive('verifyCaptcha')
            ->once();

        Mockery::mock('alias:Src\LoginUtility')
            ->shouldReceive('getSanitisedInputData')
            ->with(['captcha' => 'valid_token', 'data' => 'test'], null)
            ->once()
            ->andReturn(['data' => 'sanitized_test']);

        Mockery::mock('alias:Src\Db')
            ->shouldReceive('connect2')
            ->once()
            ->andReturn($this->pdoMock);

        $transactionMock = Mockery::mock('alias:Src\Transaction');
        $transactionMock->shouldReceive('beginTransaction')->once();
        $transactionMock->shouldReceive('commit')->once();
        $transactionMock->shouldReceive('rollback')->never();

        Mockery::mock('alias:Src\SubmitForm')
            ->shouldReceive('submitForm')
            ->with('test_table', ['data' => 'sanitized_test'], $this->pdoMock)
            ->once()
            ->andReturn(true);

        Mockery::mock('alias:Src\Utility')
            ->shouldReceive('msgSuccess')
            ->with(201, 'Record created successfully')
            ->once();

        // Set environment variable
        putenv('FILE_UPLOAD_CLOUDMERSIVE=cloudmersive_key');

        // Execute
        SubmitPostData::submitToOneTablenImage('test_table');
    }

    /**
     * Test submitToOneTablenImage with file upload.
     */
    public function testSubmitToOneTablenImageWithFile(): void
    {
        // Mock static methods with Mockery
        Mockery::mock('alias:Src\CorsHandler')
            ->shouldReceive('setHeaders')
            ->once();

        Mockery::mock('alias:Src\functionality\middleware\GetRequestData')
            ->shouldReceive('getRequestData')
            ->once()
            ->andReturn([
                'captcha' => 'valid_token',
                'data' => 'test',
                'files' => ['name' => 'test.jpg']
            ]);

        Mockery::mock('alias:Src\Recaptcha')
            ->shouldReceive('verifyCaptcha')
            ->once();

        Mockery::mock('alias:Src\LoginUtility')
            ->shouldReceive('getSanitisedInputData')
            ->with(['captcha' => 'valid_token', 'data' => 'test', 'files' => ['name' => 'test.jpg']], null)
            ->once()
            ->andReturn(['data' => 'sanitized_test']);

        Mockery::mock('alias:Src\Db')
            ->shouldReceive('connect2')
            ->once()
            ->andReturn($this->pdoMock);

        $transactionMock = Mockery::mock('alias:Src\Transaction');
        $transactionMock->shouldReceive('beginTransaction')->once();
        $transactionMock->shouldReceive('commit')->once();
        $transactionMock->shouldReceive('rollback')->never();

        Mockery::mock('alias:Src\FileUploader')
            ->shouldReceive('fileUploadSingle')
            ->with('uploads/', 'image', 'cloudmersive_key', ['name' => 'test.jpg'])
            ->once()
            ->andReturn('test.jpg');

        Mockery::mock('alias:Src\Utility')
            ->shouldReceive('checkInputImage')
            ->with('test.jpg')
            ->once()
            ->andReturn('sanitized_test.jpg');
        Mockery::mock('alias:Src\Utility')
            ->shouldReceive('msgSuccess')
            ->with(201, 'Record created successfully')
            ->once();

        Mockery::mock('alias:Src\SubmitForm')
            ->shouldReceive('submitForm')
            ->with('test_table', ['data' => 'sanitized_test', 'image' => 'sanitized_test.jpg'], $this->pdoMock)
            ->once()
            ->andReturn(true);

        // Set environment variable
        putenv('FILE_UPLOAD_CLOUDMERSIVE=cloudmersive_key');

        // Execute
        SubmitPostData::submitToOneTablenImage('test_table', null, 'image', 'uploads/');
    }

    /**
     * Test submitToOneTablenImage with exception handling.
     */
    public function testSubmitToOneTablenImageException(): void
    {
        // Mock static methods with Mockery
        Mockery::mock('alias:Src\CorsHandler')
            ->shouldReceive('setHeaders')
            ->once();

        Mockery::mock('alias:Src\functionality\middleware\GetRequestData')
            ->shouldReceive('getRequestData')
            ->once()
            ->andReturn(['captcha' => 'invalid_token']);

        Mockery::mock('alias:Src\Recaptcha')
            ->shouldReceive('verifyCaptcha')
            ->once()
            ->andThrow(new RuntimeException('Invalid CAPTCHA'));

        $transactionMock = Mockery::mock('alias:Src\Transaction');
        $transactionMock->shouldReceive('beginTransaction')->once();
        $transactionMock->shouldReceive('rollback')->once();

        // Expect exception
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid CAPTCHA');

        // Execute
        SubmitPostData::submitToOneTablenImage('test_table');
    }

    /**
     * Test submitToMultipleTable with valid input and no files.
     */
    public function testSubmitToMultipleTableSuccessNoFiles(): void
    {
        // Mock static methods with Mockery
        Mockery::mock('alias:Src\CorsHandler')
            ->shouldReceive('setHeaders')
            ->once();

        Mockery::mock('alias:Src\functionality\middleware\GetRequestData')
            ->shouldReceive('getRequestData')
            ->once()
            ->andReturn([
                'captcha' => 'valid_token',
                'table1' => ['data' => 'test1'],
                'table2' => ['data' => 'test2']
            ]);

        Mockery::mock('alias:Src\Recaptcha')
            ->shouldReceive('verifyCaptcha')
            ->once();

        Mockery::mock('alias:Src\LoginUtility')
            ->shouldReceive('getSanitisedInputData')
            ->with(['captcha' => 'valid_token', 'table1' => ['data' => 'test1'], 'table2' => ['data' => 'test2']], null)
            ->once()
            ->andReturn([
                'table1' => ['data' => 'sanitized_test1'],
                'table2' => ['data' => 'sanitized_test2']
            ]);

        Mockery::mock('alias:Src\Db')
            ->shouldReceive('connect2')
            ->once()
            ->andReturn($this->pdoMock);

        $transactionMock = Mockery::mock('alias:Src\Transaction');
        $transactionMock->shouldReceive('beginTransaction')->once();
        $transactionMock->shouldReceive('commit')->once();
        $transactionMock->shouldReceive('rollback')->never();

        $submitFormMock = Mockery::mock('alias:Src\SubmitForm');
        $callCount = 0;
        $submitFormMock->shouldReceive('submitForm')
            ->times(2)
            ->andReturnUsing(function ($table, $data, $pdo) use (&$callCount) {
                if ($callCount === 0) {
                    $this->assertEquals('table1', $table);
                    $this->assertEquals(['data' => 'sanitized_test1'], $data);
                    $this->assertSame($this->pdoMock, $pdo);
                } elseif ($callCount === 1) {
                    $this->assertEquals('table2', $table);
                    $this->assertEquals(['data' => 'sanitized_test2'], $data);
                    $this->assertSame($this->pdoMock, $pdo);
                }
                $callCount++;
                return true;
            });

        Mockery::mock('alias:Src\Utility')
            ->shouldReceive('msgSuccess')
            ->with(201, 'Record created successfully')
            ->once();

        // Execute
        SubmitPostData::submitToMultipleTable(['table1', 'table2']);
    }

    /**
     * Test submitImgDataSingle with valid file.
     */
    public function testSubmitImgDataSingle(): void
    {
        Mockery::mock('alias:Src\FileUploader')
            ->shouldReceive('fileUploadSingle')
            ->with('uploads/', 'image', 'cloudmersive_key', ['name' => 'test.jpg'])
            ->once()
            ->andReturn('test.jpg');

        Mockery::mock('alias:Src\Utility')
            ->shouldReceive('checkInputImage')
            ->with('test.jpg')
            ->once()
            ->andReturn('sanitized_test.jpg');

        putenv('FILE_UPLOAD_CLOUDMERSIVE=cloudmersive_key');

        $result = SubmitPostData::submitImgDataSingle('image', 'uploads/', ['name' => 'test.jpg']);
        $this->assertEquals('sanitized_test.jpg', $result);
    }

    /**
     * Test submitImgDataMultiple with valid files.
     */
    public function testSubmitImgDataMultiple(): void
    {
        Mockery::mock('alias:Src\FileUploader')
            ->shouldReceive('fileUploadMultiple')
            ->with('uploads/', 'images', 'cloudmersive_key', ['images' => ['name' => ['test1.jpg', 'test2.jpg']]])
            ->once()
            ->andReturn(['sanitized_test1.jpg', 'sanitized_test2.jpg']);

        putenv('FILE_UPLOAD_CLOUDMERSIVE=cloudmersive_key');

        $result = SubmitPostData::submitImgDataMultiple('images', 'uploads/', ['images' => ['name' => ['test1.jpg', 'test2.jpg']]]);
        $this->assertEquals(['sanitized_test1.jpg', 'sanitized_test2.jpg'], $result);
    }

    /**
     * Test submitImgDataMultiple with no files.
     */
    public function testSubmitImgDataMultipleNoFiles(): void
    {
        $result = SubmitPostData::submitImgDataMultiple('images', 'uploads/', ['images' => ['name' => []]]);
        $this->assertNull($result);
    }
}