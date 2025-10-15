<?php

namespace Src;


class SelectFn extends Select
{
  /**
   * Selects a single row from the database.
   *
   * @param string $query The SQL query to execute.
   * @param array $params The parameters to bind to the query.
   * @return array|null The selected row or null if no row is found.
   */
  public static function selectOneRow(string $table, string $identifiers, string $identifierAnswer): ?array
  {
    $query = parent::formAndMatchQuery(selection: "SELECT_ONE", table: $table, identifier1: $identifiers);
    $result = parent::selectFn2(query: $query, bind: [$identifierAnswer]);
    return $result[0];
  }

  /**
   * Retrieves all rows from the specified table.
   *
   * Use when a full dataset is needed, such as for admin views, exports, or bulk operations.
   *
   * @param string $table The name of the table to query.
   * @return array All rows from the table.
   */
  public static function selectAllRows(string $table): array
  {
    $query = parent::formAndMatchQuery(selection: "SELECT_ALL", table: $table);
    return parent::selectFn2(query: $query);
  }

  // select all from table by identifier
  public static function selectAllRowsById(string $table, string $identifier, string $identifierAnswer): array
  {
    $query = parent::formAndMatchQuery(selection: "SELECT_ONE", table: $table, identifier1: $identifier);
    return parent::selectFn2(query: $query, bind: [$identifierAnswer]);
  }


  /**
   * Selects rows where both identifiers match their respective values (AND condition).
   *
   * Useful for strict matching scenarios, such as verifying dual keys or enforcing compound constraints.
   *
   * @param string $table The table to query.
   * @param string $identifier1 The first column name to match.
   * @param string $identifier2 The second column name to match.
   * @param string $identifier1Answer The value to match for the first identifier.
   * @param string $identifier2Answer The value to match for the second identifier.
   * @return array|null The matched rows, or null if none found.
   */
  public static function selectWhereBothIdentifiersMatch(string $table, string $identifier1, string $identifier2, string $identifier1Answer, string $identifier2Answer): ?array
  {
    $query = parent::formAndMatchQuery(selection: "SELECT_AND", table: $table, identifier1: $identifier1, identifier2: $identifier2);
    return parent::selectFn2(query: $query, bind: [$identifier1Answer, $identifier2Answer]);
  }


  /**
   * Selects rows where either identifier matches its value (OR condition).
   *
   * Useful for flexible matching scenarios, such as fallback lookups or partial identity checks.
   *
   * @param string $table The table to query.
   * @param string $identifier1 The first column name to match.
   * @param string $identifier2 The second column name to match.
   * @param string $identifier1Answer The value to match for the first identifier.
   * @param string $identifier2Answer The value to match for the second identifier.
   * @return array|null The matched rows, or null if none found.
   */
  public static function selectWhereAnyIdentifierMatches(string $table, string $identifier1, string $identifier2, string $identifier1Answer, string $identifier2Answer): ?array
  {
    $query = parent::formAndMatchQuery(selection: "SELECT_OR", table: $table, identifier1: $identifier1, identifier2: $identifier2);
    return parent::selectFn2(query: $query, bind: [$identifier1Answer, $identifier2Answer]);
  }


  /**
   * Retrieves values from a specific column where the identifier matches.
   *
   * Ideal for targeted lookups, such as fetching user roles, settings, or metadata by ID.
   *
   * @param string $table The name of the table to query.
   * @param string $column The column to retrieve.
   * @param string $identifier The column used for filtering (e.g. 'id', 'email').
   * @param string $identifierAnswer The value to match against the identifier.
   * @return array|null The matched column values, or null if no match found.
   */
  public static function selectColumnByIdentifier(string $table, string $column, string $identifier, string $identifierAnswer): ?array
  {
    $query = parent::formAndMatchQuery(selection: "SELECT_COL_ID", table: $table, identifier1: $identifier, column: $column);
    return parent::selectFn2(query: $query, bind: [$identifierAnswer]);
  }

  /**
   * Selects two columns where identifier matches, with optional ordering and limit.
   */
  public static function selectTwoColumnsById(string $table, string $column1, string $column2, string $identifier, string $identifierAnswer, string $orderBy = '', string $limit = ''): ?array
  {
    $query = parent::formAndMatchQuery(selection: 'SELECT_TWO_COLS_ID', table: $table, identifier1: $identifier, column: $column1, column2: $column2, orderBy: $orderBy, limit: $limit);
    return parent::selectFn2(query: $query, bind: [$identifierAnswer]);
  }



  /**
   * Selects two columns from a table with optional ordering and limit.
   */
  public static function selectTwoColumns(string $table, string $column1, string $column2, string $orderBy = '', string $limit = ''): ?array
  {
    $query = parent::formAndMatchQuery(selection: 'SELECT_2COLS', table: $table, column: $column1, column2: $column2, orderBy: $orderBy, limit: $limit);
    return parent::selectFn2(query: $query);
  }

  /**
   * Selects distinct values from two columns.
   */
  public static function selectDistinctTwoColumns(string $table, string $column1, string $column2, string $orderBy = '', string $limit = ''): ?array
  {
    $query = parent::formAndMatchQuery(selection: 'SELECT_DISTINCT', table: $table, identifier1: $column1, identifier2: $column2, orderBy: $orderBy, limit: $limit);
    return parent::selectFn2(query: $query);
  }


  /**
   * Selects rows where identifier is greater than the given value.
   */
  public static function selectWhereGreaterThan(string $table, string $identifier, string $value, string $orderBy = '', string $limit = ''): ?array
  {
    $query = parent::formAndMatchQuery(selection: 'SELECT_GREATER', table: $table, identifier1: $identifier, orderBy: $orderBy, limit: $limit);
    return parent::selectFn2(query: $query, bind: [$value]);
  }

  /**
   * Selects rows where identifier1 is greater than value OR identifier2 equals value.
   */
  public static function selectWhereGreaterOrEqual(string $table, string $identifier1, string $identifier2, string $value1, string $value2, string $orderBy = '', string $limit = ''): ?array
  {
    $query = parent::formAndMatchQuery(selection: 'SELECT_GREATER_EQUAL', table: $table, identifier1: $identifier1, identifier2: $identifier2, orderBy: $orderBy, limit: $limit);
    return parent::selectFn2(query: $query, bind: [$value1, $value2]);
  }

  /**
   * Selects the average of a column where identifier matches.
   */
  public static function selectAverageById(string $table, string $column, string $identifier, string $value): ?array
  {
    $query = parent::formAndMatchQuery(selection: 'SELECT_AVERAGE', table: $table, column: $column, identifier1: $identifier);
    return parent::selectFn2(query: $query, bind: [$value]);
  }

  /**
   * Selects the average of a column across all rows.
   */
  public static function selectAverageAll(string $table, string $column): ?array
  {
    $query = parent::formAndMatchQuery(selection: 'SELECT_AVERAGE_ALL', table: $table, column: $column);
    return parent::selectFn2(query: $query);
  }

  /**
   * Selects the sum of a column across all rows.
   */
  public static function selectSumAll(string $table, string $column): ?array
  {
    $query = parent::formAndMatchQuery(selection: 'SELECT_SUM_ALL', table: $table, column: $column);
    return parent::selectFn2(query: $query);
  }

  // USING ARRAY TO GENERATE COLUMN NAMES

  /**
   * Selects dynamic columns from a table.
   */
  public static function selectDynamicColumns(string $table, array $implodeColArray): ?array
  {
    $query = parent::formAndMatchQuery(
      selection: 'SELECT_COL_DYNAMICALLY', 
      table: $table, 
      colArray: $implodeColArray);
    return parent::selectFn2(query: $query);
  }

  /**
   * Selects dynamic colArrays where identifier matches.
   */
  public static function selectDynamicColumnsById(string $table, array $implodeColArray, string $identifier, string $value, ?string $orderBy = null, ?string $limit = null): ?array
  {
    $query = parent::formAndMatchQuery(selection: 'SELECT_COL_DYNAMICALLY_ID', table: $table, colArray: $implodeColArray, identifier1: $identifier, orderBy: $orderBy, limit: $limit);
    return parent::selectFn2(query: $query, bind: [$value]);
  }

  /**
   * Selects dynamic columns where both identifiers match.
   */
  public static function selectDynamicColumnsByTwoIds(string $table, array $implodeColArray, string $identifier1, string $identifier2, string $value1, string $value2, ?string $orderBy = null, ?string $limit = null): ?array
  {
    $query = parent::formAndMatchQuery(selection: 'SELECT_COL_DYNAMICALLY_ID_AND', table: $table, colArray: $implodeColArray, identifier1: $identifier1, identifier2: $identifier2, orderBy: $orderBy, limit: $limit);
    return parent::selectFn2(query: $query, bind: [$value1, $value2]);
  }

  /**
   * Selects dynamic columns where either identifier matches.
   *
   * Useful for flexible lookups across multiple keys, such as fallback user IDs or alternate filters.
   *
   * @param string $table The table to query.
   * @param string $implodeColArray Comma-separated column names to select.
   * @param string $identifier1 The first identifier column.
   * @param string $identifier2 The second identifier column.
   * @param string $value1 The value to match for identifier1.
   * @param string $value2 The value to match for identifier2.
   * @param string $orderBy Optional ORDER BY clause.
   * @param string $limit Optional LIMIT clause.
   * @return array|null The matched rows or null if none found.
   */
  public static function selectDynamicColumnsByEitherId(
    string $table,
    string $implodeColArray,
    string $identifier1,
    string $identifier2,
    string $value1,
    string $value2,
    ?string $orderBy = null,
    ?string $limit = null
  ): ?array {
    $query = parent::formAndMatchQuery(
      selection: 'SELECT_COL_DYNAMICALLY_ID_OR',
      table: $table,
      colArray: $implodeColArray,
      identifier1: $identifier1,
      identifier2: $identifier2,
      orderBy: $orderBy,
      limit: $limit
    );
    return parent::selectFn2(query: $query, bind: [$value1, $value2]);
  }
}
