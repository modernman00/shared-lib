<?php

declare(strict_types=1);

namespace Src;

use PDO;
use PDOException;

class Db extends CheckToken
{
    public const BR = '<br>'; // can't be changed

    private static ?PDO $conn = null;

    private static ?PDO $mockConnection = null;

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

    /**
     * @throws PDOException if the connection cannot be established. Callers can
     * rely on always getting a usable PDO back rather than null-checking every
     * call site; a failed connection is unrecoverable for the request anyway and
     * is caught by the app's global exception handler (see ErrorHandler).
     */
    public function connect(): PDO
    {
        // apply singleton pattern by checking if db connection is already established before connecting again
        if (self::$mockConnection !== null) {
            return self::$mockConnection;
        }
        if (!isset(self::$conn)) {
            $dbVar = self::dbVariables();
            self::$conn = new PDO("mysql:host={$dbVar['host']}; dbname={$dbVar['name']}; charset={$dbVar['charset']}", username: $dbVar['username'], password: $dbVar['password'], options: [
                PDO::ATTR_PERSISTENT => true,
            ]);

            self::$conn->setAttribute(attribute: PDO::ATTR_DEFAULT_FETCH_MODE, value: PDO::FETCH_ASSOC);
            self::$conn->setAttribute(attribute: PDO::ATTR_ERRMODE, value: PDO::ERRMODE_EXCEPTION);
            self::$conn->setAttribute(attribute: PDO::ATTR_EMULATE_PREPARES, value: false);
        }

        return self::$conn;
    }

    /**
     * @throws PDOException if the connection cannot be established. See connect().
     */
    public static function connect2(): PDO
    {
        if (self::$mockConnection !== null) {
            return self::$mockConnection;
        }
        if (self::$conn === null) {
            $dbVar = self::dbVariables();
            self::$conn = new PDO("mysql:host={$dbVar['host']}; dbname={$dbVar['name']}; charset={$dbVar['charset']}", $dbVar['username'], $dbVar['password'], [
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        }

        return self::$conn;
    }

    /**
     * @throws \RuntimeException if the mysqli connection cannot be established.
     */
    public function connectSql(): \mysqli
    {
        $dbVar2 = self::dbVariables();
        $conn = mysqli_connect($dbVar2['host'], $dbVar2['username'], $dbVar2['password'], $dbVar2['name']);
        if ($conn === false) {
            throw new \RuntimeException('Failed to connect to MySQL: ' . mysqli_connect_error());
        }

        return $conn;
    }

    public static function setMockConnection(PDO $pdo): void
    {
        self::$mockConnection = $pdo;
    }

    public static function clearMockConnection(): void
    {
        self::$mockConnection = null;
    }
}
