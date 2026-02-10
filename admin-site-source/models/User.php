<?php

namespace models;

use Controllers\Database;

class User
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function findByUsername($username)
    {
        $stmt = $this->db->prepare('
            SELECT * 
            FROM users 
            WHERE username = :username 
            ORDER BY created_at DESC 
            LIMIT 1
        ');
        $stmt->execute(['username' => $username]);
        return $stmt->fetch();
    }

    public function getAllUsers($search = '')
    {
        $sql = "SELECT * FROM users";
        $params = [];
        
        if ($search) {
            $sql .= " WHERE username ILIKE :search OR domain ILIKE :search";
            $params['search'] = '%' . $search . '%';
        }
        
        $sql .= " ORDER BY username ASC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function updatePassword($username, $newHash)
    {
        $stmt = $this->db->prepare("UPDATE users SET password = :hash WHERE username = :username");
        return $stmt->execute([
            'password' => $newHash,
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
