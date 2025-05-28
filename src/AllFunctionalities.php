<?php

namespace App\shared;

use PDOException;
use App\shared\Db;
use App\shared\Exceptions\HttpException;
use PDO;

class AllFunctionalities extends Db
{

    /**
     * @psalm-param 'email'|'id' $identifier
     */
    public function update(string $table, string $column, string $column_ans, string $identifier, string $identifier_ans)
    {
        try {
            $query = "UPDATE $table SET $column =? WHERE $identifier = ?";
            $result = $this->connect()->prepare($query);
            return $result->execute([$column_ans, $identifier_ans]);
        } catch (PDOException $e) {
            Utility::showError($e);
        }
    }


    /**
     * @psalm-param 'events'|'post' $table
     * @psalm-param 'eventDate'|'post_likes' $column
     * @psalm-param 'no'|'post_no' $identifier
     */
    public static function update2(string $table, string $column, $column_ans, string $identifier, string $identifier_ans)
    {
        try {
            $query = "UPDATE $table SET $column =? WHERE $identifier = ?";
            $result = parent::connect2()->prepare($query);
            return $result->execute([$column_ans, $identifier_ans]);
        } catch (PDOException $e) {
            Utility::showError($e);
        }
    }



    // UPDATE MULTIPLE PARAMETER DYNAMICALLY

    /**
     * Undocumented function
     *
     * @param array $data - the array from the $_POST
     * @param string $table
     * @param [type] $identifier this is either id or email or username
     *
     * @psalm-param 'id' $identifier
     */
    public function updateMultiplePOST(array $data, string $table, string $identifier): bool
    {
        try {
            if (isset($data['submit'])) {
                unset($data['submit']); // remove submit if present
            }
            $implodeValue = array_values($data);
            $id = $data[$identifier]; // store $data['id]
            unset($data[$identifier]); // unset id
            $implodeKey = implode('=?, ', array_keys($data));

            $data[$identifier] = $id;


            $sql = "UPDATE $table SET $implodeKey=? WHERE $identifier =?";
            // example - 'UPDATE register SET title=?, first_name=?, second_name=? WHERE id =?'
            $stmt = $this->connect()->prepare($sql);
            return $stmt->execute($implodeValue);
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
            throw new HttpException("Could not connect");
        }

        $stmt->bindParam(':likesValue', $likesValue, PDO::PARAM_INT);
        $stmt->bindParam(':whereValue', $whereValue, PDO::PARAM_INT);
        if (!$stmt->execute()) {
            throw new HttpException("Could not execute query");
        };
    }
}
