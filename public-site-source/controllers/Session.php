<?php

namespace App\Controllers;

use PDO;
use PDOException;

class Session
{
    private static ?Config $config = null;
    private static ?PDO $db = null;

    private static function getConfig(): Config
    {
        if (self::$config === null) {
            self::$config = Config::instance();
        }
        return self::$config;
    }

    private static function getDb(): PDO
    {
        if (self::$db === null) {
            self::$db = Database::getInstance();
        }
        return self::$db;
    }

    public static function initialize(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['session_exists'])) {
            $_SESSION['session_exists'] = true;
            $_SESSION['pk_history'] = [];
            $_SESSION['ip_banned'] = false;
            $_SESSION['pk_authed'] = false;
            $_SESSION['user_logged_in'] = false;

            //insert into unauth sessions
            try {
                $db = self::getDb();
                $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                $user_agent_id = self::getUserAgentId($user_agent);
                $session_id_hash = hash('sha256', session_id());

                $statement = $db->prepare("SELECT unauthenticated_session_insert(?, ?, ?)");
                $result = $statement->execute([
                    $_SERVER['REMOTE_ADDR'],
                    $user_agent_id,
                    $session_id_hash
                ]);

                if (!$result) {
                    //too many unauthenticated sessions
                    $_SESSION['ip_banned'] = true;
                }
            } catch (PDOException $e) {
                error_log($e->getMessage());
            }
        }
    }

    public static function clear_auth_trackers(): void
    {
        try {
            $db = self::getDb();

            $statement = $db->prepare("SELECT debug_clear_auth_tables()");
            $statement->execute();
        } catch (PDOException $e) {
            error_log($e->getMessage());
        }
        session_destroy();
        self::initialize();
    }

    public static function getUserAgentId(string $user_agent): ?int
    {
        try {
            // Try to find existing user agent
            $db = self::getDb();
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

    public static function pk_authed(): bool
    {
        return $_SESSION['pk_authed'] ?? false;
    }

    public static function banIp(string $reason, int $riskScore = 1, string $duration = '24 hours'): void
    {
        try {
            $db = self::getDb();
            $statement = $db->prepare("SELECT ip_ban_ban(?, ?, ?, ?)");
            $statement->execute([
                $_SERVER['REMOTE_ADDR'],
                $reason,
                $riskScore,
                date('Y-m-d H:i:s', strtotime("+$duration"))
            ]);
            $_SESSION['ip_banned'] = true;
        } catch (PDOException $e) {
            error_log("IP ban failed: " . $e->getMessage());
        }
    }

    public static function runAuthenticationSequence(): void
    {

        $config = self::getConfig();
        $db = self::getDb();

        $pk_length = $config->application_config['pk_length'] ?? 5;
        $pk_max_history = $config->application_config['pk_max_history'] ?? 20;
        $tripwire_pages = $config->tripwire_pages ?? [];


        // Step 1: skip if already authenticated/ip_banned
        if ($_SESSION['pk_authed'] || $_SESSION['ip_banned']) {
            return;
        }


        // Step 2: Check if current IP is banned
        try {
            $statement = $db->prepare("SELECT ip_ban_check(?) as is_banned");
            $statement->execute([$_SERVER['REMOTE_ADDR']]);
            $ban_result = $statement->fetch(PDO::FETCH_ASSOC);
            $ip_ban_result = $ban_result && $ban_result['is_banned'];
            $_SESSION['ip_banned'] = $ip_ban_result;
        } catch (PDOException $e) {
            error_log("IP ban check failed: " . $e->getMessage());
        }

        if ($_SESSION['ip_banned']) {
            return;
        }


        // Step 2b: Resolve route/page
        $route = ($_GET['page'] ?? null);
        if ($route === null) {
            return;
        }

        // Build page ID mapping from pages_menu
        $pages = ['home' => '0'];
        foreach ($config->pages_menu as $index => $page) {
            $pages[$page] = (string)$index;
        }

        $id = $pages[$route] ?? $pages['home'];

        // Step 3: Add to history if not duplicate
        $last = end($_SESSION['pk_history']);
        if ($last !== $id) {
            $_SESSION['pk_history'][] = $id;
        }

        // Step 4: Tripwire check (immediately after adding to history)
        if (in_array($route, $tripwire_pages, true)) {
            self::banIp('Tripwire violation', 1, '1 hours');
            return;
        }

        // Step 5: Only proceed if history length >= pk_length
        if (count($_SESSION['pk_history']) < $pk_length) {
            return;
        }

        // Step 6a: Extract sequence
        $_SESSION['pk_sequence'] = implode('', array_slice($_SESSION['pk_history'], -$pk_length));

        // Step 6b: Validate sequence
        try {
            $statement = $db->prepare("SELECT COUNT(*) > 0 as is_valid FROM pk_get(?)");
            $statement->execute([$_SESSION['pk_sequence']]);
            $result = $statement->fetch(PDO::FETCH_ASSOC);

            if ($result && $result['is_valid']) {
                // Step 7: Delete unauth session record (cleanup)
                try {
                    $sessionIdHash = hash('sha256', session_id());
                    $deleteStmt = $db->prepare("SELECT unauthenticated_session_delete(?)");
                    $deleteStmt->execute([$sessionIdHash]);
                } catch (PDOException $e) {
                    error_log("Unauthenticated session delete failed: " . $e->getMessage());
                }

                session_regenerate_id(true);
                $_SESSION['pk_authed'] = true;

                // Redirect to secret door
                $secretDoor = $config->routing_secrets['secret_door'];
                $route = "/?page=$secretDoor";

                header("Location: $route");
                exit;
            } else {
                // Step 8: Ban if history exceeds max
                if (count($_SESSION['pk_history']) >= $pk_max_history) {
                    self::banIp('Exceeded pk_max_history', 1, '1 hours');
                }
            }
        } catch (PDOException $e) {
            error_log("PK validation failed: " . $e->getMessage());
        }
    }
}