<?php

use App\Controllers\Config;
use App\Controllers\GenericPageController;
use App\Controllers\SecretDoorController;
use App\Controllers\SecretRoomController;
use App\Controllers\Session;

require_once __DIR__ . '/autoloader.php';

// Initialize core services
//ErrorHandler::initialize();
Session::initialize();
Session::runAuthenticationSequence();

// Get configuration
$config = Config::instance();

// Resolve current page/route

$secretDoor = $config->routing_secrets['secret_door'];
$secretRoom = $config->routing_secrets['secret_room'];
$environment = $config->project_meta['environment'] ?? 'production';


$page = $_GET['page'] ?? 'home';


// Route handling
if ($page === $secretDoor) {
    if (Session::pk_authed()) {
        $controller = new SecretRoomController();
    } else {
        $controller = new SecretDoorController();
    }
    $controller->index();
} elseif ($environment == 'development' && $page == 'clear-auth-trackers') {
    Session::clear_auth_trackers();
    header("Location: /");
    exit;
} else {

    $controller = new GenericPageController();
    $controller->show($page);
}