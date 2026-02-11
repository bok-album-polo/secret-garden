<?php

namespace App\Models;

use App\Controllers\Database;
use PDO;

class SecretRoomSubmission
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }


    public function getLatestRegistrations($filters = [], $sort = ['column' => 'created_at', 'dir' => 'DESC']): array
    {
        // Subquery to get the latest version of each registration
        $subquery = "
            SELECT DISTINCT ON (r.username) r.*, u.domain, u.pk_sequence
            FROM secret_room_submissions r
            JOIN users u ON r.username = u.username
            ORDER BY r.username, r.created_at DESC
        ";

        $sql = "SELECT * FROM ($subquery) AS latest";

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
            $whereClauses[] = "created_at >= :date_from";
            $params['date_from'] = $filters['date_from'] . ' 00:00:00';
        }
        if (!empty($filters['date_to'])) {
            $whereClauses[] = "created_at <= :date_to";
            $params['date_to'] = $filters['date_to'] . ' 23:59:59';
        }

        if (!empty($whereClauses)) {
            $sql .= " WHERE " . implode(' AND ', $whereClauses);
        }

        // Sorting
        $allowedSorts = ['username', 'domain', 'pk_sequence', 'created_at', 'authenticated'];
        $sortCol = in_array($sort['column'], $allowedSorts) ? $sort['column'] : 'created_at';
        $sortDir = strtoupper($sort['dir']) === 'ASC' ? 'ASC' : 'DESC';

        $sql .= " ORDER BY $sortCol $sortDir";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function getRegistrationById($id)
    {
        $stmt = $this->db->prepare("
            SELECT r.*, u.domain, u.pk_sequence 
            FROM secret_room_submissions r
            JOIN users u ON r.username = u.username
            WHERE r.id = :id
        ");
        $stmt->execute(['id' => $id]);
        return $stmt->fetch();
    }

    public function getHistoryByUsername($username): array
    {
        $stmt = $this->db->prepare("
            SELECT r.*, u.domain, u.pk_sequence 
            FROM secret_room_submissions r
            JOIN users u ON r.username = u.username
            WHERE r.username = :username 
            ORDER BY r.created_at DESC
        ");
        $stmt->execute(['username' => $username]);
        return $stmt->fetchAll();
    }

    public function authenticate(int $id): bool
    {
        // 1. Get the username for this registration
        $stmt = $this->db->prepare("SELECT username FROM secret_room_submissions WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        if (!$row) {
            return false; // registration not found
        }

        $username = $row['username'];

        // 2. Update the registration to authenticated
        $updateReg = $this->db->prepare("UPDATE secret_room_submissions SET authenticated = true WHERE id = :id");
        $successReg = $updateReg->execute(['id' => $id]);

        // 3. Update the corresponding user to authenticated
        $updateUser = $this->db->prepare("UPDATE users SET authenticated = true WHERE username = :username");
        $successUser = $updateUser->execute(['username' => $username]);

        // 4. Return true if both succeeded
        return $successReg && $successUser;
    }


    public function createNewVersion($data, $adminUsername): bool
    {
        $sql = "
            INSERT INTO secret_room_submissions 
            (username, primary_email, authenticated, created_by) 
            VALUES 
            (:username, :primary_email, :authenticated, :created_by)
        ";

        $stmt = $this->db->prepare($sql);

        $stmt->bindValue(':username', $data['username']);
        $stmt->bindValue(':primary_email', $data['primary_email']);
        $stmt->bindValue(':authenticated', $data['authenticated'], PDO::PARAM_BOOL);
        $stmt->bindValue(':created_by', $adminUsername);

        return $stmt->execute();
    }
}
