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
            echo("View {$viewFile} not found");
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


    protected function validateCsrf(): bool
    {
        $token = $_POST['csrf_token'] ?? '';
        return !empty($token) && hash_equals($_SESSION['csrf_token'] ?? '', $token);
    }

    private static function getCsrfToken(): string
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
    public static function renderFields(array $fields, array $defaults = []): string
    {
        $html = '';

        // Add CSRF token hidden field at the start
        $csrfToken = self::getCsrfToken();
        $html .= '<input type="hidden" name="csrf_token" value="'
            . htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') . '">';

        foreach ($fields as $field) {
            $name = $field['name'];
            $label = htmlspecialchars($field['label'], ENT_QUOTES, 'UTF-8');
            $type = htmlspecialchars($field['html_type'], ENT_QUOTES, 'UTF-8');

            $defaultValue = $defaults[$name] ?? '';

            $html .= '<div style="margin-bottom:1em;">';
            $html .= "<label>{$label}</label><br>";

            if ($type === 'textarea') {
                $html .= '<textarea name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"'
                    . ($field['required'] ? ' required' : '')
                    . (isset($field['maxlength']) ? ' maxlength="' . (int)$field['maxlength'] . '"' : '')
                    . '>'
                    . htmlspecialchars($defaultValue, ENT_QUOTES, 'UTF-8')
                    . '</textarea><br>';
            } elseif ($type === 'file') {
                $html .= '<input type="file" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"'
                    . ($field['required'] ? ' required' : '')
                    . '><br>';
            } else {
                $html .= '<input type="' . $type . '" name="' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '"'
                    . ' value="' . htmlspecialchars($defaultValue, ENT_QUOTES, 'UTF-8') . '"'
                    . ($field['required'] ? ' required' : '')
                    . (isset($field['maxlength']) ? ' maxlength="' . (int)$field['maxlength'] . '"' : '')
                    . '><br>';
            }

            if (!empty($field['help_text'])) {
                $html .= '<small>' . htmlspecialchars($field['help_text'], ENT_QUOTES, 'UTF-8') . '</small><br>';
            }

            $html .= '</div>';
        }

        return $html;
    }


}
