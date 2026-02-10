<?php

namespace App\Core;

use App\Controllers\RegistrationController;

class Router
{
    private array $routes = [];

    public function add($route, $controller, $action): void
    {
        $this->routes[$route] = ['controller' => $controller, 'action' => $action];
    }

    public function dispatch($route): void
    {
        if (isset($this->routes[$route])) {
            $controllerName = $this->routes[$route]['controller'];
            $action = $this->routes[$route]['action'];

            $controller = new $controllerName();
            $controller->$action();
        } else {
            // Default route
            $controller = new RegistrationController();
            $controller->index();
        }
    }
}
