<?php

namespace Src;

use Src\Update;

class UpdateFn extends Update
{


  public static function updateMultiple(string $table, array $data, string $identifier): bool
  {
    $update = new Update($table);
    return    $update->updateMultiplePOST($data, $identifier);
  }

  // update multiple tables 
  public static function updateMultipleTables(array $TableAndData, array $allowedTables, string $identifier, string $identifierValue): void
  {

    foreach($TableAndData as $table => $data){
      if(in_array($table, $allowedTables)){
        $update = new Update($table);
        $data[$identifier] = $identifierValue;
        $update->updateMultiplePOST($data, $identifier);
      }
    }
  } 

/**
 * Make an update query with data and one or more identifiers.
 *
 * This function dynamically builds an SQL UPDATE statement
 * with support for single or multiple identifiers joined by
 * logical operators (AND / OR).
 *
 * Automatically removes the 'submit' key if present and 
 * validates that all identifiers exist in the data array.
 *
 * @param array $data The associative array of data to update. Must include identifier keys.
 * @param string|array $identifiers One or more column names to identify the record(s) (e.g. 'email' or ['email', 'mobile']).
 * @param string $logic Optional. Logical operator between identifiers (AND or OR). Default is 'AND'.
 *
 * @return bool True on success, False on failure.
 *
 * @example 
 * // ✅ Example 1: Single identifier
 * $data = [
 *     'email' => 'user@example.com', // identifier
 *     'first_name' => 'John',
 *     'last_name' => 'Doe',
 *     'age' => 33,
 *     'submit' => 'Update' // optional; will be removed automatically
 * ];
 * $model->makeUpdateFn($data, 'email');
 *
 * // ✅ Example 2: Multiple identifiers (AND)
 * $data = [
 *     'email' => 'user@example.com',
 *     'mobile' => '07123456789',
 *     'first_name' => 'John',
 *     'last_name' => 'Doe',
 *     'status' => 'active'
 * ];
 * $model->makeUpdateFn($data, ['email', 'mobile'], 'AND');
 *
 * // ✅ Example 3: Multiple identifiers (OR)
 * $data = [
 *     'email' => 'user@example.com',
 *     'mobile' => '07123456789',
 *     'status' => 'inactive'
 * ];
 * $model->makeUpdateFn($data, ['email', 'mobile'], 'OR');
 */

  public static function makeUpdateFn(string $table, array $data, string|array $identifier, ?string $logic): bool
  {
    $update = new Update($table);
    return    $update->makeUpdate($data, $identifier, $logic);
  }
}
