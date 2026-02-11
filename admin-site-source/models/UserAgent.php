<?php

namespace models;

use App\Controllers\Database;
use PDO;
use PDOException;

class UserAgent
{

    public static function getUserAgentId(string $user_agent): ?int
    {
        try {
            // Try to find existing user agent
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT id FROM user_agents WHERE user_agent = :user_agent");
            $stmt->bindValue(':user_agent', $user_agent, PDO::PARAM_STR);
            $stmt->execute();
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result && isset($result['id'])) {
                return (int)$result['id'];
            }

            // If not found, insert new user agent
            $insertStmt = $db->prepare("
            INSERT INTO user_agents (user_agent)
            VALUES (:user_agent)
            RETURNING id
        ");
            $insertStmt->bindValue(':user_agent', $user_agent, PDO::PARAM_STR);
            $insertStmt->execute();
            $newResult = $insertStmt->fetch(PDO::FETCH_ASSOC);

            return $newResult ? (int)$newResult['id'] : null;
        } catch (PDOException $e) {
            error_log("User agent lookup/insert failed: " . $e->getMessage());
            return null;
        }
    }

}