<?php

declare(strict_types=1);

namespace Src;

use PDOException;
use PDO;
use Src\Exceptions\BadRequestException;
use Src\Exceptions\DatabaseException;
use Src\Exceptions\NotFoundException;

/**
 * Class Update
 * Handles updating records in a database table.
 */
class Update extends Db
{
    public function __construct(public string $table)
    {
    }

    /**
     * Update a specific column in the table based on an identifier.
     *
     * @param string $column The column to update
     * @param int|string|array $columnAnswer The new value for the column
     * @param string $identifier The condition column
     * @param string $identifierAnswer The value to match in the identifier column
     *
     * @return bool True if the update was successful, false otherwise
     */
    public function updateTable(string $column, int|string|array $columnAnswer, string $identifier, int|string $identifierAnswer): bool
    {
        try {
            // Prepare the query with parameterized values
            $query = "UPDATE $this->table SET $column = :columnValue WHERE $identifier = :identifierValue";
            $stmt = parent::connect2()->prepare($query);

            // Bind parameters for security
            $stmt->bindParam(':columnValue', $columnAnswer);
            $stmt->bindParam(':identifierValue', $identifierAnswer);

            // Execute the statement and return the result as a bool
            return $stmt->execute();
        } catch (PDOException $e) {
            // Log or handle the exception as needed
            Utility::showError($e);

            return false;
        }
    }

    public function updateTableMulti(string $column, string $columnAnswer, array $identifiers)
    {
        try {
            // Build the SET clause for the column to update
            $setClause = "$column = ?";

            // Build the WHERE clause for each identifier
            $whereClause = '';
            $params = [$columnAnswer];

            foreach ($identifiers as $identifier => $value) {
                if ($whereClause !== '') {
                    $whereClause .= ' AND ';
                }
                $whereClause .= "$identifier = ?";
                $params[] = $value;
            }

            // Construct the full SQL query
            $query = "UPDATE $this->table SET $setClause WHERE $whereClause";

            $result = parent::connect2()->prepare($query);

            $result->execute($params);

            return $result;
        } catch (PDOException $e) {
            Utility::showError($e);
        }
    }

    public function updateTwoColumns(array $columns, array $columnAnswers, array $identifiers)
    {
        try {
            // Build the SET clause for the columns to update
            $setClause = implode(' = ?, ', $columns) . ' = ?';

            // Build the WHERE clause for each identifier
            $whereClause = '';
            $params = array_merge($columnAnswers, array_values($identifiers));

            foreach ($identifiers as $identifier => $value) {
                if ($whereClause !== '') {
                    $whereClause .= ' AND ';
                }
                $whereClause .= "$identifier = ?";
            }

            // Construct the full SQL query
            $query = "UPDATE $this->table SET $setClause WHERE $whereClause";

            $result = parent::connect2()->prepare($query);
            $result->execute($params);

            return $result;
        } catch (PDOException $e) {
            Utility::showError($e);
        }
    }

            /**
         * Makes an UPDATE query with the given data and identifiers.
         *
         * $data must contain the columns to update and their new values.
         * $identifiers must contain the columns to identify the rows to update.
         * The identifiers are combined using the given $logic operator (default: 'AND').
         *
         * @throws NotFoundException if any identifier does not exist in $data.
         * @throws BadRequestException if $data is empty after removing the identifiers.
         * @return bool True if the update was successful, false otherwise.
         */
    public function makeUpdate(array $data, $identifiers, string $logic = 'AND'): bool
    {
        try {
            if (isset($data['submit'])) {
                unset($data['submit']);
            }

            // Normalise logic operator
            $logic = strtoupper($logic) === 'OR' ? 'OR' : 'AND';

            // Ensure identifiers is an array
            $identifiers = is_array($identifiers) ? $identifiers : [$identifiers];

            // Ensure all identifiers exist in data
            foreach ($identifiers as $id) {
                if (!isset($data[$id])) {
                    throw new NotFoundException("Identifier '$id' does not exist in data.");
                }
            }

            // Extract and remove identifier values from data
            $idValues = [];
            foreach ($identifiers as $id) {
                $idValues[$id] = $data[$id];
                unset($data[$id]);
            }

            if (empty($data)) {
                throw new BadRequestException('No data to update.');
            }

            // Build SET clause
            $setClause = implode(' = ?, ', array_keys($data)) . ' = ?';

            // Build WHERE clause (supporting AND/OR)
            $whereParts = array_map(fn($id) => "$id = ?", $identifiers);
            $whereClause = implode(" $logic ", $whereParts);

            // Combine full SQL
            $sql = "UPDATE {$this->table} SET $setClause WHERE $whereClause";

            // Prepare statement
            $stmt = $this->connect()->prepare($sql);

            // Merge update + identifier values for binding
            $values = array_merge(array_values($data), array_values($idValues));

            return $stmt->execute($values);
        } catch (PDOException $e) {
            Utility::showError($e);
            return false;
        }
    }

    public function updateMultiplePOST(array $data, string $identifier): bool
    {
        try {
            if (isset($data['submit'])) {
                unset($data['submit']); // remove submit if present
            }

            // Get the value of the identifier (e.g., 'mobile' column)
            if (!isset($data[$identifier])) {
                throw new NotFoundException("Identifier '$identifier' does not exist in data.");
            }

            $idValue = $data[$identifier]; // Save value for WHERE clause
            unset($data[$identifier]);     // Remove from SET clause

            if (empty($data)) {
                throw new BadRequestException('No data to update.');
            }

            // Build SQL
            $fields = implode(' = ?, ', array_keys($data)) . ' = ?';
            $sql = "UPDATE {$this->table} SET $fields WHERE $identifier = ?";

            // Prepare statement
            $stmt = $this->connect()->prepare($sql);

            // Combine values to bind
            $values = array_values($data);
            $values[] = $idValue; // Last value for WHERE clause

            return $stmt->execute($values);
        } catch (PDOException $e) {
            Utility::showError($e);

            return false;
        }
    }

        public static function updateWithTimestamp($table, $likesColumn, $likesValue, $timestampColumn, $whereColumn, $whereValue)
    {
        $query = "UPDATE $table
        SET $likesColumn = :likesValue, $timestampColumn = CURRENT_TIMESTAMP
        WHERE $whereColumn = :whereValue
    ";
        $stmt = parent::connect2()->prepare($query);

        if (!$stmt) {
            throw new DatabaseException('Could not connect');
        }

        $stmt->bindParam(':likesValue', $likesValue, PDO::PARAM_INT);
        $stmt->bindParam(':whereValue', $whereValue, PDO::PARAM_INT);
        if (!$stmt->execute()) {
            throw new DatabaseException('Could not execute query');
        }
    }

    public function updateWhereRaw(string $setColumn, $setValue, string $where, array $params)
{
    $sql = "UPDATE {$this->table} SET {$setColumn} = ? {$where}";
    $stmt = parent::connect2()->prepare($sql);
    $stmt->execute(array_merge([$setValue], $params));
    return $stmt;
}

}
