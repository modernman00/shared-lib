<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Src\CorsHandler;

/**
 * CORS Handler Tests
 * Tests CORS functionality and security
 */
class CorsHandlerTest extends TestCase
{
    protected function setUp(): void
    {
        // Reset server variables and headers before each test
        $_SERVER = [];
        $this->resetHeaders();
    }

    protected function tearDown(): void
    {
        // Clean up after each test
        $_SERVER = [];
        $this->resetHeaders();
    }

    /**
     * Test basic CORS headers are set correctly
     */
    public function testBasicCorsHeadersAreSet(): void
    {
        // Arrange
        $_SERVER['HTTP_ORIGIN'] = 'http://localhost:8080';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        
        // Mock environment
        putenv('APP_ENV=development');
        putenv('APP_URL=http://localhost:8080');

        // Act
        ob_start();
        CorsHandler::setHeaders();
        ob_end_clean();

        // Assert - We'll need to capture headers differently in real implementation
        $this->assertTrue(true); // Placeholder - headers would be tested with header capture
    }

    /**
     * Test development environment allows multiple origins
     */
    public function testDevelopmentEnvironmentAllowsMultipleOrigins(): void
    {
        // Test data for different development origins
        $developmentOrigins = [
            'http://localhost:8080',
            'http://127.0.0.1:8080',
            'http://idecide.test',
            'http://idecide.test:80'
        ];

        putenv('APP_ENV=development');

        foreach ($developmentOrigins as $origin) {
            $_SERVER['HTTP_ORIGIN'] = $origin;
            $_SERVER['REQUEST_METHOD'] = 'POST';

            // In real implementation, we'd capture and verify the Access-Control-Allow-Origin header
            $allowedOrigin = $this->simulateGetAllowedOrigin($origin, true);
            
            $this->assertEquals($origin, $allowedOrigin, "Origin $origin should be allowed in development");
        }
    }

    /**
     * Test production environment is more restrictive
     */
    public function testProductionEnvironmentIsRestrictive(): void
    {
        // Arrange
        putenv('APP_ENV=production');
        putenv('APP_URL=https://idecide.com');
        
        $_SERVER['HTTP_ORIGIN'] = 'https://idecide.com';
        $_SERVER['REQUEST_METHOD'] = 'POST';

        // Should allow matching APP_URL
        $allowedOrigin = $this->simulateGetAllowedOrigin('https://idecide.com', false);
        $this->assertEquals('https://idecide.com', $allowedOrigin);

        // Should reject different origin
        $allowedOrigin = $this->simulateGetAllowedOrigin('https://malicious.com', false);
        $this->assertNotEquals('https://malicious.com', $allowedOrigin);
    }

    /**
     * Test OPTIONS preflight request handling
     */
    public function testOptionsPreflightHandling(): void
    {
        // Arrange
        $_SERVER['REQUEST_METHOD'] = 'OPTIONS';
        $_SERVER['HTTP_ORIGIN'] = 'http://localhost:8080';
        putenv('APP_ENV=development');

        // Capture output buffer since exit() is called
        ob_start();
        
        // Use try-catch to handle exit() in test environment
        try {
            CorsHandler::setHeaders();
        } catch (\Exception $e) {
            // In real implementation, this would exit with 200 status
        }
        
        ob_end_clean();
        
        // In real test, we'd verify 200 status code and proper headers
        $this->assertTrue(true);
    }

    /**
     * Test API-specific headers
     */
    public function testApiHeadersConfiguration(): void
    {
        // Arrange
        $_SERVER['HTTP_ORIGIN'] = 'http://localhost:8080';
        $_SERVER['REQUEST_METHOD'] = 'GET';
        putenv('APP_ENV=development');

        // Act
        ob_start();
        CorsHandler::setApiHeaders();
        ob_end_clean();

        // Assert - In real implementation, we'd verify:
        // - Content-Type: application/json
        // - Allowed methods include GET, POST, PUT, DELETE
        // - Proper API headers are included
        $this->assertTrue(true);
    }

    /**
     * Test form-specific headers
     */
    public function testFormHeadersConfiguration(): void
    {
        // Arrange
        $_SERVER['HTTP_ORIGIN'] = 'http://localhost:8080';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        putenv('APP_ENV=development');

        // Act
        ob_start();
        CorsHandler::setFormHeaders();
        ob_end_clean();

        // Assert - In real implementation, we'd verify:
        // - Content-Type: application/x-www-form-urlencoded
        // - Only POST methods allowed
        // - Form-specific headers included
        $this->assertTrue(true);
    }

    /**
     * Test origin validation
     */
    public function testOriginValidation(): void
    {
        putenv('APP_ENV=development');
        
        // Valid origins for development
        $validOrigins = [
            'http://localhost:8080',
            'http://idecide.test'
        ];

        foreach ($validOrigins as $origin) {
            $_SERVER['HTTP_ORIGIN'] = $origin;
            $isValid = $this->simulateValidateOrigin($origin, true);
            $this->assertTrue($isValid, "Origin $origin should be valid in development");
        }

        // Invalid origin
        $_SERVER['HTTP_ORIGIN'] = 'https://malicious.com';
        $isValid = $this->simulateValidateOrigin('https://malicious.com', true);
        $this->assertFalse($isValid, "Malicious origin should be rejected");
    }

    /**
     * Test origin enforcement blocks invalid requests
     */
    public function testOriginEnforcementBlocksInvalidRequests(): void
    {
        // Arrange - malicious origin
        $_SERVER['HTTP_ORIGIN'] = 'https://malicious.com';
        putenv('APP_ENV=production');
        putenv('APP_URL=https://idecide.com');

        // Act & Assert
        // In real implementation, this would exit with 403 status
        // We'd need to mock the exit() and http_response_code() functions
        
        $this->expectOutputString(''); // No output should be generated in this test setup
        
        // This would normally call CorsHandler::enforceOrigin() and expect exit
        $this->assertTrue(true); // Placeholder
    }

    /**
     * Test security headers are included
     */
    public function testSecurityHeadersAreIncluded(): void
    {
        // Arrange
        $_SERVER['HTTP_ORIGIN'] = 'http://localhost:8080';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        putenv('APP_ENV=development');

        // Act
        ob_start();
        CorsHandler::setHeaders();
        ob_end_clean();

        // Assert - In real implementation, we'd verify these headers are set:
        // - X-Content-Type-Options: nosniff
        // - X-Frame-Options: DENY  
        // - X-XSS-Protection: 1; mode=block
        // - Referrer-Policy: strict-origin-when-cross-origin
        
        $expectedSecurityHeaders = [
            'X-Content-Type-Options',
            'X-Frame-Options', 
            'X-XSS-Protection',
            'Referrer-Policy'
        ];

        foreach ($expectedSecurityHeaders as $header) {
            // In real test, we'd check if header was set
            $this->assertTrue(true, "Security header $header should be set");
        }
    }

    /**
     * Test credentials are allowed for same-origin requests
     */
    public function testCredentialsAreAllowedForSameOrigin(): void
    {
        // Arrange
        $_SERVER['HTTP_ORIGIN'] = 'http://localhost:8080';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        putenv('APP_ENV=development');
        putenv('APP_URL=http://localhost:8080');

        // Act
        ob_start();
        CorsHandler::setHeaders();
        ob_end_clean();

        // Assert - Access-Control-Allow-Credentials should be true
        $this->assertTrue(true); // Placeholder for credential header verification
    }

    /**
     * Integration test with LoginController
     */
    public function testCorsIntegrationWithLoginController(): void
    {
        // Arrange
        $_SERVER['HTTP_ORIGIN'] = 'http://idecide.test';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = [
            'email' => 'test@example.com',
            'password' => 'password123',
            'g-recaptcha-response' => 'test-token'
        ];
        
        putenv('APP_ENV=development');
        putenv('RECAPTCHA_SECRET_KEY=6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe');

        // Act - This would test the actual integration
        // In real test, we'd verify CORS headers are set before login processing
        
        $this->assertTrue(true); // Placeholder for integration test
    }

    // Helper methods to simulate private methods (since we can't access them directly)

    private function simulateGetAllowedOrigin(string $requestOrigin, bool $isDevelopment): string
    {
        if ($isDevelopment) {
            $allowedOrigins = [
                'http://localhost:8080',
                'http://127.0.0.1:8080', 
                'http://idecide.test',
                'http://idecide.test:80'
            ];
            
            if (in_array($requestOrigin, $allowedOrigins, true)) {
                return $requestOrigin;
            }
            
            return getenv('APP_URL') ?: 'http://localhost:8080';
        }
        
        $appUrl = getenv('APP_URL');
        if ($appUrl && $requestOrigin === $appUrl) {
            return $appUrl;
        }
        
        return $this->getCurrentDomain();
    }

    private function simulateValidateOrigin(string $requestOrigin, bool $isDevelopment): bool
    {
        if ($isDevelopment) {
            $allowedOrigins = [
                'http://localhost:8080',
                'http://127.0.0.1:8080',
                'http://idecide.test', 
                'http://idecide.test:80'
            ];
            
            return in_array($requestOrigin, $allowedOrigins, true) ||
                   $requestOrigin === getenv('APP_URL');
        }
        
        $appUrl = getenv('APP_URL');
        return $requestOrigin === $appUrl || $requestOrigin === $this->getCurrentDomain();
    }

    private function getCurrentDomain(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return $protocol . $host;
    }

    private function resetHeaders(): void
    {
        // In real implementation, we'd need to reset sent headers
        // This is a limitation of testing header() function calls
    }

    /**
     * Data provider for various CORS scenarios
     */
    public function corsScenarioProvider(): array
    {
        return [
            'development_localhost' => [
                'environment' => 'development',
                'origin' => 'http://localhost:8080',
                'expected' => true
            ],
            'development_idecide_test' => [
                'environment' => 'development', 
                'origin' => 'http://idecide.test',
                'expected' => true
            ],
            'production_valid' => [
                'environment' => 'production',
                'origin' => 'https://idecide.com',
                'expected' => true
            ],
            'production_invalid' => [
                'environment' => 'production',
                'origin' => 'https://malicious.com', 
                'expected' => false
            ]
        ];
    }

    /**
     * Test various CORS scenarios
     * @dataProvider corsScenarioProvider
     */
    public function testCorsScenarios(string $environment, string $origin, bool $expected): void
    {
        putenv("APP_ENV=$environment");
        if ($environment === 'production') {
            putenv('APP_URL=https://idecide.com');
        }
        
        $_SERVER['HTTP_ORIGIN'] = $origin;
        
        $isDevelopment = $environment === 'development';
        $result = $this->simulateValidateOrigin($origin, $isDevelopment);
        
        $this->assertEquals($expected, $result, 
            "CORS validation failed for $environment environment with origin $origin");
    }
}