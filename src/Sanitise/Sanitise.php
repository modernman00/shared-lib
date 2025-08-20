<?php

declare(strict_types=1);

namespace Src\Sanitise;

use RuntimeException;
use Src\Exceptions\InvalidArgumentException;

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
            if (!isset($dataLength['data'], $dataLength['min'], $dataLength['max']) ||
                count($dataLength['data']) !== count($dataLength['min']) ||
                count($dataLength['data']) !== count($dataLength['max'])
            ) {
                throw new InvalidArgumentException('Invalid dataLength structure');
            }
        }

        // Remove non-essential fields
        unset($formData['submit']);
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
    public function validateCsrfToken(string $sessionToken): self
    {
        if (!isset($this->formData['token']) || !hash_equals($sessionToken, $this->formData['token'])) {
            throw new RuntimeException('Invalid or missing CSRF token');
        }
        unset($this->formData['token']);

        return $this;
    }

    /**
     * Validates email format.
     *
     * @return $this
     */
    private function validateEmail(): self
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
    private function validatePassword(): self
    {
        if (isset($this->formData['password'], $this->formData['confirm_password']) &&
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
    private function checkEmpty(): self
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
    private function checkLength(): self
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
    private function sanitizeData(): self
    {
        foreach ($this->formData as $key => $value) {
            if (!is_string($value)) {
                $this->cleanData[$key] = $value;
                continue;
            }
            if ($key === 'email') {
                $this->cleanData[$key] = filter_var($value, FILTER_SANITIZE_EMAIL);
            } else {
                $this->cleanData[$key] = htmlspecialchars(trim($value), ENT_QUOTES | ENT_HTML5, 'UTF-8');
            }
        }

        return $this;
    }

    /**
     * Hashes password if present.
     *
     * @return $this
     *
     * @throws RuntimeException If password hashing fails
     */
    private function hashPassword(): self
    {
        if (isset($this->cleanData['password'], $this->cleanData['confirm_password'])) {
            $hashed = password_hash($this->cleanData['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            if ($hashed === false) {
                throw new RuntimeException('Password hashing failed');
            }
            $this->cleanData['password'] = $hashed;
            unset($this->cleanData['confirm_password']);
        }

        return $this;
    }

    /**
     * Runs all validation and sanitization steps.
     *
     * @return $this
     */
    private function runValidation(): self
    {
        $this->validateEmail()
            ->validatePassword()
            ->checkEmpty()
            ->checkLength()
            ->sanitizeData()
            ->hashPassword();

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

        if (!empty($this->errors)) {
            throw new RuntimeException('Validation failed: ' . implode(', ', $this->errors));
        }

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
