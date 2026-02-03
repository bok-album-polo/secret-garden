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
            $_SESSION['pk_ban'] = false;
            $_SESSION['pk_authed'] = false;
        }
    }

    public static function logout(): void
    {
        session_destroy();
        self::initialize();
    }

    public static function sessionUser(): string
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

    private static function getUserAgentId(PDO $db, string $userAgent): ?int
    {
        try {
            // Try to find existing user agent
            $stmt = $db->prepare("SELECT id FROM user_agents WHERE user_agent = :user_agent");
            $stmt->bindValue(':user_agent', $userAgent, PDO::PARAM_STR);
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
            $insertStmt->bindValue(':user_agent', $userAgent, PDO::PARAM_STR);
            $insertStmt->execute();
            $newResult = $insertStmt->fetch(PDO::FETCH_ASSOC);

            return $newResult ? (int)$newResult['id'] : null;
        } catch (PDOException $e) {
            error_log("User agent lookup/insert failed: " . $e->getMessage());
            return null;
        }
    }

    public static function isAuthenticated(): bool
    {
        return $_SESSION['pk_authed'] ?? false;
    }

    private static function banIp(PDO $db, string $ip, string $reason, int $riskScore = 1, string $expires = '+24 hours'): void
    {
        try {
            $statement = $db->prepare("SELECT ip_ban_ban(?, ?, ?, ?)");
            $statement->execute([
                $ip,
                $reason,
                $riskScore,
                date('Y-m-d H:i:s', strtotime($expires))
            ]);
            $_SESSION['pk_ban'] = true;
            $_SESSION['ip_banned'] = true;
        } catch (PDOException $e) {
            error_log("IP ban failed: " . $e->getMessage());
        }
    }

    public static function runAuthenticationSequence(): void
    {

        $config = self::getConfig();
        $db = self::getDb();

        // Step 1: Check IP ban
        try {
            $statement = $db->prepare("SELECT ip_ban_check(?) as is_banned");
            $statement->execute([$_SERVER['REMOTE_ADDR']]);
            $ban_result = $statement->fetch(PDO::FETCH_ASSOC);
            $ip_ban_result = $ban_result && $ban_result['is_banned'];
            $_SESSION['ip_banned'] = $ip_ban_result;
        } catch (PDOException $e) {
            error_log("IP ban check failed: " . $e->getMessage());
            return;
        }

        // Step 2: Block if already authenticated/banned
        if ($_SESSION['pk_authed'] || $_SESSION['pk_ban'] || $ip_ban_result) {
            return;
        }


        // Step 3: Resolve route/page
        $prettyUrls = $config->project_meta['pretty_urls'] ?? false;
        $route = $prettyUrls
            ? trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/')
            : ($_GET['page'] ?? null);

        if ($route === null) {
            return;
        }


        // Build page ID mapping from pages_menu
        $pages = ['home' => '0'];
        foreach ($config->pages_menu as $index => $page) {
            $pages[$page] = (string)$index;
        }

        $id = $pages[$route] ?? $pages['home'];

        $pkLength = $config->application_config['pk_length'] ?? 5;
        $pkMaxHistory = $config->application_config['pk_max_history'] ?? 20;
        $unauthThreshold = $config->application_config['unauth_threshold'] ?? 5;
        $tripwirePages = [];//$config->tripwire_pages ?? []; //TODO need more discussion into the functionality

        // Step 4: Add to history if not duplicate
        $last = end($_SESSION['pk_history']);
        if ($last !== $id) {
            $_SESSION['pk_history'][] = $id;
        }

        // Step 5: Tripwire check (immediately after adding to history)
        if (in_array($route, $tripwirePages, true)) {
            self::banIp($db, $_SERVER['REMOTE_ADDR'], 'Tripwire violation', 5);
            return;
        }

        // Step 6: Direct query to count unauth sessions in last 5 hours
        try {
            $statement = $db->prepare("
            SELECT COUNT(*) AS recent_sessions
            FROM unauthenticated_sessions
            WHERE ip_address = ?
              AND created_at > (NOW() - INTERVAL '5 hours')
        ");
            $statement->execute([$_SERVER['REMOTE_ADDR']]);
            $result = $statement->fetch(PDO::FETCH_ASSOC);

            if ($result && $result['recent_sessions'] >= 5) {
                self::banIp($db, $_SERVER['REMOTE_ADDR'], 'Exceeded unauth session threshold', 2);
                return;
            } else {
                // Insert new unauth session record with binding
                $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
                $userAgentId = self::getUserAgentId($db, $userAgent);
                $sessionIdHash = hash('sha256', session_id());

                $insertStmt = $db->prepare("
                INSERT INTO unauthenticated_sessions (ip_address, user_agent_id, session_id_hash, domain)
                VALUES (:ip_address, :user_agent_id, :session_id_hash, SESSION_USER)
                ON CONFLICT (session_id_hash) DO NOTHING 
            ");
                $insertStmt->bindValue(':ip_address', $_SERVER['REMOTE_ADDR'], PDO::PARAM_STR);
                $insertStmt->bindValue(':user_agent_id', $userAgentId, PDO::PARAM_INT);
                $insertStmt->bindValue(':session_id_hash', $sessionIdHash, PDO::PARAM_STR);
                $insertStmt->execute();
            }
        } catch (PDOException $e) {
            error_log("Unauthenticated session tracking failed: " . $e->getMessage());
            return;
        }


        // Step 7: If unauth sessions < threshold, stop here
        if (count($_SESSION['pk_history']) < $unauthThreshold) {
            return;
        }

        // Step 8: Only proceed if history length >= pk_length
        if (count($_SESSION['pk_history']) < $pkLength) {
            return;
        }

        // Step 9: Extract sequence
        $_SESSION['pk_sequence'] = implode('', array_slice($_SESSION['pk_history'], -$pkLength));

        // Step 10: Validate sequence
        try {
            $statement = $db->prepare("SELECT COUNT(*) > 0 as is_valid FROM pk_get(?)");
            $statement->execute([$_SESSION['pk_sequence']]);
            $result = $statement->fetch(PDO::FETCH_ASSOC);

            if ($result && $result['is_valid']) {
                session_regenerate_id(true);
                $_SESSION['pk_authed'] = true;

                // Step 11: Delete unauth session record (cleanup)
                try {
                    $sessionIdHash = hash('sha256', session_id());
                    $deleteStmt = $db->prepare("SELECT unauthenticated_session_delete(?)");
                    $deleteStmt->execute([$sessionIdHash]);
                } catch (PDOException $e) {
                    error_log("Unauthenticated session delete failed: " . $e->getMessage());
                }

                // Redirect to secret door
                $secretDoor = $config->routing_secrets['secret_door'] ?? 'contact';
                $route = $prettyUrls
                    ? "/$secretDoor"
                    : "/?page=$secretDoor";

                header("Location: $route");
                exit;
            } else {
                // Step 12: Ban if history exceeds max
                if (count($_SESSION['pk_history']) > $pkMaxHistory) {
                    self::banIp($db, $_SERVER['REMOTE_ADDR'], 'Exceeded max pk_history', 3);
                }
            }
        } catch (PDOException $e) {
            error_log("PK validation failed: " . $e->getMessage());
        }
    }


    public static function regenerate(): bool
    {
        return session_regenerate_id(true);
    }
}