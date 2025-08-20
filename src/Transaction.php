<?php

declare(strict_types=1);

namespace Src;

class Transaction extends Db
{
    public static function beginTransaction()
    {
        return self::connect2()->beginTransaction();
    }

    public static function lastId()
    {
        return self::connect2()->lastInsertId();
    }

    public static function commit()
    {
        return self::connect2()->commit();
    }

    public static function rollback()
    {
        return self::connect2()->rollBack();
    }
}
