#!/bin/bash

# Test runner script for shared-lib project
echo "===== Running Unit Tests for shared-lib ====="
echo ""

# Make sure we're in the right directory
cd "$(dirname "$0")"

# Check if vendor directory exists
if [ ! -d "vendor" ]; then
    echo "Installing dependencies..."
    composer install
fi

# Run PHPUnit tests
echo "Running PHPUnit tests..."
./vendor/bin/phpunit --configuration phpunit.xml

echo ""
echo "===== Test execution completed ====="
