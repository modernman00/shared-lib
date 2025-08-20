<?php

declare(strict_types=1);

namespace Src;

use PDO;
use PDOException;

class Select extends Db
{
    /**
     * Generates a SQL query based on the selection type and parameters provided.
     *
     * @param string $selection The type of selection (e.g., 'SELECT_ALL', 'SELECT_ONE').
     * @param string $table the name of the table to query
     * @param string|null $identifier1 the first identifier for the query (optional)
     * @param string|null $identifier2 the second identifier for the query (optional)
     * @param mixed $identifier3 additional identifier for more complex queries (optional)
     * @param string|null $column the column to select (optional)
     * @param string|null $column2 a second column to select (optional)
     * @param string|null $orderBy the ORDER BY clause (optional)
     * @param string|null $limit the LIMIT clause (optional)
     * @param array|null $colArray an array of columns to select dynamically (optional)
     *
     * @return string|null the generated SQL query or null if no valid selection type is provided
     */
    public static function formAndMatchQuery(
        string $selection,
        string $table,
        ?string $identifier1 = null,
        ?string $identifier2 = null,
        ?string $identifier3 = null,
        ?string $column = null,
        ?string $column2 = null,
        ?string $orderBy = null,
        ?string $limit = null,
        ?array $colArray = null
    ): string|null {
        // for col dynamically -
        if ($colArray) {
            $implodeColArray = implode(separator: ', ', array: $colArray);
        }

        // validate or escape $table and $column

        if (!Utility::onlyLettersNumbersUnderscore($selection)) {
            throw new \Src\Exceptions\ValidationException('selection not well formed');
        } elseif (!Utility::onlyLettersNumbersUnderscore($table)) {
            throw new \Src\Exceptions\ValidationException('table not well formed');
        } elseif ($identifier1 !== null && !Utility::onlyLettersNumbersUnderscore($identifier1)) {
            throw new \Src\Exceptions\ValidationException('identifier1 not well formed');
        } elseif ($identifier2 !== null && !Utility::onlyLettersNumbersUnderscore($identifier2)) {
            throw new \Src\Exceptions\ValidationException('identifier2 not well formed');
        } elseif ($identifier3 !== null && !Utility::onlyLettersNumbersUnderscore($identifier3)) {
            throw new \Src\Exceptions\ValidationException('identifier3 not well formed');
        } elseif ($column !== null && !Utility::onlyLettersNumbersUnderscore($column)) {
            throw new \Src\Exceptions\ValidationException('column not well formed');
        } elseif ($column2 !== null && !Utility::onlyLettersNumbersUnderscore($column2)) {
            throw new \Src\Exceptions\ValidationException('column2 not well formed');
        } elseif ($orderBy !== null && !Utility::onlyLettersNumbersUnderscore($orderBy)) {
            throw new \Src\Exceptions\ValidationException('orderBy not well formed');
        } elseif ($limit !== null && !Utility::onlyLettersNumbersUnderscore($limit)) {
            throw new \Src\Exceptions\ValidationException('limit not well formed');
        }

        return match ($selection) {
            'SELECT_OR' => "SELECT * FROM $table WHERE $identifier1 =? OR $identifier2 = ? $orderBy $limit",
            'SELECT_AND' => "SELECT * FROM $table WHERE $identifier1 =? AND $identifier2 = ? $orderBy $limit",
            'SELECT_ALL3' => "SELECT * FROM $table WHERE $identifier1 =? AND $identifier2 = ? AND $identifier3 = ? $orderBy $limit",
            'SELECT_OR_AND' => "SELECT * FROM $table WHERE $identifier1 =? OR $identifier2 = ? AND $identifier3 = ? $orderBy $limit",
            'SELECT_NOT' => "SELECT * FROM $table WHERE $identifier1 !=? AND $identifier2 = ? $orderBy $limit",
            'SELECT_NOT_AND' => "SELECT * FROM $table WHERE $identifier1 !=? AND $identifier2 != ? $orderBy $limit",
            'SELECT_NOT_OR' => "SELECT * FROM $table WHERE $identifier1 !=? OR $identifier2 != ? $orderBy $limit",
            'SELECT_ALL' => "SELECT * FROM $table $orderBy $limit",
            'SELECT_ONE' => "SELECT * FROM $table WHERE $identifier1 = ? $orderBy $limit",
            'SELECT_COL' => "SELECT $column FROM $table $orderBy $limit",
            'SELECT_2COLS' => "SELECT $column, $column2 FROM $table $orderBy $limit",
            'SELECT_COL_ID' => "SELECT $column FROM $table WHERE $identifier1 = ? $orderBy $limit",
            'SELECT_TWO_COLS_ID' => "SELECT $column, $column2 FROM $table WHERE $identifier1 = ? $orderBy $limit",
            'SELECT_GREATER' => "SELECT * FROM $table WHERE $identifier1 > ? $orderBy $limit",
            'SELECT_GREATER_EQUAL' => "SELECT * FROM $table WHERE $identifier1 > ? OR $identifier2 = ? $orderBy $limit",
            'SELECT_COUNT_TWO' => "SELECT * FROM $table WHERE $identifier1 = ? AND $identifier2 = ?",
            'SELECT_COUNT_ONE' => "SELECT * FROM $table WHERE $identifier1 = ?",
            'SELECT_COUNT_ALL' => "SELECT * FROM $table",
            'SELECT_DISTINCT' => "SELECT DISTINCT $identifier1, $identifier2 FROM $table $orderBy $limit",
            'SELECT_AVERAGE' => "SELECT AVG($column) FROM $table WHERE $identifier1 = ?",
            'SELECT_AVERAGE_ALL' => "SELECT AVG($column) as total FROM $table",
            'SELECT_SUM_ALL' => "SELECT SUM($column) as total FROM $table",
            'SELECT_COL_DYNAMICALLY' => "SELECT $implodeColArray FROM $table",
            'SELECT_COL_DYNAMICALLY_ID' => "SELECT $implodeColArray FROM $table WHERE $identifier1 = ? $orderBy $limit",
            'SELECT_COL_DYNAMICALLY_ID_AND' => "SELECT $implodeColArray FROM $table WHERE $identifier1 = ? AND $identifier2 = ? $orderBy $limit",
            default => null
        };
    }

    /**
     * @param string $table
     * @param string $query SELECT * FROM account WHERE id = ? || SELECT * FROM $table WHERE $dev = ? AND $dev2 = ?
     * @param array|null $bind = ['woguns@ymail.com', "wale@loaneasyfinance.com"];
     */
    public function selectFn(string $query, ?array $bind = null): array|int|string
    {
        try {
            $sql = $query;
            $result = $this->connect()->prepare($sql);
            $result->execute($bind);

            return $result->fetchAll();
        } catch (PDOException $e) {
            Utility::showError($e);

            return [];
        }
    }

    public function selectFn1(string $query, ?array $bind = null): array|int|string
    {
        try {
            $sql = $query;
            $result = $this->connect()->prepare($sql);
            $result->execute($bind);

            return $result->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Utility::showError($e);

            return [];
        }
    }

    /**
     * @param string $query
     * @param array|null $bind
     *
     * @return string|array|int
     */
    public static function selectFn2(string $query, ?array $bind = null): string|array|int
    {
        try {
            $sql = $query;
            $result = self::connect2()->prepare($sql);
            $result->execute($bind);

            return $result->fetchAll();
        } catch (PDOException $e) {
            Utility::showError($e);

            return [];
        }
    }

    /**
     * Undocumented function.
     *
     * @param string $query - SELECT * FROM account WHERE id = ? || SELECT * FROM $table WHERE $dev = ? AND $dev2 = ?
     * @param array $bind = ['woguns@ymail.com', "wale@loaneasyfinance.com"];
     *
     * @return mixed
     */
    public function selectCountFn(string $query, ?array $bind = null): string|array|int
    {
        try {
            $sql = $query;
            $result = $this->connect()->prepare(query: $sql);
            $result->execute(params: $bind);

            return $result->rowCount();
        } catch (PDOException $e) {
            Utility::showError(th: $e);

            return [];
        }
    }

    /**
     * @param string $query
     * @param array|null $bind
     *
     * @return string|array|int
     */
    public static function selectCountFn2(string $query, ?array $bind = null): string|array|int
    {
        try {
            $sql = $query;
            $result = self::connect2()->prepare(query: $sql);
            $result->execute(params: $bind);

            return $result->rowCount();
        } catch (PDOException $e) {
            Utility::showError(th: $e);

            return [];
        }
    }

    /**
     * @param mixed $table
     *
     * @return mixed
     */
    public function selectCountAll($table): mixed
    {
        try {
            $query = "SELECT COUNT(*) FROM $table";

            return $this->connect()->query($query)->fetchColumn();
        } catch (PDOException $e) {
            Utility::showError(th: $e);

            return [];
        }
    }

    /**
     * @param array $array [selection => SELECT_ALL, table =>account, identifier1 =>id, identifier2(null), bind=>[$id]]
     * @param mixed $callback the Select function albeit in string example - selectCountFn, selectFn
     * @param string $switch to switch between ONE_IDENTIFIER or TWO_IDENTIFIERS
     *
     * @return mixed
     */
    public static function combineSelect(array $array, $callback, string $switch)
    {
        try {
            $query = match ($switch) {
                'ONE_IDENTIFIER_COLUMN' => self::formAndMatchQuery(selection: $array['selection'], table: $array['table'], column: $array['column']),

                'ONE_IDENTIFIER_COLUMN_ID' => self::formAndMatchQuery(selection: $array['selection'], table: $array['table'], column: $array['column'], identifier1: $array['identifier1']),

                'TWO_IDENTIFIER_COLUMN' => self::formAndMatchQuery(selection: $array['selection'], table: $array['table'], column: $array['column'], column2: $array['column2']),

                'ONE_IDENTIFIER' => self::formAndMatchQuery(selection: $array['selection'], table: $array['table'], identifier1: $array['identifier1']),

                'TWO_IDENTIFIERS' => self::formAndMatchQuery(selection: $array['selection'], table: $array['table'], identifier1: $array['identifier1'], identifier2: $array['identifier2']),
            };

            return self::$callback($query, $array['bind'] ?? null);
        } catch (\Throwable $th) {
            Utility::showError(th: $th);
        }
    }
}
