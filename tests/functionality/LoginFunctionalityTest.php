<?php

declare(strict_types=1);

namespace tests\functionality;

use Src\JwtHandler;
use Src\Db;
use Src\Select;
use Src\Update;
use Src\Exceptions\NotFoundException;
use Src\Exceptions\UnauthorisedException;
use Src\Exceptions\RecaptchaFailedException;
use Src\Exceptions\TooManyRequestsException;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Response;
use PDO;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for the LoginFunctionality class.
 */
class LoginFunctionalityTest extends TestCase
{
    private PDO $pdo;
    private MockObject $selectMock;
    private MockObject $updateMock;
    private MockObject $guzzleMock;
    private string $sessionName;
    private string $publicKey;
    private string $privateKey;

    protected function setUp(): void
    {
        parent::setUp();

        // Generate RSA key pair for JWT
        $keyConfig = [
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ];
        $key = openssl_pkey_new($keyConfig);
        $this->privateKey = openssl_pkey_get_details($key)['key'];
        $this->publicKey = openssl_pkey_get_details($key)['key'];

        // Initialize in-memory SQLite database for Limiter
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("
            CREATE TABLE rate_limiter (
                id VARCHAR(255) PRIMARY KEY,
                state BLOB
            )
        ");
        // Skip database mocking for now due to type mismatch

        // Initialize mocks
        $this->selectMock = $this->createMock(Select::class);
        $this->updateMock = $this->createMock(Update::class);
        $this->guzzleMock = $this->createMock(Client::class);

        // Set up environment variables
        $this->sessionName = 'user_session';
        $_ENV['APP_ENV'] = 'testing';
        $_ENV['APP_URL'] = 'http://idecide.test';
        $_ENV['DOMAIN_NAME'] = 'idecide.test';
        $_ENV['TOKEN_NAME'] = 'auth_token';
        $_ENV['COOKIE_EXPIRE'] = 3600;
        $_ENV['JWT_TOKEN'] = $this->privateKey;
        $_ENV['SECRET_RECAPTCHA_KEY'] = '6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI';
        $_ENV['SECRET_RECAPTCHA_KEY_TWO_START_LETTER'] = '6L';
        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_NAME'] = 'test_db';
        $_ENV['DB_USERNAME'] = 'test_user';
        $_ENV['DB_PASSWORD'] = 'test_pass';
        $_ENV['DB_TABLE_LOGIN'] = 'account';

        // Set up server and session
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        $_SERVER['HTTP_ORIGIN'] = 'http://idecide.test';
        $_SESSION = [];
        $_POST = [];
        $_COOKIE = [];

        // Mock GuzzleHttp\Client for sendPostRequest
        $this->guzzleMock->method('post')
            ->willReturn(new Response(200, [], json_encode(['success' => true, 'hostname' => $_ENV['DOMAIN_NAME']])));
        \Src\sendPostRequest::setMockClient($this->guzzleMock);

        // Mock session_regenerate_id
        if (!function_exists('Src\functionality\session_regenerate_id')) {
            eval('namespace Src\functionality; function session_regenerate_id(bool $delete_old_session = false): bool { return true; }');
        }

        // Mock setcookie
        if (!function_exists('Src\setcookie')) {
            eval('namespace Src; function setcookie($name, $value, $expires, $path, $domain, $secure, $httponly) { \Tests\Unit\Functionality\LoginFunctionalityTest::storeCookie($name, $value, $expires, $path, $domain, $secure, $httponly); return true; }');
        }
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        $_POST = [];
        $_SERVER = [];
        $_COOKIE = [];
        Db::clearMockConnection();
        \Src\sendPostRequest::clearMockClient();
        parent::tearDown();
    }

    /**
     * Stores cookie data for verification.
     */
    public static array $cookieData = [];

    public static function storeCookie(string $name, string $value, int $expires, string $path, string $domain, bool $secure, bool $httponly): void
    {
        self::$cookieData = [
            'name' => $name,
            'value' => $value,
            'expires' => $expires,
            'path' => $path,
            'domain' => $domain,
            'secure' => $secure,
            'httponly' => $httponly,
        ];
    }

    /**
     * Tests the show method with a valid session.
     */
    public function testShowWithValidSession(): void
    {
        // Arrange
        $session = [$this->sessionName => 'valid_user'];
        $viewPath = 'login.success';
        $bladeOutput = 'Rendered view content';

        // Mock BladeOne
        $bladeMock = $this->createMock(\eftec\bladeone\BladeOne::class);
        $bladeMock->expects($this->once())
            ->method('run')
            ->with(str_replace('/', DIRECTORY_SEPARATOR, $viewPath), $this->callback(function ($data) {
                return isset($data['nonce']) && is_string($data['nonce']);
            }))
            ->willReturn($bladeOutput);

        // Inject BladeOne mock
        $reflection = new \ReflectionClass(\Src\Utility::class);
        $bladeProperty = $reflection->getProperty('blade');
        $bladeProperty->setAccessible(true);
        $bladeProperty->setValue(null, $bladeMock);

        // Capture output
        $this->expectOutputString($bladeOutput);

        // Act
        LoginFunctionality::show($session, $this->sessionName, $viewPath);
    }

    /**
     * Tests the show method with missing session data.
     */
    public function testShowThrowsExceptionForMissingSession(): void
    {
        // Arrange
        $session = [];
        $viewPath = 'login.success';

        // Assert
        $this->expectException(UnauthorisedException::class);
        $this->expectExceptionMessage('NOT SURE WE KNOW YOU');

        // Act
        LoginFunctionality::show($session, $this->sessionName, $viewPath);
    }

    /**
     * Tests successful login with JWT issuance and remember me.
     */
    public function testLoginSuccessWithJwtAndRememberMe(): void
    {
        // Arrange
        $input = ['email' => 'test@example.com', 'password' => 'Pass123!', '_token' => 'valid_csrf'];
        $captchaAction = 'login';
        $issueJwt = true;
        $user = ['id' => 1, 'user_id' => 1, 'email' => 'test@example.com', 'password' => password_hash('Pass123!', PASSWORD_BCRYPT), 'role' => 'user'];
        $_SESSION['csrf_token'] = 'valid_csrf';
        $_POST['g-recaptcha-response'] = 'valid_captcha';
        $_POST['rememberMe'] = 'true';

        $this->selectMock->expects($this->once())
            ->method('selectFn2')
            ->with($this->stringContains('SELECT * FROM account WHERE email = ?'), ['test@example.com'])
            ->willReturn([$user]);

        // Inject Select mock
        Select::setMock($this->selectMock);

        // Expect JSON output
        $jwtHandler = new JwtHandler();
        $token = $jwtHandler->jwtEncodeData($user);
        $expectedOutput = json_encode([
            'message' => 'Login Successful',
            'token' => ['token' => $token],
            'status' => 'success',
        ]);
        $this->expectOutputString($expectedOutput);

        // Act
        LoginFunctionality::login($input, $captchaAction, $issueJwt);

        // Verify cookie
        $this->assertNotEmpty(self::$cookieData);
        $this->assertEquals($_ENV['TOKEN_NAME'], self::$cookieData['name']);
        $this->assertEquals($token, self::$cookieData['value']);
        $this->assertEquals('/', self::$cookieData['path']);
        $this->assertEquals($_ENV['APP_URL'], self::$cookieData['domain']);
        $this->assertFalse(self::$cookieData['secure']);
        $this->assertFalse(self::$cookieData['httponly']);
    }

    /**
     * Tests successful login with session-based authentication.
     */
    public function testLoginSuccessWithSession(): void
    {
        // Arrange
        $input = ['email' => 'test@example.com', 'password' => 'Pass123!', '_token' => 'valid_csrf'];
        $captchaAction = 'login';
        $issueJwt = false;
        $user = ['id' => 1, 'user_id' => 1, 'email' => 'test@example.com', 'password' => password_hash('Pass123!', PASSWORD_BCRYPT), 'role' => 'user'];
        $_SESSION['csrf_token'] = 'valid_csrf';
        $_POST['g-recaptcha-response'] = 'valid_captcha';

        $this->selectMock->expects($this->once())
            ->method('selectFn2')
            ->with($this->stringContains('SELECT * FROM account WHERE email = ?'), ['test@example.com'])
            ->willReturn([$user]);

        // Inject Select mock
        Select::setMock($this->selectMock);

        // Expect JSON output
        $expectedOutput = json_encode([
            'message' => 'Login Successful',
            'token' => null,
            'status' => 'success',
        ]);
        $this->expectOutputString($expectedOutput);

        // Act
        LoginFunctionality::login($input, $captchaAction, $issueJwt);

        // Assert
        $this->assertEquals($user['id'], $_SESSION['ID']);
        $this->assertEmpty(self::$cookieData);
    }

    /**
     * Tests login with missing identifier (email or username).
     */
    public function testLoginThrowsExceptionForMissingIdentifier(): void
    {
        // Arrange
        $input = ['password' => 'Pass123!', '_token' => 'valid_csrf'];
        $_SESSION['csrf_token'] = 'valid_csrf';
        $_POST['g-recaptcha-response'] = 'valid_captcha';

        // Assert
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('We cannot locate the information');

        // Act
        LoginFunctionality::login($input);
    }

    /**
     * Tests login with CAPTCHA verification failure.
     */
    public function testLoginThrowsExceptionForCaptchaFailure(): void
    {
        // Arrange
        $input = ['email' => 'test@example.com', 'password' => 'Pass123!', '_token' => 'valid_csrf'];
        $_SESSION['csrf_token'] = 'valid_csrf';
        $_POST['g-recaptcha-response'] = '';

        // Assert
        $this->expectException(RecaptchaFailedException::class);
        $this->expectExceptionMessage("🚨 Oops! Forgot the 'I'm not a robot' box!");

        // Act
        LoginFunctionality::login($input);
    }

    /**
     * Tests login with rate limit exceeded.
     */
    public function testLoginThrowsExceptionForRateLimit(): void
    {
        // Arrange
        $input = ['email' => 'test@example.com', 'password' => 'Pass123!', '_token' => 'valid_csrf'];
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

        // Assert
        $this->expectException(TooManyRequestsException::class);
        $this->expectExceptionMessageMatches('/Too many login attempts/');

        // Act
        LoginFunctionality::login($input);
    }

    /**
     * Tests login with invalid CSRF token.
     */
    public function testLoginThrowsExceptionForInvalidToken(): void
    {
        // Arrange
        $input = ['email' => 'test@example.com', 'password' => 'Pass123!', '_token' => 'invalid_csrf'];
        $_SESSION['csrf_token'] = 'valid_csrf';
        $_POST['g-recaptcha-response'] = 'valid_captcha';

        // Assert
        $this->expectException(UnauthorisedException::class);
        $this->expectExceptionMessage('We are not familiar with the nature of your activities.');

        // Act
        LoginFunctionality::login($input);
    }

    /**
     * Tests login with invalid credentials.
     */
    public function testLoginThrowsExceptionForInvalidCredentials(): void
    {
        // Arrange
        $input = ['email' => 'test@example.com', 'password' => 'WrongPass!', '_token' => 'valid_csrf'];
        $user = ['id' => 1, 'user_id' => 1, 'email' => 'test@example.com', 'password' => password_hash('Pass123!', PASSWORD_BCRYPT), 'role' => 'user'];
        $_SESSION['csrf_token'] = 'valid_csrf';
        $_POST['g-recaptcha-response'] = 'valid_captcha';

        $this->selectMock->expects($this->once())
            ->method('selectFn2')
            ->with($this->stringContains('SELECT * FROM account WHERE email = ?'), ['test@example.com'])
            ->willReturn([$user]);

        // Inject Select mock
        Select::setMock($this->selectMock);

        // Assert
        $this->expectException(NotFoundException::class);
        $this->expectExceptionMessage('Oops! Wrong email or password.');

        // Act
        LoginFunctionality::login($input);
    }

    /**
     * Tests login with password needing rehash.
     */
    public function testLoginWithPasswordRehash(): void
    {
        // Arrange
        $input = ['email' => 'test@example.com', 'password' => 'Pass123!', '_token' => 'valid_csrf'];
        $captchaAction = 'login';
        $issueJwt = true;
        $user = ['id' => 1, 'user_id' => 1, 'email' => 'test@example.com', 'password' => password_hash('Pass123!', PASSWORD_BCRYPT, ['cost' => 10]), 'role' => 'user'];
        $_SESSION['csrf_token'] = 'valid_csrf';
        $_POST['g-recaptcha-response'] = 'valid_captcha';

        $this->selectMock->expects($this->once())
            ->method('selectFn2')
            ->with($this->stringContains('SELECT * FROM account WHERE email = ?'), ['test@example.com'])
            ->willReturn([$user]);

        $this->updateMock->expects($this->once())
            ->method('updateTable')
            ->with('password', $this->stringContains('$2y$12$'), 'id', 1)
            ->willReturn(true);

        // Inject mocks
        Select::setMock($this->selectMock);
        Update::setMock($this->updateMock);

        // Expect JSON output
        $jwtHandler = new JwtHandler();
        $token = $jwtHandler->jwtEncodeData($user);
        $expectedOutput = json_encode([
            'message' => 'Login Successful',
            'token' => ['token' => $token],
            'status' => 'success',
        ]);
        $this->expectOutputString($expectedOutput);

        // Act
        LoginFunctionality::login($input, $captchaAction, $issueJwt);
    }
}

/**
 * Mock Select class.
 */
namespace Src;

class Select
{
    private static ?MockObject $mock = null;

    public static function setMock(MockObject $mock): void
    {
        self::$mock = $mock;
    }

    public static function formAndMatchQuery(string $selection, string $table, string $identifier1, ?string $column = null, ?string $column2 = null): string
    {
        return 'SELECT * FROM ' . $table . ' WHERE ' . $identifier1 . ' = ?';
    }

    public static function selectFn2(string $query, array $bind): array
    {
        return self::$mock ? self::$mock->selectFn2($query, $bind) : [];
    }
}

/**
 * Mock Update class.
 */
class Update
{
    private static ?MockObject $mock = null;

    public function __construct(string $table) {}

    public static function setMock(MockObject $mock): void
    {
        self::$mock = $mock;
    }

    public function updateTable(string $column, string $value, string $identifier, $id): bool
    {
        return self::$mock ? self::$mock->updateTable($column, $value, $identifier, $id) : false;
    }
}

/**
 * Mock sendPostRequest function.
 */
function sendPostRequest(string $url, array $formData, array $options = []): ?array
{
    $mockClient = \Tests\Unit\Functionality\LoginFunctionalityTest::$mockClient;
    if ($mockClient) {
        $response = $mockClient->post($url, array_merge(['form_params' => $formData], $options));
        $body = $response->getBody()->getContents();
        return json_decode($body, true);
    }
    return null;
}

namespace Tests\Unit\Functionality;

class LoginFunctionalityTest
{
    public static ?MockObject $mockClient = null;

    public static function setMockClient(MockObject $client): void
    {
        self::$mockClient = $client;
    }

    public static function clearMockClient(): void
    {
        self::$mockClient = null;
    }
}

/**
 * Mock Db class.
 */
namespace Src;

class Db
{
    private static ?PDO $connection = null;

    public static function setMockConnection(PDO $pdo): void
    {
        self::$connection = $pdo;
    }

    public static function clearMockConnection(): void
    {
        self::$connection = null;
    }

    public static function connect2(): PDO
    {
        if (self::$connection !== null) {
            return self::$connection;
        }
        throw new \RuntimeException('No mock connection set for testing');
    }
}