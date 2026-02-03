<?php

namespace App\Controllers;

class Controller
{
    protected function render(string $view, array $data = []): void
    {
        // Extract data for use in views
        if (!empty($data)) {
            extract($data, EXTR_SKIP);
        }

        $viewFile = __DIR__ . "/../views/{$view}.php";

        if (file_exists($viewFile)) {
            // Use layout wrapper
            require __DIR__ . "/../views/layout/template.php";
        } else {
//            $this->redirect('/');
            error_log("View {$viewFile} not found");
        }

        //pad file size by random amount
        $randomBytes = random_int(1000, 5000);
        echo "<div style=\"display:none;\">";
        echo bin2hex(random_bytes($randomBytes));
        echo "</div>";
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
