# Unit Tests for shared-lib

This directory contains comprehensive unit tests for the shared-lib project.

## Setup

1. Make sure PHPUnit is installed:
   ```bash
   composer install
   ```

2. Run all tests:
   ```bash
   ./vendor/bin/phpunit
   ```
   
   Or use the provided script:
   ```bash
   ./run_tests.sh
   ```

## Test Coverage

The following classes have unit tests:

### Core Classes
- **UtilityTest** - Tests for `Src\Utility` class
  - Date manipulation functions
  - IP address detection
  - Input sanitization
  - String utilities
  - Environment detection

- **DbTest** - Tests for `Src\Db` class
  - Database connection constants
  - Connection methods structure

- **JwtHandlerTest** - Tests for `Src\JwtHandler` class
  - JWT token encoding
  - Authentication flow structure
  - Token payload validation

### Exception Classes
- **ExceptionsTest** - Tests for all exception classes in `Src\Exceptions`
  - HttpException with status codes
  - All custom exception types
  - Exception message handling

### Security Classes
- **RecaptchaTest** - Tests for `Src\Recaptcha` class
  - CAPTCHA verification flow
  - Error handling for various failure scenarios
  - Configuration validation

### Authentication Classes
- **TokenTest** - Tests for `Src\Token` class
  - Token generation
  - Session management
  - Token uniqueness

- **LoginUtilityTest** - Tests for `Src\LoginUtility` class
  - Password verification structure
  - Token generation utilities
  - Method existence validation

### Utility Classes
- **NumberTwoWordsTest** - Tests for `Src\NumberTwoWords` class
  - Number to words conversion
  - Currency formatting
  - Multiple format options

### Validation Classes
- **ValidateTest** - Tests for `Src\Sanitise\Validate` class
  - Form validation logic
  - Error message generation
  - Password confirmation checking

- **SanitiseTest** - Tests for `Src\Sanitise\Sanitise` class
  - Input sanitization
  - Validation rules
  - Error handling
  - CSRF token validation
  - Password hashing

### Communication Classes
- **EmailTest** - Tests for `Src\Email` class
  - Email validation
  - Priority settings
  - Method chaining
  - Configuration validation

## Test Structure

Each test class follows these conventions:

1. **setUp()** and **tearDown()** methods for test isolation
2. **Descriptive test method names** explaining what is being tested
3. **Exception testing** where appropriate
4. **Mock objects** for external dependencies (when needed)
5. **Data providers** for testing multiple scenarios

## Running Specific Tests

Run a specific test class:
```bash
./vendor/bin/phpunit tests/UtilityTest.php
```

Run tests with coverage (if xdebug is installed):
```bash
./vendor/bin/phpunit --coverage-html coverage/
```

## Notes

Some tests are currently structural tests that verify:
- Method existence
- Class instantiation
- Basic functionality without external dependencies

For full integration testing, you would need to:
1. Set up test databases
2. Mock external API calls
3. Configure test email systems
4. Set up proper test environments

## Adding New Tests

When adding tests for new classes:

1. Create a new test file: `tests/ClassNameTest.php`
2. Extend `PHPUnit\Framework\TestCase`
3. Add proper namespace imports
4. Include setUp/tearDown methods if needed
5. Test public methods and edge cases
6. Add exception testing where appropriate

## Test Dependencies

The test suite requires:
- PHPUnit 10.x
- PHP 8.1+
- Composer autoloading
- Session support for session-related tests
