<?php

namespace App\Controllers;

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
                throw new PDOException("Database connection failed: " . $e->getMessage());
            }
        }

        return self::$instance;
    }

    public static function dbUserInfo(): string
    {
        $data = [];
        try {
            $stmt = self::getDb()->query("SELECT SESSION_USER, CURRENT_USER");
            $data = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log("Session user query failed: " . $e->getMessage());
        }
        return json_encode($data);
    }
}