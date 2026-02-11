<?php

namespace App\Controllers;

use PDO;
use PDOException;

class Session
{
    public static function initialize(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_name('admin');
            session_start();
        }

        if (empty($_SESSION['session_exists'])) {
            $_SESSION['session_exists'] = true;
            $_SESSION['roles'] = [];
            $_SESSION['user_logged_in'] = false;
        }
    }

    public static function isLoggedIn(): bool
    {
        return $_SESSION['user_logged_in'] ?? false;
    }

    public static function logout(): void
    {
        session_destroy();
        self::initialize();
    }
}