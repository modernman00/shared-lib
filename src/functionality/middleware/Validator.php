<?php

namespace Src\functionality\middleware;

use InvalidArgumentException;


class Validator
{
    public static function requireKeys(array $input, array $required): void
    {
        foreach ($required as $key) {
            if (!isset($input[$key]) || trim($input[$key]) === '') {
                throw new InvalidArgumentException("Missing required field: {$key}");
            }
        }
    }

    public static function validateEmail(string $email): void
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException("Invalid email format");
        }
    }

    public static function validatePassword(string $password): void
    {
        if (strlen($password) < 6) {
            throw new InvalidArgumentException("Password must be at least 6 characters");
        }
    }
}
