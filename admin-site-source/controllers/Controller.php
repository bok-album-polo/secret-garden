<?php

namespace App\Controllers;

use App\Models\User;

class Controller
{
    public function __construct()
    {
        AuthController::checkAuth();
    }

    protected function render(string $view, array $data = []): void
    {
        // Extract data for use in views
        if (!empty($data)) {
            extract($data, EXTR_SKIP);
        }

        $baseDir = __DIR__ . '/../views/';
        $viewFile = $baseDir . $view . '.php';

        if (file_exists($viewFile)) {
            require $baseDir . 'template.php';
        } elseif (file_exists($baseDir . 'functional/' . $view . '.php')) {
            $viewFile = $baseDir . 'functional/' . $view . '.php';
            require $baseDir . 'template.php';
        } elseif (file_exists($baseDir . 'static/' . $view . '.php')) {
            $viewFile = $baseDir . 'static/' . $view . '.php';
            require $baseDir . 'template.php';
        } else {
            error_log("View {$view} not found in views/, functional/, or static/");
            echo "View {$view} not found";
        }

        // pad file size by random amount
        $randomBytes = random_int(1000, 5000);
        echo '<div style="display:none;">';
        echo bin2hex(random_bytes($randomBytes));
        echo '</div>';
    }


    protected function renderOld($view, $data = [])
    {
        extract($data);

        $viewFile = __DIR__ . "/../views/{$view}.php";
        if (file_exists($viewFile)) {
            require __DIR__ . "/../views/layout/template.php";
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
