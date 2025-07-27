<?php

declare(strict_types=1);

namespace Tests\Unit\Functionality;

use Src\functionality\PasswordRecoveryService;
use Src\Db;
use Src\Exceptions\NotFoundException;
use Src\Exceptions\UnauthorisedException;
use Src\Exceptions\RecaptchaFailedException;
use Src\Exceptions\TooManyRequestsException;
use Src\Exceptions\ValidationException;
use PDO;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the PasswordRecoveryService class.
 */
class PasswordRecoveryServiceTest extends TestCase
{
    private PDO $pdo;
    private MockInterface $sendEmailMock;
    private MockInterface $bladeMock;
    private MockInterface $tokenMock;
    private MockInterface $dbMock;
    private string $sessionName;

    protected function setUp(): void
    {
        parent::setUp();

        // Initialize in-memory SQLite database
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("
            CREATE TABLE rate_limiter (
                id VARCHAR(255) PRIMARY KEY,
                state BLOB
            );
            CREATE TABLE account (
                id INTEGER PRIMARY KEY,
                user_id INTEGER,
                email VARCHAR(255),
                password VARCHAR(255),
                role VARCHAR(50),
                token VARCHAR(50)
            );
        ");

        // Mock Db::connect2 to return the in-memory PDO
        $this->dbMock = Mockery::mock(Db::class);
        $this->dbMock->shouldReceive('connect2')->andReturn($this->pdo);

        // Initialize mocks
        $this->sendEmailMock = Mockery::mock('Src\SendEmail');
        $this->bladeMock = Mockery::mock('eftec\bladeone\BladeOne');
        $this->tokenMock = Mockery::mock('Src\Token');

        // Mock sendPostRequest function
        Mockery::mock('alias:Src\sendPostRequest')
            ->shouldReceive('sendPostRequest')
            ->withAnyArgs()
            ->andReturn(['success' => true, 'hostname' => 'idecide.test']);

        // Set up environment variables
        $this->sessionName = 'user_session';
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['APP_URL'] = 'http://idecide.test';
        $_ENV['DOMAIN_NAME'] = 'idecide.test';
        $_ENV['TOKEN_NAME'] = 'auth_token';
        $_ENV['COOKIE_EXPIRE'] = 3600;
        $_ENV['SECRET_RECAPTCHA_KEY'] = '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI';
        $_ENV['SECRET_RECAPTCHA_KEY_TWO_START_LETTER'] = '6L';
        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_NAME'] = 'test_db';
        $_ENV['DB_USERNAME'] = 'test_user';
        $_ENV['DB_PASSWORD'] = 'test_pass';
        $_ENV['DB_TABLE_LOGIN'] = 'account';
        $_ENV['SMTP_HOST'] = 'smtp.example.com';
        $_ENV['MEMBER_USERNAME'] = 'member_user';
        $_ENV['MEMBER_PASSWORD'] = 'member_pass';
        $_ENV['MEMBER_EMAIL'] = 'no-reply@example.com';
        $_ENV['MEMBER_SENDER'] = 'Member Sender';
        $_ENV['TEST_EMAIL'] = 'test@example.com';
        $_ENV['LOGGER_NAME'] = 'app';
        $_ENV['LOGGER_PATH'] = '/../../bootstrap/log/idecide.log';

        // Set up server and session
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_ORIGIN'] = 'http://idecide.test';
        $_SESSION = [];
        $_POST = [];
        $_COOKIE = [];

        // Mock session_regenerate_id
        if (!function_exists('Src\functionality\session_regenerate_id')) {
            eval('namespace Src\functionality; function session_regenerate_id(bool $delete_old_session = false): bool { return true; }');
        }
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_SERVER = [];
        $_COOKIE = [];
        Mockery::close();
        parent::tearDown();
    }

    /**
     * Tests the show method with a valid session.
     */
    public function testShowWithValidSession(): void
    {
        // Arrange
        $session = [$this->sessionName => 'valid_user'];
        $viewPath = 'emailResetView';
        $bladeOutput = 'Rendered view content';

        $this->bladeMock->shouldReceive('run')
            ->once()
            ->with(str_replace('/', DIRECTORY_SEPARATOR, $viewPath), Mockery::on(function ($data) {
                return isset($data['nonce']) && is_string($data['nonce']);
            }))
            ->andReturn($bladeOutput);

        // Inject BladeOne mock
        $reflection = new \ReflectionClass(\Src\Utility::class);
        $bladeProperty = $reflection->getProperty('blade');
        $bladeProperty->setAccessible(true);
        $bladeProperty->setValue(null, $this->bladeMock);

        // Capture output
        $this->expectOutputString($bladeOutput);

        // Act
        PasswordRecoveryService::show($session, $this->sessionName, $viewPath);
    }

    /**
     * Tests the show method with missing session data.
     */
    public function testShowThrowsExceptionForMissingSession(): void
    {
        // Arrange
        $session = [];
        $viewPath = 'emailResetView';

        // Assert
        $this->expectException(UnauthorisedException::class);
        $this->expectExceptionMessage('NOT SURE WE KNOW YOU');

        // Act
        PasswordRecoveryService::show($session, $this->sessionName, $viewPath);
    }

    /**
     * Tests successful password recovery process.
     */
    public function testProcessRecoverySuccess(): void
    {
        // Arrange
        $input = ['email' => 'test@example.com', '_token' => 'valid_csrf'];
        $viewPath = 'msg/customer/token';
        $user = ['id' => 1, 'user_id' => 1, 'email' => 'test@example.com', 'password' => password_hash('Pass123!', PASSWORD_BCRYPT), 'role' => 'user', 'token' => null];
        $token = 'ABC123';
        $_SESSION['csrf_token'] = 'valid_csrf';
        $_POST['g-recaptcha-response'] = 'valid_captcha';

        // Insert test user into in-memory database
        $stmt = $this->pdo->prepare("INSERT INTO account (id, user_id, email, password, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([1, 1, 'test@example.com', password_hash('Pass123!', PASSWORD_BCRYPT), 'user']);

        // Mock SendEmail
        $this->sendEmailMock->shouldReceive('sendEmail')
            ->once()
            ->with(
                'test@example.com',
                'there',
                'TOKEN',
                Mockery::type('string'),
                null,
                null
            )
            ->andReturn(true);

        // Mock BladeOne for ToSendEmail
        $this->bladeMock->shouldReceive('run')
            ->once()
            ->with(str_replace('/', '.', $viewPath), Mockery::on(function ($data) {
                return isset($data['data']['token']) && $data['data']['token'] === 'ABC123' && $data['data']['email'] === 'test@example.com';
            }))
            ->andReturn('Recovery email');

        // Inject BladeOne mock
        $reflection = new \ReflectionClass(\Src\Utility::class);
        $bladeProperty = $reflection->getProperty('blade');
        $bladeProperty->setAccessible(true);
        $bladeProperty->setValue(null, $this->bladeMock);

        // Mock Token::generateAuthToken
        $this->tokenMock->shouldReceive('generateAuthToken')
            ->once()
            ->andReturn($token);

        // Inject Db mock into Select and Update
        $reflectionSelect = new \ReflectionClass(\Src\Select::class);
        $dbPropertySelect = $reflectionSelect->getProperty('db');
        $dbPropertySelect->setAccessible(true);
        $dbPropertySelect->setValue(null, $this->dbMock);

        $reflectionUpdate = new \ReflectionClass(\Src\Update::class);
        $dbPropertyUpdate = $reflectionUpdate->getProperty('db');
        if ($dbPropertyUpdate) {
            $dbPropertyUpdate->setAccessible(true);
            $dbPropertyUpdate->setValue(null, $this->dbMock);
        }

        // Expect JSON output
        $expectedOutput = json_encode(['message' => 'Recovery token sent successfully', 'status' => 'success']);
        $this->expectOutputString($expectedOutput);

        // Act
        PasswordRecoveryService::processRecovery($input, $viewPath);

        // Assert session data
        $this->assertEquals(1, $_SESSION['auth']['identifyCust']);
        $this->assertNotEmpty($_SESSION['auth']['2FA_token_ts']);

        // Assert token in database
        $stmt = $this->pdo->prepare("SELECT token FROM account WHERE id = ?");
        $stmt->execute([1]);
        $this->assertEquals($token, $stmt->fetchColumn());
    }

    /**
     * Tests processRecovery with missing input.
     */
    public function testProcessRecoveryThrowsExceptionForMissingInput(): void
    {
        // Arrange
        $input = [];
        $viewPath = 'msg/customer/token';
        $_SESSION['csrf_token'] = 'valid_csrf';
        $_POST['g-recaptcha-response'] = 'valid_captcha';

        // Assert
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Missing recovery input');

        // Act
        PasswordRecoveryService::processRecovery($input, $viewPath);
    }

    /**
     * Tests processRecovery with invalid email.
     */
    public function testProcessRecoveryThrowsExceptionForInvalidEmail(): void
    {
        // Arrange
        $input = ['email' => 'invalid', '_token' => 'valid_csrf'];
        $viewPath = 'msg/customer/token';
        $_SESSION['csrf_token'] = 'valid_csrf';
        $_POST['g-recaptcha-response'] = 'valid_captcha';

        // Assert
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessageMatches('/There is a problem with your input/');

        // Act
        PasswordRecoveryService::processRecovery($input, $viewPath);
    }

    /**
     * Tests processRecovery with user not found.
     */
    public function testProcessRecoveryThrowsExceptionForUserNotFound(): void
    {
        // Arrange
        $input = ['email' => 'test@example.com', '_token' => 'valid_csrf'];
        $viewPath = 'msg/customer/token';
        $_SESSION['csrf_token'] = 'valid_csrf';
        $_POST['g-recaptcha-response'] = 'valid_captcha';

        // Inject Db mock
        $reflectionSelect = new \ReflectionClass(\Src\Select::class);
        $dbPropertySelect = $reflectionSelect->getProperty('db');
        $dbPropertySelect->setAccessible(true);
        $dbPropertySelect->setValue(null, $this->dbMock);

        // Assert
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('User not found');

        // Act
        PasswordRecoveryService::processRecovery($input, $viewPath);
    }

    /**
     * Tests processRecovery with CAPTCHA verification failure.
     */
    public function testProcessRecoveryThrowsExceptionForCaptchaFailure(): void
    {
        // Arrange
        $input = ['email' => 'test@example.com', '_token' => 'valid_csrf'];
        $viewPath = 'msg/customer/token';
        $_SESSION['csrf_token'] = 'valid_csrf';
        $_POST['g-recaptcha-response'] = '';

        // Assert
        $this->expectException(RecaptchaFailedException::class);
        $this->expectExceptionMessage("🚨 Oops! Forgot the 'I'm not a robot' box!");

        // Act
        PasswordRecoveryService::processRecovery($input, $viewPath);
    }

    /**
     * Tests processRecovery with rate limit exceeded.
     */
    public function testProcessRecoveryThrowsExceptionForRateLimit(): void
    {
        // Arrange
        $input = ['email' => 'test@example.com', '_token' => 'valid_csrf'];
        $viewPath = 'msg/customer/token';
        $_SESSION['csrf_token'] = 'valid_csrf';
        $_POST['g-recaptcha-response'] = 'valid_captcha';

        // Simulate rate limit exceeded
        $rateLimiterFactory = new \Symfony\Component\RateLimiter\RateLimiterFactory([
            'id' => 'login',
            'policy' => 'fixed_window',
            'limit' => 5,
            'interval' => '900 seconds',
        ], new \Src\PdoStorage($this->pdo));
        $limiter = $rateLimiterFactory->create('test@example.com');
        for ($i = 0; $i < 6; $i++) {
            $limiter->consume(1);
        }

        // Inject Db mock
        $reflectionSelect = new \ReflectionClass(\Src\Select::class);
        $dbPropertySelect = $reflectionSelect->getProperty('db');
        $dbPropertySelect->setAccessible(true);
        $dbPropertySelect->setValue(null, $this->dbMock);

        // Assert
        $this->expectException(TooManyRequestsException::class);
        $this->expectExceptionMessageMatches('/Too many login attempts/');

        // Act
        PasswordRecoveryService::processRecovery($input, $viewPath);
    }

    /**
     * Tests processRecovery with invalid CSRF token.
     */
    public function testProcessRecoveryThrowsExceptionForInvalidToken(): void
    {
        // Arrange
        $input = ['email' => 'test@example.com', '_token' => 'invalid_csrf'];
        $viewPath = 'msg/customer/token';
        $_SESSION['csrf_token'] = 'valid_csrf';
        $_POST['g-recaptcha-response'] = 'valid_captcha';

        // Inject Db mock
        $reflectionSelect = new \ReflectionClass(\Src\Select::class);
        $dbPropertySelect = $reflectionSelect->getProperty('db');
        $dbPropertySelect->setAccessible(true);
        $dbPropertySelect->setValue(null, $this->dbMock);

        // Assert
        $this->expectException(UnauthorisedException::class);
        $this->expectExceptionMessage('We are not familiar with the nature of your activities.');

        // Act
        PasswordRecoveryService::processRecovery($input, $viewPath);
    }

    /**
     * Tests processRecovery with missing email credentials.
     */
    public function testProcessRecoveryThrowsExceptionForMissingEmailCredentials(): void
    {
        // Arrange
        $input = ['email' => 'test@example.com', '_token' => 'valid_csrf'];
        $viewPath = 'msg/customer/token';
        $user = ['id' => 1, 'user_id' => 1, 'email' => 'test@example.com', 'password' => password_hash('Pass123!', PASSWORD_BCRYPT), 'role' => 'user', 'token' => null];
        $_SESSION['csrf_token'] = 'valid_csrf';
        $_POST['g-recaptcha-response'] = 'valid_captcha';

        // Insert test user
        $stmt = $this->pdo->prepare("INSERT INTO account (id, user_id, email, password, role) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([1, 1, 'test@example.com', password_hash('Pass123!', PASSWORD_BCRYPT), 'user']);

        // Unset email credentials
        unset($_ENV['MEMBER_USERNAME']);

        // Mock Token::generateAuthToken
        $this->tokenMock->shouldReceive('generateAuthToken')
            ->once()
            ->andReturn('ABC123');

        // Inject Db mock
        $reflectionSelect = new \ReflectionClass(\Src\Select::class);
        $dbPropertySelect = $reflectionSelect->getProperty('db');
        $dbPropertySelect->setAccessible(true);
        $dbPropertySelect->setValue(null, $this->dbMock);

        $reflectionUpdate = new \ReflectionClass(\Src\Update::class);
        $dbPropertyUpdate = $reflectionUpdate->getProperty('db');
        if ($dbPropertyUpdate) {
            $dbPropertyUpdate->setAccessible(true);
            $dbPropertyUpdate->setValue(null, $this->dbMock);
        }

        // Assert
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('email username not available');

        // Act
        PasswordRecoveryService::processRecovery($input, $viewPath);
    }
}