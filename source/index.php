<?php

use App\Core\ErrorHandler;
use App\Core\Session;
use App\Core\Router;

use App\Controllers\ContactController;
use App\Controllers\RegistrationController;

require_once __DIR__ . '/src/autoloader.php';
require_once __DIR__ . '/config/config.php'; // defines PAGES, SECRET_DOOR, SECRET_PAGE, etc.

// Initialize core services
ErrorHandler::initialize();
Session::initialize();
Session::runAuthenticationSequence();
// Initialize router
$router = new Router();

// Register routes (slugs map to controllers/actions)
$router->add('contact-us', ContactController::class, 'index');
$router->add('registration', RegistrationController::class, 'index');
$router->add('registration-summary', RegistrationController::class, 'registrationSummary');

// for all static views
$router->add('*', \App\Controllers\PageController::class, 'show');


// Decide a route based on config
if (ENABLE_PRETTY_URLS) {
    $uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $route = $uri ?: 'home';
} else {
    $route = $_GET['page'] ?? 'home';
}

// Dispatch to the appropriate controller
$router->dispatch($route);
