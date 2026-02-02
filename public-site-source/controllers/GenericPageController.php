<?php

namespace App\Controllers;

class GenericPageController extends Controller
{
    public function show(string $route): void
    {
        // Build views path based on the route name
        $viewPath = 'pages/' . $route;
        $this->render($viewPath, [
            'title' => ucfirst(str_replace('-', ' ', $route))
        ]);
    }
}
