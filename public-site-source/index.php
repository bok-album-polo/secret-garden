<?php

use App\Core\ErrorHandler;
use App\Core\Session;
use App\Controllers\SecretDoorController;
use App\Controllers\SecretRoomController;
use App\Controllers\GenericPageController;
use App\Config;

require_once __DIR__ . '/src/autoloader.php';

// Initialize core services
//ErrorHandler::initialize();
Session::initialize();
Session::runAuthenticationSequence();

// Get configuration
$config = Config::instance();

// Resolve current page/route
$prettyUrls = $config->project_meta['pretty_urls'] ?? false;
$secretDoor = $config->routing_secrets['secret_door'];
$secretRoom = $config->routing_secrets['secret_room'];

if ($prettyUrls) {
    $uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $page = $uri ?: 'home';
} else {
    $page = $_GET['page'] ?? 'home';
}

// Route handling
if ($page === $secretDoor) {
    if (Session::isAuthenticated()) {
        $controller = new SecretRoomController();
    } else {
        $controller = new SecretDoorController();
    }
    $controller->index();
} else {
    $controller = new GenericPageController();
    $controller->show($page);
}