<?php

namespace App\Core;

use App\Config;
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

    public static function isAuthenticated(): bool
    {
        return $_SESSION['pk_authed'] ?? false;
    }

    public static function runAuthenticationSequence(): void
    {
        $config = self::getConfig();
        $db = self::getDb();

        // Step 1: Check IP ban
        try {
            $statement = $db->prepare("SELECT check_ip_ban(?) as is_banned");
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

        // Add to history if not duplicate
        $last = end($_SESSION['pk_history']);
        if ($last !== $id) {
            $_SESSION['pk_history'][] = $id;
        }

        $pkLength = $config->application_config['pk_length'] ?? 5;

        // Only proceed if history length >= pk_length
        if (count($_SESSION['pk_history']) < $pkLength) {
            return;
        }

        // Step 4: Extract sequence
        $_SESSION['pk_sequence'] = implode('', array_slice($_SESSION['pk_history'], -$pkLength));

        // Step 5: Validate sequence
        try {
            $statement = $db->prepare("SELECT COUNT(*) > 0 as is_valid FROM get_pk(?)");
            $statement->execute([$_SESSION['pk_sequence']]);
            $result = $statement->fetch(PDO::FETCH_ASSOC);

            if ($result && $result['is_valid']) {
                session_regenerate_id(true);
                $_SESSION['pk_authed'] = true;

                $secretDoor = $config->routing_secrets['secret_door'] ?? 'contact';
                $route = $prettyUrls
                    ? "/$secretDoor"
                    : "/?page=$secretDoor";

                header("Location: $route");
                exit;
            } else {
                // Ban if history exceeds max
                $pkMaxHistory = $config->application_config['pk_max_history'] ?? 20;
                if (count($_SESSION['pk_history']) > $pkMaxHistory) {
                    $_SESSION['pk_ban'] = true;
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