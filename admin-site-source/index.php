<?php

session_start();

require_once __DIR__ . '/autoloader.php';

use App\Controllers\LoginController;
use App\Controllers\SecretRoomController;
use App\Controllers\UserController;

$route = $_GET['route'] ?? 'dashboard';


// Handle login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $route === 'login') {
    (new LoginController())->login();
    exit;
}

match ($route) {
    'login' => (new LoginController())->showLogin(),
    'logout' => (new LoginController())->logout(),
    'dashboard' => (new SecretRoomController())->index(),
    'submission-view' => (new SecretRoomController())->view(),
    'submission-edit' => (new SecretRoomController())->edit(),
    'user-activate' => (new UserController())->activateUser(),
    'authenticate' => (new SecretRoomController())->authenticate(),
    'users-management' => (new UserController())->index(),
    'user-reset-password' => (new UserController())->resetPassword(),
    'user-update-roles' => (new UserController())->updateRoles(),
    default => (new SecretRoomController())->index(),
};
