<?php

namespace App\Models;

use App\Controllers\Database;

class User
{
    private \PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    public function activate(string $username)
    {
        $stmt = $this->db->prepare("UPDATE users SET authenticated = true, activated_at = NOW() WHERE username = :username");
        $stmt->bindValue(':username', $username, \PDO::PARAM_INT);
        $stmt->execute();
    }

    public function findByUsername($username)
    {
        $stmt = $this->db->prepare('
            SELECT * 
            FROM users 
            WHERE username = :username 
            ORDER BY time_dispatched DESC 
            LIMIT 1
        ');
        $stmt->execute(['username' => $username]);
        return $stmt->fetch();
    }

    public function getAllUsers($filters = [], $sort = ['column' => 'activated_at', 'dir' => 'DESC']): array
    {
        $sql = "SELECT * FROM users";
        $whereClauses = [];
        $params = [];

        if (!empty($filters['username'])) {
            $whereClauses[] = "username ILIKE :username";
            $params['username'] = '%' . $filters['username'] . '%';
        }
        if (!empty($filters['domain'])) {
            $whereClauses[] = "domain ILIKE :domain";
            $params['domain'] = '%' . $filters['domain'] . '%';
        }
        if (!empty($filters['pk_sequence'])) {
            $whereClauses[] = "pk_sequence = :pk_sequence";
            $params['pk_sequence'] = $filters['pk_sequence'];
        }
        if (!empty($filters['authenticated'])) {
            if ($filters['authenticated'] === 'yes') {
                $whereClauses[] = "authenticated = true";
            } elseif ($filters['authenticated'] === 'no') {
                $whereClauses[] = "authenticated = false";
            }
        }
        if (!empty($filters['date_from'])) {
            $whereClauses[] = "activated_at >= :date_from";
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $whereClauses[] = "activated_at <= :date_to";
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        // Default: only activated users
        if (empty($filters['activated'])) {
            $whereClauses[] = "activated_at IS NOT NULL";
        } else {
            if ($filters['activated'] === 'yes') {
                $whereClauses[] = "activated_at IS NOT NULL";
            } elseif ($filters['activated'] === 'no') {
                $whereClauses[] = "activated_at IS NULL";
            }
        }

        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }

        // Sorting
        $allowedSorts = ['username', 'domain', 'pk_sequence', 'activated_at', 'authenticated'];
        $sortCol = in_array($sort['column'], $allowedSorts) ? $sort['column'] : 'activated_at';
        $sortDir = strtoupper($sort['dir']) === 'ASC' ? 'ASC' : 'DESC';

        $sql .= " ORDER BY $sortCol $sortDir";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function updatePassword($username, $password): bool
    {
        $stmt = $this->db->prepare("UPDATE users SET password = :password WHERE username = :username");
        return $stmt->execute([
            'password' => $password,
            'username' => $username
        ]);
    }

    public function addRole($username, $role)
    {
        // Check if role already exists
        $stmt = $this->db->prepare("SELECT 1 FROM user_roles WHERE username = :username AND role = :role");
        $stmt->execute(['username' => $username, 'role' => $role]);
        if ($stmt->fetch()) {
            return true; // Already exists
        }

        $stmt = $this->db->prepare("INSERT INTO user_roles (username, role) VALUES (:username, :role)");
        return $stmt->execute(['username' => $username, 'role' => $role]);
    }

    public function removeRole($username, $role)
    {
        $stmt = $this->db->prepare("DELETE FROM user_roles WHERE username = :username AND role = :role");
        return $stmt->execute(['username' => $username, 'role' => $role]);
    }
}
