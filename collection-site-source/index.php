<?php

use App\Core\ErrorHandler;
use App\Core\Session;

use App\Controllers\SecretDoorController;
use App\Controllers\SecretPageController;

require_once __DIR__ . '/src/autoloader.php';
require_once __DIR__ . '/config/config.php'; // defines PAGES, SECRET_DOOR, SECRET_PAGE, etc.

// Initialize core services
ErrorHandler::initialize();
Session::initialize();
Session::runAuthenticationSequence();

if (ENABLE_PRETTY_URLS) {
    $uri = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $page = $uri ?: 'home';
} else {
    $page = $_GET['page'] ?? 'home';
}

switch ($page) {
    case SECRET_DOOR:
        if ($_SESSION['pk_auth']) {
            $controller = new SecretPageController();
            $controller->index();
        } else {
            // secret door
            $controller = new SecretDoorController();
            $controller->index();
        }
        break;
    default:
        //render page
        $controller = new \App\Controllers\GenericPageController();
        $controller->show($page);
        break;
}
