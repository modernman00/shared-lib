<?php

namespace Src;

use \PDOException;
use Src\Db;
use Src\Utility;
use Src\Exceptions\NotFoundException;
use Src\Exceptions\BadRequestException;

/**
 * Class Update
 * Handles updating records in a database table.
 *
 * @package Src
 */

class Update extends Db
{

    public function __construct(public string $table) {}

    /**
     * Update a specific column in the table based on an identifier
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
            PHP_EOL;
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
            PHP_EOL;
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
                throw new BadRequestException("No data to update.");
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
}
