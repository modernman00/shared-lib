<?php

declare(strict_types=1);

namespace Src;

use PDO;
use PDOStatement;
use Src\Exceptions\HttpException;
use Src\Exceptions\NotFoundException;

/**
 * SubmitForm.
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
     *
     * @return bool True on success
     */
    public static function submitForm(string $table, array $fields, ?PDO $pdo = null): string
    {
        if (empty($table) || empty($fields)) {
            throw new NotFoundException();
        }

        try {
            $connection = $pdo ?? Db::connect2(); // Trusts that connect2() returns a valid PDO instance

            // Defensive: escape column names if table is dynamic (not shown here)
            $columns = implode(', ', array_keys($fields));
            $placeholders = implode(', :', array_keys($fields));

            $sql = "INSERT INTO {$table} ({$columns}) VALUES (:{$placeholders})";
            $stmt = $connection->prepare($sql);

            if (!$stmt instanceof PDOStatement) {
                throw new HttpException('Unable to prepare SQL statement');
            }

            foreach ($fields as $key => $value) {
                if (!$stmt->bindValue(":{$key}", $value)) {
                    throw new HttpException("Binding failed for '{$key}'");
                }
            }

            if (!$stmt->execute()) {
                throw new HttpException('Insert execution failed');
            }

            $lastId = $connection->lastInsertId('no');
            $UPPER_TABLE = strtoupper($table);
            $_SESSION["LAST_INSERT_ID_$UPPER_TABLE"] = $lastId;

            return $lastId;
        } catch (\PDOException $pdoEx) {
            // Log this internally, but avoid leaking stack traces
            Utility::showError($pdoEx);
            throw new HttpException('Database error occurred');
        } catch (\Throwable $th) {
            Utility::showError($th);
            throw new HttpException('Unexpected error occurred');
        }
    }

    public static function submitFormDynamic($table, $field)
    {

        try {
            $DYNAMIC = strtoupper($table);

            // EXTRACT THE KEY FOR THE COL NAME
            $key = array_keys($field);
            $col = implode(', ', $key);
            $placeholder = implode(', :', $key);

            // prep statement using placeholder :name
            $stmt = "INSERT INTO $table ($col) VALUES (:$placeholder)";

            $query = parent::connect2()->prepare($stmt);
            if (!$query) {
                throw new \Exception("Not able to insert data", 1);
            }
            foreach ($field as $keys => $values) {
                $query->bindValue(":$keys", $values);
            }
            $outcome = $query->execute();
            if (!$outcome) {
                throw new \Exception("Unable to execute the query.", 1);
            }

            $lastId = parent::connect2()->lastInsertId();

            if (!$lastId) {
                throw new \Exception("Unable to connect to the database", 1);
            }

            $_SESSION["LAST_INSERT_ID_$DYNAMIC"] = $lastId;

            msgSuccess(200, $lastId);

            return $outcome;
        } catch (\PDOException $e) {
            showError($e);
        } catch (\Throwable $e) {
            showError($e);
        }
    }

        /**
     * 
     * @param mixed $table - database table
     * @param mixed $field - post array
     * @param mixed $lastIdCol the column you want to return the last id - e.g id, no, eventNo 
     * @return mixed 
     */

    public static function submitFormDynamicLastId($table, $field, $lastIdCol)
    {
        try {
            // Prepare the SQL statement
            $columns = implode(', ', array_keys($field));
            $placeholders = ':' . implode(', :', array_keys($field));
            $query = "INSERT INTO $table ($columns) VALUES ($placeholders)";

            $connection = parent::connect2();
            
            $stmt = $connection->prepare($query);


            foreach ($field as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }

            $outcome = $stmt->execute();

            if (!$outcome) {
                msgException(406, "Unable to execute the query.");
            }

            $lastInsertedId = $connection->lastInsertId($lastIdCol);

            $dynamicTable = strtoupper($table);
            $_SESSION["LAST_INSERT_ID_$dynamicTable"] = $lastInsertedId;

            // msgSuccess(200, $lastInsertedId);

            return $lastInsertedId;
        } catch (\PDOException $e) {
            showError($e);
            return $e;
        } catch (\Throwable $e) {
            showError($e);
            return $e;
        }
    }
}
