<?php
$sql = 'UPDATE ' . $tableName . ' SET col = ?';
$db->prepare($sql)->execute();
