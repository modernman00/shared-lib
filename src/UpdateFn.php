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
}
