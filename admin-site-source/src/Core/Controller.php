<?php

namespace App\Core;

class Controller
{
    protected function render($view, $data = [])
    {
        extract($data);
        
        $viewFile = __DIR__ . "/../Views/{$view}.php";
        if (file_exists($viewFile)) {
            require __DIR__ . "/../Views/layout/template.php";
        } else {
            die("View {$view} not found.");
        }
    }

    protected function redirect($url)
    {
        header("Location: {$url}");
        exit;
    }
}
