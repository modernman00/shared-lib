<?php

declare(strict_types=1);

namespace Src;

use PDO;
use PDOException;

/**
 * Class InnerJoin.
 *
 * A database utility for dynamically constructing and executing
 * INNER JOIN queries between multiple tables.
 *
 * --- JOIN CHEAT SHEET FOR BEGINNERS ---
 * INNER JOIN (Methods like: joinParamSelect, joinParam, joinAll)
 *    - Outcome: "Only give me results where there is a match in BOTH tables."
 *    - Real-world Example: Get users who HAVE placed an order. If a user hasn't ordered, they are completely excluded from the result.
 * --------------------------------------
 *
 * Usage Notes:
 * - All methods rely on well-formed table/column input. Consider sanitising user-supplied values via Utility::checkInput.
 * - Returns either a result set array or false if an exception occurs.
 * - Designed for use with PDO and procedural routing.
 */
class InnerJoin extends Db
{

    /**
     * Executes an INNER JOIN query with specific conditions, custom select fields, order, and limit.
     * 
     * Use Case: Use this when you ONLY want records that match perfectly across all tables, 
     * AND you need fine-grained control over which specific columns to retrieve, how to sort them, and how many to get.
     *
     * @param string $para The column used for joining the tables (e.g., 'id').
     * @param string $paraWhere The column used in the WHERE clause on the primary table.
     * @param array $table An array of table names to join with the primary table.
     * @param mixed $bind The value to bind to the WHERE clause condition.
     * @param string $selectFields The specific columns to select (defaults to '*').
     * @param string|null $orderBy The column and direction to order by (e.g., 'created_at DESC').
     * @param int|null $limit The maximum number of records to return.
     * @return array|bool Returns an array of fetched records on success, or false on failure.
     */
    public static function joinParamSelect(
      
        string $para,
        string $paraWhere,
        array $table,
        mixed $bind = null,
        string $selectFields = '*',
        ?string $orderBy = null,
        ?int $limit = null
    ): array|bool {
               		$firstTable = array_shift($table);
        $firstTable = Utility::checkInput(data: $firstTable);

        try {
            $buildInnerJoinQuery = array_map(
                fn($tab): string => "INNER JOIN $tab ON $tab.$para = $firstTable.$para",
                $table
            );

            $innerQueryToString = join(' ', $buildInnerJoinQuery);

            $query = "SELECT $selectFields FROM $firstTable $innerQueryToString WHERE $firstTable.$paraWhere = ?";

            if ($orderBy !== null) {
                $query .= " ORDER BY $orderBy";
            }

            if ($limit !== null) {
                $query .= " LIMIT $limit";
            }

            $pdo = self::connect2();
            $pdo->exec("SET SQL_BIG_SELECTS=1");
            $result = $pdo->prepare($query);
            $result->execute([$bind]);
            return $result->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            Utility::showError($e);
            return false;
        }
    }


    /**
     * Executes an INNER JOIN query using instance connection with a single WHERE condition.
     * 
     * Use Case: Use this when you want strict matches ONLY (records must exist in all joined tables) 
     * and you are filtering the results based on a specific column value (e.g., where 'status' = 'active').
     *
     * @param string $para The column used for joining the tables.
     * @param string $paraWhere The column used in the WHERE clause on the primary table.
     * @param array $table An array of table names to join with the primary table.
     * @param mixed $bind The value to bind to the WHERE clause condition.
     * @return array|bool Returns an array of fetched records on success, or false on failure.
     */
    public function joinParam(string $para, string $paraWhere, array $table, mixed $bind): array|bool
    {
        		$firstTable = array_shift($table);
        $firstTable = Utility::checkInput(data: $firstTable);

        try {
            $buildInnerJoinQuery = array_map(
                callback: fn($tab): string => "
                INNER JOIN $tab ON $firstTable.$para = $tab.$para ",
                array: $table
            );

            $innerQueryToString = join(
                separator: ' ',
                array: $buildInnerJoinQuery
            );

            $query2 = "SELECT * FROM $firstTable $innerQueryToString 
            WHERE $firstTable.$paraWhere = ?";

            $result = $this->connect()->prepare($query2);

            $result->execute([$bind]);

            return $result->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            Utility::showError($e);

            return false;
        }
    }

    /**
     * Executes an INNER JOIN query across multiple tables and orders the results.
     * Uses instance connection.
     * 
     * Use Case: Use this when you want a strict match across tables (e.g. get all products that have categories) 
     * and you want the results ordered by a specific column without any WHERE filtering.
     *
     * @param string $firstTable The primary table to select from.
     * @param string $para The column used for joining the tables.
     * @param array $table An array of table names to join with the primary table.
     * @param string $orderBy The column to order the results by in DESCENDING order.
     * @return mixed Returns an array of fetched records on success, or false on failure.
     */
    public function joinAll(string $para, array $table, string $orderBy): mixed
    {
             		$firstTable = array_shift($table);
        $firstTable = Utility::checkInput(data: $firstTable);
        try {
            $buildInnerJoinQuery = array_map(
                callback: fn($tab): string => " INNER JOIN $tab ON $firstTable.$para = $tab.$para",
                array: $table
            );

            $innerQueryToString = join(
                separator: ' ',
                array: $buildInnerJoinQuery
            );
            $query2 = "SELECT * FROM $firstTable  $innerQueryToString ORDER BY $orderBy  DESC";
            $result = $this->connect()->prepare($query2);
            $result->execute();

            return $result->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Utility::showError(th: $e);

            return false;
        }
    }

    /**
     * Executes an INNER JOIN query across multiple tables and orders the results.
     * Uses static database connection.
     * 
     * Use Case: Identical to joinAll(), but uses a static connection (self::connect2()). 
     * Use this when you are calling the method statically like InnerJoin::joinAll2(...).
     *
     * @param string $firstTable The primary table to select from.
     * @param string $para The column used for joining the tables.
     * @param array $table An array of table names to join with the primary table.
     * @param string $orderBy The column to order the results by in DESCENDING order.
     * @return mixed Returns an array of fetched records on success, or false on failure.
     */
    public static function joinAll2(string $para, array $table, string $orderBy): mixed
    {
              		$firstTable = array_shift($table);
        $firstTable = Utility::checkInput(data: $firstTable);
        try {
            $buildInnerJoinQuery = array_map(fn($tab) => " INNER JOIN $tab ON $firstTable.$para = $tab.$para ", $table);

            $innerQueryToString = join(' ', $buildInnerJoinQuery);

            $query2 = "SELECT * FROM $firstTable  $innerQueryToString ORDER BY $orderBy  DESC";
            $result = self::connect2()->prepare($query2);
            $result->execute();

            return $result->fetchAll();
        } catch (PDOException $e) {
            Utility::showError(th: $e);

            return false;
        }
    }


    /**
     * Executes an INNER JOIN query, fetches records as objects, and outputs them as JSON.
     * Uses static database connection. Useful for API responses.
     * 
     * Use Case: Use this when building an API endpoint where you need to return strict-matched data 
     * directly as a JSON response to the client/browser.
     *
     * @param string $firstTable The primary table to select from.
     * @param string $para The column used for joining the tables.
     * @param array $table An array of table names to join with the primary table.
     * @param string $orderBy The column to order the results by in DESCENDING order.
     * @return void Outputs the JSON string directly.
     */
    public static function joinAll3(string $para, array $table, string $orderBy): void
    {
        $firstTable = array_shift($table);
        $firstTable = Utility::checkInput(data: $firstTable);

        try {
            $buildInnerJoinQuery = array_map(fn($tab) => " INNER JOIN $tab ON $firstTable.$para = $tab.$para ", $table);
            $innerQueryToString = join(' ', $buildInnerJoinQuery);
            $query2 = "SELECT * FROM $firstTable  $innerQueryToString ORDER BY $orderBy  DESC";
            $result = self::connect2()->prepare($query2);
            $result->execute();
            $jsResult = $result->fetchAll(PDO::FETCH_OBJ);
            echo json_encode($jsResult, JSON_PRETTY_PRINT);
        } catch (PDOException $e) {
            Utility::showError(th: $e);
        }
    }

    /**
     * Executes an INNER JOIN query with an AND condition in the WHERE clause.
     * Uses instance connection.
     * 
     * Use Case: Use this when you want strict matches ONLY, and your filter condition 
     * requires a value to match simultaneously on two different columns/tables (AND condition).
     *
     * @param string $firstTable The primary table to select from.
     * @param string $para The column used for joining and the first part of the AND condition.
     * @param array $table An array of table names to join with the primary table.
     * @param mixed $id The value to bind to both sides of the AND condition in the WHERE clause.
     * @return mixed Returns an array of fetched records on success, or false on failure.
     */
    public function joinParamAnd(string $para, array $table, mixed $id): mixed
    {
        $firstTable = array_shift($table);
        $firstTable = Utility::checkInput(data: $firstTable);
        try {
            $buildInnerJoinQuery = array_map(fn($tab) => " INNER JOIN $tab ON $firstTable.$para = $tab.$para ", $table);
            $innerQueryToString = join(' ', $buildInnerJoinQuery);
            $query2 = "SELECT * FROM $firstTable  $innerQueryToString WHERE $firstTable.$para = ? AND $table[0].$para = ?";
            $result = $this->connect()->prepare($query2);
            $result->execute([$id, $id]);

            return $result->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Utility::showError(th: $e);

            return false;
        }
    }
}
