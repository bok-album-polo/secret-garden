<?php

namespace App\Core;

class Controller
{
    protected function render(string $view, array $data = []): void
    {
        // Extract data for use in views
        if (!empty($data)) {
            extract($data, EXTR_SKIP);
        }

        $viewFile = __DIR__ . "/../Views/{$view}.php";

        if (file_exists($viewFile)) {
            // Use layout wrapper
            require __DIR__ . "/../Views/layout/template.php";
        } else {
            $this->redirect('/');
        }
    }

    protected function redirect(string $url): void
    {
        header("Location: {$url}");
        exit();
    }

    protected function setFlash(string $type, string $message): void
    {
        $_SESSION['flash'][$type][] = $message;
    }

}
