<?php

namespace App\Core;
class Database
{
    private static $instance = null;

    public static function getInstance()
    {
        if (self::$instance === null) {
            $dsn = "pgsql:host=" . DATABASE_HOST . ";dbname=" . DATABASE_NAME;
            self::$instance = new \PDO($dsn, DATABASE_USER, DATABASE_USER_PASSWORD);
            self::$instance->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
            self::$instance->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        }

        return self::$instance;
    }
}
