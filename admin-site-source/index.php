<?php

session_start();

require_once __DIR__ . '/src/includes.php';

use App\controllers\UserController;
use App\core\Router;
use App\controllers\AuthController;
use App\controllers\RegistrationController;

$router = new Router();

$router->add('login', AuthController::class, 'showLogin');
$router->add('logout', AuthController::class, 'logout');
$router->add('dashboard', RegistrationController::class, 'index');
$router->add('view_registration', RegistrationController::class, 'views');
$router->add('edit_registration', RegistrationController::class, 'edit');
$router->add('authenticate', RegistrationController::class, 'authenticate');

// User Management Routes
$router->add('user_management', UserController::class, 'index');
$router->add('user_reset_password', UserController::class, 'resetPassword');
$router->add('user_update_roles', UserController::class, 'updateRoles');


// Handle login POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_GET['route'] ?? '') === 'login') {
    $router->add('login', AuthController::class, 'login');
}

$route = $_GET['route'] ?? 'dashboard';
$router->dispatch($route);
