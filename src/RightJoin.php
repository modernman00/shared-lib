<?php

declare(strict_types=1);

namespace Src;

use PDO;
use PDOException;

/**
 * Class RightJoin.
 *
 * A database utility for dynamically constructing and executing
 * RIGHT JOIN queries between multiple tables.
 *
 * --- JOIN CHEAT SHEET FOR BEGINNERS ---
 * RIGHT JOIN (Methods like: joinAll4)
 *    - Outcome: "Give me EVERYTHING from the SECONDARY tables, and any matching data from the primary (first) table."
 *    - Real-world Example: Get ALL orders, even if the user who placed them was somehow deleted. The order still shows up, but the user details will be empty/null.
 *
 * Usage Notes:
 * - All methods rely on well-formed table/column input. Consider sanitising user-supplied values via Utility::checkInput.
 * - Returns either a result set array or false if an exception occurs.
 * - Designed for use with PDO and procedural routing.
 */
class RightJoin extends Db
{
    /**
     * Executes a RIGHT JOIN query across multiple tables and orders the results.
     * Uses static database connection.
     * 
     * Use Case: Use this when you want ALL records from the secondary tables provided in the array, 
     * plus matching records from the primary table. It's the reverse of a LEFT JOIN.
     *
     * @param string $firstTable The primary table to select from.
     * @param string $para The column used for joining the tables.
     * @param array $table An array of table names to right join with the primary table.
     * @param string $orderBy The column to order the results by in DESCENDING order.
     * @return mixed Returns an array of fetched records on success, or false on failure.
     */
    public static function joinAll4(string $para, array $table, string $orderBy): mixed
    {
               		$firstTable = array_shift($table);
        $firstTable = Utility::checkInput(data: $firstTable);

        try {
            $buildInnerJoinQuery = array_map(fn($tab) => " RIGHT JOIN $tab ON $firstTable.$para = $tab.$para ", $table);
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
}
