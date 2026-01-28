<?php

namespace App\Core;

use App\Controllers\PageController;
use App\Controllers\RegistrationController;
use App\Core\Session;
use App\Models\UserNamePool;

class Router
{
    private array $routes = [];

    public function add(string $route, string $controller, string $action): void
    {
        $this->routes[$route] = [
            'controller' => $controller,
            'action' => $action
        ];
    }

    public function dispatch(string $route): void
    {

        //If authenticated, force secret page only if not already there
        if (\App\Core\Session::isAuthenticated()
            && $route !== SECRET_PAGE
            && $route !== 'pk-reset'
            && $route !== 'registration-summary') {

            $target = ENABLE_PRETTY_URLS ? '/' . SECRET_PAGE : '?page=' . SECRET_PAGE;
            $userData = UserNamePool::getDispatchedUser();
            Session::set('dispatched_user', $userData);
            header('Location: ' . $target, true, 302);
            exit;
        }

        if (isset($this->routes[$route])) {
            $controllerName = $this->routes[$route]['controller'];
            $action = $this->routes[$route]['action'];

            $controller = new $controllerName();
            $controller->$action($route);
        } else {
            // Fallback: use PageController for static pages
            $controller = new PageController();
            $controller->show($route);
        }
    }

}
