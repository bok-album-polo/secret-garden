<?php

namespace App\core;

use PDO;
use PDOException;

class Database
{
    private static ?\Controllers\Database $instance = null;
    private $pdo;

    private function __construct()
    {
        $config = require __DIR__ . '/../../config/config.php';
        $db = $config['db'];

        try {
            $this->pdo = new PDO(
                "pgsql:host={$db['host']};port={$db['port']};dbname={$db['name']}",
                $db['user'],
                $db['pass'],
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]
            );
        } catch (PDOException $e) {
            die('Database connection failed: ' . $e->getMessage());
        }
    }

    public static function getInstance(): ?\Controllers\Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection(): PDO
    {
        return $this->pdo;
    }
}
