<?php

namespace App\Models;

use App\Core\Database;
use PDO;

class DynamicModel
{
    private $table;
    private $pdo;
    private $columns = [];
    private $primaryKey = 'id';

    public function __construct(string $table)
    {
        $this->pdo = Database::getInstance();
        $this->table = $table;
        $this->loadColumns();
    }

    private function loadColumns()
    {
        $stmt = $this->pdo->prepare("
            SELECT column_name, column_default
            FROM information_schema.columns 
            WHERE table_schema = 'public' AND table_name = :table
        ");
        $stmt->execute(['table' => $this->table]);
        $cols = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($cols as $col) {
            $this->columns[] = $col['column_name'];
            if (strpos($col['column_default'] ?? '', 'nextval') !== false) {
                $this->primaryKey = $col['column_name'];
            }
        }
    }

    public function insert(array $data)
    {
        // exclude auto-increment PK
        $fields = array_diff(array_intersect(array_keys($data), $this->columns), [$this->primaryKey]);
        $placeholders = array_map(fn($f) => ":$f", $fields);

        $sql = "INSERT INTO {$this->table} (" . implode(",", $fields) . ")
                VALUES (" . implode(",", $placeholders) . ")";
        $stmt = $this->pdo->prepare($sql);

        foreach ($fields as $f) {
            $stmt->bindValue(":$f", $data[$f]);
        }

        $stmt->execute();
        return $this->pdo->lastInsertId("{$this->table}_{$this->primaryKey}_seq");
    }

    public function update($id, array $data)
    {
        $fields = array_diff(array_intersect(array_keys($data), $this->columns), [$this->primaryKey]);
        $assignments = implode(",", array_map(fn($f) => "$f = :$f", $fields));

        $sql = "UPDATE {$this->table} SET $assignments WHERE {$this->primaryKey} = :id";
        $stmt = $this->pdo->prepare($sql);

        foreach ($fields as $f) {
            $stmt->bindValue(":$f", $data[$f]);
        }
        $stmt->bindValue(":id", $id);

        return $stmt->execute();
    }

    public function delete($id)
    {
        $stmt = $this->pdo->prepare("DELETE FROM {$this->table} WHERE {$this->primaryKey} = :id");
        return $stmt->execute(['id' => $id]);
    }

    public function find($id)
    {
        $stmt = $this->pdo->prepare("SELECT * FROM {$this->table} WHERE {$this->primaryKey} = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function all()
    {
        $stmt = $this->pdo->query("SELECT * FROM {$this->table}");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
