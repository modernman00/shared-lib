<?php

declare(strict_types=1);

namespace Src\Sanitise;

use RuntimeException;
use Src\Exceptions\InvalidArgumentException;
use Src\Exceptions\UnauthorisedException;

/**
 * Sanitise.
 *
 * Validates and sanitizes form data with optional length constraints.
 *
 * Notes:
 * - Designed for modular use across various forms.
 * - Handles CSRF token validation, email format, password matching, and input sanitization.
 * - Throws exceptions for validation errors.
 */
class Sanitise
{
    public array $errors = [];

    public array $cleanData = [];

    /**
     * @param array<string, mixed> $formData Input data to sanitize and validate
     * @param array{data: string[], min: int[], max: int[]}|null $dataLength Optional length constraints
     *
     * @throws InvalidArgumentException If formData is empty or dataLength is malformed
     */
    public function __construct(
        private array $formData,
        private ?array $dataLength = null
    ) {
        if (empty($formData)) {
            throw new InvalidArgumentException('Form data cannot be empty');
        }

        // Validate dataLength structure
        if ($dataLength !== null) {
            if (
                !isset($dataLength['data'], $dataLength['min'], $dataLength['max']) ||
                count($dataLength['data']) !== count($dataLength['min']) ||
                count($dataLength['data']) !== count($dataLength['max'])
            ) {
                throw new InvalidArgumentException('Invalid dataLength structure');
            }
        }

        // Remove non-essential fields
        unset($this->formData['submit']);
    }

    /**
     * Validates CSRF token against session token.
     *
     * @param string $sessionToken The expected CSRF token
     *
     * @return $this
     *
     * @throws RuntimeException If CSRF token is invalid or missing
     */
     protected function validateCsrfToken(): self
    {
        if (isset($this->formData['token'])) {

            $sessionToken = $_SESSION['token'];
            $postToken = $this->formData['token'];
              $headerToken = $_SERVER['HTTP_X_XSRF_TOKEN'] ?? $_COOKIE['XSRF-TOKEN'] ?? null;

            $valid = false;
            if ($sessionToken && hash_equals($sessionToken, $headerToken)) {
                $valid = true;
            } elseif ($sessionToken && hash_equals($sessionToken, $postToken)) {
                $valid = true;
            }

            if (!$valid) {
                throw new UnauthorisedException('We are not familiar with the nature of your activities.');
            }
     
        }


        return $this;
    }

    /**
     * Validates email format.
     *
     * @return $this
     */
    protected function validateEmail(): self
    {
        if (isset($this->formData['email']) && !filter_var($this->formData['email'], FILTER_VALIDATE_EMAIL)) {
            $this->errors[] = 'Invalid email format';
        }

        return $this;
    }

    /**
     * Validates password and confirm_password match.
     *
     * @return $this
     */
    protected function validatePassword(): self
    {
        if (
            isset($this->formData['password'], $this->formData['confirm_password']) &&
            $this->formData['password'] !== $this->formData['confirm_password']
        ) {
            $this->errors[] = 'Passwords do not match';
        }

        return $this;
    }

    /**
     * Checks for empty required fields.
     *
     * @return $this
     */
    protected function checkEmpty(): self
    {
        // if key is submit  skip it
        if (isset($this->formData['submit'])) {
            unset($this->formData['submit']);
        }
        foreach ($this->formData as $key => $value) {
            if (is_string($value) && ($value === '' || $value === 'select')) {
                $cleanKey = strtoupper(preg_replace('/[^A-Za-z0-9]/', ' ', $key));
                $this->errors[] = "The $cleanKey field is required";
            }
        }

        return $this;
    }

    /**
     * Validates input lengths against constraints.
     *
     * @return $this
     */
    protected function checkLength(): self
    {
        if ($this->dataLength) {
            foreach ($this->dataLength['data'] as $index => $key) {
                if (!isset($this->formData[$key])) {
                    continue;
                }
                $value = $this->formData[$key];
                if (!is_string($value)) {
                    $this->errors[] = "Field $key must be a string";
                    continue;
                }
                $cleanKey = strtoupper(preg_replace('/[^A-Za-z0-9]/', ' ', $key));
                if (strlen($value) < $this->dataLength['min'][$index]) {
                    $this->errors[] = "Field $cleanKey is below minimum length";
                } elseif (strlen($value) > $this->dataLength['max'][$index]) {
                    $this->errors[] = "Field $cleanKey exceeds maximum length";
                }
            }
        }

        return $this;
    }

    /**
     * Sanitizes form data based on field type.
     *
     * @return $this
     */
    protected function sanitizeData(): self
    {
        foreach ($this->formData as $key => $value) {

            // if (!is_string($value)) {
            //     $this->cleanData[$key] = $value;
            //     continue;
            // }

                $this->cleanData[$key] = \checkInput($value);
            
        }

        return $this;
    }


    /**
     * Runs all validation and sanitization steps.
     *
     * @return $this
     */
    protected function runValidation(): self
    {
        $this->validateCsrfToken()
            ->validateEmail()
            ->validatePassword()
            ->checkEmpty()
            ->checkLength()
            ->sanitizeData();

        return $this;
    }

    /**
     * Returns sanitized data if no errors occurred.
     *
     * @return array<string, mixed>
     *
     * @throws RuntimeException If validation errors occurred
     */
    public function getCleanData(): array
    {

        $this->runValidation();

        return $this->cleanData;
    }

    /**
     * Returns validation errors.
     *
     * @return array<string>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
