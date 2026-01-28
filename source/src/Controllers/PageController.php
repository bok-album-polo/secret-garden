<?php

namespace App\Controllers;

use App\Core\Controller;
use App\Core\Session;

class PageController extends Controller
{
    public function show(string $route): void
    {
        if ($route == 'pk-reset') {
            Session::logout();
        }
        // Build view path based on the route name
        $viewPath = 'pages/' . $route;
        $this->render($viewPath, [
            'title' => ucfirst(str_replace('-', ' ', $route))
        ]);
    }
}
