<?php

namespace App\Controllers;

use App\Models\User;

class Controller
{
    protected Config $config;
    protected \PDO $db;

    public function __construct()
    {
        self::checkAuth();
        $this->config = Config::instance();
        $this->db = Database::getInstance();
    }

    public static function checkAuth(): void
    {
        $route = $_GET['route'] ?? '';
        if (!Session::isLoggedIn() && $route != 'login') {
            header('Location: index.php?route=login');
            exit;
        }
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


    protected function redirect($url)
    {
        header("Location: {$url}");
        exit;
    }

    protected function validateCsrf(): bool
    {
        $token = $_POST['csrf_token'] ?? '';
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }

        $isValid = hash_equals($_SESSION['csrf_token'], $token);

        // Always remove token after validation attempt
        unset($_SESSION['csrf_token']);

        return $isValid;
    }

    public static function getCsrfToken(): string
    {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    /**
     * Render form fields with optional default values.
     *
     * @param array $fields Field definitions.
     * @param array $defaults Associative array of default values keyed by field name.
     * @return string         The generated HTML markup.
     */
    public static function renderForm(array   $fields,
                                      array   $defaults = [],
                                      bool    $isSecretRoom = false,
                                      ?string $target_username = null,
                                      bool    $form_readonly = false): string
    {

        $csrfToken = self::getCsrfToken();
        $html = '<form method="POST" enctype="multipart/form-data">';
        $html .= '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">';

        foreach ($fields as $field) {
            $name = htmlspecialchars($field['name'], ENT_QUOTES, 'UTF-8');
            $label = htmlspecialchars($field['label'] ?? ucfirst($name), ENT_QUOTES, 'UTF-8');
            $type = htmlspecialchars($field['html_type'] ?? 'text', ENT_QUOTES, 'UTF-8');
            $value = htmlspecialchars($defaults[$field['name']] ?? '', ENT_QUOTES, 'UTF-8');

            $required = !empty($field['required']) ? ' required' : '';
            $maxlength = isset($field['maxlength']) ? ' maxlength="' . (int)$field['maxlength'] . '"' : '';
            if ($form_readonly) {
                $readonly = ' readonly';
            } else {
                $readonly = !empty($field['readonly']) ? ' readonly' : '';
            }

            // Hidden fields: no label or wrapper
            if ($type === 'hidden') {
                $html .= "<input type=\"hidden\" name=\"{$name}\" value=\"{$value}\">";
                continue;
            }

            $html .= '<div>';
            $html .= "<label for=\"{$name}\">{$label}</label><br>";

            if ($type === 'textarea') {
                $html .= "<textarea id=\"{$name}\" name=\"{$name}\"{$required}{$maxlength}{$readonly}>{$value}</textarea>";
            } elseif ($type === 'file') {
                $html .= "<input type=\"file\" id=\"{$name}\" name=\"{$name}\"{$required}>";
            } else {
                $html .= "<input type=\"{$type}\" id=\"{$name}\" name=\"{$name}\" value=\"{$value}\"{$required}{$maxlength}{$readonly}>";
            }

            if (!empty($field['help_text'])) {
                $help = htmlspecialchars($field['help_text'], ENT_QUOTES, 'UTF-8');
                $html .= "<br><small>{$help}</small>";
            }

            $html .= '</div>';
        }

        if (!$form_readonly) {
            $html .= '<div>';
            $html .= '<button type="submit" style="margin-top: 1em;">Submit</button>';
            $html .= '</div>';
        }

        $html .= '</form>';
        
        return $html;
    }

}
