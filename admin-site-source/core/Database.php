<?php

namespace App\Core;

use App\Controllers\Config;
use PDO;
use PDOException;

class Database
{
    private static ?PDO $instance = null;

    private function __construct()
    {
        // Private constructor to prevent direct instantiation
    }

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $config = Config::instance();
            $database = $config->database_server;
            $dbCreds = $config->db_credentials;

            $dsn = sprintf(
                "pgsql:host=%s;port=%d;dbname=%s;sslmode=%s",
                $database['host'],
                $database['port'],
                $database['db_name'],
                $database['ssl_mode']
            );

            try {
                self::$instance = new PDO($dsn, $dbCreds['user'], $dbCreds['pass'], [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    PDO::ATTR_PERSISTENT => false
                ]);
            } catch (PDOException $e) {
                error_log($e->getMessage());
                throw new PDOException("Database connection failed: " . $e->getMessage());
            }
        }

        return self::$instance;
    }
}
