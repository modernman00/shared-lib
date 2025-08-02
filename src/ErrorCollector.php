<?php 

namespace Src;

class ErrorCollector
{
    private static array $errors = [];

    public static function capture(callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            self::$errors[] = $e->getMessage();
        }
    }

    public static function hasErrors(): bool
    {
        return !empty(self::$errors);
    }

    public static function respond(): void
    {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['errors' => self::$errors]);
    }
}
