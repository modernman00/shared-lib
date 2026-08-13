<?php

declare(strict_types=1);

namespace Src;

class Transaction extends Db
{
    public static function beginTransaction()
    {
        if (self::connect2()->inTransaction()) {
            return true;
        }
        return self::connect2()->beginTransaction();
    }

    public static function lastId()
    {
        return self::connect2()->lastInsertId();
    }

    public static function commit()
    {
        if (!self::connect2()->inTransaction()) {
            return true;
        }
        return self::connect2()->commit();
    }

    public static function rollback()
    {
        if (!self::connect2()->inTransaction()) {
            return true;
        }
        return self::connect2()->rollBack();
    }
}
