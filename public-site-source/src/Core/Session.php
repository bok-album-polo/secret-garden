<?php

namespace App\Core;

use App\Core\Database;
use Cassandra\Varint;

class Session
{
    public static function initialize()
    {
        session_start();

        if (empty($_SESSION['session_exists'])) { //initialize session variables on session creation
            $_SESSION['session_exists'] = true;
            $_SESSION['pk_history'] = [];
            $_SESSION['pk_ban'] = false;
            $_SESSION['pk_auth'] = false;
        }

    }

    public static function logout()
    {
        session_destroy();
        self::initialize();
    }

    public static function sessionUser()
    {

        $data = [];
        try {
            $db = Database::getInstance();

            $stmt = $db->query("SELECT SESSION_USER, CURRENT_USER");
            $data = $stmt->fetch();

        } catch (\Exception $e) {
            error_log($e->getMessage());
        }
        return json_encode($data);
    }

    public static function isAuthenticated()
    {
        return $_SESSION['pk_auth'] ?? false;
    }

    public static function runAuthenticationSequence()
    {
        $db = Database::getInstance();

        // Step 1: Check IP ban
        $statement = $db->prepare("SELECT check_ip_ban(?) as is_banned");
        $statement->execute([$_SERVER['REMOTE_ADDR']]);
        $ban_result = $statement->fetch();
        $ip_ban = $ban_result ? $ban_result['is_banned'] : false;

        // Step 2: Block if already authenticated/banned
        if ($_SESSION['pk_auth'] || $_SESSION['pk_ban'] || $ip_ban) {
            return;
        }

        // Step 3: Resolve route/page
        $route = ENABLE_PRETTY_URLS
            ? trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/')
            : ($_GET['page'] ?? null);

        if ($route === null) {
            return;
        }

        $id = PAGES[$route] ?? PAGES['home'];

        // Add to history if not duplicate
        $last = end($_SESSION['pk_history']);
        if ($last !== $id) {
            $_SESSION['pk_history'][] = $id;
        }

        // Only proceed if history length >= PK_LENGTH
        if (count($_SESSION['pk_history']) < PK_LENGTH) {
            return;
        }

        // Step 4: Extract sequence
        $_SESSION['pk_sequence'] = implode('', array_slice($_SESSION['pk_history'], -PK_LENGTH));


        // Validate sequence
        $statement = $db->prepare("SELECT COUNT(*) > 0 as is_valid FROM get_pk(?)");
        $statement->execute([$_SESSION['pk_sequence']]);
        $result = $statement->fetch();


        if ($result && $result['is_valid']) {
            session_regenerate_id(true);
            $_SESSION['pk_auth'] = true;
            $route = "/?page=" . SECRET_DOOR;
            if (ENABLE_PRETTY_URLS) {
                $route = "/" . SECRET_DOOR;
            }
            header("Location: $route");
            exit; // always call exit after header redirect
        } else {
            // Ban if history exceeds max
            if (count($_SESSION['pk_history']) > PK_MAX_HISTORY) {
                $_SESSION['pk_ban'] = true;
            }
        }
    }

    public static function set(string $keyName, $value)
    {
        return $_SESSION[$keyName] = $value;
    }

    public static function get(string $keyName)
    {
        return $_SESSION[$keyName] ?? null;
    }

}
