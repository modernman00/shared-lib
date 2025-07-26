<?php

declare(strict_types=1);

namespace Src;

use PDOException;

class Delete extends Db
{
    public static function formAndMatchQuery(string $selection, string $table, ?string $identifier1 = null, ?string $identifier2 = null, ?string $column = null, ?string $limit = null): string
    {
        if (!Utility::onlyLettersNumbersUnderscore($selection)) {
            throw new \Src\Exceptions\ValidationException('selection not well formed');
        } elseif (!Utility::onlyLettersNumbersUnderscore($table)) {
            throw new \Src\Exceptions\ValidationException('table not well formed');
        } elseif ($identifier1 !== null && !Utility::onlyLettersNumbersUnderscore($identifier1)) {
            throw new \Src\Exceptions\ValidationException('identifier1 not well formed');
        } elseif ($identifier2 !== null && !Utility::onlyLettersNumbersUnderscore($identifier2)) {
            throw new \Src\Exceptions\ValidationException('identifier2 not well formed');
        } elseif ($column !== null && !Utility::onlyLettersNumbersUnderscore($column)) {
            throw new \Src\Exceptions\ValidationException('column not well formed');
        } elseif ($limit !== null && !Utility::onlyLettersNumbersUnderscore($limit)) {
            throw new \Src\Exceptions\ValidationException('limit not well formed');
        }

        return match ($selection) {
            'DELETE_OR' => "DELETE FROM $table WHERE $identifier1 =? OR $identifier2 = ? $limit",
            'DELETE_AND' => "DELETE FROM $table WHERE $identifier1 =? AND $identifier2 = ? $limit",
            'DELETE_ALL' => "DELETE FROM $table $limit",
            'DELETE_ONE' => "DELETE FROM $table WHERE $identifier1 = ? $limit",
            'DELETE_COL' => "DELETE $column FROM $table $limit",
            'DELETE_UPDATE' => "UPDATE $table SET status ='deleted' WHERE $identifier1 = ? LIMIT 1",
            default => null
        };
    }

    /**
     * Executes a DELETE query with the given parameters.
     *
     * @param string $query The DELETE query to execute
     * @param mixed[]|null $bind An array of parameter values to bind to the query
     *
     * @return bool Returns true if the query was executed successfully, false otherwise
     *
     * @throws PDOException if an error occurs during query execution
     */
    public static function deleteFn(string $query, ?array $bind = null): bool
    {
        try {
            $statement = parent::connect2()->prepare($query);
            $statement->execute($bind);

            return $statement->rowCount();
        } catch (PDOException $e) {
            Utility::showError($e);

            return false;
        }
    }
}
