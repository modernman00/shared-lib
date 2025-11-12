<?php

declare(strict_types=1);

namespace Src;

use PDO;
use PDOException;

/**
 * Class InnerJoin.
 *
 * A database utility for dynamically constructing and executing
 * INNER, LEFT, and RIGHT JOIN queries between multiple tables.
 *
 * Usage Notes:
 * - All methods rely on well-formed table/column input. Consider sanitising user-supplied values via Utility::checkInput.
 * - Returns either a result set array or false if an exception occurs.
 * - Designed for use with PDO and procedural routing.
 */
class InnerJoin extends Db
{


    /**
     * Makes an INNER JOIN query with the given parameters.
 
     * @param string $firstTable 'comment_reactions'
     * @param string $para 'comment_id'
     * @param string $paraWhere 'comment_no'
     * @param array $table ['comments', 'users']
     * @param mixed $bind 'comment_id'
     * @param string $selectFields 'users.id, users.fullName',
     * @param string $orderBy 'comment_reactions.updated_at DESC',
     * @param int $limit 1
     * @return array|bool 
     * @throws \Src\Exceptions\ValidationException 
     * @throws \InvalidArgumentException 
     * @throws \Psr\Log\InvalidArgumentException 
     */
    public static function joinParamSelect(
        string $firstTable,
        string $para,
        string $paraWhere,
        array $table,
        mixed $bind = null,
        string $selectFields = '*',
        ?string $orderBy = null,
        ?int $limit = null
    ): array|bool {
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

            $result = self::connect2()->prepare($query);
            $result->execute([$bind]);
            return $result->fetchAll(PDO::FETCH_ASSOC);

        } catch (PDOException $e) {
            Utility::showError($e);
            return false;
        }
    }

    /**
     * @param string $firstTable the first table in the array
     * @param string $para the id parameter
     * @param array $table table name
     * @param mixed $id id
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

    /**
     * @param string $firstTable the first table in the array
     * @param string $para the id parameter
     * @param array $table table names `(do not include the first table)
     * @param string $paraWhere - the para to use with the WHERE keyword
     * @param mixed $bind bind variable
     */
    public function joinParam(string $firstTable, string $para, string $paraWhere, array $table, mixed $bind): array|bool
    {
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

    public function joinAll(string $firstTable, string $para, array $table, string $orderBy): mixed
    {
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
     * firstTable -> the first table in the array
     * para - id
     * table -> the array of db tables
     * orderBy -> the input you want to order it by - date, age etc.
     */
    public static function joinAll2(string $firstTable, string $para, array $table, string $orderBy): mixed
    {
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

    public static function joinAll4(string $firstTable, string $para, array $table, string $orderBy): mixed
    {
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

    public static function joinAll3(string $firstTable, string $para, array $table, string $orderBy): void
    {
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

    public function joinParamAnd(string $firstTable, string $para, array $table, mixed $id): mixed
    {
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
