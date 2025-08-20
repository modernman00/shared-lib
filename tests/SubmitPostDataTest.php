<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use Mockery;
use Tests\Traits\MocksShowErrorTrait;
use Src\functionality\SubmitPostData;
use RuntimeException;



class SubmitPostDataTest extends TestCase
{
      use MocksShowErrorTrait;

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    protected function setUp(): void
{
    parent::setUp();

    // Completely bypass error functions
    $this->mockShowErrorFunctions('Src\functionality');

    // Optional: create a real logger so any code expecting it won't fail
    $logger = new \Monolog\Logger('TestLogger');
    $logger->pushHandler(new \Monolog\Handler\StreamHandler('php://stdout'));

    Mockery::mock('alias:Src\\Utility')
        ->shouldReceive('getLogger')->andReturn($logger)
        ->byDefault();

    // Optional: bypass msgSuccess if needed
    Mockery::mock('alias:Src\\Utility')
        ->shouldReceive('msgSuccess')->andReturnNull()->byDefault();
}

    /** @test */
    public function testSubmitsDataSuccessfully(): void
    {
        $cleanData = ['token' => 'valid-token'];
        $multipleTablesnData = [
            'users' => ['name' => 'Alice']
        ];

        Mockery::mock('alias:Src\Recaptcha')
            ->shouldReceive('verifyCaptcha')->once()->with($cleanData);

        Mockery::mock('alias:Src\CheckToken')
            ->shouldReceive('tokenCheck')->once()->with('valid-token');

       Mockery::mock('alias:Src\Transaction')
    ->shouldReceive('beginTransaction')->once()
    ->shouldReceive('commit')->once()
    ->shouldReceive('rollback')->once();


        Mockery::mock('alias:Src\Db')
            ->shouldReceive('connect2')->andReturn(Mockery::mock(\PDO::class));

        Mockery::mock('alias:Src\SubmitForm')
            ->shouldReceive('submitForm')->once()->andReturn(true);

        Mockery::mock('alias:Src\Utility')
            ->shouldReceive('msgSuccess')->once()->with(201, 'Record created successfully');

        SubmitPostData::submit($multipleTablesnData, $cleanData);

        $this->addToAssertionCount(1);
    }

    /** @test */
    public function testThrowsWhenCaptchaFails(): void
    {
        $cleanData = ['token' => 'valid-token'];
        $multipleTablesnData = ['users' => ['name' => 'Alice']];

        Mockery::mock('alias:Src\Recaptcha')
            ->shouldReceive('verifyCaptcha')->once()->with($cleanData)
            ->andThrow(new \RuntimeException('Captcha failed'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Captcha failed');

        SubmitPostData::submit($multipleTablesnData, $cleanData);
    }

    /** @test */
    public function testThrowsWhenTokenIsInvalid(): void
    {
        $cleanData = ['token' => 'invalid-token'];
        $multipleTablesnData = ['users' => ['name' => 'Alice']];

        Mockery::mock('alias:Src\Recaptcha')
            ->shouldReceive('verifyCaptcha')->once()->with($cleanData);

        Mockery::mock('alias:Src\CheckToken')
            ->shouldReceive('tokenCheck')->once()->with('invalid-token')
            ->andThrow(new \RuntimeException('Invalid token'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid token');

        SubmitPostData::submit($multipleTablesnData, $cleanData);
    }

    /** @test */
    public function testRollbackOnFailedSubmission(): void
    {
        $cleanData = ['token' => 'valid-token'];
        $multipleTablesnData = [
            'users' => ['name' => 'Alice'],
            'posts' => ['title' => 'Hello']
        ];

        Mockery::mock('alias:Src\Recaptcha')
            ->shouldReceive('verifyCaptcha')->once()->with($cleanData);

        Mockery::mock('alias:Src\CheckToken')
            ->shouldReceive('tokenCheck')->once()->with('valid-token');

        Mockery::mock('alias:Src\Transaction')
            ->shouldReceive('beginTransaction')->once()
            ->shouldReceive('rollback')->once();

        Mockery::mock('alias:Src\Db')
            ->shouldReceive('connect2')->andReturn(Mockery::mock(\PDO::class));

        Mockery::mock('alias:Src\SubmitForm')
            ->shouldReceive('submitForm')->once()->andReturn(false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("users didn't submit");

        SubmitPostData::submit($multipleTablesnData, $cleanData);
    }

    /** @test */
    public function testUploadsSingleImageSuccessfully(): void
    {
        $uploadPath = '/tmp';
        $formInput = 'image';

        Mockery::mock('alias:Src\FileUploader')
            ->shouldReceive('fileUploadSingle')->once()
            ->with($uploadPath, $formInput, $_ENV['FILE_UPLOAD_CLOUDMERSIVE'] ?? null)
            ->andReturn('uploaded.jpg');

        $filename = SubmitPostData::submitImgDataSingle($formInput, $uploadPath);

        $this->assertEquals('uploaded.jpg', $filename);
    }

    /** @test */
    public function testUploadsMultipleImagesSuccessfully(): void
    {
        $uploadPath = '/tmp';
        $formInput = 'images';

        Mockery::mock('alias:Src\FileUploader')
            ->shouldReceive('fileUploadMultiple')->once()
            ->with($uploadPath, $formInput, $_ENV['FILE_UPLOAD_CLOUDMERSIVE'] ?? null)
            ->andReturn(['img1.jpg', 'img2.jpg']);

        $files = SubmitPostData::submitImgDataMultiple($formInput, $uploadPath);

        $this->assertEquals(['img1.jpg', 'img2.jpg'], $files);
    }
}
