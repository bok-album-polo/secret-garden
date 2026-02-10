<?php

session_start();

require_once __DIR__ . '/autoloader.php';

use App\Controllers\AuthController;
use App\Controllers\RegistrationController;
use App\Controllers\UserController;

$route = $_GET['route'] ?? 'dashboard';

// Handle login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $route === 'login') {
    (new AuthController())->login();
    exit;
}

match ($route) {
    'login' => (new AuthController())->showLogin(),
    'logout' => (new AuthController())->logout(),
    'dashboard' => (new RegistrationController())->index(),
    'view_registration' => (new RegistrationController())->view(),
    'edit_registration' => (new RegistrationController())->edit(),
    'authenticate' => (new RegistrationController())->authenticate(),
    'user_management' => (new UserController())->index(),
    'user_reset_password' => (new UserController())->resetPassword(),
    'user_update_roles' => (new UserController())->updateRoles(),
    default => (new RegistrationController())->index(),
};
