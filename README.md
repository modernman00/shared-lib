# Shared Library

[![CI/CD Pipeline](https://github.com/modernman00/shared-lib/actions/workflows/ci.yml/badge.svg)](https://github.com/modernman00/shared-lib/actions/workflows/ci.yml)
[![PHP Version](https://img.shields.io/badge/PHP-8.1%20%7C%208.2%20%7C%208.3-blue)](https://www.php.net/)
[![License](https://img.shields.io/badge/license-MIT-green)](LICENSE)
[![Latest Version](https://img.shields.io/github/v/release/modernman00/shared-lib)](https://github.com/modernman00/shared-lib/releases)

A comprehensive PHP shared library containing common functions and classes for use across multiple projects.

## Features

- **Authentication & Security**: JWT handling, secure sessions, token management
- **Database Operations**: PDO utilities, query builders, database helpers
- **Form Building**: Bootstrap and Bulma form builders
- **File Management**: Upload handling, image optimization, virus scanning
- **Email Services**: PHPMailer integration, email templates
- **Validation & Sanitization**: Input validation, data sanitization
- **Rate Limiting**: Request throttling and rate limiting
- **Logging**: Monolog integration for structured logging
- **Exception Handling**: Custom exception classes

## Installation

Install via Composer:

```bash
composer require waleolaogun/shared-lib
```

## Requirements

- PHP 8.1 or higher
- Composer for dependency management

## Usage

### Basic Example

```php
<?php

require_once 'vendor/autoload.php';

use Src\Auth;
use Src\Db;
use Src\Utility;

// Initialize database connection
$db = new Db();

// Use authentication
$auth = new Auth($db);

// Use utility functions
$sanitized = Utility::sanitizeInput($_POST['data']);
```

### Available Classes

- `Src\Auth` - Authentication and authorization
- `Src\Db` - Database operations and utilities
- `Src\Email` - Email sending capabilities
- `Src\FileUploader` - File upload handling
- `Src\JwtHandler` - JWT token management
- `Src\Limiter` - Rate limiting functionality
- `Src\Utility` - General utility functions
- And many more...

## Development

### Setting Up Development Environment

1. Clone the repository:
   ```bash
   git clone https://github.com/modernman00/shared-lib.git
   cd shared-lib
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Run code quality checks:
   ```bash
   # PHP CS Fixer
   vendor/bin/php-cs-fixer fix --dry-run --diff

   # PHPStan static analysis
   vendor/bin/phpstan analyse
   ```

### Code Quality Standards

This project follows PSR-12 coding standards and uses:

- **PHP CS Fixer** for code formatting
- **PHPStan** for static analysis (Level 6)
- **Composer audit** for security vulnerability scanning

### CI/CD Pipeline

The project uses GitHub Actions for continuous integration and deployment:

- **Automated Testing**: Runs on PHP 8.1, 8.2, and 8.3
- **Code Quality Checks**: PHP CS Fixer and PHPStan analysis
- **Security Scanning**: Dependency vulnerability checks
- **Automated Dependency Updates**: Dependabot integration
- **Release Management**: Automated release asset creation

### Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/amazing-feature`
3. Make your changes and ensure they pass all quality checks
4. Commit your changes: `git commit -m 'Add amazing feature'`
5. Push to the branch: `git push origin feature/amazing-feature`
6. Open a Pull Request

All pull requests are automatically tested against our CI pipeline.

### Running Tests Locally

```bash
# Install development dependencies
composer install --dev

# Run PHP CS Fixer
vendor/bin/php-cs-fixer fix --dry-run --diff --verbose

# Run static analysis
vendor/bin/phpstan analyse --memory-limit=1G

# Check for security vulnerabilities
composer audit
```

## Versioning

This project uses [Semantic Versioning](https://semver.org/). For available versions, see the [releases page](https://github.com/modernman00/shared-lib/releases).

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## Support

For support, please open an issue on the [GitHub repository](https://github.com/modernman00/shared-lib/issues).

## Changelog

For a detailed changelog, see [RELEASES](https://github.com/modernman00/shared-lib/releases).
