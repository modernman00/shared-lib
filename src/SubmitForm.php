<?php

declare(strict_types=1);

namespace Src;

use Src\Exceptions\HttpException;
use PDO;
use PDOStatement;

/**
 * SubmitForm
 *
 * Securely inserts form data into a database table.
 * Assumes trust in the source of table names (consider escaping or mapping).
 */
class SubmitForm extends Db
{
    /**
     * Insert sanitized data into the specified table.
     *
     * @param string $table Table name — must be validated externally
     * @param array<string, scalar> $fields Associative array of column => value pairs
     *
     * @throws HttpException If insertion fails at any point
     * @return bool True on success
     */
    public static function submitForm(string $table, array $fields): bool
    {
        if (empty($table) || empty($fields)) {
            throw new HttpException('Table name and fields cannot be empty', 400);
        }

        try {
            $connection = self::connect2(); // Trusts that connect2() returns a valid PDO instance

            // Defensive: escape column names if table is dynamic (not shown here)
            $columns = implode(', ', array_keys($fields));
            $placeholders = implode(', :', array_keys($fields));

            $sql = "INSERT INTO {$table} ({$columns}) VALUES (:{$placeholders})";
            $stmt = $connection->prepare($sql);

            if (!$stmt instanceof PDOStatement) {
                throw new HttpException('Unable to prepare SQL statement', 500);
            }

            foreach ($fields as $key => $value) {
                if (!$stmt->bindValue(":{$key}", $value)) {
                    throw new HttpException("Binding failed for '{$key}'", 500);
                }
            }

            if (!$stmt->execute()) {
                throw new HttpException("Insert execution failed", 500);
            }

            return true;
        } catch (\PDOException $pdoEx) {
            // Log this internally, but avoid leaking stack traces
            Utility::showError($pdoEx);
            throw new HttpException('Database error occurred', 500);
        } catch (\Throwable $th) {
            Utility::showError($th);
            throw new HttpException('Unexpected error occurred', 500);
        }
    }
}
