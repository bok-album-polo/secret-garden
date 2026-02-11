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
    'view_registration' => (new SecretRoomController())->view(),
    'edit_registration' => (new SecretRoomController())->edit(),
    'activate_user' => (new UserController())->activateUser(),
    'authenticate' => (new SecretRoomController())->authenticate(),
    'user_management' => (new UserController())->index(),
    'user_reset_password' => (new UserController())->resetPassword(),
    'user_update_roles' => (new UserController())->updateRoles(),
    default => (new SecretRoomController())->index(),
};
