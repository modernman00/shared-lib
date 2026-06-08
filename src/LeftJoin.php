<?php

declare(strict_types=1);

namespace Src;

use PDO;
use PDOException;

/**
 * Class LeftJoin.
 *
 * A database utility for dynamically constructing and executing
 * LEFT JOIN queries between multiple tables.
 *
 * --- JOIN CHEAT SHEET FOR BEGINNERS ---
 * LEFT JOIN (Methods like: joinParamOr)
 *    - Outcome: "Give me EVERYTHING from the PRIMARY (first) table, and any matching data from the secondary tables."
 *    - Real-world Example: Get ALL users, regardless of whether they have placed an order. If they haven't ordered, they still show up but the order details will be empty/null.
 *
 * Usage Notes:
 * - All methods rely on well-formed table/column input. Consider sanitising user-supplied values via Utility::checkInput.
 * - Returns either a result set array or false if an exception occurs.
 * - Designed for use with PDO and procedural routing.
 */
class LeftJoin extends Db
{
    /**
     * Executes a LEFT JOIN query with an OR condition in the WHERE clause.
     * 
     * Use Case: Use this when you want ALL records from your primary table, 
     * plus any related data from other tables if it exists. The OR condition allows 
     * matching records based on two different possibilities (e.g. user is sender OR receiver).
     *
     * @param string $firstTable The primary table to select from.
     * @param string $para The column used for joining and for the WHERE condition.
     * @param array $table An array of table names to join with the primary table.
     * @param mixed $id The value to bind to both sides of the OR condition in the WHERE clause.
     * @return array|bool Returns an array of fetched records on success, or false on failure.
     */
    public static function joinParamOr(string $firstTable, string $para, array $table, mixed $id): array|bool
    {
        $firstTable = Utility::checkInput(data: $firstTable);

        try {
            $buildInnerJoinQuery = array_map(
                callback: fn($tab): string => "
                LEFT JOIN $tab ON $firstTable.$para = $tab.$para",
                array: $table
            );

            $innerQueryToString = join(
                separator: ' ',
                array: $buildInnerJoinQuery
            );

            $query = "SELECT * FROM $firstTable $innerQueryToString WHERE $firstTable.$para=? OR $table[0].$para = ?";

            $result = self::connect2()->prepare($query);

            $result->execute([$id, $id]);

            return $result->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Utility::showError(th: $e);

            return false;
        }
    }
}
