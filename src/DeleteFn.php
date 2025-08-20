<?php

namespace Src;

class DeleteFn extends Delete
{

  /**
   * Deletes rows from a table where a single identifier matches a value.
   *
   * Use this for targeted deletions, such as removing a single record by ID or unique key.
   * This method is designed to be explicit, preventing accidental mass deletions.
   *
   * @param string $table The table to delete from.
   * @param string $identifier The column name used to identify the row(s) to delete.
   * @param string|int $colunAnswer The value to match in the identifier column.
   * @return int The number of rows affected by the deletion.
   */
  public static function deleteOneRow(string $table, string $column, string|int $columnAnswer): int
  {
    $query = parent::formAndMatchQuery(
      selection: 'DELETE_ONE',
      table: $table,
      identifier1: $column
    );

    return parent::deleteFn(query: $query, bind: [$columnAnswer]);
  }

  /**
   * Deletes rows where both identifiers match.
   *
   * Ideal for compound key deletes, ensuring deletions are tightly scoped.
   */
  public static function deleteByTwoRowsAnd(
    string $table,
    string $identifier1,
    string $identifier2,
    string|int $value1,
    string|int $value2
  ): int {
    $query = parent::formAndMatchQuery(
      selection: 'DELETE_AND',
      table: $table,
      identifier1: $identifier1,
      identifier2: $identifier2
    );

    return parent::deleteFn(query: $query, bind: [$value1, $value2]);
  }

  /**
   * Deletes rows where both identifiers match.
   *
   * Ideal for compound key deletes, ensuring deletions are tightly scoped.
   */
  public static function deleteByTwosOr(
    string $table,
    string $identifier1,
    string $identifier2,
    string|int $value1,
    string|int $value2
  ): int {
    $query = parent::formAndMatchQuery(
      selection: 'DELETE_OR',
      table: $table,
      identifier1: $identifier1,
      identifier2: $identifier2
    );

    return parent::deleteFn(query: $query, bind: [$value1, $value2]);
  }

  /**
 * Deletes all rows from the table.
 *
 * ⚠️ HIGH-RISK: This should be used only in maintenance or reset operations,
 * never from user-triggered actions without multiple safety checks.
 */
public static function deleteAllRows(string $table): int
{
    $query = parent::formAndMatchQuery(selection: 'DELETE_ALL', table: $table);
    return parent::deleteFn(query: $query);
}

/**
 * Soft deletes a record by updating its `status` column to 'deleted'.
 *
 * This preserves the row for auditing or potential recovery, while removing it
 * from active queries (assuming queries filter out `status = 'deleted'`).
 *
 * @param string $table The table to update.
 * @param string $identifier The column name used to identify the target row.
 * @param string|int $value The identifier's value for the row to mark as deleted.
 * @return int Number of rows affected (0 if no match found, 1 if successful).
 */
public static function softDeleteByIdentifier(
    string $table,
    string $identifier,
    string|int $value
): int {
    // Build an UPDATE query that sets status to 'deleted' for the matched row only.
    // LIMIT 1 prevents accidental mass updates if the identifier is not unique.
    $query = parent::formAndMatchQuery(
        selection: 'DELETE_UPDATE', // Mapped to: UPDATE $table SET status='deleted' WHERE $identifier1 = ? LIMIT 1
        table: $table,
        identifier1: $identifier
    );

    // Execute the update with bound parameter(s) for safety against SQL injection.
    return parent::deleteFn(query: $query, bind: [$value]);
}


}
