<?php

declare(strict_types=1);

namespace Src;

use PDO;
use PDOException;

class Db extends CheckToken
{
    public const BR = '<br>'; // can't be changed

    private static $conn = null;

    private static function dbVariables(): array
    {
        return [
            'host' => $_ENV['DB_HOST'],
            'name' => $_ENV['DB_NAME'],
            'username' => $_ENV['DB_USERNAME'],
            'password' => $_ENV['DB_PASSWORD'],
            'charset' => 'utf8mb4',
        ];
    }

    public function connect()
    {
        // apply singleton pattern by checking if db connection is already established before connecting again
        $conn = null;
        try {
            if (!isset($conn)) {
                $dbVar = self::dbVariables();
                $conn = new PDO("mysql:host={$dbVar['host']}; dbname={$dbVar['name']}; charset={$dbVar['charset']}", username: $dbVar['username'], password: $dbVar['password'], options: [
                    PDO::ATTR_PERSISTENT => true,
                ]);

                $conn->setAttribute(attribute: PDO::ATTR_DEFAULT_FETCH_MODE, value: PDO::FETCH_ASSOC);
                $conn->setAttribute(attribute: PDO::ATTR_ERRMODE, value: PDO::ERRMODE_EXCEPTION);
                $conn->setAttribute(attribute: PDO::ATTR_EMULATE_PREPARES, value: false);

                return $conn;
            } else {
                return $conn;
            }
        } catch (PDOException $e) {
            Utility::showError($e);
        }
    }

    public static function connect2()
    {
        try {
            if (self::$conn === null) {
                $dbVar = self::dbVariables();
                self::$conn = new PDO("mysql:host={$dbVar['host']}; dbname={$dbVar['name']}; charset={$dbVar['charset']}", $dbVar['username'], $dbVar['password'], [
                    PDO::ATTR_PERSISTENT => true,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]);
            }

            return self::$conn;
        } catch (PDOException $e) {
            Utility::showError($e);

            return null;
        }
    }

    public function connectSql()
    {
        $dbVar2 = self::dbVariables();
        try {
            return mysqli_connect($dbVar2['host'], $dbVar2['username'], $dbVar2['password'], $dbVar2['name']);
        } catch (\Throwable $e) {
            Utility::showError($e);
        }
    }
}
